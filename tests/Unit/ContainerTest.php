<?php
/**
 * Tests unitaires du conteneur de services.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit;

use JDE\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class ContainerTest extends TestCase {

	public function testGetReturnsRegisteredInstance(): void {
		$container = new Container();
		$service   = new stdClass();

		$container->instance( 'service', $service );

		$this->assertSame( $service, $container->get( 'service' ) );
	}

	public function testFactoryIsCalledOnceAndResultCached(): void {
		$container = new Container();
		$calls     = 0;

		$container->set(
			'service',
			static function () use ( &$calls ): stdClass {
				++$calls;
				return new stdClass();
			}
		);

		$first  = $container->get( 'service' );
		$second = $container->get( 'service' );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $calls );
	}

	public function testFactoryReceivesContainer(): void {
		$container = new Container();
		$received  = null;

		$container->set(
			'service',
			static function ( Container $c ) use ( &$received ): stdClass {
				$received = $c;
				return new stdClass();
			}
		);

		$container->get( 'service' );

		$this->assertSame( $container, $received );
	}

	public function testHasReportsBothInstancesAndFactories(): void {
		$container = new Container();

		$this->assertFalse( $container->has( 'a' ) );

		$container->instance( 'a', new stdClass() );
		$this->assertTrue( $container->has( 'a' ) );

		$container->set( 'b', static fn (): stdClass => new stdClass() );
		$this->assertTrue( $container->has( 'b' ) );
	}

	public function testGetThrowsWhenServiceMissing(): void {
		$container = new Container();

		$this->expectException( RuntimeException::class );
		$container->get( 'inconnu' );
	}
}
