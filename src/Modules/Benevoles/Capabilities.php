<?php
/**
 * Gestion des capacités et rôles du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles;

defined( 'ABSPATH' ) || exit;

/**
 * Centralise les capacités et rôles WordPress du module.
 *
 * Le module gère trois rôles distincts (bénévole, jury, arbitre) afin de
 * permettre des affichages différenciés sur le profil et des règles d'accès
 * propres à chaque type. Une seule capacité de gestion (`MANAGE`) couvre
 * toutes les opérations admin du module ; une capacité commune
 * (`ACCES_PROFIL`) est attribuée aux trois rôles pour ouvrir le shortcode
 * de profil.
 */
final class Capabilities {

	/**
	 * Capacité requise pour les écrans admin du module Bénévoles.
	 */
	public const MANAGE = 'jde_manage_benevoles';

	/**
	 * Capacité partagée par les trois rôles WP créés par le module.
	 *
	 * Permet d'autoriser l'accès au shortcode de profil sans dépendre
	 * d'un rôle spécifique (un même test côté contrôleur REST suffit).
	 */
	public const ACCES_PROFIL = 'jde_acces_profil_personnel';

	/**
	 * Identifiants des rôles WordPress créés par le module.
	 */
	public const ROLE_BENEVOLE = 'jde_benevole';
	public const ROLE_JURY     = 'jde_jury';
	public const ROLE_ARBITRE  = 'jde_arbitre';

	/**
	 * Correspondance entre le type de rôle interne (BD) et le rôle WP cible.
	 *
	 * @var array<string, string>
	 */
	public const ROLE_MAP = array(
		'benevole' => self::ROLE_BENEVOLE,
		'jury'     => self::ROLE_JURY,
		'arbitre'  => self::ROLE_ARBITRE,
	);

	/**
	 * Capacités primitives auto-générées par WordPress pour le CPT
	 * `jde_evenement_rh` (capability_type = ['jde_evenement_rh', 'jde_evenements_rh']).
	 */
	private const CPT_PRIMITIVE_CAPS = array(
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
	 * Toutes les capacités JDE attribuées au rôle administrateur.
	 *
	 * @return string[]
	 */
	public static function adminCaps(): array {
		return array_merge( array( self::MANAGE ), self::CPT_PRIMITIVE_CAPS );
	}

	/**
	 * Ajouter toutes les capacités au rôle administrateur.
	 *
	 * Idempotent : ne fait rien si la capacité est déjà attribuée.
	 */
	public static function addToAdministrator(): void {
		$role = get_role( 'administrator' );
		if ( null === $role ) {
			return;
		}

		foreach ( self::adminCaps() as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Créer les trois rôles WP du module (bénévole, jury, arbitre).
	 *
	 * Idempotent : si un rôle existe déjà on s'assure simplement qu'il
	 * possède la capacité d'accès au profil (utile lors d'une mise à jour
	 * du plugin où la capacité aurait été ajoutée après-coup).
	 */
	public static function createRoles(): void {
		foreach ( self::ROLE_MAP as $typeRole => $roleSlug ) {
			$existing = get_role( $roleSlug );
			if ( null === $existing ) {
				add_role(
					$roleSlug,
					self::roleDisplayName( $typeRole ),
					array(
						'read'             => true,
						self::ACCES_PROFIL => true,
					)
				);
				continue;
			}

			if ( ! $existing->has_cap( self::ACCES_PROFIL ) ) {
				$existing->add_cap( self::ACCES_PROFIL );
			}
			if ( ! $existing->has_cap( 'read' ) ) {
				$existing->add_cap( 'read' );
			}
		}
	}

	/**
	 * Supprimer les trois rôles WP. Utilisé uniquement par `uninstall.php`.
	 */
	public static function removeRoles(): void {
		foreach ( self::ROLE_MAP as $roleSlug ) {
			if ( null !== get_role( $roleSlug ) ) {
				remove_role( $roleSlug );
			}
		}
	}

	/**
	 * Retirer toutes les capacités custom de tous les rôles.
	 */
	public static function removeFromAllRoles(): void {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return;
		}

		$caps = array_merge( self::adminCaps(), array( self::ACCES_PROFIL ) );

		foreach ( array_keys( $wp_roles->roles ) as $roleName ) {
			$role = get_role( (string) $roleName );
			if ( null === $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Libellé d'affichage d'un rôle WP créé par le module.
	 */
	private static function roleDisplayName( string $typeRole ): string {
		switch ( $typeRole ) {
			case 'benevole':
				return __( 'Bénévole JDE', 'jde-plugin' );
			case 'jury':
				return __( 'Jury JDE', 'jde-plugin' );
			case 'arbitre':
				return __( 'Arbitre JDE', 'jde-plugin' );
			default:
				return __( 'Personnel JDE', 'jde-plugin' );
		}
	}
}
