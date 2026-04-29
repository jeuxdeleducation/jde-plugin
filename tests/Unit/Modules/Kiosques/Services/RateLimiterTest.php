<?php
/**
 * Tests unitaires de RateLimiter.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Kiosques\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use JDE\Modules\Kiosques\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testFirstHitInitializesCounter(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )
			->once()
			->andReturnUsing(
				static function ( $key, $value, $ttl ) {
					$GLOBALS['__rl_set'] = array( $key, $value, $ttl );
					return true;
				}
			);

		$limiter = new RateLimiter();
		$this->assertTrue( $limiter->hit( 'auth_127.0.0.1', 5, 900 ) );

		$this->assertSame( 1, $GLOBALS['__rl_set'][1]['count'] );
		$this->assertSame( 900, $GLOBALS['__rl_set'][2] );
	}

	public function testSecondHitWithinWindowIncrements(): void {
		$now = time();
		Functions\when( 'get_transient' )
			->justReturn(
				array(
					'count' => 1,
					'first' => $now - 60,
				)
			);

		$captured = null;
		Functions\expect( 'set_transient' )
			->once()
			->andReturnUsing(
				static function ( $key, $value, $ttl ) use ( &$captured ) {
					$captured = array( $key, $value, $ttl );
					return true;
				}
			);

		$limiter = new RateLimiter();
		$this->assertTrue( $limiter->hit( 'bucket', 5, 900 ) );

		$this->assertSame( 2, $captured[1]['count'] );
		// TTL résiduel ≈ 900 - 60 = 840 (avec une marge de 1s pour exécution).
		$this->assertGreaterThan( 830, $captured[2] );
		$this->assertLessThanOrEqual( 840, $captured[2] );
	}

	public function testExceedingMaxAttemptsReturnsFalse(): void {
		$now = time();
		Functions\when( 'get_transient' )
			->justReturn(
				array(
					'count' => 5,
					'first' => $now - 30,
				)
			);
		Functions\expect( 'set_transient' )->never();

		$limiter = new RateLimiter();
		$this->assertFalse( $limiter->hit( 'bucket', 5, 900 ) );
	}

	public function testWindowExpirationStartsNewCounter(): void {
		$now = time();
		// Entrée vieille de 1000s alors que la fenêtre = 900s.
		Functions\when( 'get_transient' )
			->justReturn(
				array(
					'count' => 5,
					'first' => $now - 1000,
				)
			);

		$captured = null;
		Functions\expect( 'set_transient' )
			->once()
			->andReturnUsing(
				static function ( $key, $value ) use ( &$captured ) {
					$captured = $value;
					return true;
				}
			);

		$limiter = new RateLimiter();
		$this->assertTrue( $limiter->hit( 'bucket', 5, 900 ) );
		$this->assertSame( 1, $captured['count'] );
	}

	public function testResetDeletesTransient(): void {
		Functions\expect( 'delete_transient' )
			->once()
			->andReturnUsing(
				static function ( $key ) {
					$GLOBALS['__rl_deleted'] = $key;
					return true;
				}
			);

		( new RateLimiter() )->reset( 'auth_x' );

		$this->assertStringStartsWith( 'jde_rl_', $GLOBALS['__rl_deleted'] );
	}
}
