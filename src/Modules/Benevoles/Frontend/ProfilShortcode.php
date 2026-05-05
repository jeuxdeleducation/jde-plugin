<?php
/**
 * Shortcode `[jde_profil_benevole]`.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Frontend;

use JDE\Modules\Benevoles\Admin\SettingsPage;
use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;
use JDE\Support\Assets;
use JDE\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Rend le profil personnel pour les bénévoles/jurys/arbitres connectés.
 *
 * Délègue tout l'UI au bundle React `public-profil`. Les sections
 * affichées (assignations, signatures, OneDrive…) sont calculées par
 * le contrôleur REST `PublicProfileController`.
 */
final class ProfilShortcode {

	public const TAG = 'jde_profil_benevole';

	public function __construct(
		private readonly Assets $assets,
		private readonly Template $template,
	) {}

	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function render( array|string $atts = array(), ?string $content = null ): string {
		unset( $atts, $content );

		if ( ! is_user_logged_in() ) {
			return '<div class="jde-profil__notice"><p>'
				. esc_html__( 'Connectez-vous avec votre compte bénévole/jury/arbitre.', 'jde-plugin' )
				. '</p></div>';
		}

		$this->assets->enqueueScript( 'jde-public-profil', 'public-profil', array( 'wp-element' ) );
		$this->assets->enqueueStyle( 'jde-public-profil', 'public-profil' );

		$settings = (array) get_option( SettingsPage::OPTION_NAME, array() );

		$config = array(
			'restUrl'       => esc_url_raw( rest_url( 'jde/v1/benevoles/' ) ),
			'restNonce'     => wp_create_nonce( 'wp_rest' ),
			'containerId'   => 'jde-profil-app-root',
			'contactEmail'  => (string) ( $settings['contact_email'] ?? get_option( 'admin_email', '' ) ),
			'profilContent' => array(
				'benevole' => (string) ( $settings['intro_benevole'] ?? '' ),
				'jury'     => (string) ( $settings['intro_jury'] ?? '' ),
				'arbitre'  => (string) ( $settings['intro_arbitre'] ?? '' ),
			),
		);

		wp_add_inline_script(
			'jde-public-profil',
			'window.jdeBenevoles = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		return $this->template->capture( 'public/profil-app.php' );
	}
}
