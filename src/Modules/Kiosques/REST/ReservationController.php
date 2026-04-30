<?php
/**
 * Endpoint REST public pour la création de réservations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\REST;

use JDE\Modules\Kiosques\Exceptions\EvenementInactifException;
use JDE\Modules\Kiosques\Exceptions\KiosqueAlreadyReservedException;
use JDE\Modules\Kiosques\Exceptions\KiosqueIndisponibleException;
use JDE\Modules\Kiosques\Exceptions\KiosqueIntrouvableException;
use JDE\Modules\Kiosques\Exceptions\QuotaExceededException;
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Services\AuthService;
use JDE\Modules\Kiosques\Services\PublicStateBuilder;
use JDE\Modules\Kiosques\Services\ReservationService;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Route :
 *   POST /jde/v1/reservations
 *   Body : { kiosque_id: int }
 *
 * Authentification : cookie (session exposant). Tous les codes d'erreur
 * possibles sont mappés sur des HTTP statuses précis pour permettre au
 * client de réagir (modal de conflit, message de quota, etc.).
 */
final class ReservationController extends AbstractController {

	public function __construct(
		private readonly AuthService $auth,
		private readonly ReservationService $service,
		private readonly ExposantRepository $exposants,
		private readonly PublicStateBuilder $stateBuilder,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/reservations',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'kiosque_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$exposantId = $this->auth->resolveSession();
		if ( null === $exposantId ) {
			return $this->errorResponse(
				'not_authenticated',
				__( 'Session expirée. Reconnecte-toi avec ton code.', 'jde-plugin' ),
				401
			);
		}

		$kiosqueId = (int) $request->get_param( 'kiosque_id' );

		try {
			$this->service->create( $exposantId, $kiosqueId, null );
		} catch ( KiosqueAlreadyReservedException $e ) {
			return $this->errorResponseWithFreshState(
				'kiosque_pris',
				__( 'Ce kiosque vient d\'être réservé par un autre exposant.', 'jde-plugin' ),
				409,
				$exposantId,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( EvenementInactifException $e ) {
			return $this->errorResponse(
				'event_inactive',
				$e->getMessage(),
				403
			);
		} catch ( KiosqueIndisponibleException $e ) {
			return $this->errorResponseWithFreshState(
				'kiosque_indisponible',
				__( 'Ce kiosque n\'est pas disponible.', 'jde-plugin' ),
				409,
				$exposantId,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( KiosqueIntrouvableException $e ) {
			return $this->errorResponse(
				'kiosque_introuvable',
				__( 'Kiosque introuvable.', 'jde-plugin' ),
				404,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( QuotaExceededException $e ) {
			return $this->errorResponse(
				'quota_exceeded',
				__( 'Tu as atteint le nombre maximum de kiosques que tu peux réserver.', 'jde-plugin' ),
				422,
				array( 'quota' => $e->quota )
			);
		} catch ( Throwable $e ) {
			return $this->errorResponse(
				'reservation_error',
				__( 'Erreur lors de la réservation.', 'jde-plugin' ),
				500
			);
		}

		// Succès : retourner le state à jour (incluant la nouvelle réservation).
		$exposant = $this->exposants->findById( $exposantId );
		if ( null === $exposant ) {
			return $this->errorResponse(
				'not_authenticated',
				__( 'Session invalide.', 'jde-plugin' ),
				401
			);
		}

		$state = $this->stateBuilder->build( $exposant );
		return new WP_REST_Response( $state, 201 );
	}

	/**
	 * Construire une réponse d'erreur 4xx en y attachant le state à jour
	 * (utile pour les conflits 409 : le client peut rafraîchir son plan
	 * sans une requête supplémentaire à GET /me).
	 */
	private function errorResponseWithFreshState(
		string $code,
		string $message,
		int $status,
		int $exposantId,
		array $extra
	): WP_Error {
		$exposant = $this->exposants->findById( $exposantId );
		if ( null !== $exposant ) {
			$state = $this->stateBuilder->build( $exposant );
			if ( null !== $state ) {
				$extra['fresh_state'] = $state;
			}
		}
		return $this->errorResponse( $code, $message, $status, $extra );
	}
}
