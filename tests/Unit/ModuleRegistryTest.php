<?php
/**
 * Tests unitaires du registre de modules.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit;

use JDE\Container;
use JDE\Modules\ModuleInterface;
use JDE\Modules\ModuleRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase {

	public function testAddAndGetModule(): void {
		$registry = new ModuleRegistry( new Container() );
		$module   = $this->makeModule( 'demo' );

		$registry->add( $module );

		$this->assertSame( $module, $registry->get( 'demo' ) );
		$this->assertSame( array( 'demo' ), $registry->ids() );
	}

	public function testRegisterAllPropagatesContainerToModules(): void {
		$container = new Container();
		$registry  = new ModuleRegistry( $container );

		$received = null;
		$module   = $this->makeModule(
			'demo',
			static function ( Container $c ) use ( &$received ): void {
				$received = $c;
			}
		);

		$registry->add( $module );
		$registry->registerAll();

		$this->assertSame( $container, $received );
		$this->assertTrue( $registry->isRegistered() );
	}

	public function testRegisterAllIsIdempotent(): void {
		$registry = new ModuleRegistry( new Container() );
		$calls    = 0;

		$registry->add(
			$this->makeModule(
				'demo',
				static function () use ( &$calls ): void {
					++$calls;
				}
			)
		);

		$registry->registerAll();
		$registry->registerAll();

		$this->assertSame( 1, $calls );
	}

	public function testDuplicateIdThrows(): void {
		$registry = new ModuleRegistry( new Container() );
		$registry->add( $this->makeModule( 'demo' ) );

		$this->expectException( LogicException::class );
		$registry->add( $this->makeModule( 'demo' ) );
	}

	public function testCannotAddAfterRegisterAll(): void {
		$registry = new ModuleRegistry( new Container() );
		$registry->registerAll();

		$this->expectException( LogicException::class );
		$registry->add( $this->makeModule( 'tardif' ) );
	}

	private function makeModule( string $id, ?\Closure $onRegister = null ): ModuleInterface {
		return new class( $id, $onRegister ) implements ModuleInterface {
			public function __construct( private readonly string $moduleId, private readonly ?\Closure $onRegister ) {}

			public function id(): string {
				return $this->moduleId;
			}

			public function register( Container $container ): void {
				if ( null !== $this->onRegister ) {
					( $this->onRegister )( $container );
				}
			}
		};
	}
}
