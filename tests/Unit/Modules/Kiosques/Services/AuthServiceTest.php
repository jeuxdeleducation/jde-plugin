<?php
/**
 * Tests unitaires de AuthService.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Kiosques\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use JDE\Modules\Kiosques\Services\AuthService;
use JDE\Tests\Stubs\SpyCookieWriter;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

final class AuthServiceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// Reset $_COOKIE entre les tests.
		$_COOKIE = array();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testCreateSessionStoresTransientAndSetsCookie(): void {
		Functions\when( 'wp_generate_password' )->justReturn( 'TOKEN_FIXTURE' );
		Functions\expect( 'set_transient' )
			->once()
			->with(
				AuthService::TRANSIENT_PREFIX . 'TOKEN_FIXTURE',
				array( 'exposant_id' => 42 ),
				AuthService::SESSION_TTL_DAYS * DAY_IN_SECONDS
			);

		$cookies = new SpyCookieWriter();
		$service = new AuthService( $cookies );

		$token = $service->createSession( 42 );

		$this->assertSame( 'TOKEN_FIXTURE', $token );
		$this->assertCount( 1, $cookies->writes );
		$this->assertSame( AuthService::COOKIE_NAME, $cookies->writes[0]['name'] );
		$this->assertSame( 'TOKEN_FIXTURE', $cookies->writes[0]['value'] );
		$this->assertGreaterThan( time() + 86000, $cookies->writes[0]['expires'] );
	}

	public function testResolveSessionReturnsExposantIdWhenTransientPresent(): void {
		$_COOKIE[ AuthService::COOKIE_NAME ] = 'TOKEN_FIXTURE';

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'get_transient' )
			->justReturn( array( 'exposant_id' => 17 ) );

		$service = new AuthService( new SpyCookieWriter() );
		$this->assertSame( 17, $service->resolveSession() );
	}

	public function testResolveSessionReturnsNullWhenNoCookie(): void {
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();

		$service = new AuthService( new SpyCookieWriter() );
		$this->assertNull( $service->resolveSession() );
	}

	public function testResolveSessionReturnsNullWhenTransientMissing(): void {
		$_COOKIE[ AuthService::COOKIE_NAME ] = 'TOKEN_FIXTURE';

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'get_transient' )->justReturn( false );

		$service = new AuthService( new SpyCookieWriter() );
		$this->assertNull( $service->resolveSession() );
	}

	public function testResolveSessionReturnsNullWhenExposantIdInvalid(): void {
		$_COOKIE[ AuthService::COOKIE_NAME ] = 'TOKEN_FIXTURE';

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'get_transient' )
			->justReturn( array( 'exposant_id' => 0 ) );

		$service = new AuthService( new SpyCookieWriter() );
		$this->assertNull( $service->resolveSession() );
	}

	public function testDestroySessionDeletesTransientAndClearsCookie(): void {
		$_COOKIE[ AuthService::COOKIE_NAME ] = 'TOKEN_FIXTURE';

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\expect( 'delete_transient' )
			->once()
			->with( AuthService::TRANSIENT_PREFIX . 'TOKEN_FIXTURE' );

		$cookies = new SpyCookieWriter();
		$service = new AuthService( $cookies );
		$service->destroySession();

		$this->assertCount( 1, $cookies->clears );
		$this->assertSame( AuthService::COOKIE_NAME, $cookies->clears[0] );
	}

	public function testDestroySessionWithoutCookieStillClearsCookie(): void {
		$cookies = new SpyCookieWriter();
		$service = new AuthService( $cookies );
		$service->destroySession();

		$this->assertCount( 1, $cookies->clears );
	}
}
