<?php
/**
 * Tests unitaires du EvenementService.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Kiosques\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Services\EvenementService;
use PHPUnit\Framework\TestCase;

final class EvenementServiceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testIsActiveReadsFromMeta(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, EvenementPostType::META_ACTIF, true )
			->andReturn( '1' );

		$this->assertTrue( ( new EvenementService() )->isActive( 42 ) );
	}

	public function testDeactivateUpdatesMetaToFalse(): void {
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 7, EvenementPostType::META_ACTIF, false );

		( new EvenementService() )->deactivate( 7 );

		// La vérification se fait via Brain Monkey au tearDown ;
		// on bump explicitement le compteur PHPUnit pour éviter le statut "risky".
		$this->addToAssertionCount( 1 );
	}

	public function testActivateSetsMetaDirectly(): void {
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 7, EvenementPostType::META_ACTIF, true );

		( new EvenementService() )->activate( 7 );

		$this->addToAssertionCount( 1 );
	}
}
