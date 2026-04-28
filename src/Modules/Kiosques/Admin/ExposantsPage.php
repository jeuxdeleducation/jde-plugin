<?php
/**
 * Page de gestion des exposants pour un événement.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Kiosques\Capabilities;
use JDE\Modules\Kiosques\Models\Exposant;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Services\CodeGenerator;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Page d'admin (hors menu) accessible via `?page=jde-exposants&evenement_id=X`.
 *
 * Affiche un formulaire d'ajout en haut, puis un tableau des exposants
 * existants avec leur code (copiable d'un clic) et un bouton Supprimer.
 *
 * Les actions (créer, supprimer) passent par admin-post.php avec nonce
 * et redirection. Les notifications de succès/erreur sont stockées dans
 * un transient lié à l'utilisateur (pattern « flash messages »).
 */
final class ExposantsPage {

	public const PAGE_SLUG = 'jde-exposants';

	private const ACTION_CREATE = 'jde_create_exposant';
	private const ACTION_DELETE = 'jde_delete_exposant';
	private const NONCE_NAME    = 'jde_exposants_nonce';

	public function __construct(
		private readonly ExposantRepository $exposants,
		private readonly CodeGenerator $codeGenerator,
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
		add_action( 'admin_post_' . self::ACTION_CREATE, array( $this, 'handleCreate' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( $this, 'handleDelete' ) );
	}

	/**
	 * Enregistrer une page hidden (accessible par URL mais hors menu latéral).
	 */
	public function registerPage(): void {
		add_submenu_page(
			'',
			__( 'Exposants', 'jde-plugin' ),
			__( 'Exposants', 'jde-plugin' ),
			Capabilities::MANAGE,
			self::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	/**
	 * URL d'admin pour cette page (utile pour le bouton de l'écran d'édition).
	 */
	public static function url( int $evenementId ): string {
		return add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				'evenement_id' => $evenementId,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Rendre la page.
	 */
	public function renderPage(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule de l'identifiant pour afficher la page
		$evenementId = isset( $_GET['evenement_id'] ) ? (int) $_GET['evenement_id'] : 0;
		$evenement   = $evenementId > 0 ? get_post( $evenementId ) : null;

		if ( ! $evenement || EvenementPostType::SLUG !== $evenement->post_type ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Exposants', 'jde-plugin' ) . '</h1>';
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Événement introuvable.', 'jde-plugin' )
				. '</p></div></div>';
			return;
		}

		$flash     = $this->popFlash();
		$exposants = $this->exposants->findByEvenement( $evenementId );
		?>
		<div class="wrap">
			<h1>
				<?php
				printf(
					/* translators: %s: titre de l'événement */
					esc_html__( 'Exposants — %s', 'jde-plugin' ),
					esc_html( $evenement->post_title )
				);
				?>
			</h1>

			<p>
				<a href="<?php echo esc_url( get_edit_post_link( $evenementId ) ?? '' ); ?>" class="button">
					← <?php esc_html_e( 'Retour à l\'événement', 'jde-plugin' ); ?>
				</a>
			</p>

			<?php if ( null !== $flash ) : ?>
				<div class="notice notice-<?php echo esc_attr( $flash['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $flash['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Ajouter un exposant', 'jde-plugin' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #c3c4c7;padding:15px;margin-bottom:30px;">
				<?php wp_nonce_field( self::ACTION_CREATE, self::NONCE_NAME ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_CREATE ); ?>">
				<input type="hidden" name="evenement_id" value="<?php echo (int) $evenementId; ?>">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="nom_entreprise"><?php esc_html_e( 'Nom de l\'entreprise', 'jde-plugin' ); ?></label></th>
						<td><input name="nom_entreprise" id="nom_entreprise" type="text" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="nb_kiosques_max"><?php esc_html_e( 'Nombre de kiosques alloués', 'jde-plugin' ); ?></label></th>
						<td><input name="nb_kiosques_max" id="nb_kiosques_max" type="number" min="1" max="50" value="1" required class="small-text"></td>
					</tr>
				</table>

				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Ajouter et générer un code', 'jde-plugin' ); ?>
					</button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Exposants enregistrés', 'jde-plugin' ); ?></h2>
			<?php if ( empty( $exposants ) ) : ?>
				<p><?php esc_html_e( 'Aucun exposant pour le moment.', 'jde-plugin' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Entreprise', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Code d\'accès', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Kiosques alloués', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Date de création', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'jde-plugin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $exposants as $exp ) : ?>
							<?php $this->renderRow( $exp ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php $this->printCopyScript(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function renderRow( Exposant $exp ): void {
		$deleteUrl = wp_nonce_url(
			add_query_arg(
				array(
					'action'      => self::ACTION_DELETE,
					'exposant_id' => $exp->id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_DELETE . '_' . (int) $exp->id,
			self::NONCE_NAME
		);
		?>
		<tr>
			<td><strong><?php echo esc_html( $exp->nomEntreprise ); ?></strong></td>
			<td>
				<code class="jde-code" style="font-size:14px;background:#f6f7f7;padding:2px 6px;"><?php echo esc_html( $exp->codeAcces ); ?></code>
				<button
					type="button"
					class="button button-small jde-copy-code"
					data-code="<?php echo esc_attr( $exp->codeAcces ); ?>"
					style="margin-left:6px;">
					<?php esc_html_e( 'Copier', 'jde-plugin' ); ?>
				</button>
			</td>
			<td><?php echo (int) $exp->nbKiosquesMax; ?></td>
			<td><?php echo esc_html( $this->formatDate( $exp->dateCreation ) ); ?></td>
			<td>
				<a href="<?php echo esc_url( $deleteUrl ); ?>"
					onclick="return confirm('<?php echo esc_js( __( 'Supprimer cet exposant ? Action irréversible.', 'jde-plugin' ) ); ?>');"
					class="button button-link-delete">
					<?php esc_html_e( 'Supprimer', 'jde-plugin' ); ?>
				</a>
			</td>
		</tr>
		<?php
	}

	/**
	 * Petit script JS inline pour le bouton « Copier le code ».
	 */
	private function printCopyScript(): void {
		$txtCopied = esc_js( __( 'Copié !', 'jde-plugin' ) );
		$txtCopy   = esc_js( __( 'Copier', 'jde-plugin' ) );
		?>
		<script>
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.jde-copy-code');
			if (!btn) { return; }
			e.preventDefault();
			var code = btn.dataset.code || '';
			var done = function () {
				var prev = btn.textContent;
				btn.textContent = '<?php echo $txtCopied; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';
				setTimeout(function () { btn.textContent = '<?php echo $txtCopy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'; }, 1500);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(code).then(done);
			} else {
				var ta = document.createElement('textarea');
				ta.value = code;
				document.body.appendChild(ta);
				ta.select();
				try { document.execCommand('copy'); } catch (err) {}
				document.body.removeChild(ta);
				done();
			}
		});
		</script>
		<?php
	}

	/**
	 * Handler de création d'un exposant.
	 */
	public function handleCreate(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION_CREATE ) ) {
			wp_die( esc_html__( 'Jeton de sécurité invalide.', 'jde-plugin' ), 403 );
		}

		$evenementId   = isset( $_POST['evenement_id'] ) ? (int) $_POST['evenement_id'] : 0;
		$nomEntreprise = isset( $_POST['nom_entreprise'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['nom_entreprise'] ) )
			: '';
		$nbMax         = isset( $_POST['nb_kiosques_max'] ) ? (int) $_POST['nb_kiosques_max'] : 0;

		if ( $evenementId <= 0 || '' === $nomEntreprise || $nbMax < 1 ) {
			$this->setFlash( 'error', __( 'Données invalides.', 'jde-plugin' ) );
			$this->redirectBack( $evenementId );
		}

		try {
			$code     = $this->codeGenerator->generateUnique();
			$now      = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
			$exposant = new Exposant(
				id: null,
				evenementId: $evenementId,
				nomEntreprise: $nomEntreprise,
				nbKiosquesMax: min( $nbMax, 50 ),
				codeAcces: $code,
				dateCreation: $now,
				creePar: get_current_user_id(),
			);

			$this->exposants->save( $exposant );

			$this->setFlash(
				'success',
				sprintf(
					/* translators: %s: nom de l'entreprise */
					__( 'Exposant « %s » ajouté avec succès.', 'jde-plugin' ),
					$nomEntreprise
				)
			);
		} catch ( Throwable $e ) {
			$this->setFlash( 'error', __( 'Erreur lors de la création de l\'exposant.', 'jde-plugin' ) );
		}

		$this->redirectBack( $evenementId );
	}

	/**
	 * Handler de suppression.
	 */
	public function handleDelete(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		$exposantId = isset( $_GET['exposant_id'] ) ? (int) $_GET['exposant_id'] : 0;
		$nonce      = isset( $_GET[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_GET[ self::NONCE_NAME ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION_DELETE . '_' . $exposantId ) ) {
			wp_die( esc_html__( 'Jeton de sécurité invalide.', 'jde-plugin' ), 403 );
		}

		$exposant = $this->exposants->findById( $exposantId );

		if ( null === $exposant ) {
			$this->setFlash( 'error', __( 'Exposant introuvable.', 'jde-plugin' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			exit;
		}

		$this->exposants->delete( $exposantId );
		$this->setFlash(
			'success',
			sprintf(
				/* translators: %s: nom de l'entreprise */
				__( 'Exposant « %s » supprimé.', 'jde-plugin' ),
				$exposant->nomEntreprise
			)
		);

		$this->redirectBack( $exposant->evenementId );
	}

	/**
	 * Rediriger vers la page exposants de l'événement.
	 */
	private function redirectBack( int $evenementId ): never {
		wp_safe_redirect( self::url( $evenementId ) );
		exit;
	}

	private function setFlash( string $type, string $message ): void {
		$key = 'jde_exposants_flash_' . get_current_user_id();
		set_transient(
			$key,
			array(
				'type'    => 'error' === $type ? 'error' : 'success',
				'message' => $message,
			),
			60
		);
	}

	/**
	 * @return array{type: string, message: string}|null
	 */
	private function popFlash(): ?array {
		$key   = 'jde_exposants_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( false === $flash ) {
			return null;
		}
		delete_transient( $key );

		return is_array( $flash ) ? $flash : null;
	}

	/**
	 * Formater une date UTC en local pour l'admin.
	 */
	private function formatDate( DateTimeImmutable $date ): string {
		$wpTimezone = wp_timezone();
		$local      = $date->setTimezone( $wpTimezone );
		return wp_date( 'Y-m-d H:i', $local->getTimestamp() );
	}
}
