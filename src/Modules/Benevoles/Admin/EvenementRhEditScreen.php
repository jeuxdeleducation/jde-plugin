<?php
/**
 * Meta box d'édition d'une édition RH.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;
use JDE\Modules\Benevoles\Services\CloneService;
use JDE\Modules\Benevoles\Services\EvenementRhService;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Ajoute la meta box principale du CPT `jde_evenement_rh`.
 *
 * Champs édités : actif (case unique), date début, date fin, URL OneDrive
 * de base, signatures requises (entente / lettre), blocs d'introduction
 * de profil par rôle, plus un bouton « Cloner depuis » pour réutiliser la
 * structure d'une édition antérieure.
 */
final class EvenementRhEditScreen {

	public const META_BOX_ID  = 'jde-rh-meta-box';
	public const NONCE_FIELD  = 'jde_rh_meta_nonce';
	public const NONCE_ACTION = 'jde_rh_save_meta';
	public const CLONE_NONCE  = 'jde_rh_clone';
	public const CLONE_FIELD  = 'jde_rh_clone_nonce';

	public function __construct(
		private readonly EvenementRhService $service,
		private readonly CloneService $cloneService,
	) {}

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'addMetaBox' ) );
		add_action( 'save_post_' . EvenementRhPostType::SLUG, array( $this, 'saveMeta' ), 10, 2 );
	}

	public function addMetaBox(): void {
		add_meta_box(
			self::META_BOX_ID,
			__( 'Configuration de l\'édition RH', 'jde-plugin' ),
			array( $this, 'renderMetaBox' ),
			EvenementRhPostType::SLUG,
			'normal',
			'high'
		);
	}

	public function renderMetaBox( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		wp_nonce_field( self::CLONE_NONCE, self::CLONE_FIELD );

		$actif       = (bool) get_post_meta( $post->ID, EvenementRhPostType::META_ACTIF, true );
		$dateDebut   = (string) get_post_meta( $post->ID, EvenementRhPostType::META_DATE_DEBUT, true );
		$dateFin     = (string) get_post_meta( $post->ID, EvenementRhPostType::META_DATE_FIN, true );
		$onedriveUrl = (string) get_post_meta( $post->ID, EvenementRhPostType::META_ONEDRIVE_BASE_URL, true );
		$signEntente = (bool) get_post_meta( $post->ID, EvenementRhPostType::META_DOIT_SIGNER_ENTENTE, true );
		$signLettre  = (bool) get_post_meta( $post->ID, EvenementRhPostType::META_DOIT_SIGNER_LETTRE, true );
		$introB      = (string) get_post_meta( $post->ID, EvenementRhPostType::META_INTRO_BENEVOLE, true );
		$introJ      = (string) get_post_meta( $post->ID, EvenementRhPostType::META_INTRO_JURY, true );
		$introA      = (string) get_post_meta( $post->ID, EvenementRhPostType::META_INTRO_ARBITRE, true );

		$autresEditions = $this->getOtherEditionsForClone( $post->ID );

		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label><?php esc_html_e( 'État', 'jde-plugin' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="jde_rh_actif" value="1" <?php checked( $actif ); ?> />
						<?php esc_html_e( 'Édition active (une seule à la fois)', 'jde-plugin' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="jde-rh-date-debut"><?php esc_html_e( 'Date de début', 'jde-plugin' ); ?></label></th>
				<td><input type="date" id="jde-rh-date-debut" name="jde_rh_date_debut" value="<?php echo esc_attr( $dateDebut ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="jde-rh-date-fin"><?php esc_html_e( 'Date de fin', 'jde-plugin' ); ?></label></th>
				<td>
					<input type="date" id="jde-rh-date-fin" name="jde_rh_date_fin" value="<?php echo esc_attr( $dateFin ); ?>" />
					<p class="description"><?php esc_html_e( 'La purge automatique se déclenche 2 ans après cette date.', 'jde-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="jde-rh-onedrive"><?php esc_html_e( 'URL OneDrive de base', 'jde-plugin' ); ?></label></th>
				<td><input type="url" id="jde-rh-onedrive" name="jde_rh_onedrive_base_url" class="regular-text" value="<?php echo esc_attr( $onedriveUrl ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Signatures requises', 'jde-plugin' ); ?></th>
				<td>
					<label><input type="checkbox" name="jde_rh_doit_signer_entente" value="1" <?php checked( $signEntente ); ?> /> <?php esc_html_e( 'Entente', 'jde-plugin' ); ?></label><br />
					<label><input type="checkbox" name="jde_rh_doit_signer_lettre" value="1" <?php checked( $signLettre ); ?> /> <?php esc_html_e( 'Lettre d\'engagement', 'jde-plugin' ); ?></label>
				</td>
			</tr>
			<tr>
				<th><label for="jde-rh-intro-benevole"><?php esc_html_e( 'Intro profil — Bénévole', 'jde-plugin' ); ?></label></th>
				<td><textarea id="jde-rh-intro-benevole" name="jde_rh_intro_benevole" rows="3" class="large-text"><?php echo esc_textarea( $introB ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="jde-rh-intro-jury"><?php esc_html_e( 'Intro profil — Jury', 'jde-plugin' ); ?></label></th>
				<td><textarea id="jde-rh-intro-jury" name="jde_rh_intro_jury" rows="3" class="large-text"><?php echo esc_textarea( $introJ ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="jde-rh-intro-arbitre"><?php esc_html_e( 'Intro profil — Arbitre', 'jde-plugin' ); ?></label></th>
				<td><textarea id="jde-rh-intro-arbitre" name="jde_rh_intro_arbitre" rows="3" class="large-text"><?php echo esc_textarea( $introA ); ?></textarea></td>
			</tr>
			<?php if ( array() !== $autresEditions ) : ?>
			<tr>
				<th><label for="jde-rh-clone-source"><?php esc_html_e( 'Cloner depuis', 'jde-plugin' ); ?></label></th>
				<td>
					<select id="jde-rh-clone-source" name="jde_rh_clone_source">
						<option value="0"><?php esc_html_e( '— Ne pas cloner —', 'jde-plugin' ); ?></option>
						<?php foreach ( $autresEditions as $autre ) : ?>
							<option value="<?php echo (int) $autre->ID; ?>"><?php echo esc_html( $autre->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<p>
						<label><input type="checkbox" name="jde_rh_clone_quarts" value="1" checked /> <?php esc_html_e( 'Inclure les quarts', 'jde-plugin' ); ?></label><br />
						<label><input type="checkbox" name="jde_rh_clone_plages" value="1" checked /> <?php esc_html_e( 'Inclure les plages de disponibilité', 'jde-plugin' ); ?></label>
					</p>
					<p class="description"><?php esc_html_e( 'Sélectionnez une édition existante pour copier ses postes (et optionnellement quarts/plages) lors de l\'enregistrement.', 'jde-plugin' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	public function saveMeta( int $postId, WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
			return;
		}

		// État actif : passer par le service pour appliquer la contrainte d'unicité.
		$wantsActif = ! empty( $_POST['jde_rh_actif'] );
		if ( $wantsActif ) {
			$this->service->activate( $postId );
		} else {
			$this->service->deactivate( $postId );
		}

		update_post_meta( $postId, EvenementRhPostType::META_DATE_DEBUT, sanitize_text_field( (string) ( $_POST['jde_rh_date_debut'] ?? '' ) ) );
		update_post_meta( $postId, EvenementRhPostType::META_DATE_FIN, sanitize_text_field( (string) ( $_POST['jde_rh_date_fin'] ?? '' ) ) );
		update_post_meta( $postId, EvenementRhPostType::META_ONEDRIVE_BASE_URL, esc_url_raw( (string) ( $_POST['jde_rh_onedrive_base_url'] ?? '' ) ) );
		update_post_meta( $postId, EvenementRhPostType::META_DOIT_SIGNER_ENTENTE, ! empty( $_POST['jde_rh_doit_signer_entente'] ) );
		update_post_meta( $postId, EvenementRhPostType::META_DOIT_SIGNER_LETTRE, ! empty( $_POST['jde_rh_doit_signer_lettre'] ) );
		update_post_meta( $postId, EvenementRhPostType::META_INTRO_BENEVOLE, wp_kses_post( wp_unslash( (string) ( $_POST['jde_rh_intro_benevole'] ?? '' ) ) ) );
		update_post_meta( $postId, EvenementRhPostType::META_INTRO_JURY, wp_kses_post( wp_unslash( (string) ( $_POST['jde_rh_intro_jury'] ?? '' ) ) ) );
		update_post_meta( $postId, EvenementRhPostType::META_INTRO_ARBITRE, wp_kses_post( wp_unslash( (string) ( $_POST['jde_rh_intro_arbitre'] ?? '' ) ) ) );

		// Clonage à la demande (vérifie son propre nonce).
		$cloneSource = (int) ( $_POST['jde_rh_clone_source'] ?? 0 );
		if ( $cloneSource > 0 && isset( $_POST[ self::CLONE_FIELD ] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( (string) $_POST[ self::CLONE_FIELD ] ) ), self::CLONE_NONCE )
		) {
			$this->cloneService->clone(
				$cloneSource,
				$postId,
				! empty( $_POST['jde_rh_clone_quarts'] ),
				! empty( $_POST['jde_rh_clone_plages'] )
			);
		}
	}

	/**
	 * @return WP_Post[]
	 */
	private function getOtherEditionsForClone( int $excludePostId ): array {
		$query = new WP_Query(
			array(
				'post_type'      => EvenementRhPostType::SLUG,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post__not_in'   => array( $excludePostId ),
				'no_found_rows'  => true,
			)
		);

		return array_values(
			array_filter(
				$query->posts,
				static fn ( $p ): bool => $p instanceof WP_Post
			)
		);
	}
}
