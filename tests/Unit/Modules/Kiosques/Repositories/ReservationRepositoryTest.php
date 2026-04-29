<?php
/**
 * Tests unitaires de ReservationRepository.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Kiosques\Repositories;

use JDE\Modules\Kiosques\Exceptions\KiosqueAlreadyReservedException;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReservationRepositoryTest extends TestCase {

	public function testCreateReturnsReservationOnSuccess(): void {
		$wpdb = $this->makeWpdbStub( insertResult: 1, insertId: 42 );

		$repo        = new ReservationRepository( $wpdb );
		$reservation = $repo->create(
			kiosqueId: 7,
			exposantId: 3,
			creePar: null
		);

		$this->assertSame( 42, $reservation->id );
		$this->assertSame( 7, $reservation->kiosqueId );
		$this->assertSame( 3, $reservation->exposantId );
		$this->assertNull( $reservation->creePar );
	}

	public function testCreateThrowsWhenKiosqueAlreadyReserved(): void {
		$wpdb = $this->makeWpdbStub(
			insertResult: false,
			lastError: "Duplicate entry '7' for key 'kiosque_id'"
		);

		$repo = new ReservationRepository( $wpdb );

		$this->expectException( KiosqueAlreadyReservedException::class );
		$repo->create( kiosqueId: 7, exposantId: 3 );
	}

	public function testCreateExposesKiosqueIdInException(): void {
		$wpdb = $this->makeWpdbStub(
			insertResult: false,
			lastError: 'Duplicate entry'
		);

		$repo = new ReservationRepository( $wpdb );

		try {
			$repo->create( kiosqueId: 99, exposantId: 1 );
			$this->fail( 'Exception attendue.' );
		} catch ( KiosqueAlreadyReservedException $e ) {
			$this->assertSame( 99, $e->kiosqueId );
		}
	}

	public function testCreateThrowsRuntimeExceptionOnOtherDbError(): void {
		$wpdb = $this->makeWpdbStub(
			insertResult: false,
			lastError: 'Some other DB failure'
		);

		$repo = new ReservationRepository( $wpdb );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Some other DB failure' );

		$repo->create( kiosqueId: 7, exposantId: 3 );
	}

	/**
	 * Construit une sous-classe anonyme de wpdb avec les comportements voulus.
	 */
	private function makeWpdbStub(
		mixed $insertResult,
		int $insertId = 0,
		string $lastError = ''
	): \wpdb {
		$stub             = new \wpdb();
		$stub->insert_id  = $insertId;
		$stub->last_error = $lastError;

		// Override insert via une sous-classe à la volée.
		return new class( $stub, $insertResult ) extends \wpdb {
			public function __construct(
				\wpdb $base,
				private readonly mixed $insertResult
			) {
				$this->prefix     = $base->prefix;
				$this->insert_id  = $base->insert_id;
				$this->last_error = $base->last_error;
			}

			public function insert( string $table, array $data, array|string $format = '' ): int|false {
				return $this->insertResult;
			}
		};
	}
}
