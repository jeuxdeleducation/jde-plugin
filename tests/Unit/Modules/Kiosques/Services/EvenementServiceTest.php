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

	public function testActivateDeactivatesOthersThenSetsActive(): void {
		// Le stub WP_Query (chargé par tests/bootstrap.php) lit la liste de
		// posts depuis $GLOBALS['__wp_query_posts_stub'].
		$GLOBALS['__wp_query_posts_stub']    = array( 1, 2, 3 );
		$GLOBALS['__update_post_meta_calls'] = array();

		Functions\when( 'update_post_meta' )->alias(
			static function ( $id, $key, $value ) {
				$GLOBALS['__update_post_meta_calls'][] = array( $id, $key, $value );
				return true;
			}
		);

		( new EvenementService() )->activate( 7 );

		$calls = $GLOBALS['__update_post_meta_calls'];

		$this->assertCount( 4, $calls );

		// Les 3 premiers : désactivation des événements 1, 2, 3.
		$deactivated = array_map( static fn ( $c ) => $c[0], array_slice( $calls, 0, 3 ) );
		sort( $deactivated );
		$this->assertSame( array( 1, 2, 3 ), $deactivated );

		foreach ( array_slice( $calls, 0, 3 ) as $call ) {
			$this->assertSame( EvenementPostType::META_ACTIF, $call[1] );
			$this->assertFalse( $call[2] );
		}

		// Le dernier : activation de 7.
		$this->assertSame( array( 7, EvenementPostType::META_ACTIF, true ), $calls[3] );
	}

	public function testActivateExcludesTargetFromDeactivation(): void {
		// Si l'événement à activer (5) est déjà dans la liste des actifs,
		// il NE doit PAS être désactivé puis réactivé (économie d'une écriture).
		$GLOBALS['__wp_query_posts_stub']    = array( 5, 8 );
		$GLOBALS['__update_post_meta_calls'] = array();

		Functions\when( 'update_post_meta' )->alias(
			static function ( $id, $key, $value ) {
				$GLOBALS['__update_post_meta_calls'][] = array( $id, $key, $value );
				return true;
			}
		);

		( new EvenementService() )->activate( 5 );

		$calls = $GLOBALS['__update_post_meta_calls'];

		// Une seule désactivation (8) + une activation (5) = 2 appels.
		$this->assertCount( 2, $calls );
		$this->assertSame( array( 8, EvenementPostType::META_ACTIF, false ), $calls[0] );
		$this->assertSame( array( 5, EvenementPostType::META_ACTIF, true ), $calls[1] );
	}
}
