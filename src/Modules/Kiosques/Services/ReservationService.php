<?php
/**
 * Logique métier des réservations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

use JDE\Modules\Kiosques\Exceptions\EvenementInactifException;
use JDE\Modules\Kiosques\Exceptions\KiosqueIndisponibleException;
use JDE\Modules\Kiosques\Exceptions\KiosqueIntrouvableException;
use JDE\Modules\Kiosques\Exceptions\QuotaExceededException;
use JDE\Modules\Kiosques\Models\Kiosque;
use JDE\Modules\Kiosques\Models\Reservation;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Repositories\AuditRepository;
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Repositories\KiosqueRepository;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;
use RuntimeException;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestre les vérifications pré-réservation puis délègue à
 * {@see ReservationRepository::create()} pour l'insertion atomique.
 *
 * Lève des exceptions typées que les contrôleurs REST traduisent en
 * codes HTTP appropriés :
 *  - {@see EvenementInactifException}        → 403
 *  - {@see KiosqueIntrouvableException}      → 404
 *  - {@see KiosqueIndisponibleException}     → 409
 *  - {@see QuotaExceededException}           → 422
 *  - {@see KiosqueAlreadyReservedException}  → 409 (race condition gérée par la BD)
 *
 * Toutes les opérations modifient l'état → un appel à `AuditRepository::log()`
 * est systématique avec le payload contextuel (qui, quoi, avant/après).
 */
class ReservationService {

	public function __construct(
		private readonly ReservationRepository $reservations,
		private readonly KiosqueRepository $kiosques,
		private readonly ExposantRepository $exposants,
		private readonly EvenementService $evenements,
		private readonly AuditRepository $audit,
		private readonly ?EmailService $emailService = null,
	) {}

	/**
	 * Créer une réservation après validation des pré-conditions.
	 *
	 * @param int      $exposantId   Exposant qui réserve.
	 * @param int      $kiosqueId    Kiosque demandé.
	 * @param int|null $creePar      Null = self-serve ; sinon user_id de l'admin.
	 * @param bool     $bypassQuota  Permettre à l'admin de dépasser le quota (défaut false).
	 * @param string|null $notesAdmin Note d'accompagnement (création manuelle).
	 *
	 * @throws EvenementInactifException
	 * @throws KiosqueIntrouvableException
	 * @throws KiosqueIndisponibleException
	 * @throws QuotaExceededException
	 */
	public function create(
		int $exposantId,
		int $kiosqueId,
		?int $creePar = null,
		bool $bypassQuota = false,
		?string $notesAdmin = null
	): Reservation {
		$exposant = $this->exposants->findById( $exposantId );
		if ( null === $exposant ) {
			throw new RuntimeException( 'Exposant introuvable.' );
		}

		if ( ! $this->evenements->isActive( $exposant->evenementId ) ) {
			throw new EvenementInactifException();
		}

		$kiosque = $this->kiosques->findById( $kiosqueId );
		if ( null === $kiosque || $kiosque->evenementId !== $exposant->evenementId ) {
			throw new KiosqueIntrouvableException( $kiosqueId );
		}

		if ( Kiosque::STATUT_DISPONIBLE !== $kiosque->statut ) {
			throw new KiosqueIndisponibleException( $kiosqueId );
		}

		if ( ! $bypassQuota ) {
			$existing = $this->reservations->countByExposant( $exposantId );
			if ( $existing >= $exposant->nbKiosquesMax ) {
				throw new QuotaExceededException( $exposant->nbKiosquesMax );
			}
		}

		$reservation = $this->reservations->create( $kiosqueId, $exposantId, $creePar, $notesAdmin );

		// Verrouiller le plan à la 1ʳᵉ réservation de l'événement.
		$totalForEvent = $this->reservations->countByEvenement( $exposant->evenementId );
		if ( 1 === $totalForEvent ) {
			update_post_meta( $exposant->evenementId, EvenementPostType::META_PLAN_VERROUILLE, true );
		}

		// Courriel de confirmation automatique quand le quota est atteint (self-serve uniquement).
		if ( null === $creePar && null !== $this->emailService && null !== $exposant->courriel ) {
			$nowCount = $this->reservations->countByExposant( $exposantId );
			if ( $nowCount >= $exposant->nbKiosquesMax ) {
				try {
					$numeros = $this->reservations->findKiosqueNumerosByExposant( $exposantId );
					$titre   = (string) get_the_title( $exposant->evenementId );
					$this->emailService->sendReservationConfirmation( $exposant, $titre, $numeros );
				} catch ( Throwable $emailError ) {
					// Ne pas bloquer la réservation si l'envoi échoue.
					unset( $emailError );
				}
			}
		}

		$this->audit->log(
			$creePar ?? 0,
			'reservation.create',
			'reservation',
			null === $reservation->id ? 0 : $reservation->id,
			array(
				'kiosque_id'   => $kiosqueId,
				'exposant_id'  => $exposantId,
				'source'       => null === $creePar ? 'exposant' : 'admin',
				'bypass_quota' => $bypassQuota,
				'notes_admin'  => $notesAdmin,
			)
		);

		return $reservation;
	}

