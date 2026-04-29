<?php
/**
 * Abstraction pour l'écriture de cookies HTTP (testable).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Permet d'injecter une implémentation alternative en tests, où
 * `setcookie()` n'est pas appelable (headers déjà envoyés).
 */
interface CookieWriter {

	/**
	 * Définir un cookie HttpOnly + Secure + SameSite=Lax.
	 *
	 * @param string $name    Nom du cookie.
	 * @param string $value   Valeur (token).
	 * @param int    $expires Timestamp Unix d'expiration.
	 */
	public function set( string $name, string $value, int $expires ): void;

	/**
	 * Supprimer un cookie côté navigateur (expiration passée).
	 */
	public function clear( string $name ): void;
}
