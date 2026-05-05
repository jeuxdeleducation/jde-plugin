<?php
/**
 * Page de réglages du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Page de réglages du module.
 *
 * Stocke un tableau associatif dans l'option `OPTION_NAME` :
 *  - contact_email   : adresse vers laquelle les candidats peuvent
 *                      écrire (variable {{contact_email}} dans les
 *                      courriels).
 *  - expediteur_nom  : nom utilisé pour l'en-tête `From:`.
 *  - expediteur_email: courriel utilisé pour l'en-tête `From:`.
 *  - intro_<role>    : bloc HTML libre affiché en tête du profil de
 *                      chaque type de personne.
 */
final class SettingsPage {

	public const OPTION_NAME = 'jde_plugin_benevoles_settings';
	public const PAGE_SLUG   = 'jde-benevoles-settings';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'handleSubmission' ) );
	}

	public function handleSubmission(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['jde_benevoles_settings_submit'] ) ) {
			return;
		}

		check_admin_referer( 'jde_benevoles_settings' );

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}

		$value = array(
			'contact_email'    => sanitize_email( (string) ( $_POST['contact_email'] ?? '' ) ),
			'expediteur_nom'   => sanitize_text_field( (string) ( $_POST['expediteur_nom'] ?? '' ) ),
			'expediteur_email' => sanitize_email( (string) ( $_POST['expediteur_email'] ?? '' ) ),
			'intro_benevole'   => wp_kses_post( wp_unslash( (string) ( $_POST['intro_benevole'] ?? '' ) ) ),
			'intro_jury'       => wp_kses_post( wp_unslash( (string) ( $_POST['intro_jury'] ?? '' ) ) ),
			'intro_arbitre'    => wp_kses_post( wp_unslash( (string) ( $_POST['intro_arbitre'] ?? '' ) ) ),
		);

		update_option( self::OPTION_NAME, $value, false );

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

		$opt     = (array) get_option( self::OPTION_NAME, array() );
		$updated = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Paramètres Bénévoles', 'jde-plugin' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php esc_html_e( 'Réglages enregistrés.', 'jde-plugin' ); ?>
				</p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'jde_benevoles_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="jde-benevoles-contact-email"><?php esc_html_e( 'Courriel de contact', 'jde-plugin' ); ?></label>
						</th>
						<td>
							<input type="email" id="jde-benevoles-contact-email" name="contact_email" class="regular-text"
								value="<?php echo esc_attr( (string) ( $opt['contact_email'] ?? '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Adresse vers laquelle les candidats peuvent écrire (utilisée comme variable dans les courriels).', 'jde-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="jde-benevoles-expediteur-nom"><?php esc_html_e( 'Nom expéditeur', 'jde-plugin' ); ?></label>
						</th>
						<td>
							<input type="text" id="jde-benevoles-expediteur-nom" name="expediteur_nom" class="regular-text"
								value="<?php echo esc_attr( (string) ( $opt['expediteur_nom'] ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="jde-benevoles-expediteur-email"><?php esc_html_e( 'Courriel expéditeur', 'jde-plugin' ); ?></label>
						</th>
						<td>
							<input type="email" id="jde-benevoles-expediteur-email" name="expediteur_email" class="regular-text"
								value="<?php echo esc_attr( (string) ( $opt['expediteur_email'] ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="jde-benevoles-intro-benevole"><?php esc_html_e( 'Introduction profil — Bénévole', 'jde-plugin' ); ?></label>
						</th>
						<td>
							<textarea id="jde-benevoles-intro-benevole" name="intro_benevole" rows="4" class="large-text code"><?php echo esc_textarea( (string) ( $opt['intro_benevole'] ?? '' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="jde-benevoles-intro-jury"><?php esc_html_e( 'Introduction profil — Jury', 'jde-plugin' ); ?></label>
						</th>
						<td>
							<textarea id="jde-benevoles-intro-jury" name="intro_jury" rows="4" class="large-text code"><?php echo esc_textarea( (string) ( $opt['intro_jury'] ?? '' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="jde-benevoles-intro-arbitre"><?php esc_html_e( 'Introduction profil — Arbitre', 'jde-plugin' ); ?></label>
						</th>
						<td>
							<textarea id="jde-benevoles-intro-arbitre" name="intro_arbitre" rows="4" class="large-text code"><?php echo esc_textarea( (string) ( $opt['intro_arbitre'] ?? '' ) ); ?></textarea>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Enregistrer les réglages', 'jde-plugin' ), 'primary', 'jde_benevoles_settings_submit' ); ?>
			</form>
		</div>
		<?php
	}
}
