<?php
/**
 * Modèle de données : Plage de disponibilité prédéfinie.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Représente une plage horaire prédéfinie par le gestionnaire d'événement.
 *
 * Le candidat coche, parmi les plages disponibles pour l'événement actif,
 * celles où il sera disponible. L'algorithme d'auto-assignation se base sur
 * cette intersection pour proposer les quarts qui couvrent les
 * disponibilités déclarées.
 *
 * Le champ `ordre` permet au gestionnaire de réordonner les plages dans
 * l'interface (drag-and-drop).
 */
final readonly class PlageDisponibilite {

	public function __construct(
		public ?int $id,
		public int $evenementRhId,
		public string $libelle,
		public DateTimeImmutable $dateDebut,
		public DateTimeImmutable $dateFin,
		public int $ordre,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			evenementRhId: (int) $row['evenement_rh_id'],
			libelle: (string) $row['libelle'],
			dateDebut: new DateTimeImmutable( (string) $row['date_debut'], $tz ),
			dateFin: new DateTimeImmutable( (string) $row['date_fin'], $tz ),
			ordre: (int) $row['ordre'],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'              => $this->id,
			'evenement_rh_id' => $this->evenementRhId,
			'libelle'         => $this->libelle,
			'date_debut'      => $this->dateDebut->format( 'c' ),
			'date_fin'        => $this->dateFin->format( 'c' ),
			'ordre'           => $this->ordre,
		);
	}
}
