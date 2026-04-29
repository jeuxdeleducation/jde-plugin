<?php
/**
 * Implémentation production de CookieWriter (utilise `setcookie()`).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Écrit les cookies via la fonction native `setcookie()`.
 *
 * Configuration par défaut : `HttpOnly + Secure (si HTTPS) + SameSite=Lax`,
 * path `/`. Si les headers ont déjà été envoyés, l'opération est silencieusement
 * ignorée pour éviter les warnings PHP.
 */
final class PhpCookieWriter implements CookieWriter {

	public function set( string $name, string $value, int $expires ): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	public function clear( string $name ): void {
		$this->set( $name, '', time() - HOUR_IN_SECONDS );
	}
}
