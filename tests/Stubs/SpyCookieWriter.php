<?php
/**
 * Implémentation test de CookieWriter qui mémorise les opérations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Stubs;

use JDE\Modules\Kiosques\Services\CookieWriter;

/**
 * Capture les appels `set()` et `clear()` pour permettre aux tests
 * d'assertir sur le comportement de AuthService sans toucher à
 * `setcookie()` (impossible à mocker proprement quand les headers
 * sont déjà envoyés en contexte de test).
 */
final class SpyCookieWriter implements CookieWriter {

	/** @var array<int, array{name: string, value: string, expires: int}> */
	public array $writes = array();

	/** @var string[] */
	public array $clears = array();

	public function set( string $name, string $value, int $expires ): void {
		$this->writes[] = compact( 'name', 'value', 'expires' );
	}

	public function clear( string $name ): void {
		$this->clears[] = $name;
	}
}
