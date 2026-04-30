<?php
/**
 * Endpoints REST publics d'authentification des exposants.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\REST;

use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Services\AuthService;
use JDE\Modules\Kiosques\Services\EvenementService;
use JDE\Modules\Kiosques\Services\PublicStateBuilder;
use JDE\Modules\Kiosques\Services\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Routes :
 *   POST   /jde/v1/auth/code     → authentifier avec un code, poser cookie.
 *   DELETE /jde/v1/auth/session  → déconnexion.
 *   GET    /jde/v1/me            → état courant pour la session active.
 *
 * Le rate limit `5 tentatives par IP / 15 min` s'applique à `/auth/code`.
 * Tous les endpoints utilisent le nonce REST via le header `X-WP-Nonce`.
 */
final class AuthController extends AbstractController {

	private const RATE_LIMIT_MAX    = 5;
	private const RATE_LIMIT_WINDOW = 900; // 15 min

	public function __construct(
		private readonly AuthService $auth,
		private readonly RateLimiter $rateLimiter,
		private readonly ExposantRepository $exposants,
		private readonly EvenementService $evenements,
		private readonly PublicStateBuilder $stateBuilder,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/auth/code',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'authenticateWithCode' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/session',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'destroySession' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'getCurrentState' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST /auth/code — authentification.
	 */
	public function authenticateWithCode( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$code = strtoupper( trim( (string) $request->get_param( 'code' ) ) );

		$bucket = 'auth_' . sha1( $this->getClientIp() );
		if ( ! $this->rateLimiter->hit( $bucket, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW ) ) {
			return $this->errorResponse(
				'rate_limited',
				__( 'Trop de tentatives. Réessaie dans 15 minutes.', 'jde-plugin' ),
				429
			);
		}

		$exposant = $this->exposants->findByCode( $code );
		if ( null === $exposant ) {
			return $this->errorResponse(
				'invalid_code',
				__( 'Code invalide.', 'jde-plugin' ),
				401
			);
		}

		if ( ! $this->evenements->isActive( $exposant->evenementId ) ) {
			return $this->errorResponse(
				'event_inactive',
				__( 'L\'événement n\'est plus actif.', 'jde-plugin' ),
				403
			);
		}

		// Auth réussie : créer la session et reset le compteur de tentatives.
		if ( null !== $exposant->id ) {
			$this->auth->createSession( $exposant->id );
		}
		$this->rateLimiter->reset( $bucket );

		$state = $this->stateBuilder->build( $exposant );
		if ( null === $state ) {
			return $this->errorResponse(
				'evenement_introuvable',
				__( 'Événement introuvable.', 'jde-plugin' ),
				404
			);
		}

		return new WP_REST_Response( $state );
	}

	/**
	 * DELETE /auth/session — déconnexion.
	 */
	public function destroySession( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->auth->destroySession();
		return new WP_REST_Response( null, 204 );
	}

	/**
	 * GET /me — état courant pour la session active.
	 */
	public function getCurrentState( WP_REST_Request $request ): WP_REST_Response|WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$exposantId = $this->auth->resolveSession();
		if ( null === $exposantId ) {
			return $this->errorResponse(
				'not_authenticated',
				__( 'Session expirée. Reconnecte-toi avec ton code.', 'jde-plugin' ),
				401
			);
		}

		$exposant = $this->exposants->findById( $exposantId );
		if ( null === $exposant ) {
			$this->auth->destroySession();
			return $this->errorResponse(
				'not_authenticated',
				__( 'Session invalide.', 'jde-plugin' ),
				401
			);
		}

		$state = $this->stateBuilder->build( $exposant );
		if ( null === $state ) {
			return $this->errorResponse(
				'evenement_introuvable',
				__( 'Événement introuvable.', 'jde-plugin' ),
				404
			);
		}

		return new WP_REST_Response( $state );
	}

	/**
	 * Récupérer l'IP cliente, en tenant compte des proxies inverses.
	 */
	private function getClientIp(): string {
		$candidates = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $candidates as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$value = sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) );
				$ips   = explode( ',', $value );
				$first = trim( $ips[0] );
				if ( '' !== $first ) {
					return $first;
				}
			}
		}
		return '0.0.0.0';
	}
}
