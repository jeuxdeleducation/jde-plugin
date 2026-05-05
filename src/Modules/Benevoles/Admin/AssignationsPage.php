<?php
/**
 * Page admin : grille d'assignations + suggestion automatique.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Repositories\AssignationRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;
use JDE\Modules\Benevoles\Repositories\QuartRepository;
use JDE\Modules\Benevoles\Services\AssignmentService;
use JDE\Modules\Benevoles\Services\AssignmentSuggester;
use JDE\Modules\Benevoles\Services\EvenementRhService;

defined( 'ABSPATH' ) || exit;

/**
 * Vue d'ensemble des assignations + bouton « Suggérer » qui calcule des
 * propositions affichées en mode brouillon, qu'un second clic
 * « Appliquer » transforme en assignations `proposee` réelles.
 */
final class AssignationsPage {

	public const PAGE_SLUG = 'jde-benevoles-assignations';

	private static ?self $instance = null;

	public function __construct(
		private readonly AssignmentSuggester $suggester,
		private readonly AssignmentService $assignmentService,
		private readonly EvenementRhService $evenementService,
		private readonly PosteRepository $postes,
		private readonly QuartRepository $quarts,
		private readonly PersonneRepository $personnes,
		private readonly AssignationRepository $assignations,
	) {
		self::$instance = $this;
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'handleAction' ) );
	}

	public function handleAction(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['jde_assign_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['jde_assign_action'] ) )
			: '';
		if ( '' === $action ) {
			return;
		}

		check_admin_referer( 'jde_assign_action' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}

		try {
			switch ( $action ) {
				case 'apply_suggestion':
					$personneId = (int) ( $_POST['personne_id'] ?? 0 );
					$quartId    = (int) ( $_POST['quart_id'] ?? 0 );
					if ( $personneId > 0 && $quartId > 0 ) {
						$this->assignmentService->propose( $personneId, $quartId, (int) get_current_user_id() );
					}
					break;
				case 'create_manual':
					$personneId = (int) ( $_POST['personne_id'] ?? 0 );
					$quartId    = (int) ( $_POST['quart_id'] ?? 0 );
					if ( $personneId > 0 && $quartId > 0 ) {
						$this->assignmentService->propose( $personneId, $quartId, (int) get_current_user_id() );
					}
					break;
				case 'delete':
					$id = (int) ( $_POST['assignation_id'] ?? 0 );
					if ( $id > 0 ) {
						$this->assignations->delete( $id );
					}
					break;
			}
		} catch ( \Throwable $e ) {
			set_transient( 'jde_assign_error_' . get_current_user_id(), $e->getMessage(), 30 );
		}

		wp_safe_redirect(
			add_query_arg(
				array_filter(
					array(
						'page'      => self::PAGE_SLUG,
						'updated'   => '1',
						'show_sugg' => isset( $_POST['show_sugg'] ) ? '1' : null,
					)
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
			echo '<div class="wrap"><h1>' . esc_html__( 'Assignations', 'jde-plugin' ) . '</h1></div>';
			return;
		}
		self::$instance->renderPage();
	}

	private function renderPage(): void {
		$evenementId = $this->evenementService->getActiveId();
		$showSugg    = ! empty( $_GET['show_sugg'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$err         = get_transient( 'jde_assign_error_' . get_current_user_id() );
		delete_transient( 'jde_assign_error_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Assignations', 'jde-plugin' ); ?></h1>
			<?php if ( null === $evenementId ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Aucune édition RH active.', 'jde-plugin' ); ?></p></div>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ( $err ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( (string) $err ); ?></p></div>
			<?php endif; ?>

			<p>
				<a class="button button-primary" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'      => self::PAGE_SLUG,
							'show_sugg' => '1',
						),
						admin_url( 'admin.php' )
					)
				);
				?>
														">
					<?php esc_html_e( 'Suggérer des affectations', 'jde-plugin' ); ?>
				</a>
			</p>

			<?php if ( $showSugg ) : ?>
				<h2><?php esc_html_e( 'Suggestions', 'jde-plugin' ); ?></h2>
				<?php $suggestions = $this->suggester->suggest( $evenementId ); ?>
				<?php if ( array() === $suggestions ) : ?>
					<p><?php esc_html_e( 'Aucune suggestion disponible (aucun poste, aucun quart, ou plus de places).', 'jde-plugin' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr>
							<th><?php esc_html_e( 'Quart', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Poste', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Places', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Suggestions', 'jde-plugin' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $suggestions as $sugg ) : ?>
							<tr>
								<td><?php echo esc_html( $sugg['date_debut'] . ' → ' . $sugg['date_fin'] ); ?></td>
								<td><?php echo esc_html( $sugg['poste_nom'] . ' (' . $sugg['type_role'] . ')' ); ?></td>
								<td><?php echo (int) $sugg['places_restantes']; ?></td>
								<td>
									<?php foreach ( $sugg['suggestions'] as $s ) : ?>
										<form method="post" style="display:inline-block;margin-right:.5em">
											<?php wp_nonce_field( 'jde_assign_action' ); ?>
											<input type="hidden" name="jde_assign_action" value="apply_suggestion" />
											<input type="hidden" name="show_sugg" value="1" />
											<input type="hidden" name="personne_id" value="<?php echo (int) $s['personne_id']; ?>" />
											<input type="hidden" name="quart_id" value="<?php echo (int) $sugg['quart_id']; ?>" />
											<button class="button"><?php echo esc_html( $s['prenom'] . ' ' . $s['nom'] ); ?> ✓</button>
										</form>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>

			<h2 style="margin-top:2em"><?php esc_html_e( 'Assignations existantes', 'jde-plugin' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e( 'Personne', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Quart', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Poste', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'jde-plugin' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php
				$personnes = $this->personnes->findByEvenement( $evenementId );
				$rows      = array();
				foreach ( $personnes as $personne ) {
					if ( null === $personne->id ) {
						continue;
					}
					foreach ( $this->assignations->findByPersonne( $personne->id ) as $a ) {
						$quart  = $this->quarts->findById( $a->quartId );
						$poste  = null !== $quart ? $this->postes->findById( $quart->posteId ) : null;
						$rows[] = array(
							'p'  => $personne,
							'a'  => $a,
							'q'  => $quart,
							'po' => $poste,
						);
					}
				}
				?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['p']->prenom . ' ' . $row['p']->nom ); ?></td>
						<td><?php echo $row['q'] ? esc_html( $row['q']->dateDebut->format( 'Y-m-d H:i' ) ) : '—'; ?></td>
						<td><?php echo $row['po'] ? esc_html( $row['po']->nom ) : '—'; ?></td>
						<td><?php echo esc_html( $row['a']->statut ); ?>
						<?php
						if ( $row['a']->motifRefus ) :
							?>
							<br /><em><?php echo esc_html( $row['a']->motifRefus ); ?></em><?php endif; ?></td>
						<td>
							<form method="post" onsubmit="return confirm('?')">
								<?php wp_nonce_field( 'jde_assign_action' ); ?>
								<input type="hidden" name="jde_assign_action" value="delete" />
								<input type="hidden" name="assignation_id" value="<?php echo (int) ( $row['a']->id ?? 0 ); ?>" />
								<button class="button-link-delete">×</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