	/**
	 * Modifier une réservation existante (notes seulement OU déplacement vers
	 * un autre kiosque).
	 *
	 * Le déplacement est implémenté en `create + delete` pour respecter la
	 * contrainte UNIQUE sur kiosque_id : on tente d'abord de poser la nouvelle
	 * réservation sur le kiosque cible (si conflit, l'ancienne reste intacte) ;
	 * une fois la nouvelle créée, on supprime l'ancienne. La réservation
	 * retournée a donc un identifiant différent en cas de déplacement.
	 *
	 * @throws KiosqueIntrouvableException
	 * @throws KiosqueIndisponibleException
	 * @throws \JDE\Modules\Kiosques\Exceptions\KiosqueAlreadyReservedException
	 */
	public function update(
		int $reservationId,
		?int $newKiosqueId,
		?string $notesAdmin,
		int $adminUserId
	): Reservation {
		$existing = $this->reservations->findById( $reservationId );
		if ( null === $existing ) {
			throw new RuntimeException( sprintf( 'Réservation %d introuvable.', $reservationId ) );
		}

		$kiosqueChanged = null !== $newKiosqueId && $newKiosqueId !== $existing->kiosqueId;

		if ( ! $kiosqueChanged ) {
			$this->reservations->updateNotes( $reservationId, $notesAdmin );
			$updated = $this->reservations->findById( $reservationId ) ?? $existing;

			$this->audit->log(
				$adminUserId,
				'reservation.update',
				'reservation',
				$reservationId,
				array(
					'before' => array(
						'notes_admin' => $existing->notesAdmin,
					),
					'after'  => array(
						'notes_admin' => $notesAdmin,
					),
				)
			);

			return $updated;
		}

		// Déplacement de kiosque : create d'abord (vérifie le UNIQUE), delete ensuite.
		$newReservation = $this->create(
			$existing->exposantId,
			$newKiosqueId,
			$adminUserId,
			true, // bypassQuota — il s'agit d'un déplacement, le quota reste inchangé.
			$notesAdmin
		);

		$this->reservations->delete( $reservationId );

		$this->audit->log(
			$adminUserId,
			'reservation.transfer',
			'reservation',
			$reservationId,
			array(
				'before'             => array(
					'kiosque_id' => $existing->kiosqueId,
				),
				'after'              => array(
					'kiosque_id' => $newKiosqueId,
				),
				'new_reservation_id' => $newReservation->id,
			)
		);

		return $newReservation;
	}

	/**
	 * Supprimer une réservation. Déverrouille automatiquement le plan
	 * si c'était la dernière réservation de l'événement.
	 *
	 * @param int    $reservationId
	 * @param int    $adminUserId
	 * @param string $reason       Motif (loggé dans l'audit, requis).
	 */
	public function delete( int $reservationId, int $adminUserId, string $reason ): void {
		$existing = $this->reservations->findById( $reservationId );
		if ( null === $existing ) {
			throw new RuntimeException( sprintf( 'Réservation %d introuvable.', $reservationId ) );
		}

		$kiosque     = $this->kiosques->findById( $existing->kiosqueId );
		$evenementId = null !== $kiosque ? $kiosque->evenementId : null;

		$this->reservations->delete( $reservationId );

		// Déverrouillage automatique si plus aucune réservation pour l'événement.
		if ( null !== $evenementId ) {
			$remaining = $this->reservations->countByEvenement( $evenementId );
			if ( 0 === $remaining ) {
				update_post_meta( $evenementId, EvenementPostType::META_PLAN_VERROUILLE, false );
			}
		}

		$this->audit->log(
			$adminUserId,
			'reservation.delete',
			'reservation',
			$reservationId,
			array(
				'before' => array(
					'kiosque_id'  => $existing->kiosqueId,
					'exposant_id' => $existing->exposantId,
				),
				'reason' => $reason,
			)
		);
	}
}
