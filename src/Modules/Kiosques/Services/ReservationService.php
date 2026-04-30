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
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Repositories\KiosqueRepository;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestre les vérifications pré-réservation puis délègue à
 * {@see ReservationRepository::create()} pour l'insertion atomique.
 *
 * Lève des exceptions typées que le contrôleur REST traduit en codes
 * HTTP appropriés :
 *  - {@see EvenementInactifException}        → 403
 *  - {@see KiosqueIntrouvableException}      → 404
 *  - {@see KiosqueIndisponibleException}     → 409
 *  - {@see QuotaExceededException}           → 422
 *  - {@see KiosqueAlreadyReservedException}  → 409 (race condition gérée par la BD)
 */
class ReservationService {

	public function __construct(
		private readonly ReservationRepository $reservations,
		private readonly KiosqueRepository $kiosques,
		private readonly ExposantRepository $exposants,
		private readonly EvenementService $evenements,
	) {}

	/**
	 * Créer une réservation après validation des pré-conditions.
	 *
	 * @param int      $exposantId Exposant qui réserve.
	 * @param int      $kiosqueId  Kiosque demandé.
	 * @param int|null $creePar    Null = self-serve ; sinon user_id de l'admin.
	 *
	 * @throws EvenementInactifException
	 * @throws KiosqueIntrouvableException
	 * @throws KiosqueIndisponibleException
	 * @throws QuotaExceededException
	 */
	public function create( int $exposantId, int $kiosqueId, ?int $creePar = null ): Reservation {
		$exposant = $this->exposants->findById( $exposantId );
		if ( null === $exposant ) {
			throw new \RuntimeException( 'Exposant introuvable.' );
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

		$existing = $this->reservations->countByExposant( $exposantId );
		if ( $existing >= $exposant->nbKiosquesMax ) {
			throw new QuotaExceededException( $exposant->nbKiosquesMax );
		}

		$reservation = $this->reservations->create( $kiosqueId, $exposantId, $creePar );

		// Verrouiller le plan à la 1ʳᵉ réservation de l'événement.
		$totalForEvent = $this->reservations->countByEvenement( $exposant->evenementId );
		if ( 1 === $totalForEvent ) {
			update_post_meta( $exposant->evenementId, EvenementPostType::META_PLAN_VERROUILLE, true );
		}

		return $reservation;
	}
}
