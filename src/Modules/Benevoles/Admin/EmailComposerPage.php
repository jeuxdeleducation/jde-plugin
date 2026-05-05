<?php
/**
 * Page admin : composer un courriel ad-hoc.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Repositories\EmailLogRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Services\BenevoleEmailService;
use JDE\Modules\Benevoles\Services\EvenementRhService;

defined( 'ABSPATH' ) || exit;

/**
 * Composer + diffusion ciblée par rôle/statut. Affiche également
 * l'historique des envois pour l'édition active.
 */
final class EmailComposerPage {

	public const PAGE_SLUG = 'jde-benevoles-courriels';

	private static ?self $instance = null;

	public function __construct(
		private readonly EvenementRhService $evenementService,
		private readonly PersonneRepository $personnes,
		private readonly BenevoleEmailService $emails,
		private readonly EmailLogRepository $logs,
	) {
		self::$instance = $this;
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'handleSubmission' ) );
	}

	public function handleSubmission(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['jde_composer_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['jde_composer_action'] ) )
			: '';
		if ( '' === $action ) {
			return;
		}
		check_admin_referer( 'jde_composer' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}

		$evenementId = $this->evenementService->getActiveId();
		if ( null === $evenementId ) {
			wp_die( esc_html__( 'Aucune édition RH active.', 'jde-plugin' ) );
		}

		$filters = array(
			'statut'    => sanitize_key( (string) ( $_POST['filter_statut'] ?? '' ) ),
			'type_role' => sanitize_key( (string) ( $_POST['filter_role'] ?? '' ) ),
		);
		$filters = array_filter( $filters );

		$destinataires = $this->personnes->findByEvenement( $evenementId, $filters );

		$subject = sanitize_text_field( (string) ( $_POST['subject'] ?? '' ) );
		$body    = wp_kses_post( wp_unslash( (string) ( $_POST['body'] ?? '' ) ) );

		if ( 'preview' === $action ) {
			$first   = $destinataires[0] ?? null;
			$vars    = array(
				'prenom'  => $first ? $first->prenom : 'Prénom',
				'nom'     => $first ? $first->nom : 'Nom',
				'message' => $body,
			);
			$preview = $this->emails->preview( BenevoleEmailService::TPL_BROADCAST, $vars );
			set_transient(
				'jde_composer_preview_' . get_current_user_id(),
				array(
					'subject' => $preview['subject'],
					'body'    => $preview['body'],
					'count'   => count( $destinataires ),
				),
				120
			);
		} elseif ( 'send' === $action ) {
			$count = $this->emails->broadcast( $evenementId, $subject, $body, $destinataires, $filters );
			set_transient( 'jde_composer_sent_' . get_current_user_id(), $count, 60 );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}
		if ( null === self::$instance ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Composer', 'jde-plugin' ) . '</h1></div>';
			return;
		}
		self::$instance->renderPage();
	}

	private function renderPage(): void {
		$evenementId = $this->evenementService->getActiveId();
		$preview     = get_transient( 'jde_composer_preview_' . get_current_user_id() );
		$sent        = get_transient( 'jde_composer_sent_' . get_current_user_id() );
		delete_transient( 'jde_composer_preview_' . get_current_user_id() );
		delete_transient( 'jde_composer_sent_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Composer un courriel', 'jde-plugin' ); ?></h1>
			<?php if ( null === $evenementId ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Aucune édition RH active.', 'jde-plugin' ); ?></p></div>
				</div>
				<?php return; ?>
			<?php endif; ?>
			<?php if ( false !== $sent ) : ?>
				<div class="notice notice-success"><p>
				<?php
					/* translators: %d: number of emails sent */
					echo esc_html( sprintf( _n( '%d courriel envoyé.', '%d courriels envoyés.', (int) $sent, 'jde-plugin' ), (int) $sent ) );
				?>
				</p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'jde_composer' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Statut', 'jde-plugin' ); ?></th><td>
						<select name="filter_statut">
							<option value=""><?php esc_html_e( 'Tous', 'jde-plugin' ); ?></option>
							<?php foreach ( array( Personne::STATUT_EN_ATTENTE, Personne::STATUT_ACCEPTEE, Personne::STATUT_REFUSEE ) as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $st ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th><?php esc_html_e( 'Rôle', 'jde-plugin' ); ?></th><td>
						<select name="filter_role">
							<option value=""><?php esc_html_e( 'Tous', 'jde-plugin' ); ?></option>
							<?php foreach ( array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE ) as $r ) : ?>
								<option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( $r ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th><label for="jde-composer-subject"><?php esc_html_e( 'Sujet', 'jde-plugin' ); ?></label></th><td>
						<input type="text" id="jde-composer-subject" name="subject" class="large-text" required />
					</td></tr>
					<tr><th><label for="jde-composer-body"><?php esc_html_e( 'Corps (HTML, supporte les variables {{prenom}}, {{nom}})', 'jde-plugin' ); ?></label></th><td>
						<textarea id="jde-composer-body" name="body" rows="10" class="large-text code" required></textarea>
					</td></tr>
				</table>
				<button class="button" name="jde_composer_action" value="preview"><?php esc_html_e( 'Aperçu', 'jde-plugin' ); ?></button>
				<button class="button button-primary" name="jde_composer_action" value="send" onclick="return confirm('<?php esc_attr_e( 'Confirmer l\'envoi à tous les destinataires ?', 'jde-plugin' ); ?>')"><?php esc_html_e( 'Envoyer', 'jde-plugin' ); ?></button>
			</form>

			<?php if ( is_array( $preview ) ) : ?>
				<h2><?php esc_html_e( 'Aperçu', 'jde-plugin' ); ?></h2>
				<p><strong><?php esc_html_e( 'Sujet :', 'jde-plugin' ); ?></strong> <?php echo esc_html( (string) $preview['subject'] ); ?></p>
				<p><strong><?php esc_html_e( 'Destinataires sélectionnés :', 'jde-plugin' ); ?></strong> <?php echo (int) $preview['count']; ?></p>
				<iframe srcdoc="<?php echo esc_attr( (string) $preview['body'] ); ?>" style="width:100%;height:400px;border:1px solid #ccc"></iframe>
			<?php endif; ?>

			<h2 style="margin-top:2em"><?php esc_html_e( 'Historique des envois', 'jde-plugin' ); ?></h2>
			<?php $logsList = $this->logs->findByEvenement( $evenementId, 25 ); ?>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e( 'Date', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Modèle', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Sujet', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Destinataires', 'jde-plugin' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $logsList as $l ) : ?>
					<tr>
						<td><?php echo esc_html( $l->sentAt->format( 'Y-m-d H:i' ) ); ?></td>
						<td><?php echo esc_html( $l->template ); ?></td>
						<td><?php echo esc_html( $l->subject ); ?></td>
						<td><?php echo (int) $l->recipientCount; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
