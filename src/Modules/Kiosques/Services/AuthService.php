<?php
/**
 * Service d'authentification par cookie pour les exposants.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Gère les sessions exposants côté public.
 *
 * Stratégie : un cookie `HttpOnly + Secure + SameSite=Lax` portant un
 * token random est posé chez l'exposant. Côté serveur, un transient
 * mappe le token à `exposant_id`. La durée par défaut est de 7 jours.
 *
 * Le cookie est écrit via {@see CookieWriter} pour permettre les tests
 * (`setcookie` n'est pas mockable directement avec Brain Monkey car
 * c'est une fonction native PHP, pas une fonction WordPress).
 */
class AuthService {

	public const COOKIE_NAME      = 'jde_kiosques_session';
	public const SESSION_TTL_DAYS = 7;
	public const TRANSIENT_PREFIX = 'jde_session_';

	public function __construct( private readonly CookieWriter $cookies ) {}

	/**
	 * Créer une session pour un exposant.
	 *
	 * Génère un token random, le stocke dans un transient (TTL 7 jours)
	 * et pose le cookie correspondant.
	 *
	 * @param int $exposantId Identifiant de l'exposant authentifié.
	 * @return string Token généré (utile pour les tests, jamais exposé au client autrement que par cookie).
	 */
	public function createSession( int $exposantId ): string {
		$token   = wp_generate_password( 32, false );
		$ttl     = self::SESSION_TTL_DAYS * DAY_IN_SECONDS;
		$expires = time() + $ttl;

		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array( 'exposant_id' => $exposantId ),
			$ttl
		);

		$this->cookies->set( self::COOKIE_NAME, $token, $expires );

		return $token;
	}

	/**
	 * Résoudre la session courante depuis le cookie.
	 *
	 * @return int|null Identifiant de l'exposant connecté ou null si pas de session valide.
	 */
	public function resolveSession(): ?int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- cookie d'auth, pas un formulaire
		$token = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? (string) $_COOKIE[ self::COOKIE_NAME ] : '';

		if ( '' === $token ) {
			return null;
		}

		$token = sanitize_text_field( wp_unslash( $token ) );
		$data  = get_transient( self::TRANSIENT_PREFIX . $token );

		if ( ! is_array( $data ) || ! isset( $data['exposant_id'] ) ) {
			return null;
		}

		$exposantId = (int) $data['exposant_id'];
		return $exposantId > 0 ? $exposantId : null;
	}

	/**
	 * Détruire la session courante (déconnexion).
	 */
	public function destroySession(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- déconnexion
		$token = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? (string) $_COOKIE[ self::COOKIE_NAME ] : '';

		if ( '' !== $token ) {
			$token = sanitize_text_field( wp_unslash( $token ) );
			delete_transient( self::TRANSIENT_PREFIX . $token );
		}

		$this->cookies->clear( self::COOKIE_NAME );
	}
}
