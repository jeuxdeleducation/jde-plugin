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
use JDE\Modules\Kiosques\Repositories\AuditRepository;
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;
use JDE\Modules\Kiosques\Services\CodeGenerator;
use JDE\Modules\Kiosques\Services\EmailService;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Page d'admin (hors menu) accessible via `?page=jde-exposants&evenement_id=X`.
 *
 * Affiche un formulaire d'ajout en haut, puis un tableau des exposants
 * existants avec leur code (copiable d'un clic), un bouton Modifier (qui
 * ouvre une ligne d'édition inline) et un bouton Supprimer.
 *
 * Les actions (créer, modifier, supprimer) passent par admin-post.php
 * avec nonce et redirection. Les notifications de succès/erreur sont
 * stockées dans un transient lié à l'utilisateur (pattern « flash messages »).
 */
final class ExposantsPage {

	public const PAGE_SLUG = 'jde-exposants';

	private const ACTION_CREATE     = 'jde_create_exposant';
	private const ACTION_UPDATE     = 'jde_update_exposant';
	private const ACTION_DELETE     = 'jde_delete_exposant';
	private const ACTION_SEND_EMAIL = 'jde_send_exposant_email';
	private const NONCE_NAME        = 'jde_exposants_nonce';

	public function __construct(
		private readonly ExposantRepository $exposants,
		private readonly CodeGenerator $codeGenerator,
		private readonly AuditRepository $audit,
		private readonly ReservationRepository $reservations,
		private readonly EmailService $emailService,
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
		add_action( 'admin_post_' . self::ACTION_CREATE, array( $this, 'handleCreate' ) );
		add_action( 'admin_post_' . self::ACTION_UPDATE, array( $this, 'handleUpdate' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( $this, 'handleDelete' ) );
		add_action( 'admin_post_' . self::ACTION_SEND_EMAIL, array( $this, 'handleSendEmail' ) );
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
	public static function url( int $evenementId, array $extra = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page'         => self::PAGE_SLUG,
					'evenement_id' => $evenementId,
				),
				$extra
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

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lecture seule des paramètres GET pour l'affichage
		$evenementId = isset( $_GET['evenement_id'] ) ? (int) $_GET['evenement_id'] : 0;
		$editId      = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
		// phpcs:enable
		$evenement = $evenementId > 0 ? get_post( $evenementId ) : null;

		if ( ! $evenement || EvenementPostType::SLUG !== $evenement->post_type ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Exposants', 'jde-plugin' ) . '</h1>';
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Événement introuvable.', 'jde-plugin' )
				. '</p></div></div>';
			return;
		}

		$flash       = $this->popFlash();
		$exposants   = $this->exposants->findByEvenement( $evenementId );
		$reservedMap = $this->reservations->countByExposantsForEvenement( $evenementId );
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
					<tr>
						<th scope="row"><label for="courriel"><?php esc_html_e( 'Adresse courriel (optionnelle)', 'jde-plugin' ); ?></label></th>
						<td>
							<input name="courriel" id="courriel" type="email" class="regular-text">
							<p class="description"><?php esc_html_e( 'Utilisée pour envoyer le code d\'accès et la confirmation de réservation.', 'jde-plugin' ); ?></p>
						</td>
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
							<th><?php esc_html_e( 'Réservés / alloués', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Courriel', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Date de création', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'jde-plugin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $exposants as $exp ) : ?>
							<?php
							$reserved = isset( $reservedMap[ (int) $exp->id ] )
								? (int) $reservedMap[ (int) $exp->id ]
								: 0;

							if ( $editId > 0 && $editId === $exp->id ) {
								$this->renderEditRow( $exp, $reserved, $evenementId );
							} else {
								$this->renderRow( $exp, $reserved, $evenementId );
							}
							?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php $this->printCopyScript(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function renderRow( Exposant $exp, int $reserved, int $evenementId ): void {
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
		$editUrl   = self::url( $evenementId, array( 'edit' => (int) $exp->id ) );

		$overQuota = $reserved > $exp->nbKiosquesMax;
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
			<td<?php echo $overQuota ? ' style="color:#b32d2e;font-weight:600;"' : ''; ?>>
				<?php
				echo esc_html(
					sprintf(
						'%d / %d',
						$reserved,
						(int) $exp->nbKiosquesMax
					)
				);
				if ( $overQuota ) {
					echo ' ⚠';
				}
				?>
			</td>
			<td style="font-size:12px;">
				<?php if ( null !== $exp->courriel ) : ?>
					<span title="<?php echo esc_attr( $exp->courriel ); ?>"><?php echo esc_html( $exp->courriel ); ?></span>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:6px;">
						<?php wp_nonce_field( self::ACTION_SEND_EMAIL . '_' . (int) $exp->id, self::NONCE_NAME ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SEND_EMAIL ); ?>">
						<input type="hidden" name="exposant_id" value="<?php echo (int) $exp->id; ?>">
						<textarea
							name="message_personnalise"
							rows="2"
							placeholder="<?php esc_attr_e( 'Message personnalisé (optionnel)…', 'jde-plugin' ); ?>"
							style="display:block;width:100%;font-size:12px;margin:4px 0;box-sizing:border-box;resize:vertical;"></textarea>
						<button type="submit" class="button button-small">
							<?php esc_html_e( 'Envoyer le code', 'jde-plugin' ); ?>
						</button>
					</form>
					<?php if ( null !== $exp->emailEnvoyeLe ) : ?>
						<em style="color:#666;font-size:11px;">
							<?php
							printf(
								/* translators: %s: date d'envoi */
								esc_html__( 'Envoyé le %s', 'jde-plugin' ),
								esc_html( $this->formatDate( $exp->emailEnvoyeLe ) )
							);
							?>
						</em>
					<?php endif; ?>
				<?php else : ?>
					<em style="color:#aaa;"><?php esc_html_e( '—', 'jde-plugin' ); ?></em>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( $this->formatDate( $exp->dateCreation ) ); ?></td>
			<td>
				<a href="<?php echo esc_url( $editUrl ); ?>#jde-exposant-edit-<?php echo (int) $exp->id; ?>" class="button button-small">
					<?php esc_html_e( 'Modifier', 'jde-plugin' ); ?>
				</a>
				<a href="<?php echo esc_url( $deleteUrl ); ?>"
					onclick="return confirm('<?php echo esc_js( __( 'Supprimer cet exposant ? Action irréversible.', 'jde-plugin' ) ); ?>');"
					class="button button-small button-link-delete">
					<?php esc_html_e( 'Supprimer', 'jde-plugin' ); ?>
				</a>
			</td>
		</tr>
		<?php
	}

	/**
	 * Ligne d'édition inline (s'affiche quand `?edit=<id>` est dans l'URL).
	 */
	private function renderEditRow( Exposant $exp, int $reserved, int $evenementId ): void {
		$cancelUrl = self::url( $evenementId );
		?>
		<tr id="jde-exposant-edit-<?php echo (int) $exp->id; ?>" style="background:#fff8e5;">
			<td colspan="5">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
					<?php wp_nonce_field( self::ACTION_UPDATE . '_' . (int) $exp->id, self::NONCE_NAME ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_UPDATE ); ?>">
					<input type="hidden" name="exposant_id" value="<?php echo (int) $exp->id; ?>">
					<input type="hidden" name="evenement_id" value="<?php echo (int) $evenementId; ?>">

					<label style="flex:1 1 240px;">
						<span style="display:block;font-size:11px;color:#666;"><?php esc_html_e( 'Nom de l\'entreprise', 'jde-plugin' ); ?></span>
						<input type="text" name="nom_entreprise" value="<?php echo esc_attr( $exp->nomEntreprise ); ?>" class="regular-text" required style="width:100%;">
					</label>

					<label>
						<span style="display:block;font-size:11px;color:#666;"><?php esc_html_e( 'Kiosques alloués', 'jde-plugin' ); ?></span>
						<input type="number" name="nb_kiosques_max" value="<?php echo (int) $exp->nbKiosquesMax; ?>" min="1" max="50" class="small-text" required>
					</label>

					<label style="flex:1 1 220px;">
						<span style="display:block;font-size:11px;color:#666;"><?php esc_html_e( 'Courriel (optionnel)', 'jde-plugin' ); ?></span>
						<input type="email" name="courriel" value="<?php echo esc_attr( $exp->courriel ?? '' ); ?>" class="regular-text" style="width:100%;">
					</label>

					<div style="font-size:12px;color:#555;align-self:center;padding-top:18px;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: nombre de réservations existantes */
								__( 'Déjà réservés : %d', 'jde-plugin' ),
								$reserved
							)
						);
						?>
					</div>

					<div style="padding-top:14px;">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'jde-plugin' ); ?></button>
						<a href="<?php echo esc_url( $cancelUrl ); ?>" class="button"><?php esc_html_e( 'Annuler', 'jde-plugin' ); ?></a>
					</div>
				</form>
				<?php if ( $reserved > 0 ) : ?>
					<p class="description" style="margin-top:8px;">
						<?php esc_html_e( 'Note : si tu baisses le nombre alloué sous le nombre de kiosques déjà réservés, les réservations existantes ne sont pas touchées. L\'exposant ne pourra simplement plus en ajouter tant que le compte ne redescend pas sous la limite.', 'jde-plugin' ); ?>
					</p>
				<?php endif; ?>
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
			? trim( sanitize_text_field( wp_unslash( (string) $_POST['nom_entreprise'] ) ) )
			: '';
		$nbMax         = isset( $_POST['nb_kiosques_max'] ) ? (int) $_POST['nb_kiosques_max'] : 0;
		$courriel      = isset( $_POST['courriel'] )
			? sanitize_email( wp_unslash( (string) $_POST['courriel'] ) )
			: '';
		$courriel      = '' !== $courriel ? $courriel : null;

		if ( $evenementId <= 0 || '' === $nomEntreprise || $nbMax < 1 ) {
			$this->setFlash( 'error', __( 'Données invalides.', 'jde-plugin' ) );
			$this->redirectBack( $evenementId );
		}

		if ( $this->exposants->nameExistsForEvenement( $evenementId, $nomEntreprise ) ) {
			$this->setFlash(
				'error',
				sprintf(
					/* translators: %s: nom de l'entreprise déjà utilisé */
					__( 'Un exposant nommé « %s » existe déjà pour cet événement.', 'jde-plugin' ),
					$nomEntreprise
				)
			);
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
				courriel: $courriel,
				emailEnvoyeLe: null,
				dateCreation: $now,
				creePar: get_current_user_id(),
			);

			$saved = $this->exposants->save( $exposant );

			$this->audit->log(
				get_current_user_id(),
				'exposant.create',
				'exposant',
				null === $saved->id ? 0 : $saved->id,
				array(
					'evenement_id'    => $evenementId,
					'nom_entreprise'  => $nomEntreprise,
					'nb_kiosques_max' => min( $nbMax, 50 ),
				)
			);

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
	 * Handler de modification d'un exposant existant.
	 *
	 * Champs modifiables : nom_entreprise, nb_kiosques_max.
	 * Le code d'accès reste immuable (cf. Exposant::save).
	 *
	 * Aucune vérification de cohérence avec les réservations existantes :
	 * baisser le quota sous le nombre actuel de réservations ne casse rien
	 * (le PublicStateBuilder utilise déjà max(0, …) pour kiosques_restants).
	 */
	public function handleUpdate(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		$exposantId = isset( $_POST['exposant_id'] ) ? (int) $_POST['exposant_id'] : 0;

		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION_UPDATE . '_' . $exposantId ) ) {
			wp_die( esc_html__( 'Jeton de sécurité invalide.', 'jde-plugin' ), 403 );
		}

		$evenementId   = isset( $_POST['evenement_id'] ) ? (int) $_POST['evenement_id'] : 0;
		$nomEntreprise = isset( $_POST['nom_entreprise'] )
			? trim( sanitize_text_field( wp_unslash( (string) $_POST['nom_entreprise'] ) ) )
			: '';
		$nbMax         = isset( $_POST['nb_kiosques_max'] ) ? (int) $_POST['nb_kiosques_max'] : 0;
		$courriel      = isset( $_POST['courriel'] )
			? sanitize_email( wp_unslash( (string) $_POST['courriel'] ) )
			: '';
		$courriel      = '' !== $courriel ? $courriel : null;

		$existing = $this->exposants->findById( $exposantId );

		if ( null === $existing || $existing->evenementId !== $evenementId ) {
			$this->setFlash( 'error', __( 'Exposant introuvable.', 'jde-plugin' ) );
			$this->redirectBack( $evenementId );
		}

		if ( '' === $nomEntreprise || $nbMax < 1 ) {
			$this->setFlash( 'error', __( 'Données invalides.', 'jde-plugin' ) );
			$this->redirectBack( $evenementId );
		}

		if ( $this->exposants->nameExistsForEvenement( $evenementId, $nomEntreprise, $exposantId ) ) {
			$this->setFlash(
				'error',
				sprintf(
					/* translators: %s: nom de l'entreprise déjà utilisé */
					__( 'Un autre exposant nommé « %s » existe déjà pour cet événement.', 'jde-plugin' ),
					$nomEntreprise
				)
			);
			$this->redirectBack( $evenementId );
		}

		$updated = new Exposant(
			id: $existing->id,
			evenementId: $existing->evenementId,
			nomEntreprise: $nomEntreprise,
			nbKiosquesMax: min( $nbMax, 50 ),
			codeAcces: $existing->codeAcces,
			courriel: $courriel,
			emailEnvoyeLe: $existing->emailEnvoyeLe,
			dateCreation: $existing->dateCreation,
			creePar: $existing->creePar,
		);

		try {
			$this->exposants->save( $updated );

			$this->audit->log(
				get_current_user_id(),
				'exposant.update',
				'exposant',
				$exposantId,
				array(
					'before' => array(
						'nom_entreprise'  => $existing->nomEntreprise,
						'nb_kiosques_max' => $existing->nbKiosquesMax,
					),
					'after'  => array(
						'nom_entreprise'  => $updated->nomEntreprise,
						'nb_kiosques_max' => $updated->nbKiosquesMax,
					),
				)
			);

			$this->setFlash(
				'success',
				sprintf(
					/* translators: %s: nom de l'entreprise */
					__( 'Exposant « %s » mis à jour.', 'jde-plugin' ),
					$updated->nomEntreprise
				)
			);
		} catch ( Throwable $e ) {
			$this->setFlash( 'error', __( 'Erreur lors de la modification.', 'jde-plugin' ) );
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

		$this->audit->log(
			get_current_user_id(),
			'exposant.delete',
			'exposant',
			$exposantId,
			array(
				'evenement_id'   => $exposant->evenementId,
				'nom_entreprise' => $exposant->nomEntreprise,
			)
		);

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
	 * Handler d'envoi du code d'accès par courriel.
	 */
	public function handleSendEmail(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce vérifié manuellement ci-dessous
		$exposantId = isset( $_POST['exposant_id'] ) ? (int) $_POST['exposant_id'] : 0;
		$nonce      = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';
		// phpcs:enable

		if ( ! wp_verify_nonce( $nonce, self::ACTION_SEND_EMAIL . '_' . $exposantId ) ) {
			wp_die( esc_html__( 'Jeton de sécurité invalide.', 'jde-plugin' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified above
		$messagePersonnalise = isset( $_POST['message_personnalise'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['message_personnalise'] ) )
			: '';

		$exposant = $this->exposants->findById( $exposantId );
		if ( null === $exposant || null === $exposant->courriel ) {
			$this->setFlash( 'error', __( 'Exposant introuvable ou adresse courriel manquante.', 'jde-plugin' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			exit;
		}

		$titre = (string) get_the_title( $exposant->evenementId );
		$url   = home_url( '/reservation-kiosques/' );

		$sent = $this->emailService->sendAccessCode( $exposant, $titre, $url, $messagePersonnalise );

		if ( $sent ) {
			$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
			$this->exposants->markEmailSent( $exposantId, $now );

			$this->audit->log(
				get_current_user_id(),
				'exposant.email_sent',
				'exposant',
				$exposantId,
				array(
					'courriel' => $exposant->courriel,
				)
			);

			$this->setFlash(
				'success',
				sprintf(
					/* translators: %s: adresse courriel */
					__( 'Code d\'accès envoyé à %s.', 'jde-plugin' ),
					$exposant->courriel
				)
			);
		} else {
			$this->setFlash( 'error', __( 'Échec de l\'envoi du courriel. Vérifiez la configuration SMTP.', 'jde-plugin' ) );
		}

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
