<?php
/**
 * Shortcode `[jde_reservation_kiosques]`.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Frontend;

use JDE\Support\Assets;
use JDE\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre le shortcode `[jde_reservation_kiosques]` qui rend la
 * page publique de réservation.
 *
 * Le rendu :
 *  - Charge le bundle React `public-reservation` (style + script).
 *  - Injecte la configuration runtime via `window.jdeKiosques`.
 *  - Rend le template `templates/public/reservation-app.php` (surchargeable
 *    par le thème actif).
 */
final class ReservationShortcode {

	public const TAG = 'jde_reservation_kiosques';

	public function __construct(
		private readonly Assets $assets,
		private readonly Template $template,
	) {}

	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Callback de rendu du shortcode.
	 *
	 * @param array<string, mixed>|string $atts    Attributs (ignorés pour l'instant).
	 * @param string|null                 $content Contenu (ignoré).
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	public function render( array|string $atts = array(), ?string $content = null ): string {
		// L'enqueue ne peut pas se faire au moment du rendu si la page est
		// déjà servie ; mais sur une page WP normale, le_content fire avant
		// wp_print_scripts donc on est OK.
		$this->assets->enqueueScript(
			'jde-public-reservation',
			'public-reservation',
			array( 'wp-element' )
		);
		$this->assets->enqueueStyle( 'jde-public-reservation', 'public-reservation' );

		$settings = (array) get_option( 'jde_plugin_settings', array() );

		$config = array(
			'restUrl'      => esc_url_raw( rest_url( 'jde/v1/' ) ),
			'restNonce'    => wp_create_nonce( 'wp_rest' ),
			'containerId'  => 'jde-reservation-app-root',
			'contactEmail' => '' !== ( $settings['email_contact'] ?? '' )
				? $settings['email_contact']
				: 'info@jeuxdeleducation.com',
			'strings'      => array(
				'public_code_heading'         => '' !== ( $settings['public_code_heading'] ?? '' ) ? $settings['public_code_heading'] : null,
				'public_code_subheading'      => '' !== ( $settings['public_code_subheading'] ?? '' ) ? $settings['public_code_subheading'] : null,
				'public_quota_title'          => '' !== ( $settings['public_quota_title'] ?? '' ) ? $settings['public_quota_title'] : null,
				'public_quota_intro_single'   => '' !== ( $settings['public_quota_intro_single'] ?? '' ) ? $settings['public_quota_intro_single'] : null,
				'public_quota_intro_plural'   => '' !== ( $settings['public_quota_intro_plural'] ?? '' ) ? $settings['public_quota_intro_plural'] : null,
				'public_code_error_invalid'   => '' !== ( $settings['public_code_error_invalid'] ?? '' ) ? $settings['public_code_error_invalid'] : null,
				'public_code_error_ratelimit' => '' !== ( $settings['public_code_error_ratelimit'] ?? '' ) ? $settings['public_code_error_ratelimit'] : null,
			),
		);

		wp_add_inline_script(
			'jde-public-reservation',
			'window.jdeKiosques = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		return $this->template->capture( 'public/reservation-app.php' );
	}
}
