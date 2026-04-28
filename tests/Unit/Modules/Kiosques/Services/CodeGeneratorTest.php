<?php
/**
 * Tests unitaires du générateur de codes.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Kiosques\Services;

use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Services\CodeGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CodeGeneratorTest extends TestCase {

	public function testRandomCodeMatchesExpectedFormat(): void {
		$generator = new CodeGenerator( $this->makeRepo() );

		for ( $i = 0; $i < 50; $i++ ) {
			$code = $generator->randomCode();
			$this->assertMatchesRegularExpression( '/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $code );
		}
	}

	public function testRandomCodeNeverContainsAmbiguousCharacters(): void {
		$generator = new CodeGenerator( $this->makeRepo() );
		$ambiguous = array( '0', 'O', '1', 'I' );

		for ( $i = 0; $i < 50; $i++ ) {
			$code = $generator->randomCode();
			foreach ( $ambiguous as $char ) {
				$this->assertStringNotContainsString( $char, $code );
			}
		}
	}

	public function testGenerateUniqueReturnsCodeWhenNoCollision(): void {
		$repo = $this->createMock( ExposantRepository::class );
		$repo->method( 'codeExists' )->willReturn( false );

		$generator = new CodeGenerator( $repo );
		$code      = $generator->generateUnique();

		$this->assertMatchesRegularExpression( '/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $code );
	}

	public function testGenerateUniqueRetriesOnCollision(): void {
		$repo = $this->createMock( ExposantRepository::class );
		// Simule une collision sur les 3 premières tentatives, puis succès.
		$repo->method( 'codeExists' )
			->willReturnOnConsecutiveCalls( true, true, true, false );

		$generator = new CodeGenerator( $repo );
		$code      = $generator->generateUnique();

		$this->assertMatchesRegularExpression( '/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $code );
	}

	public function testGenerateUniqueThrowsAfterMaxAttempts(): void {
		$repo = $this->createMock( ExposantRepository::class );
		$repo->method( 'codeExists' )->willReturn( true );

		$generator = new CodeGenerator( $repo );

		$this->expectException( RuntimeException::class );
		$generator->generateUnique();
	}

	public function testIsValidFormatAcceptsWellFormedCodes(): void {
		$this->assertTrue( CodeGenerator::isValidFormat( 'ABCD-EFGH' ) );
		$this->assertTrue( CodeGenerator::isValidFormat( 'K7P3-XR9M' ) );
	}

	public function testIsValidFormatRejectsAmbiguousCharacters(): void {
		$this->assertFalse( CodeGenerator::isValidFormat( '0ABC-DEFG' ) );
		$this->assertFalse( CodeGenerator::isValidFormat( 'OABC-DEFG' ) );
		$this->assertFalse( CodeGenerator::isValidFormat( '1ABC-DEFG' ) );
		$this->assertFalse( CodeGenerator::isValidFormat( 'IABC-DEFG' ) );
	}

	public function testIsValidFormatRejectsBadFormat(): void {
		$this->assertFalse( CodeGenerator::isValidFormat( 'ABCDEFGH' ) ); // pas de tiret
		$this->assertFalse( CodeGenerator::isValidFormat( 'ABC-DEFGH' ) ); // mauvaise longueur
		$this->assertFalse( CodeGenerator::isValidFormat( 'abcd-efgh' ) ); // minuscules
	}

	private function makeRepo(): ExposantRepository {
		$repo = $this->createMock( ExposantRepository::class );
		$repo->method( 'codeExists' )->willReturn( false );
		return $repo;
	}
}
