<?php
/**
 * Menu d'administration du module Kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use JDE\Modules\Kiosques\Capabilities;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Construit le menu top-level « Kiosques » et y rattache les écrans CPT.
 *
 * Le CPT est enregistré avec `show_in_menu = false` pour qu'on puisse
 * placer le menu où on veut (ici, juste sous Pages dans l'admin). On
 * réutilise les écrans natifs WordPress (`edit.php` et `post-new.php`)
 * comme entrées.
 */
final class AdminMenu {

	public const SLUG = 'jde-kiosques';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_filter( 'parent_file', array( $this, 'highlightMenuOnEditScreens' ) );
	}

	/**
	 * Créer le menu top-level et ses sous-menus.
	 */
	public function registerMenu(): void {
		add_menu_page(
			__( 'Kiosques', 'jde-plugin' ),
			__( 'Kiosques', 'jde-plugin' ),
			Capabilities::MANAGE,
			self::SLUG,
			'', // pas de callback : le sous-menu fera la redirection.
			'dashicons-grid-view',
			25
		);

		// Sous-menu 1 : remplacer le faux écran top-level par la liste des événements.
		add_submenu_page(
			self::SLUG,
			__( 'Événements', 'jde-plugin' ),
			__( 'Tous les événements', 'jde-plugin' ),
			Capabilities::MANAGE,
			'edit.php?post_type=' . EvenementPostType::SLUG
		);

		// Sous-menu 2 : ajouter un événement.
		add_submenu_page(
			self::SLUG,
			__( 'Ajouter un événement', 'jde-plugin' ),
			__( 'Ajouter', 'jde-plugin' ),
			Capabilities::MANAGE,
			'post-new.php?post_type=' . EvenementPostType::SLUG
		);

		// Retirer le faux item top-level (qui pointe vers une page sans callback).
		remove_submenu_page( self::SLUG, self::SLUG );
	}

	/**
	 * Garder le menu « Kiosques » surligné sur les écrans liés au CPT.
	 *
	 * Sans ce filtre, les écrans `edit.php?post_type=jde_evenement` et
	 * `post-new.php?post_type=jde_evenement` n'auraient pas de menu actif
	 * dans la barre latérale.
	 */
	public function highlightMenuOnEditScreens( string $parentFile ): string {
		global $current_screen;

		if ( $current_screen instanceof \WP_Screen
			&& EvenementPostType::SLUG === $current_screen->post_type
		) {
			return self::SLUG;
		}

		return $parentFile;
	}
}
