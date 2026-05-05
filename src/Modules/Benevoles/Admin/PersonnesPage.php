<?php
/**
 * Page admin : liste des personnes inscrites.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Repositories\InscriptionReponseRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Services\AcceptanceService;
use JDE\Modules\Benevoles\Services\EvenementRhService;

defined( 'ABSPATH' ) || exit;

/**
 * Liste filtrable des personnes de l'édition RH active avec actions
 * accepter/refuser et édition de l'URL OneDrive.
 */
final class PersonnesPage {

	public const PAGE_SLUG = 'jde-benevoles-personnes';

	private static ?self $instance = null;

	public function __construct(
		private readonly PersonneRepository $personnes,
		private readonly InscriptionReponseRepository $reponses,
		private readonly EvenementRhService $evenementService,
		private readonly AcceptanceService $acceptanceService,
	) {
		self::$instance = $this;
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'handleAction' ) );
	}

	public function handleAction(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['jde_personnes_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['jde_personnes_action'] ) )
			: '';
		if ( '' === $action ) {
			return;
		}

		check_admin_referer( 'jde_personnes_action' );

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}

		$id = (int) ( $_POST['personne_id'] ?? 0 );
		if ( $id <= 0 ) {
			return;
		}

		try {
			switch ( $action ) {
				case 'accept':
					$this->acceptanceService->accept( $id, (int) get_current_user_id() );
					break;
				case 'reject':
					$motif = sanitize_textarea_field( (string) ( $_POST['motif'] ?? '' ) );
					$this->acceptanceService->reject( $id, (int) get_current_user_id(), $motif );
					break;
				case 'set_onedrive':
					$url      = esc_url_raw( (string) ( $_POST['onedrive_url'] ?? '' ) );
					$personne = $this->personnes->findById( $id );
					if ( null !== $personne ) {
						$this->personnes->save(
							new Personne(
								id: $personne->id,
								evenementRhId: $personne->evenementRhId,
								typeRole: $personne->typeRole,
								prenom: $personne->prenom,
								nom: $personne->nom,
								courriel: $personne->courriel,
								telephone: $personne->telephone,
								statut: $personne->statut,
								wpUserId: $personne->wpUserId,
								onedriveUrl: '' !== $url ? $url : null,
								decidePar: $personne->decidePar,
								dateInscription: $personne->dateInscription,
								dateDecision: $personne->dateDecision,
								dateFinEvenement: $personne->dateFinEvenement,
							)
						);
					}
					break;
			}
		} catch ( \Throwable $e ) {
			set_transient( 'jde_personnes_error_' . get_current_user_id(), $e->getMessage(), 30 );
		}

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

		if ( null === self::$instance ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Personnes', 'jde-plugin' ) . '</h1></div>';
			return;
		}

		self::$instance->renderPage();
	}

	private function renderPage(): void {
		$evenementId = $this->evenementService->getActiveId();

		$filterStatut = sanitize_key( (string) ( $_GET['statut'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filterRole   = sanitize_key( (string) ( $_GET['role'] ?? '' ) );    // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$filters = array();
		if ( '' !== $filterStatut ) {
			$filters['statut'] = $filterStatut;
		}
		if ( '' !== $filterRole ) {
			$filters['type_role'] = $filterRole;
		}

		$personnes = null === $evenementId
			? array()
			: $this->personnes->findByEvenement( $evenementId, $filters );

		$err = get_transient( 'jde_personnes_error_' . get_current_user_id() );
		delete_transient( 'jde_personnes_error_' . get_current_user_id() );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Personnes', 'jde-plugin' ); ?></h1>

			<?php if ( null === $evenementId ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Aucune édition RH active.', 'jde-plugin' ); ?></p></div>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ( $err ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( (string) $err ); ?></p></div>
			<?php endif; ?>

			<form method="get" style="margin:1em 0">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<select name="statut">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'jde-plugin' ); ?></option>
					<?php foreach ( array( Personne::STATUT_EN_ATTENTE, Personne::STATUT_ACCEPTEE, Personne::STATUT_REFUSEE ) as $st ) : ?>
						<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filterStatut, $st ); ?>><?php echo esc_html( $st ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="role">
					<option value=""><?php esc_html_e( 'Tous les rôles', 'jde-plugin' ); ?></option>
					<?php foreach ( array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE ) as $r ) : ?>
						<option value="<?php echo esc_attr( $r ); ?>" <?php selected( $filterRole, $r ); ?>><?php echo esc_html( $r ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filtrer', 'jde-plugin' ), 'secondary', '', false ); ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nom', 'jde-plugin' ); ?></th>
						<th><?php esc_html_e( 'Courriel', 'jde-plugin' ); ?></th>
						<th><?php esc_html_e( 'Rôle', 'jde-plugin' ); ?></th>
						<th><?php esc_html_e( 'Statut', 'jde-plugin' ); ?></th>
						<th><?php esc_html_e( 'Inscription', 'jde-plugin' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'jde-plugin' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( array() === $personnes ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Aucune personne ne correspond à ces filtres.', 'jde-plugin' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $personnes as $p ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $p->prenom . ' ' . $p->nom ); ?></strong>
								<?php $reps = null !== $p->id ? $this->reponses->findByPersonneId( $p->id ) : array(); ?>
								<?php if ( array() !== $reps ) : ?>
									<details><summary><?php esc_html_e( 'Voir les réponses', 'jde-plugin' ); ?></summary>
										<ul>
											<?php foreach ( $reps as $r ) : ?>
												<li><strong><?php echo esc_html( $r->fieldLabel ); ?> :</strong> <?php echo esc_html( (string) $r->fieldValue ); ?></li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $p->courriel ); ?></td>
							<td><?php echo esc_html( $p->typeRole ); ?></td>
							<td><?php echo esc_html( $p->statut ); ?></td>
							<td><?php echo esc_html( $p->dateInscription->format( 'Y-m-d H:i' ) ); ?></td>
							<td>
								<?php if ( Personne::STATUT_EN_ATTENTE === $p->statut ) : ?>
									<form method="post" style="display:inline">
										<?php wp_nonce_field( 'jde_personnes_action' ); ?>
										<input type="hidden" name="personne_id" value="<?php echo (int) ( $p->id ?? 0 ); ?>" />
										<input type="hidden" name="jde_personnes_action" value="accept" />
										<button class="button button-primary"><?php esc_html_e( 'Accepter', 'jde-plugin' ); ?></button>
									</form>
									<form method="post" style="display:inline" onsubmit="return confirm('<?php esc_attr_e( 'Refuser cette candidature ?', 'jde-plugin' ); ?>')">
										<?php wp_nonce_field( 'jde_personnes_action' ); ?>
										<input type="hidden" name="personne_id" value="<?php echo (int) ( $p->id ?? 0 ); ?>" />
										<input type="hidden" name="jde_personnes_action" value="reject" />
										<button class="button"><?php esc_html_e( 'Refuser', 'jde-plugin' ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( Personne::STATUT_ACCEPTEE === $p->statut ) : ?>
									<form method="post" style="margin-top:.5em">
										<?php wp_nonce_field( 'jde_personnes_action' ); ?>
										<input type="hidden" name="personne_id" value="<?php echo (int) ( $p->id ?? 0 ); ?>" />
										<input type="hidden" name="jde_personnes_action" value="set_onedrive" />
										<input type="url" name="onedrive_url" value="<?php echo esc_attr( (string) $p->onedriveUrl ); ?>" placeholder="https://…" style="width:240px" />
										<button class="button"><?php esc_html_e( 'Mettre à jour OneDrive', 'jde-plugin' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
