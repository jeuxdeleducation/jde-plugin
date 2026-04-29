<?php
/**
 * Limiteur de débit basé sur les transients WordPress.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Implémente un rate limit fixed-window à compteur :
 *  - première tentative dans la fenêtre : compteur = 1, timestamp = now.
 *  - tentatives suivantes dans la même fenêtre : compteur += 1.
 *  - tentatives après expiration : nouvelle fenêtre (compteur = 1).
 *
 * Utilise un transient (cache court terme persistant si l'objet cache
 * est branché ; sinon stocké dans wp_options). La clé est dérivée d'un
 * « bucket » fourni par l'appelant — par convention l'appelant compose
 * `bucket = sha256(IP) . ':' . endpoint`.
 */
class RateLimiter {

	private const TRANSIENT_PREFIX = 'jde_rl_';

	/**
	 * Tenter une opération. Incrémente le compteur si possible, retourne
	 * `true` si la tentative est autorisée, `false` si la limite est atteinte.
	 *
	 * @param string $bucket         Identifiant unique de l'opération limitée.
	 * @param int    $maxAttempts    Nombre maximal de tentatives par fenêtre.
	 * @param int    $windowSeconds  Durée de la fenêtre en secondes.
	 */
	public function hit( string $bucket, int $maxAttempts, int $windowSeconds ): bool {
		if ( $maxAttempts < 1 || $windowSeconds < 1 ) {
			return true;
		}

		$key   = self::TRANSIENT_PREFIX . sha1( $bucket );
		$now   = time();
		$entry = get_transient( $key );

		// Nouvelle fenêtre si transient absent ou expiré logiquement.
		if (
			! is_array( $entry )
			|| ! isset( $entry['count'], $entry['first'] )
			|| ( $now - (int) $entry['first'] ) >= $windowSeconds
		) {
			set_transient(
				$key,
				array(
					'count' => 1,
					'first' => $now,
				),
				$windowSeconds
			);
			return true;
		}

		$count = (int) $entry['count'];

		if ( $count >= $maxAttempts ) {
			return false;
		}

		set_transient(
			$key,
			array(
				'count' => $count + 1,
				'first' => (int) $entry['first'],
			),
			// TTL résiduel de la fenêtre courante.
			max( 1, $windowSeconds - ( $now - (int) $entry['first'] ) )
		);

		return true;
	}

	/**
	 * Réinitialiser le compteur d'un bucket (ex. : suite à une auth réussie).
	 */
	public function reset( string $bucket ): void {
		delete_transient( self::TRANSIENT_PREFIX . sha1( $bucket ) );
	}
}
