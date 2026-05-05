<?php
/**
 * Page admin : édition des 6 modèles transactionnels.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Services\BenevoleEmailService;

defined( 'ABSPATH' ) || exit;

/**
 * Édite les modèles `subject` + `html_body` stockés dans l'option
 * `jde_plugin_benevoles_emails`. Si une entrée est laissée vide, le
 * service retombera sur le fichier de fallback dans templates/emails.
 */
final class EmailTemplatesPage {

	public const PAGE_SLUG = 'jde-benevoles-modeles';

	private static ?self $instance = null;

	public function register(): void {
		self::$instance = $this;
		add_action( 'admin_init', array( $this, 'handleSubmission' ) );
	}

	public function handleSubmission(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['jde_modeles_submit'] ) ) {
			return;
		}
		check_admin_referer( 'jde_modeles' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}

		$value = (array) get_option( BenevoleEmailService::OPTION_KEY, array() );
		foreach ( self::templates() as $key => $_label ) {
			$value[ $key ] = array(
				'subject'   => sanitize_text_field( (string) ( $_POST[ 'subject_' . $key ] ?? '' ) ),
				'html_body' => wp_kses_post( wp_unslash( (string) ( $_POST[ 'body_' . $key ] ?? '' ) ) ),
			);
		}
		update_option( BenevoleEmailService::OPTION_KEY, $value, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}
		$value = (array) get_option( BenevoleEmailService::OPTION_KEY, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Modèles de courriels', 'jde-plugin' ); ?></h1>
			<p><?php esc_html_e( 'Variables : {{prenom}}, {{nom}}, {{courriel}}, {{evenement_titre}}, {{contact_email}}. Sections conditionnelles : {{#var}}…{{/var}}.', 'jde-plugin' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'jde_modeles' ); ?>
				<?php
				foreach ( self::templates() as $key => $label ) :
					$entry = (array) ( $value[ $key ] ?? array() );
					?>
					<h2><?php echo esc_html( $label ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th><label><?php esc_html_e( 'Sujet', 'jde-plugin' ); ?></label></th>
							<td><input type="text" class="large-text" name="subject_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) ( $entry['subject'] ?? '' ) ); ?>" /></td></tr>
						<tr><th><label><?php esc_html_e( 'Corps HTML', 'jde-plugin' ); ?></label></th>
							<td><textarea class="large-text code" rows="8" name="body_<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( (string) ( $entry['html_body'] ?? '' ) ); ?></textarea></td></tr>
					</table>
				<?php endforeach; ?>
				<?php submit_button( __( 'Enregistrer les modèles', 'jde-plugin' ), 'primary', 'jde_modeles_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array<string, string>
	 */
	private static function templates(): array {
		return array(
			BenevoleEmailService::TPL_CONFIRMATION => __( 'Confirmation d\'inscription', 'jde-plugin' ),
			BenevoleEmailService::TPL_ACCEPTATION  => __( 'Acceptation', 'jde-plugin' ),
			BenevoleEmailService::TPL_REFUS        => __( 'Refus', 'jde-plugin' ),
			BenevoleEmailService::TPL_ASSIGNATION  => __( 'Assignation', 'jde-plugin' ),
			BenevoleEmailService::TPL_RAPPEL       => __( 'Rappel avant événement', 'jde-plugin' ),
			BenevoleEmailService::TPL_REMERCIEMENT => __( 'Remerciement', 'jde-plugin' ),
		);
	}
}
