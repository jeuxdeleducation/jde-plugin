<?php
/**
 * Menu d'administration du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Construit le menu top-level « Bénévoles » et ses sous-menus.
 *
 * Pattern identique à celui du module Kiosques : slug custom + callback
 * de redirection vers la liste des éditions RH afin que le menu reste
 * fiable sur toutes les installations WP.
 */
final class AdminMenu {

	public const SLUG = 'jde-benevoles';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_filter( 'parent_file', array( $this, 'highlightMenuOnEditScreens' ) );
		add_filter( 'submenu_file', array( $this, 'highlightSubmenuOnEditScreens' ) );
	}

	public function registerMenu(): void {
		add_menu_page(
			__( 'Bénévoles', 'jde-plugin' ),
			__( 'Bénévoles', 'jde-plugin' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'displayMainPage' ),
			'dashicons-groups',
			null
		);

		add_submenu_page(
			self::SLUG,
			__( 'Toutes les éditions RH', 'jde-plugin' ),
			__( 'Éditions RH', 'jde-plugin' ),
			Capabilities::MANAGE,
			'edit.php?post_type=' . EvenementRhPostType::SLUG
		);

		add_submenu_page(
			self::SLUG,
			__( 'Ajouter une édition RH', 'jde-plugin' ),
			__( 'Ajouter', 'jde-plugin' ),
			Capabilities::MANAGE,
			'post-new.php?post_type=' . EvenementRhPostType::SLUG
		);

		add_submenu_page(
			self::SLUG,
			__( 'Personnes', 'jde-plugin' ),
			__( 'Personnes', 'jde-plugin' ),
			Capabilities::MANAGE,
			PersonnesPage::PAGE_SLUG,
			array( PersonnesPage::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Postes et quarts', 'jde-plugin' ),
			__( 'Postes & quarts', 'jde-plugin' ),
			Capabilities::MANAGE,
			PostesPage::PAGE_SLUG,
			array( PostesPage::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Assignations', 'jde-plugin' ),
			__( 'Assignations', 'jde-plugin' ),
			Capabilities::MANAGE,
			AssignationsPage::PAGE_SLUG,
			array( AssignationsPage::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Formulaires d\'inscription', 'jde-plugin' ),
			__( 'Formulaires', 'jde-plugin' ),
			Capabilities::MANAGE,
			FormulairesPage::PAGE_SLUG,
			array( FormulairesPage::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Composer un courriel', 'jde-plugin' ),
			__( 'Courriels', 'jde-plugin' ),
			Capabilities::MANAGE,
			EmailComposerPage::PAGE_SLUG,
			array( EmailComposerPage::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Modèles de courriels', 'jde-plugin' ),
			__( 'Modèles courriels', 'jde-plugin' ),
			Capabilities::MANAGE,
			EmailTemplatesPage::PAGE_SLUG,
			array( EmailTemplatesPage::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Paramètres Bénévoles', 'jde-plugin' ),
			__( 'Paramètres', 'jde-plugin' ),
			Capabilities::MANAGE,
			SettingsPage::PAGE_SLUG,
			array( SettingsPage::class, 'render' )
		);

		remove_submenu_page( self::SLUG, self::SLUG );
	}

	public function displayMainPage(): void {
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . EvenementRhPostType::SLUG ) );
		exit;
	}

	public function highlightMenuOnEditScreens( string $parentFile ): string {
		global $current_screen;

		if ( $current_screen instanceof \WP_Screen
			&& EvenementRhPostType::SLUG === $current_screen->post_type
		) {
			return self::SLUG;
		}

		return $parentFile;
	}

	public function highlightSubmenuOnEditScreens( ?string $submenuFile ): ?string {
		global $current_screen, $pagenow;

		if ( $current_screen instanceof \WP_Screen
			&& EvenementRhPostType::SLUG === $current_screen->post_type
		) {
			if ( 'post-new.php' === $pagenow ) {
				return 'post-new.php?post_type=' . EvenementRhPostType::SLUG;
			}
			return 'edit.php?post_type=' . EvenementRhPostType::SLUG;
		}

		return $submenuFile;
	}
}
