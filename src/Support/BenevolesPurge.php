<?php
/**
 * Routine de purge des données du module Bénévoles retiré.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Supprime toutes les traces persistantes laissées par l'ancien module
 * Bénévoles (tables BD, options, capacités, rôles, événements cron, posts
 * du CPT et leurs meta, transients).
 *
 * Routine **temporaire** : elle existe uniquement pour permettre aux sites
 * qui possédaient le module de récupérer un état propre lors de la mise à
 * jour qui retire le module. Une fois cette mise à jour déployée sur le
 * seul site concerné, la classe et son point d'accroche dans
 * {@see \JDE\Plugin} pourront être supprimés au cycle suivant.
 *
 * Idempotente via l'option {@see self::FLAG_OPTION} : la purge ne s'exécute
 * qu'une seule fois par installation. La classe est volontairement isolée
 * dans `Support/` pour ne dépendre d'aucune classe du module supprimé.
 */
final class BenevolesPurge {

	/**
	 * Drapeau persistant qui signale que la purge a déjà été exécutée.
	 */
	public const FLAG_OPTION = 'jde_plugin_benevoles_purged';

	/**
	 * Préfixe commun des dix tables du module (sans le préfixe `wpdb`).
	 *
	 * @var string[]
	 */
	private const TABLE_SUFFIXES = array(
		'personnes',
		'inscription_reponses',
		'postes',
		'quarts',
		'plages_dispo',
		'disponibilites',
		'assignations',
		'notifications',
		'signatures',
		'email_log',
	);

	/**
	 * Options stockées par le module Bénévoles.
	 *
	 * @var string[]
	 */
	private const OPTIONS = array(
		'jde_plugin_benevoles_db_version',
		'jde_plugin_benevoles_settings',
		'jde_plugin_benevoles_emails',
	);

	/**
	 * Capacités custom à retirer de tous les rôles WP existants.
	 *
	 * Comprend la capacité de gestion, la capacité d'accès au profil, et
	 * les dix capacités primitives auto-générées par WordPress pour le CPT
	 * `jde_evenement_rh` (capability_type = ['jde_evenement_rh', 'jde_evenements_rh']).
	 *
	 * @var string[]
	 */
	private const CAPABILITIES = array(
		'jde_manage_benevoles',
		'jde_acces_profil_personnel',
		'edit_jde_evenements_rh',
		'edit_others_jde_evenements_rh',
		'publish_jde_evenements_rh',
		'read_private_jde_evenements_rh',
		'delete_jde_evenements_rh',
		'delete_private_jde_evenements_rh',
		'delete_published_jde_evenements_rh',
		'delete_others_jde_evenements_rh',
		'edit_private_jde_evenements_rh',
		'edit_published_jde_evenements_rh',
	);

	/**
	 * Rôles WP créés par le module.
	 *
	 * @var string[]
	 */
	private const ROLES = array(
		'jde_benevole',
		'jde_jury',
		'jde_arbitre',
	);

	/**
	 * Hook WP-Cron planifié par le module.
	 */
	private const CRON_HOOK = 'jde_benevoles_retention_cleanup';

	/**
	 * Slug du CPT enregistré par le module.
	 */
	private const CPT_SLUG = 'jde_evenement_rh';

	/**
	 * Préfixes de transients utilisés par le module.
	 *
	 * Tous expirent en moins de 15 minutes, mais on nettoie quand même pour
	 * laisser une `wp_options` propre.
	 *
	 * @var string[]
	 */
	private const TRANSIENT_PREFIXES = array(
		'jde_personnes_error_',
		'jde_assign_error_',
		'jde_composer_sent_',
		'jde_composer_error_',
		'jde_benevoles_inscription_',
	);

	/**
	 * Lancer la purge si elle n'a jamais été exécutée. Idempotent.
	 */
	public static function maybeRun(): void {
		if ( '1' === (string) get_option( self::FLAG_OPTION, '' ) ) {
			return;
		}

		self::run();

		update_option( self::FLAG_OPTION, '1', true );
	}

	/**
	 * Exécuter toutes les étapes de purge sans tenir compte du drapeau.
	 *
	 * Exposée publiquement pour faciliter un éventuel re-jeu manuel via
	 * WP-CLI. Ne devrait pas être appelée directement en production.
	 */
	public static function run(): void {
		self::deletePostsAndMeta();
		self::dropTables();
		self::deleteOptions();
		self::removeCapabilities();
		self::removeRoles();
		self::clearCronHook();
		self::deleteTransients();
	}

	/**
	 * Supprimer tous les posts du CPT (et leurs meta par cascade WP).
	 */
	private static function deletePostsAndMeta(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
				self::CPT_SLUG
			)
		);

		foreach ( (array) $ids as $postId ) {
			wp_delete_post( (int) $postId, true );
		}
	}

	/**
	 * Supprimer les dix tables BD du module.
	 */
	private static function dropTables(): void {
		global $wpdb;

		foreach ( self::TABLE_SUFFIXES as $suffix ) {
			$table = $wpdb->prefix . 'jde_rh_' . $suffix;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
	}

	/**
	 * Supprimer les options persistées par le module.
	 */
	private static function deleteOptions(): void {
		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Retirer les capacités custom de tous les rôles existants.
	 */
	private static function removeCapabilities(): void {
		global $wp_roles;

		if ( ! isset( $wp_roles ) || ! ( $wp_roles instanceof \WP_Roles ) ) {
			return;
		}

		foreach ( array_keys( $wp_roles->roles ) as $roleName ) {
			$role = get_role( (string) $roleName );
			if ( null === $role ) {
				continue;
			}
			foreach ( self::CAPABILITIES as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Supprimer les trois rôles WP créés par le module.
	 */
	private static function removeRoles(): void {
		foreach ( self::ROLES as $roleSlug ) {
			if ( null !== get_role( $roleSlug ) ) {
				remove_role( $roleSlug );
			}
		}
	}

	/**
	 * Annuler tous les événements WP-Cron du module.
	 */
	private static function clearCronHook(): void {
		// `wp_clear_scheduled_hook` purge toutes les occurrences planifiées
		// pour ce hook, peu importe leurs arguments — le module n'utilisait
		// qu'une seule planification sans argument.
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Supprimer les transients (et leurs timeouts) qui matchent les préfixes
	 * connus du module.
	 *
	 * Les transients vivent dans `wp_options` (clefs `_transient_<nom>` et
	 * `_transient_timeout_<nom>`) lorsqu'aucun cache externe n'est branché.
	 * Avec un cache objet persistant, l'API WP les place ailleurs et cette
	 * requête ne les voit pas — pas grave : ils expirent rapidement.
	 */
	private static function deleteTransients(): void {
		global $wpdb;

		foreach ( self::TRANSIENT_PREFIXES as $prefix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'_transient_' . $wpdb->esc_like( $prefix ) . '%',
					'_transient_timeout_' . $wpdb->esc_like( $prefix ) . '%'
				)
			);

			foreach ( (array) $names as $optionName ) {
				$optionName = (string) $optionName;
				if ( str_starts_with( $optionName, '_transient_timeout_' ) ) {
					delete_option( $optionName );
					continue;
				}
				$transient = substr( $optionName, strlen( '_transient_' ) );
				delete_transient( $transient );
			}
		}
	}
}
