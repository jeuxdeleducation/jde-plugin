<?php
/**
 * Tests unitaires du modèle Kiosque.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Kiosques\Models;

use JDE\Modules\Kiosques\Models\Kiosque;
use PHPUnit\Framework\TestCase;

final class KiosqueTest extends TestCase {

	public function testFromRowParsesAllFieldsCorrectly(): void {
		$row = array(
			'id'                => '42',
			'evenement_id'      => '7',
			'numero'            => 'A-12',
			'pos_x'             => '12.3456',
			'pos_y'             => '78.9000',
			'largeur'           => '5.0000',
			'hauteur'           => '5.0000',
			'dimensions_texte'  => "10' × 10'",
			'notes'             => 'Près de la sortie',
			'statut'            => Kiosque::STATUT_DISPONIBLE,
			'date_creation'     => '2026-04-15 10:00:00',
			'date_modification' => '2026-04-15 10:30:00',
		);

		$kiosque = Kiosque::fromRow( $row );

		$this->assertSame( 42, $kiosque->id );
		$this->assertSame( 7, $kiosque->evenementId );
		$this->assertSame( 'A-12', $kiosque->numero );
		$this->assertSame( 12.3456, $kiosque->posX );
		$this->assertSame( "10' × 10'", $kiosque->dimensionsTexte );
		$this->assertSame( Kiosque::STATUT_DISPONIBLE, $kiosque->statut );
		$this->assertSame( '2026-04-15T10:00:00+00:00', $kiosque->dateCreation->format( 'c' ) );
	}

	public function testFromRowTreatsEmptyOptionalFieldsAsNull(): void {
		$row = array(
			'id'                => '1',
			'evenement_id'      => '1',
			'numero'            => 'B-01',
			'pos_x'             => '0',
			'pos_y'             => '0',
			'largeur'           => '1',
			'hauteur'           => '1',
			'dimensions_texte'  => '',
			'notes'             => '',
			'statut'            => Kiosque::STATUT_INDISPONIBLE,
			'date_creation'     => '2026-04-15 10:00:00',
			'date_modification' => '2026-04-15 10:00:00',
		);

		$kiosque = Kiosque::fromRow( $row );

		$this->assertNull( $kiosque->dimensionsTexte );
		$this->assertNull( $kiosque->notes );
	}

	public function testToArrayUsesIso8601Dates(): void {
		$kiosque = Kiosque::fromRow(
			array(
				'id'                => 1,
				'evenement_id'      => 1,
				'numero'            => 'A',
				'pos_x'             => 10,
				'pos_y'             => 10,
				'largeur'           => 5,
				'hauteur'           => 5,
				'dimensions_texte'  => null,
				'notes'             => null,
				'statut'            => Kiosque::STATUT_DISPONIBLE,
				'date_creation'     => '2026-04-15 10:00:00',
				'date_modification' => '2026-04-15 10:00:00',
			)
		);

		$array = $kiosque->toArray();

		$this->assertSame( 'A', $array['numero'] );
		$this->assertSame( '2026-04-15T10:00:00+00:00', $array['date_creation'] );
		$this->assertNull( $array['dimensions_texte'] );
	}
}
