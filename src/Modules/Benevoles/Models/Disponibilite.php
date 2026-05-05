<?php
/**
 * Modèle de données : Disponibilité cochée par une personne.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Lien plusieurs-à-plusieurs entre une personne et une plage de disponibilité.
 *
 * Une contrainte UNIQUE (personne_id, plage_id) garantit qu'une plage ne
 * peut pas être cochée deux fois par la même personne.
 */
final readonly class Disponibilite {

	public function __construct(
		public ?int $id,
		public int $personneId,
		public int $plageId,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			personneId: (int) $row['personne_id'],
			plageId: (int) $row['plage_id'],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'          => $this->id,
			'personne_id' => $this->personneId,
			'plage_id'    => $this->plageId,
		);
	}
}
