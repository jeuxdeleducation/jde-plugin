<?php
/**
 * Page de paramètres du module Kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use JDE\Modules\Kiosques\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Page d'administration accessible via le menu Kiosques → Paramètres.
 *
 * Utilise l'API WordPress Settings pour persister un tableau d'options
 * sous la clé `jde_plugin_settings`. Les sections :
 *  1. Expéditeur (nom et adresse)
 *  2. Courriel — code d'accès (objet + corps personnalisé)
 *  3. Courriel — confirmation de réservation (objet + corps)
 *  4. Messages publics (textes de l'application exposant)
 */
final class SettingsPage {

	public const PAGE_SLUG   = 'jde-settings';
	public const OPTION_NAME = 'jde_plugin_settings';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
	}

	public function registerPage(): void {
		add_submenu_page(
			AdminMenu::SLUG,
			__( 'Paramètres Kiosques', 'jde-plugin' ),
			__( 'Paramètres', 'jde-plugin' ),
			Capabilities::MANAGE,
			self::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	public function registerSettings(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		// Section 1 — Expéditeur.
		add_settings_section(
			'jde_settings_expediteur',
			__( 'Expéditeur des courriels', 'jde-plugin' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Nom et adresse utilisés comme expéditeur des courriels automatiques.', 'jde-plugin' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		$this->addField(
			'email_expediteur_nom',
			__( 'Nom de l\'expéditeur', 'jde-plugin' ),
			'jde_settings_expediteur',
			'text',
			get_option( 'blogname', '' )
		);
		$this->addField(
			'email_expediteur_adresse',
			__( 'Adresse courriel de l\'expéditeur', 'jde-plugin' ),
			'jde_settings_expediteur',
			'email',
			get_option( 'admin_email', '' )
		);
		$this->addField(
			'email_contact',
			__( 'Adresse de contact (affichée aux exposants)', 'jde-plugin' ),
			'jde_settings_expediteur',
			'email',
			'info@jeuxdeleducation.com'
		);

		// Section 2 — Courriel code d'accès.
		add_settings_section(
			'jde_settings_email_code',
			__( 'Courriel — code d\'accès', 'jde-plugin' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Envoyé manuellement depuis la page Exposants. Laisser le corps vide pour utiliser le gabarit par défaut.', 'jde-plugin' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		$this->addField(
			'email_code_sujet',
			__( 'Objet du courriel', 'jde-plugin' ),
			'jde_settings_email_code',
			'text',
			''
		);
		$this->addTextareaField(
			'email_code_corps',
			__( 'Corps personnalisé (HTML)', 'jde-plugin' ),
			'jde_settings_email_code',
			__( 'Si vide, le gabarit par défaut est utilisé.', 'jde-plugin' )
		);

		// Section 3 — Courriel confirmation.
		add_settings_section(
			'jde_settings_email_confirmation',
			__( 'Courriel — confirmation de réservation', 'jde-plugin' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Envoyé automatiquement quand l\'exposant a complété toutes ses réservations.', 'jde-plugin' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		$this->addField(
			'email_confirmation_sujet',
			__( 'Objet du courriel', 'jde-plugin' ),
			'jde_settings_email_confirmation',
			'text',
			''
		);
		$this->addTextareaField(
			'email_confirmation_corps',
			__( 'Corps personnalisé (HTML)', 'jde-plugin' ),
			'jde_settings_email_confirmation',
			__( 'Si vide, le gabarit par défaut est utilisé.', 'jde-plugin' )
		);

		// Section 4 — Messages publics.
		add_settings_section(
			'jde_settings_messages',
			__( 'Messages de l\'application publique', 'jde-plugin' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Textes affichés dans l\'interface de réservation des exposants. Laisser vide pour conserver le texte par défaut.', 'jde-plugin' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		$publicFields = array(
			'public_code_heading'         => __( 'Titre de la page de code', 'jde-plugin' ),
			'public_code_subheading'      => __( 'Sous-titre de la page de code', 'jde-plugin' ),
			'public_quota_title'          => __( 'Titre de l\'écran « quota atteint »', 'jde-plugin' ),
			'public_quota_intro_single'   => __( 'Message quota atteint (1 kiosque)', 'jde-plugin' ),
			'public_quota_intro_plural'   => __( 'Message quota atteint (N kiosques) — utiliser {n} pour le nombre', 'jde-plugin' ),
			'public_code_error_invalid'   => __( 'Erreur : code invalide', 'jde-plugin' ),
			'public_code_error_ratelimit' => __( 'Erreur : trop de tentatives', 'jde-plugin' ),
		);

		foreach ( $publicFields as $key => $label ) {
			$this->addField( $key, $label, 'jde_settings_messages', 'text', '' );
		}
	}

	public function renderPage(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Paramètres Kiosques', 'jde-plugin' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE_SLUG );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Nettoyer les valeurs avant persistance.
	 *
	 * @param mixed $input Données brutes du formulaire.
	 * @return array<string, string>
	 */
	public function sanitize( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$htmlFields = array( 'email_code_corps', 'email_confirmation_corps' );
		$out        = array();

		foreach ( $input as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( in_array( $key, $htmlFields, true ) ) {
				$out[ $key ] = wp_kses_post( (string) $value );
			} elseif ( str_ends_with( $key, '_adresse' ) || str_ends_with( $key, 'email_contact' ) ) {
				$out[ $key ] = sanitize_email( (string) $value );
			} else {
				$out[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $out;
	}

	/**
	 * Enregistrer un champ texte/email standard.
	 */
	private function addField(
		string $key,
		string $label,
		string $section,
		string $type,
		string $placeholder
	): void {
		add_settings_field(
			'jde_' . $key,
			$label,
			function () use ( $key, $type, $placeholder ): void {
				$options = (array) get_option( self::OPTION_NAME, array() );
				$value   = $options[ $key ] ?? '';
				printf(
					'<input type="%s" name="%s[%s]" value="%s" placeholder="%s" class="regular-text">',
					esc_attr( $type ),
					esc_attr( self::OPTION_NAME ),
					esc_attr( $key ),
					esc_attr( $value ),
					esc_attr( $placeholder )
				);
			},
			self::PAGE_SLUG,
			$section
		);
	}

	/**
	 * Enregistrer un champ textarea.
	 */
	private function addTextareaField( string $key, string $label, string $section, string $description ): void {
		add_settings_field(
			'jde_' . $key,
			$label,
			function () use ( $key, $description ): void {
				$options = (array) get_option( self::OPTION_NAME, array() );
				$value   = $options[ $key ] ?? '';
				printf(
					'<textarea name="%s[%s]" rows="6" class="large-text">%s</textarea>',
					esc_attr( self::OPTION_NAME ),
					esc_attr( $key ),
					esc_textarea( $value )
				);
				if ( '' !== $description ) {
					echo '<p class="description">' . esc_html( $description ) . '</p>';
				}
			},
			self::PAGE_SLUG,
			$section
		);
	}
}
