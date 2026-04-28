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
 * Construit le menu top-level « Kiosques » avec un slug custom et un
 * callback de redirection vers la liste des événements.
 *
 * Pourquoi ce pattern plutôt que l'URL comme slug : sur certaines
 * installations, l'utilisation de `edit.php?post_type=jde_evenement`
 * comme slug d'`add_menu_page` provoque l'invisibilité du menu, sans
 * que la cause exacte soit identifiable (probablement une interaction
 * avec la logique interne de WordPress sur les CPT). Le pattern à slug
 * custom + callback est plus standard et fiable.
 */
final class AdminMenu {

	public const SLUG = 'jde-kiosques';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_filter( 'parent_file', array( $this, 'highlightMenuOnEditScreens' ) );
		add_filter( 'submenu_file', array( $this, 'highlightSubmenuOnEditScreens' ) );
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
			array( $this, 'displayMainPage' ),
			'dashicons-grid-view',
			26
		);

		// Sous-menu « Tous les événements » (avec le même slug que le parent
		// → remplace le sous-menu auto-généré, sans duplication).
		add_submenu_page(
			self::SLUG,
			__( 'Tous les événements', 'jde-plugin' ),
			__( 'Tous les événements', 'jde-plugin' ),
			Capabilities::MANAGE,
			'edit.php?post_type=' . EvenementPostType::SLUG
		);

		// Sous-menu « Ajouter ».
		add_submenu_page(
			self::SLUG,
			__( 'Ajouter un événement', 'jde-plugin' ),
			__( 'Ajouter', 'jde-plugin' ),
			Capabilities::MANAGE,
			'post-new.php?post_type=' . EvenementPostType::SLUG
		);

		// Retirer le sous-menu auto-généré qui réplique le slug du parent.
		remove_submenu_page( self::SLUG, self::SLUG );
	}

	/**
	 * Callback du menu top-level : redirige vers la liste des événements.
	 *
	 * Utilisé quand un utilisateur clique directement sur « Kiosques »
	 * dans la barre latérale (et non sur un sous-menu).
	 */
	public function displayMainPage(): void {
		wp_safe_redirect(
			admin_url( 'edit.php?post_type=' . EvenementPostType::SLUG )
		);
		exit;
	}

	/**
	 * Garder le menu « Kiosques » surligné sur les écrans liés au CPT.
	 *
	 * Sans ce filtre, sur post.php (édition d'un événement) et
	 * post-new.php, WP ne saurait pas quel menu top-level surligner.
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
			return 'edit.php?post_type=' . EvenementPostType::SLUG;
		}

		return $submenuFile;
	}
}
