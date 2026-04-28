<?php
/**
 * Écran d'édition d'un événement (métaboxes + sauvegarde).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Admin\ExposantsPage;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Branche les métaboxes « Plan » et « Paramètres » sur l'écran d'édition
 * du CPT, gère le téléversement via le media uploader WP, et persiste
 * les meta correspondants au save_post.
 *
 * Le container `<div id="jde-kiosques-editor">` est posé pour accueillir
 * le canvas React TypeScript (chargé séparément en Phase A.2 du module).
 */
final class EvenementEditScreen {

	private const NONCE_NAME   = 'jde_evenement_nonce';
	private const NONCE_ACTION = 'jde_save_evenement';

	public function register(): void {
		add_action(
			'add_meta_boxes_' . EvenementPostType::SLUG,
			array( $this, 'addMetaBoxes' )
		);
		add_action(
			'save_post_' . EvenementPostType::SLUG,
			array( $this, 'savePost' ),
			10,
			2
		);
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * Déclarer les métaboxes.
	 *
	 * Le paramètre `$post` est exigé par la signature du hook WordPress
	 * `add_meta_boxes_<type>` mais n'est pas utilisé ici (les métaboxes
	 * ne dépendent pas du post particulier au moment de leur enregistrement).
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function addMetaBoxes( WP_Post $post ): void {
		add_meta_box(
			'jde-plan',
			__( 'Plan de l\'événement et kiosques', 'jde-plugin' ),
			array( $this, 'renderPlanMetaBox' ),
			EvenementPostType::SLUG,
			'normal',
			'high'
		);

		add_meta_box(
			'jde-parametres',
			__( 'Paramètres', 'jde-plugin' ),
			array( $this, 'renderParametresMetaBox' ),
			EvenementPostType::SLUG,
			'side',
			'default'
		);

		add_meta_box(
			'jde-liens',
			__( 'Liens rapides', 'jde-plugin' ),
			array( $this, 'renderLiensMetaBox' ),
			EvenementPostType::SLUG,
			'side',
			'default'
		);
	}

	/**
	 * Métabox latérale « Liens rapides » : raccourci vers la gestion des exposants.
	 */
	public function renderLiensMetaBox( WP_Post $post ): void {
		?>
		<p>
			<a href="<?php echo esc_url( ExposantsPage::url( $post->ID ) ); ?>" class="button button-primary button-large" style="width:100%;text-align:center;box-sizing:border-box;">
				<?php esc_html_e( 'Gérer les exposants →', 'jde-plugin' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'Créer les exposants autorisés et copier leurs codes d\'accès.', 'jde-plugin' ); ?>
		</p>
		<?php
	}

