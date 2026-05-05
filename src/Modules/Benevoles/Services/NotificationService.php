<?php
/**
 * Service de notifications gestionnaires.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Models\Notification;
use JDE\Modules\Benevoles\Repositories\NotificationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Pousse les alertes vers le widget gestionnaire et les courriels.
 *
 * Chaque appel à `push()` crée une ligne dans `wp_jde_rh_notifications`
 * (consommée par `NotificationsWidget`) puis envoie un courriel court
 * à tous les utilisateurs détenant la capacité `jde_manage_benevoles`.
 *
 * Les courriels gestionnaires utilisent `wp_mail()` directement avec
 * un sujet préfixé selon le type — pas de rendu Mustache : ce sont des
 * notifications internes, pas des courriels transactionnels.
 */
final class NotificationService {

	public function __construct(
		private readonly NotificationRepository $notifications,
	) {}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function push(
		string $type,
		string $entityType,
		int $entityId,
		int $evenementRhId,
		array $payload = array()
	): Notification {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$notification = $this->notifications->insert(
			new Notification(
				id: null,
				type: $type,
				entityType: $entityType,
				entityId: $entityId,
				evenementRhId: $evenementRhId,
				payload: $payload,
				createdAt: $now,
				readAt: null,
				readByUserId: null,
			)
		);

		$this->notifyManagersByEmail( $notification );

		return $notification;
	}

	private function notifyManagersByEmail( Notification $notification ): void {
		$managers = $this->getManagerEmails();
		if ( array() === $managers ) {
			return;
		}

		$subject      = $this->subjectForType( $notification->type );
		$dashboardUrl = admin_url( 'admin.php?page=jde-benevoles' );

		$body  = '<p>' . esc_html__( 'Une notification requiert votre attention :', 'jde-plugin' ) . '</p>';
		$body .= '<p><strong>' . esc_html( $this->labelForType( $notification->type ) ) . '</strong></p>';
		if ( array() !== $notification->payload ) {
			$body .= '<ul>';
			foreach ( $notification->payload as $key => $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$body .= '<li>' . esc_html( (string) $key ) . ' : ' . esc_html( (string) $value ) . '</li>';
			}
			$body .= '</ul>';
		}
		$body .= '<p><a href="' . esc_url( $dashboardUrl ) . '">'
			. esc_html__( 'Ouvrir le tableau de bord Bénévoles', 'jde-plugin' )
			. '</a></p>';

		wp_mail(
			$managers,
			$subject,
			$body,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * @return string[]
	 */
	private function getManagerEmails(): array {
		$users = get_users(
			array(
				'capability' => Capabilities::MANAGE,
				'fields'     => array( 'user_email' ),
			)
		);

		$emails = array();
		foreach ( $users as $user ) {
			$mail = isset( $user->user_email ) ? trim( (string) $user->user_email ) : '';
			if ( '' !== $mail ) {
				$emails[] = $mail;
			}
		}
		return array_values( array_unique( $emails ) );
	}

	private function subjectForType( string $type ): string {
		switch ( $type ) {
			case Notification::TYPE_INSCRIPTION_NOUVELLE:
				return __( '[JDE Bénévoles] Nouvelle inscription', 'jde-plugin' );
			case Notification::TYPE_ASSIGNATION_REFUSEE:
				return __( '[JDE Bénévoles] Assignation refusée', 'jde-plugin' );
			case Notification::TYPE_CHEVAUCHEMENT_PERSONNE:
				return __( '[JDE Bénévoles] Chevauchement détecté', 'jde-plugin' );
			case Notification::TYPE_SUR_EFFECTIF_POSTE:
				return __( '[JDE Bénévoles] Sur-effectif sur un poste', 'jde-plugin' );
			case Notification::TYPE_SIGNATURE_COMPLETEE:
				return __( '[JDE Bénévoles] Document signé', 'jde-plugin' );
			default:
				return __( '[JDE Bénévoles] Notification', 'jde-plugin' );
		}
	}

	private function labelForType( string $type ): string {
		switch ( $type ) {
			case Notification::TYPE_INSCRIPTION_NOUVELLE:
				return __( 'Nouvelle inscription reçue', 'jde-plugin' );
			case Notification::TYPE_ASSIGNATION_REFUSEE:
				return __( 'Assignation refusée par la personne', 'jde-plugin' );
			case Notification::TYPE_CHEVAUCHEMENT_PERSONNE:
				return __( 'Chevauchement horaire détecté', 'jde-plugin' );
			case Notification::TYPE_SUR_EFFECTIF_POSTE:
				return __( 'Le nombre souhaité de personnes est dépassé', 'jde-plugin' );
			case Notification::TYPE_SIGNATURE_COMPLETEE:
				return __( 'Un document a été signé', 'jde-plugin' );
			default:
				return $type;
		}
	}
}
