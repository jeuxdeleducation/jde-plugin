<?php
/**
 * Page admin : CRUD postes / quarts / plages.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Models\PlageDisponibilite;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Models\Poste;
use JDE\Modules\Benevoles\Models\Quart;
use JDE\Modules\Benevoles\Repositories\PlageDisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;
use JDE\Modules\Benevoles\Repositories\QuartRepository;
use JDE\Modules\Benevoles\Services\EvenementRhService;

defined( 'ABSPATH' ) || exit;

/**
 * Permet au gestionnaire de créer/modifier/supprimer postes, quarts et
 * plages de disponibilité de l'édition active.
 */
final class PostesPage {

	public const PAGE_SLUG = 'jde-benevoles-postes';

	private static ?self $instance = null;

	public function __construct(
		private readonly PosteRepository $postes,
		private readonly QuartRepository $quarts,
		private readonly PlageDisponibiliteRepository $plages,
		private readonly EvenementRhService $evenementService,
	) {
		self::$instance = $this;
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'handleAction' ) );
	}

	public function handleAction(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['jde_postes_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['jde_postes_action'] ) )
			: '';
		if ( '' === $action ) {
			return;
		}

		check_admin_referer( 'jde_postes_action' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}

		$evenementId = $this->evenementService->getActiveId();
		if ( null === $evenementId ) {
			wp_die( esc_html__( 'Aucune édition RH active.', 'jde-plugin' ) );
		}

		switch ( $action ) {
			case 'save_poste':
				$this->savePoste( $evenementId );
				break;
			case 'delete_poste':
				$id = (int) ( $_POST['poste_id'] ?? 0 );
				if ( $id > 0 ) {
					$this->postes->delete( $id );
				}
				break;
			case 'save_quart':
				$this->saveQuart();
				break;
			case 'delete_quart':
				$id = (int) ( $_POST['quart_id'] ?? 0 );
				if ( $id > 0 ) {
					$this->quarts->delete( $id );
				}
				break;
			case 'save_plage':
				$this->savePlage( $evenementId );
				break;
			case 'delete_plage':
				$id = (int) ( $_POST['plage_id'] ?? 0 );
				if ( $id > 0 ) {
					$this->plages->delete( $id );
				}
				break;
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

	// Les helpers ci-dessous sont appelés depuis handleAction() qui a déjà
	// vérifié le nonce (check_admin_referer). PHPCS ne suit pas l'appel,
	// d'où le phpcs:disable sur la lecture de $_POST.
	private function savePoste( int $evenementId ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$id           = (int) ( $_POST['poste_id'] ?? 0 );
		$nom          = sanitize_text_field( (string) ( $_POST['nom'] ?? '' ) );
		$description  = sanitize_textarea_field( (string) ( $_POST['description'] ?? '' ) );
		$lieu         = sanitize_text_field( (string) ( $_POST['lieu'] ?? '' ) );
		$nbSouhaite   = max( 1, (int) ( $_POST['nb_personnes_souhaite'] ?? 1 ) );
		$responsableR = (int) ( $_POST['responsable_user_id'] ?? 0 );
		$typeRole     = sanitize_key( (string) ( $_POST['type_role'] ?? Personne::TYPE_BENEVOLE ) );
		// phpcs:enable

		$this->postes->save(
			new Poste(
				id: $id > 0 ? $id : null,
				evenementRhId: $evenementId,
				nom: $nom,
				description: '' !== $description ? $description : null,
				lieu: '' !== $lieu ? $lieu : null,
				nbPersonnesSouhaite: $nbSouhaite,
				responsableUserId: $responsableR > 0 ? $responsableR : null,
				typeRole: $typeRole,
			)
		);
	}

	private function saveQuart(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$id      = (int) ( $_POST['quart_id'] ?? 0 );
		$posteId = (int) ( $_POST['poste_id_for_quart'] ?? 0 );
		$debutIn = (string) ( $_POST['date_debut'] ?? 'now' );
		$finIn   = (string) ( $_POST['date_fin'] ?? 'now' );
		// phpcs:enable

		if ( $posteId <= 0 ) {
			return;
		}
		$tz    = new DateTimeZone( 'UTC' );
		$debut = new DateTimeImmutable( $debutIn, $tz );
		$fin   = new DateTimeImmutable( $finIn, $tz );

		$this->quarts->save(
			new Quart(
				id: $id > 0 ? $id : null,
				posteId: $posteId,
				dateDebut: $debut,
				dateFin: $fin,
			)
		);
	}

	private function savePlage( int $evenementId ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$id      = (int) ( $_POST['plage_id'] ?? 0 );
		$libelle = sanitize_text_field( (string) ( $_POST['libelle'] ?? '' ) );
		$debutIn = (string) ( $_POST['date_debut'] ?? 'now' );
		$finIn   = (string) ( $_POST['date_fin'] ?? 'now' );
		$ordre   = (int) ( $_POST['ordre'] ?? 0 );
		// phpcs:enable

		$tz    = new DateTimeZone( 'UTC' );
		$debut = new DateTimeImmutable( $debutIn, $tz );
		$fin   = new DateTimeImmutable( $finIn, $tz );

		$this->plages->save(
			new PlageDisponibilite(
				id: $id > 0 ? $id : null,
				evenementRhId: $evenementId,
				libelle: $libelle,
				dateDebut: $debut,
				dateFin: $fin,
				ordre: $ordre,
			)
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}

		if ( null === self::$instance ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Postes & quarts', 'jde-plugin' ) . '</h1></div>';
			return;
		}
		self::$instance->renderPage();
	}

	private function renderPage(): void {
		$evenementId = $this->evenementService->getActiveId();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Postes & quarts', 'jde-plugin' ); ?></h1>
			<?php if ( null === $evenementId ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Aucune édition RH active.', 'jde-plugin' ); ?></p></div>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Postes', 'jde-plugin' ); ?></h2>
			<?php $postes = $this->postes->findByEvenement( $evenementId ); ?>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e( 'Nom', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Rôle', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Lieu', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Nb souhaité', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Quarts', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'jde-plugin' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				foreach ( $postes as $p ) :
					$quartsP = null !== $p->id ? $this->quarts->findByPoste( $p->id ) : array();
					?>
					<tr>
						<td><strong><?php echo esc_html( $p->nom ); ?></strong>
						<?php
						if ( $p->description ) :
							?>
							<br /><em><?php echo esc_html( $p->description ); ?></em><?php endif; ?></td>
						<td><?php echo esc_html( $p->typeRole ); ?></td>
						<td><?php echo esc_html( (string) $p->lieu ); ?></td>
						<td><?php echo (int) $p->nbPersonnesSouhaite; ?></td>
						<td>
							<?php foreach ( $quartsP as $q ) : ?>
								<div>
									<?php echo esc_html( $q->dateDebut->format( 'Y-m-d H:i' ) . ' → ' . $q->dateFin->format( 'H:i' ) ); ?>
									<form method="post" style="display:inline" onsubmit="return confirm('<?php esc_attr_e( 'Supprimer ce quart ?', 'jde-plugin' ); ?>')">
										<?php wp_nonce_field( 'jde_postes_action' ); ?>
										<input type="hidden" name="jde_postes_action" value="delete_quart" />
										<input type="hidden" name="quart_id" value="<?php echo (int) ( $q->id ?? 0 ); ?>" />
										<button class="button-link-delete">×</button>
									</form>
								</div>
							<?php endforeach; ?>
							<form method="post" style="margin-top:.5em">
								<?php wp_nonce_field( 'jde_postes_action' ); ?>
								<input type="hidden" name="jde_postes_action" value="save_quart" />
								<input type="hidden" name="poste_id_for_quart" value="<?php echo (int) ( $p->id ?? 0 ); ?>" />
								<input type="datetime-local" name="date_debut" required />
								<input type="datetime-local" name="date_fin" required />
								<button class="button"><?php esc_html_e( '+ Quart', 'jde-plugin' ); ?></button>
							</form>
						</td>
						<td>
							<form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Supprimer ce poste et tous ses quarts ?', 'jde-plugin' ); ?>')">
								<?php wp_nonce_field( 'jde_postes_action' ); ?>
								<input type="hidden" name="jde_postes_action" value="delete_poste" />
								<input type="hidden" name="poste_id" value="<?php echo (int) ( $p->id ?? 0 ); ?>" />
								<button class="button button-link-delete"><?php esc_html_e( 'Supprimer', 'jde-plugin' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Ajouter un poste', 'jde-plugin' ); ?></h3>
			<form method="post">
				<?php wp_nonce_field( 'jde_postes_action' ); ?>
				<input type="hidden" name="jde_postes_action" value="save_poste" />
				<table class="form-table" role="presentation">
					<tr><th><label><?php esc_html_e( 'Nom', 'jde-plugin' ); ?></label></th><td><input type="text" name="nom" class="regular-text" required /></td></tr>
					<tr><th><label><?php esc_html_e( 'Description', 'jde-plugin' ); ?></label></th><td><textarea name="description" rows="2" class="large-text"></textarea></td></tr>
					<tr><th><label><?php esc_html_e( 'Lieu', 'jde-plugin' ); ?></label></th><td><input type="text" name="lieu" class="regular-text" /></td></tr>
					<tr><th><label><?php esc_html_e( 'Nb personnes souhaité', 'jde-plugin' ); ?></label></th><td><input type="number" name="nb_personnes_souhaite" min="1" value="1" /></td></tr>
					<tr><th><label><?php esc_html_e( 'Responsable (ID utilisateur)', 'jde-plugin' ); ?></label></th><td><input type="number" name="responsable_user_id" min="0" /></td></tr>
					<tr><th><label><?php esc_html_e( 'Type de rôle', 'jde-plugin' ); ?></label></th><td>
						<select name="type_role">
							<?php foreach ( array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE ) as $r ) : ?>
								<option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( $r ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
				</table>
				<?php submit_button( __( 'Créer le poste', 'jde-plugin' ) ); ?>
			</form>

			<h2 style="margin-top:2em"><?php esc_html_e( 'Plages de disponibilité', 'jde-plugin' ); ?></h2>
			<?php $plages = $this->plages->findByEvenement( $evenementId ); ?>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e( 'Libellé', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Début', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Fin', 'jde-plugin' ); ?></th>
					<th><?php esc_html_e( 'Ordre', 'jde-plugin' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $plages as $pl ) : ?>
					<tr>
						<td><?php echo esc_html( $pl->libelle ); ?></td>
						<td><?php echo esc_html( $pl->dateDebut->format( 'Y-m-d H:i' ) ); ?></td>
						<td><?php echo esc_html( $pl->dateFin->format( 'Y-m-d H:i' ) ); ?></td>
						<td><?php echo (int) $pl->ordre; ?></td>
						<td><form method="post" onsubmit="return confirm('?')"><?php wp_nonce_field( 'jde_postes_action' ); ?><input type="hidden" name="jde_postes_action" value="delete_plage" /><input type="hidden" name="plage_id" value="<?php echo (int) ( $pl->id ?? 0 ); ?>" /><button class="button-link-delete">×</button></form></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Ajouter une plage', 'jde-plugin' ); ?></h3>
			<form method="post">
				<?php wp_nonce_field( 'jde_postes_action' ); ?>
				<input type="hidden" name="jde_postes_action" value="save_plage" />
				<input type="text" name="libelle" placeholder="<?php esc_attr_e( 'Libellé', 'jde-plugin' ); ?>" required />
				<input type="datetime-local" name="date_debut" required />
				<input type="datetime-local" name="date_fin" required />
				<input type="number" name="ordre" placeholder="<?php esc_attr_e( 'Ordre', 'jde-plugin' ); ?>" value="0" />
				<?php submit_button( __( 'Ajouter', 'jde-plugin' ), 'secondary', '', false ); ?>
			</form>
		</div>
		<?php
	}
}