	/**
	 * Métabox « Plan ».
	 */
	public function renderPlanMetaBox( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$attachmentId = (int) get_post_meta( $post->ID, EvenementPostType::META_PLAN_ATTACHMENT_ID, true );
		$imageUrl     = $attachmentId > 0 ? wp_get_attachment_image_url( $attachmentId, 'large' ) : '';
		$fullUrl      = $attachmentId > 0 ? wp_get_attachment_image_url( $attachmentId, 'full' ) : '';
		?>
		<div id="jde-plan-uploader">
			<input type="hidden" name="jde_plan_attachment_id" id="jde-plan-attachment-id" value="<?php echo esc_attr( (string) $attachmentId ); ?>">

			<div class="jde-plan-preview" style="margin-bottom:10px;background:#f6f7f7;border:1px dashed #c3c4c7;padding:10px;text-align:center;">
				<?php if ( $imageUrl ) : ?>
					<img src="<?php echo esc_url( $imageUrl ); ?>" alt="<?php esc_attr_e( 'Plan de l\'événement', 'jde-plugin' ); ?>" style="max-width:100%;max-height:400px;">
				<?php else : ?>
					<p style="color:#666;margin:20px 0;"><?php esc_html_e( 'Aucun plan téléversé pour le moment.', 'jde-plugin' ); ?></p>
				<?php endif; ?>
			</div>

			<p>
				<button type="button" class="button button-primary" id="jde-plan-upload-btn">
					<?php echo $imageUrl ? esc_html__( 'Remplacer le plan', 'jde-plugin' ) : esc_html__( 'Téléverser un plan', 'jde-plugin' ); ?>
				</button>

				<button type="button" class="button" id="jde-plan-remove-btn"<?php echo $imageUrl ? '' : ' style="display:none;"'; ?>>
					<?php esc_html_e( 'Retirer le plan', 'jde-plugin' ); ?>
				</button>
			</p>
		</div>

		<hr style="margin:20px 0;">

		<p><strong><?php esc_html_e( 'Éditeur de kiosques', 'jde-plugin' ); ?></strong></p>

		<div
			id="jde-kiosques-editor"
			data-evenement-id="<?php echo (int) $post->ID; ?>"
			data-plan-url="<?php echo esc_attr( (string) $fullUrl ); ?>"
			data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
		>
			<p class="description">
				<?php esc_html_e( 'Le canvas interactif sera disponible dans une prochaine itération du module.', 'jde-plugin' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Métabox « Paramètres ».
	 */
	public function renderParametresMetaBox( WP_Post $post ): void {
		$afficherNoms = (bool) get_post_meta( $post->ID, EvenementPostType::META_AFFICHER_NOMS, true );
		?>
		<p>
			<label>
				<input type="checkbox" name="jde_afficher_noms_entreprises" value="1"<?php checked( $afficherNoms ); ?>>
				<?php esc_html_e( 'Afficher le nom des entreprises sur les kiosques réservés', 'jde-plugin' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Si décoché, les exposants verront seulement « Réservé » sur les kiosques pris par les autres.', 'jde-plugin' ); ?>
		</p>
		<?php
	}

	/**
	 * Sauvegarder les meta au save_post.
	 *
	 * @param int     $postId Identifiant du post.
	 * @param WP_Post $post   Instance du post.
	 */
	public function savePost( int $postId, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( EvenementPostType::SLUG !== $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}

		// Plan attachment.
		if ( isset( $_POST['jde_plan_attachment_id'] ) ) {
			$attachmentId = (int) $_POST['jde_plan_attachment_id'];
			update_post_meta( $postId, EvenementPostType::META_PLAN_ATTACHMENT_ID, $attachmentId );
		}

		// Visibilité des noms d'entreprises.
		$afficherNoms = isset( $_POST['jde_afficher_noms_entreprises'] )
			&& '1' === sanitize_text_field( wp_unslash( (string) $_POST['jde_afficher_noms_entreprises'] ) );
		update_post_meta( $postId, EvenementPostType::META_AFFICHER_NOMS, $afficherNoms );
	}

	/**
	 * Enqueue conditionnel : seulement sur les écrans d'édition d'événement.
	 */
	public function enqueueAssets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || EvenementPostType::SLUG !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		// Petit script inline pour le téléversement du plan via wp.media.
		// Le canvas React TS est enqueued séparément quand il sera prêt.
		wp_register_script( 'jde-plan-uploader', '', array( 'jquery' ), JDE_PLUGIN_VERSION, true );
		wp_enqueue_script( 'jde-plan-uploader' );
		wp_add_inline_script( 'jde-plan-uploader', $this->planUploaderJs() );
	}

	/**
	 * JS minimal pour gérer le bouton « Téléverser » via wp.media.
	 */
	private function planUploaderJs(): string {
		$txtTitle   = esc_js( __( 'Choisir le plan', 'jde-plugin' ) );
		$txtButton  = esc_js( __( 'Utiliser ce plan', 'jde-plugin' ) );
		$txtNone    = esc_js( __( 'Aucun plan téléversé pour le moment.', 'jde-plugin' ) );
		$txtConfirm = esc_js( __( 'Retirer le plan ?', 'jde-plugin' ) );
		$txtAlt     = esc_js( __( 'Plan de l\'événement', 'jde-plugin' ) );

		return <<<JS
jQuery(function (\$) {
	var frame;

	\$('#jde-plan-upload-btn').on('click', function (e) {
		e.preventDefault();

		if (frame) { frame.open(); return; }

		frame = wp.media({
			title: '{$txtTitle}',
			button: { text: '{$txtButton}' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			var url = (att.sizes && att.sizes.large) ? att.sizes.large.url : att.url;
			\$('#jde-plan-attachment-id').val(att.id);
			\$('.jde-plan-preview').html('<img src="' + url + '" alt="{$txtAlt}" style="max-width:100%;max-height:400px;">');
			\$('#jde-plan-remove-btn').show();
		});

		frame.open();
	});

	\$('#jde-plan-remove-btn').on('click', function (e) {
		e.preventDefault();
		if (!confirm('{$txtConfirm}')) { return; }
		\$('#jde-plan-attachment-id').val('0');
		\$('.jde-plan-preview').html('<p style="color:#666;margin:20px 0;">{$txtNone}</p>');
		\$(this).hide();
	});
});
JS;
	}
}
