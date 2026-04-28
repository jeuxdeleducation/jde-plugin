<?php
/**
 * Notice de diagnostic temporaire.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use JDE\Modules\Kiosques\Capabilities;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Affiche un bandeau diagnostic en haut de toute page admin
 * lorsque l'URL contient `?jde_debug=1`.
 *
 * Sert à investiguer pourquoi le menu top-level « Kiosques » peut ne
 * pas apparaître sur certaines installations malgré la présence de la
 * capacité dans le rôle. À retirer une fois le bug identifié.
 */
final class DiagnosticNotice {

	private const QUERY_FLAG = 'jde_debug';

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule du flag de debug
		if ( empty( $_GET[ self::QUERY_FLAG ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $menu, $submenu;

		$user      = wp_get_current_user();
		$canManage = current_user_can( Capabilities::MANAGE );
		$slug      = AdminMenu::SLUG;
		$jdeCaps   = array_filter(
			array_keys( $user->allcaps ),
			static fn ( string $cap ): bool => str_starts_with( $cap, 'jde_' )
		);

		$menuFound    = false;
		$menuPosition = null;
		foreach ( (array) $menu as $position => $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				$menuFound    = true;
				$menuPosition = $position;
				break;
			}
		}

		$submenuCount = isset( $submenu[ $slug ] ) ? count( (array) $submenu[ $slug ] ) : 0;

		$cptObject = get_post_type_object( EvenementPostType::SLUG );
		$cptExists = null !== $cptObject;
		$cptShowUi = $cptExists && (bool) $cptObject->show_ui;

		echo '<div class="notice notice-info"><h3>JDE Plugin — diagnostic</h3><table style="font-family:monospace;font-size:12px;"><tbody>';

		$rows = array(
			'Plugin version'                        => esc_html( JDE_PLUGIN_VERSION ),
			'Utilisateur'                           => esc_html( '' !== $user->user_login ? $user->user_login : '(anonyme)' ) . ' (ID ' . (int) $user->ID . ')',
			'Rôles'                                 => esc_html( implode( ', ', (array) $user->roles ) ),
			'current_user_can(jde_manage_kiosques)' => $canManage ? '✅ true' : '❌ false',
			'Capacités jde_* actives'               => $jdeCaps ? esc_html( implode( ', ', $jdeCaps ) ) : '(aucune)',
			'Slug menu attendu'                     => esc_html( $slug ),
			'Menu présent dans $menu'               => $menuFound
				? ( '✅ position ' . esc_html( (string) $menuPosition ) )
				: '❌ absent',
			'Sous-menus enregistrés'                => (string) $submenuCount,
			'CPT jde_evenement enregistré'          => $cptExists ? '✅' : '❌',
			'CPT show_ui'                           => $cptShowUi ? '✅' : '❌',
		);

		foreach ( $rows as $label => $value ) {
			printf(
				'<tr><td style="padding:2px 12px 2px 0;"><strong>%s</strong></td><td>%s</td></tr>',
				esc_html( $label ),
				wp_kses_post( $value )
			);
		}

		echo '</tbody></table>';

		if ( $menuFound && isset( $menu[ $menuPosition ] ) ) {
			echo '<details style="margin-top:8px;"><summary>Détails $menu[' . esc_html( (string) $menuPosition ) . ']</summary>'
				. '<pre style="font-size:11px;background:#f6f7f7;padding:6px;">'
				. esc_html( print_r( $menu[ $menuPosition ], true ) ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				. '</pre></details>';
		}

		if ( $submenuCount > 0 ) {
			echo '<details style="margin-top:8px;"><summary>Détails $submenu[\'' . esc_html( $slug ) . '\']</summary>'
				. '<pre style="font-size:11px;background:#f6f7f7;padding:6px;">'
				. esc_html( print_r( $submenu[ $slug ], true ) ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				. '</pre></details>';
		}

		echo '</div>';
	}
}
