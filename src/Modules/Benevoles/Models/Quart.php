<?php
/**
 * Modèle de données : Quart (créneau d'un poste).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Représente un créneau horaire d'un poste donné.
 *
 * Les dates sont stockées en UTC. La détection de chevauchement entre
 * deux quarts se fait via `dateDebut < autre.dateFin && dateFin > autre.dateDebut`.
 */
final readonly class Quart {

	public function __construct(
		public ?int $id,
		public int $posteId,
		public DateTimeImmutable $dateDebut,
		public DateTimeImmutable $dateFin,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			posteId: (int) $row['poste_id'],
			dateDebut: new DateTimeImmutable( (string) $row['date_debut'], $tz ),
			dateFin: new DateTimeImmutable( (string) $row['date_fin'], $tz ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'         => $this->id,
			'poste_id'   => $this->posteId,
			'date_debut' => $this->dateDebut->format( 'c' ),
			'date_fin'   => $this->dateFin->format( 'c' ),
		);
	}
}
