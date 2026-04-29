<?php
/**
 * Gestion des capacités du module Kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques;

defined( 'ABSPATH' ) || exit;

/**
 * Centralise l'ajout et le retrait de la capacité custom.
 *
 * Stratégie : à l'activation, on ajoute la capacité au rôle administrateur.
 * À la désactivation, on la conserve (au cas où le plugin serait réactivé) ;
 * la suppression complète se fait dans `uninstall.php`. Les hooks WordPress
 * et les contrôleurs REST utilisent {@see MANAGE} dans
 * `current_user_can()` plutôt que de vérifier le rôle directement.
 */
final class Capabilities {

	/**
	 * Capacité requise pour les écrans custom du module (menu, page Exposants, etc.).
	 */
	public const MANAGE = 'jde_manage_kiosques';

	/**
	 * Capacités primitives auto-générées par WordPress pour le CPT
	 * `jde_evenement` (capability_type = ['jde_evenement', 'jde_evenements']).
	 *
	 * Toutes nécessaires pour permettre à l'utilisateur de naviguer la liste,
	 * créer, modifier et supprimer des événements via les écrans natifs WP.
	 */
	private const CPT_PRIMITIVE_CAPS = array(
		'edit_jde_evenements',
		'edit_others_jde_evenements',
		'publish_jde_evenements',
		'read_private_jde_evenements',
		'delete_jde_evenements',
		'delete_private_jde_evenements',
		'delete_published_jde_evenements',
		'delete_others_jde_evenements',
		'edit_private_jde_evenements',
		'edit_published_jde_evenements',
	);

	/**
	 * Toutes les capacités JDE attribuées au rôle administrateur.
	 *
	 * @return string[]
	 */
	public static function allCaps(): array {
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

		foreach ( self::allCaps() as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Retirer toutes les capacités de tous les rôles.
	 *
	 * Utilisé uniquement par `uninstall.php` (pas à la désactivation, pour
	 * éviter de perdre l'attribution lors d'un cycle activate/deactivate).
	 */
	public static function removeFromAllRoles(): void {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return;
		}

		foreach ( array_keys( $wp_roles->roles ) as $role_name ) {
			$role = get_role( (string) $role_name );
			if ( null === $role ) {
				continue;
			}
			foreach ( self::allCaps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
