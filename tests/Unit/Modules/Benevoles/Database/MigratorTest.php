<?php
/**
 * Tests unitaires du Migrator du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Benevoles\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use JDE\Modules\Benevoles\Database\Migrator;
use JDE\Modules\Benevoles\Database\Schema;
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
		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'update_option' )->once();

		$schema = $this->createMock( Schema::class );
		$schema->expects( $this->once() )->method( 'createAllTables' );

		$migrator = new Migrator( $schema );
		$migrator->run();

		Functions\when( 'get_option' )->justReturn( (string) Migrator::CURRENT_VERSION );

		$migrator->run();
	}

	public function testInstalledVersionReturnsZeroWhenOptionMissing(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$schema   = $this->createMock( Schema::class );
		$migrator = new Migrator( $schema );

		$this->assertSame( 0, $migrator->installedVersion() );
	}

	public function testOptionKeyIsScopedToBenevolesModule(): void {
		// Garde-fou : la clef d'option doit être distincte de celle du
		// module Kiosques afin de versionner les deux schémas indépendamment.
		$this->assertSame( 'jde_plugin_benevoles_db_version', Migrator::OPTION_KEY );
		$this->assertNotSame( 'jde_plugin_db_version', Migrator::OPTION_KEY );
	}
}
