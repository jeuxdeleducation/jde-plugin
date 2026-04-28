<?php
/**
 * Tests unitaires du modèle Exposant.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Kiosques\Models;

use JDE\Modules\Kiosques\Models\Exposant;
use PHPUnit\Framework\TestCase;

final class ExposantTest extends TestCase {

	public function testFromRowParsesAllFieldsCorrectly(): void {
		$row = array(
			'id'              => '17',
			'evenement_id'    => '3',
			'nom_entreprise'  => 'ACME inc.',
			'nb_kiosques_max' => '2',
			'code_acces'      => 'K7P3-XR9M',
			'date_creation'   => '2026-04-15 10:00:00',
			'cree_par'        => '1',
		);

		$exposant = Exposant::fromRow( $row );

		$this->assertSame( 17, $exposant->id );
		$this->assertSame( 'ACME inc.', $exposant->nomEntreprise );
		$this->assertSame( 2, $exposant->nbKiosquesMax );
		$this->assertSame( 'K7P3-XR9M', $exposant->codeAcces );
	}

	public function testToArrayHidesCodeByDefault(): void {
		$exposant = Exposant::fromRow(
			array(
				'id'              => 1,
				'evenement_id'    => 1,
				'nom_entreprise'  => 'Test',
				'nb_kiosques_max' => 1,
				'code_acces'      => 'SECRET-CODE',
				'date_creation'   => '2026-04-15 10:00:00',
				'cree_par'        => 1,
			)
		);

		$default  = $exposant->toArray();
		$withCode = $exposant->toArray( includeCode: true );

		$this->assertArrayNotHasKey( 'code_acces', $default );
		$this->assertArrayHasKey( 'code_acces', $withCode );
		$this->assertSame( 'SECRET-CODE', $withCode['code_acces'] );
	}
}
