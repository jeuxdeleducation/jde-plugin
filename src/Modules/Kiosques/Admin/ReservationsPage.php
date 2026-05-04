<?php
/**
 * Page d'admin « Réservations » qui sert le bundle React.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use JDE\Modules\Kiosques\Capabilities;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Support\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Page hidden accessible via `?page=jde-reservations&evenement_id=X`.
 *
 * Rend un container vide dans lequel le bundle `admin-reservations`
 * monte l'app React TS (tableau temps réel + plan + CRUD + CSV).
 */
final class ReservationsPage {

	public const PAGE_SLUG = 'jde-reservations';

	public function __construct( private readonly Assets $assets ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	public function registerPage(): void {
		add_submenu_page(
			'',
			__( 'Réservations', 'jde-plugin' ),
			__( 'Réservations', 'jde-plugin' ),
			Capabilities::MANAGE,
			self::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	public static function url( int $evenementId ): string {
		return add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				'evenement_id' => $evenementId,
			),
			admin_url( 'admin.php' )
		);
	}

	public function renderPage(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule de l'identifiant
		$evenementId = isset( $_GET['evenement_id'] ) ? (int) $_GET['evenement_id'] : 0;
		$post        = $evenementId > 0 ? get_post( $evenementId ) : null;

		if ( ! $post || EvenementPostType::SLUG !== $post->post_type ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Réservations', 'jde-plugin' ) . '</h1>';
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Événement introuvable.', 'jde-plugin' )
				. '</p></div></div>';
			return;
		}
		?>
		<div class="wrap">
			<div id="jde-reservations-app"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue le bundle uniquement sur cette page.
	 */
	public function enqueueAssets( string $hook ): void {
		// Le hook pour les pages add_submenu_page hidden est de la forme
		// 'admin_page_<slug>' (préfixe différent quand c'est dans un sous-menu).
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule
		$evenementId = isset( $_GET['evenement_id'] ) ? (int) $_GET['evenement_id'] : 0;
		$post        = $evenementId > 0 ? get_post( $evenementId ) : null;
		if ( ! $post || EvenementPostType::SLUG !== $post->post_type ) {
			return;
		}

		$attachmentId = (int) get_post_meta( $post->ID, EvenementPostType::META_PLAN_ATTACHMENT_ID, true );
		$planUrlRaw   = $attachmentId > 0 ? wp_get_attachment_image_url( $attachmentId, 'full' ) : false;
		$planUrl      = ( false === $planUrlRaw || null === $planUrlRaw ) ? null : $planUrlRaw;

		$this->assets->enqueueScript(
			'jde-admin-reservations',
			'admin-reservations',
			array( 'wp-element' )
		);
		$this->assets->enqueueStyle( 'jde-admin-reservations', 'admin-reservations' );

		$config = array(
			'restUrl'        => esc_url_raw( rest_url( 'jde/v1/' ) ),
			'restNonce'      => wp_create_nonce( 'wp_rest' ),
			'evenementId'    => $post->ID,
			'evenementTitre' => $post->post_title,
			'planUrl'        => $planUrl,
			'containerId'    => 'jde-reservations-app',
			'contactEmail'   => 'info@jeuxdeleducation.com',
			'csvUrl'         => esc_url_raw( rest_url( 'jde/v1/admin/evenements/' . $post->ID . '/reservations.csv' ) ),
			'backUrl'        => is_string( get_edit_post_link( $post->ID, 'raw' ) )
				? (string) get_edit_post_link( $post->ID, 'raw' )
				: '',
		);

		wp_add_inline_script(
			'jde-admin-reservations',
			'window.jdeKiosques = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}
}
