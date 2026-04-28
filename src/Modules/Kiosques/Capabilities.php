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
	 * Capacité requise pour gérer le module Kiosques (CPT, exposants, réservations).
	 */
	public const MANAGE = 'jde_manage_kiosques';

	/**
	 * Ajouter la capacité au rôle administrateur.
	 */
	public static function addToAdministrator(): void {
		$role = get_role( 'administrator' );
		if ( null !== $role && ! $role->has_cap( self::MANAGE ) ) {
			$role->add_cap( self::MANAGE );
		}
	}

	/**
	 * Retirer la capacité de tous les rôles.
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
			if ( null !== $role ) {
				$role->remove_cap( self::MANAGE );
			}
		}
	}
}
