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

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_filter( 'parent_file', array( $this, 'highlightMenuOnEditScreens' ) );
		add_filter( 'submenu_file', array( $this, 'highlightSubmenuOnEditScreens' ) );
	}

	/**
	 * Slug du menu top-level (= URL de la liste CPT).
	 */
	private static function listUrl(): string {
		return 'edit.php?post_type=' . EvenementPostType::SLUG;
	}

	/**
	 * Créer le menu top-level et ses sous-menus.
	 */
	public function registerMenu(): void {
		// Position 26 (juste après Commentaires à 25) pour éviter les
		// conflits — sur certaines installations, deux menus à la même
		// position rendent l'un d'eux invisible.
		$listUrl = self::listUrl();

		add_menu_page(
			__( 'Kiosques', 'jde-plugin' ),
			__( 'Kiosques', 'jde-plugin' ),
			Capabilities::MANAGE,
			$listUrl, // L'URL elle-même comme slug : clic = liste des événements.
			'',
			'dashicons-grid-view',
			26
		);

		// Sous-menu « Ajouter ». Le premier sous-menu (« Tous les événements »)
		// est généré automatiquement par WordPress à partir du parent,
		// puisqu'on utilise l'URL d'edit.php comme slug.
		add_submenu_page(
			$listUrl,
			__( 'Ajouter un événement', 'jde-plugin' ),
			__( 'Ajouter', 'jde-plugin' ),
			Capabilities::MANAGE,
			'post-new.php?post_type=' . EvenementPostType::SLUG
		);
	}

	/**
	 * Garder le menu « Kiosques » surligné sur les écrans liés au CPT.
	 *
	 * Le slug du menu étant l'URL d'edit.php, on retourne cette URL comme
	 * parent_file pour les écrans post.php (édition d'un événement) et
	 * post-new.php.
	 */
	public function highlightMenuOnEditScreens( string $parentFile ): string {
		global $current_screen;

		if ( $current_screen instanceof \WP_Screen
			&& EvenementPostType::SLUG === $current_screen->post_type
		) {
			return self::listUrl();
		}

		return $parentFile;
	}

	/**
	 * Surligner le bon sous-menu sur post.php / post-new.php.
	 *
	 * @param string|null $submenuFile Sous-menu actif (peut être null).
	 */
	public function highlightSubmenuOnEditScreens( ?string $submenuFile ): ?string {
		global $current_screen, $pagenow;

		if ( $current_screen instanceof \WP_Screen
			&& EvenementPostType::SLUG === $current_screen->post_type
		) {
			if ( 'post-new.php' === $pagenow ) {
				return 'post-new.php?post_type=' . EvenementPostType::SLUG;
			}
			return self::listUrl();
		}

		return $submenuFile;
	}
}
