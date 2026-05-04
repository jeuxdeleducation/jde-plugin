<?php
/**
 * Colonnes custom de la liste des événements + actions Activer/Désactiver.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use JDE\Modules\Kiosques\Capabilities;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Repositories\AuditRepository;
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Repositories\KiosqueRepository;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;
use JDE\Modules\Kiosques\Services\EvenementService;

defined( 'ABSPATH' ) || exit;

/**
 * Enrichit la liste des événements et expose le toggle d'activation.
 *
 * Colonnes ajoutées :
 *  - Statut (badge Actif/Inactif + bouton « Activer/Désactiver »)
 *  - Plan (✓/✗ selon présence du plan_attachment_id)
 *  - Exposants (compteur)
 *  - Réservations (compteur, à 0 en Phase A)
 *
 * Les actions Activer/Désactiver passent par `admin_post_*` avec nonce
 * et redirection. Cohérent avec le patron WP standard pour les actions
 * « one-click » depuis une liste.
 */
final class EvenementColumns {

	private const ACTION_TOGGLE = 'jde_toggle_evenement_actif';
	private const NONCE_NAME    = 'jde_toggle_evenement_actif_nonce';

	public function __construct(
		private readonly EvenementService $evenementService,
		private readonly KiosqueRepository $kiosqueRepo,
		private readonly ExposantRepository $exposantRepo,
		private readonly ReservationRepository $reservationRepo,
		private readonly AuditRepository $audit,
	) {}

	public function register(): void {
		add_filter(
			'manage_' . EvenementPostType::SLUG . '_posts_columns',
			array( $this, 'defineColumns' )
		);
		add_action(
			'manage_' . EvenementPostType::SLUG . '_posts_custom_column',
			array( $this, 'renderColumn' ),
			10,
			2
		);

		add_action( 'admin_post_' . self::ACTION_TOGGLE, array( $this, 'handleToggleAction' ) );
	}

	/**
	 * Définir l'ordre et le libellé des colonnes.
	 *
	 * @param array<string, string> $columns Colonnes par défaut.
	 * @return array<string, string>
	 */
	public function defineColumns( array $columns ): array {
		$cb    = $columns['cb'] ?? '';
		$title = $columns['title'] ?? __( 'Titre', 'jde-plugin' );
		$date  = $columns['date'] ?? __( 'Date', 'jde-plugin' );

		return array(
			'cb'               => $cb,
			'title'            => $title,
			'jde_statut'       => __( 'Statut', 'jde-plugin' ),
			'jde_plan'         => __( 'Plan', 'jde-plugin' ),
			'jde_exposants'    => __( 'Exposants', 'jde-plugin' ),
			'jde_reservations' => __( 'Réservations', 'jde-plugin' ),
			'date'             => $date,
		);
	}

	/**
	 * Rendre le contenu d'une colonne custom pour un post donné.
	 */
	public function renderColumn( string $column, int $postId ): void {
		switch ( $column ) {
			case 'jde_statut':
				$this->renderStatutColumn( $postId );
				break;
			case 'jde_plan':
				$this->renderPlanColumn( $postId );
				break;
			case 'jde_exposants':
				echo (int) $this->exposantRepo->countByEvenement( $postId );
				break;
			case 'jde_reservations':
				echo (int) $this->reservationRepo->countByEvenement( $postId );
				break;
		}
	}

	/**
	 * Colonne Statut : badge + bouton de bascule.
	 */
	private function renderStatutColumn( int $postId ): void {
		$actif = $this->evenementService->isActive( $postId );
		$url   = $this->buildToggleUrl( $postId, ! $actif );

		if ( $actif ) {
			$badge       = '<span class="jde-statut jde-statut--actif" style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">'
				. esc_html__( 'Actif', 'jde-plugin' )
				. '</span>';
			$buttonLabel = __( 'Désactiver', 'jde-plugin' );
			$buttonClass = 'button-secondary';
		} else {
			$badge       = '<span class="jde-statut jde-statut--inactif" style="background:#ddd;color:#444;padding:2px 8px;border-radius:3px;font-size:11px;">'
				. esc_html__( 'Inactif', 'jde-plugin' )
				. '</span>';
			$buttonLabel = __( 'Activer', 'jde-plugin' );
			$buttonClass = 'button-primary';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $badge . ' ';
		printf(
			'<a href="%1$s" class="button button-small %2$s" style="margin-left:6px;">%3$s</a>',
			esc_url( $url ),
			esc_attr( $buttonClass ),
			esc_html( $buttonLabel )
		);
	}

	/**
	 * Colonne Plan : ✓ si un plan est associé, ✗ sinon.
	 */
	private function renderPlanColumn( int $postId ): void {
		$attachmentId = (int) get_post_meta( $postId, EvenementPostType::META_PLAN_ATTACHMENT_ID, true );

		if ( $attachmentId > 0 ) {
			echo '<span style="color:#00a32a;font-weight:bold;" title="'
				. esc_attr__( 'Plan téléversé', 'jde-plugin' )
				. '">✓</span>';
			return;
		}

		echo '<span style="color:#cc1818;" title="'
			. esc_attr__( 'Aucun plan', 'jde-plugin' )
			. '">✗</span>';
	}

	/**
	 * Construire l'URL de bascule (avec nonce).
	 */
	private function buildToggleUrl( int $postId, bool $targetState ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION_TOGGLE,
					'post'   => $postId,
					'state'  => $targetState ? 1 : 0,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_TOGGLE . '_' . $postId,
			self::NONCE_NAME
		);
	}

	/**
	 * Handler de l'action `admin_post_jde_toggle_evenement_actif`.
	 */
	public function handleToggleAction(): void {
		$postId = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$state  = isset( $_GET['state'] ) ? (int) $_GET['state'] : 0;
		$nonce  = isset( $_GET[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ self::NONCE_NAME ] ) ) : '';

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::ACTION_TOGGLE . '_' . $postId ) ) {
			wp_die( esc_html__( 'Jeton de sécurité invalide.', 'jde-plugin' ), 403 );
		}

		$post = get_post( $postId );
		if ( ! $post || EvenementPostType::SLUG !== $post->post_type ) {
			wp_die( esc_html__( 'Événement introuvable.', 'jde-plugin' ), 404 );
		}

		if ( 1 === $state ) {
			$this->evenementService->activate( $postId );
			$this->audit->log(
				get_current_user_id(),
				'evenement.activate',
				'evenement',
				$postId,
				array( 'titre' => $post->post_title )
			);
		} else {
			$this->evenementService->deactivate( $postId );
			$this->audit->log(
				get_current_user_id(),
				'evenement.deactivate',
				'evenement',
				$postId,
				array( 'titre' => $post->post_title )
			);
		}

		$redirect = wp_get_referer();
		if ( false === $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . EvenementPostType::SLUG );
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}
