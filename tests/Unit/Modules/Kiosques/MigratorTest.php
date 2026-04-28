<?php
/**
 * Tests unitaires du Migrator.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Kiosques;

use Brain\Monkey;
use Brain\Monkey\Functions;
use JDE\Modules\Kiosques\Database\Migrator;
use JDE\Modules\Kiosques\Database\Schema;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testRunCreatesTablesOnFirstInstall(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'update_option' )
			->once()
			->with( Migrator::OPTION_KEY, (string) Migrator::CURRENT_VERSION, false );

		$schema = $this->createMock( Schema::class );
		$schema->expects( $this->once() )->method( 'createAllTables' );

		( new Migrator( $schema ) )->run();
	}

	public function testRunIsSkippedWhenAlreadyUpToDate(): void {
		Functions\when( 'get_option' )->justReturn( (string) Migrator::CURRENT_VERSION );
		Functions\expect( 'update_option' )->never();

		$schema = $this->createMock( Schema::class );
		$schema->expects( $this->never() )->method( 'createAllTables' );

		( new Migrator( $schema ) )->run();
	}

	public function testRunIsIdempotentAcrossMultipleCalls(): void {
		// Première exécution : version 0 → la migration s'applique.
		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'update_option' )->once();

		$schema = $this->createMock( Schema::class );
		$schema->expects( $this->once() )->method( 'createAllTables' );

		$migrator = new Migrator( $schema );
		$migrator->run();

		// Seconde exécution : version 1 → rien à faire (rebascule de la fonction mockée).
		Functions\when( 'get_option' )->justReturn( (string) Migrator::CURRENT_VERSION );

		$migrator->run();
	}

	public function testInstalledVersionReturnsZeroWhenOptionMissing(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$schema   = $this->createMock( Schema::class );
		$migrator = new Migrator( $schema );

		$this->assertSame( 0, $migrator->installedVersion() );
	}

	public function testInstalledVersionCastsStringToInt(): void {
		Functions\when( 'get_option' )->justReturn( '7' );

		$schema   = $this->createMock( Schema::class );
		$migrator = new Migrator( $schema );

		$this->assertSame( 7, $migrator->installedVersion() );
	}
}
