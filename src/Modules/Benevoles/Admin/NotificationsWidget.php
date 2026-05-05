<?php
/**
 * Widget dashboard : notifications du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Repositories\NotificationRepository;
use JDE\Modules\Benevoles\Services\EvenementRhService;

defined( 'ABSPATH' ) || exit;

/**
 * Affiche les 10 dernières notifications non lues + bouton « Marquer
 * tout comme lu ». Visible seulement aux détenteurs de la capacité
 * MANAGE.
 */
final class NotificationsWidget {

	private static ?self $instance = null;

	public function __construct(
		private readonly NotificationRepository $notifications,
		private readonly EvenementRhService $evenementService,
	) {
		self::$instance = $this;
	}

	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'addWidget' ) );
		add_action( 'admin_init', array( $this, 'handleAction' ) );
	}

	public function addWidget(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'jde-benevoles-notifications',
			__( 'JDE Bénévoles — notifications', 'jde-plugin' ),
			array( self::class, 'render' )
		);
	}

	public function handleAction(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['jde_notifs_mark_all'] ) ) {
			return;
		}
		check_admin_referer( 'jde_notifs_mark_all' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}
		$evenementId = $this->evenementService->getActiveId();
		if ( null !== $evenementId ) {
			$this->notifications->markAllRead( $evenementId, (int) get_current_user_id() );
		}
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}

	public static function render(): void {
		if ( null === self::$instance ) {
			echo '<p>' . esc_html__( 'Aucune notification.', 'jde-plugin' ) . '</p>';
			return;
		}
		self::$instance->renderWidget();
	}

	private function renderWidget(): void {
		$evenementId = $this->evenementService->getActiveId();
		if ( null === $evenementId ) {
			echo '<p>' . esc_html__( 'Aucune édition RH active.', 'jde-plugin' ) . '</p>';
			return;
		}

		$notifs = $this->notifications->findUnread( $evenementId, 10 );
		if ( array() === $notifs ) {
			echo '<p>' . esc_html__( 'Tout est à jour.', 'jde-plugin' ) . '</p>';
			return;
		}

		echo '<ul style="margin:0">';
		foreach ( $notifs as $n ) {
			echo '<li style="margin-bottom:.5em">';
			echo '<strong>' . esc_html( $n->createdAt->format( 'Y-m-d H:i' ) ) . '</strong> — ';
			echo esc_html( $n->type );
			if ( array() !== $n->payload ) {
				echo '<br /><small>';
				$pieces = array();
				foreach ( $n->payload as $k => $v ) {
					if ( is_scalar( $v ) ) {
						$pieces[] = $k . ' : ' . (string) $v;
					}
				}
				echo esc_html( implode( ' · ', $pieces ) );
				echo '</small>';
			}
			echo '</li>';
		}
		echo '</ul>';

		echo '<form method="post" style="margin-top:1em">';
		wp_nonce_field( 'jde_notifs_mark_all' );
		echo '<button class="button" name="jde_notifs_mark_all" value="1">' . esc_html__( 'Marquer tout comme lu', 'jde-plugin' ) . '</button>';
		echo '</form>';
	}
}
