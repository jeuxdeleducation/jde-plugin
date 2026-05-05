<?php
/**
 * Modèle de données : Assignation d'une personne à un quart.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Représente l'affectation d'une personne acceptée à un quart.
 *
 * Cycle de vie : `proposee` (création par un gestionnaire ou la
 * suggestion automatique) → `acceptee` ou `refusee` (décision de la
 * personne via son profil). Une assignation refusée conserve le motif
 * pour traçabilité et pour aider le gestionnaire à reproposer.
 *
 * La contrainte UNIQUE (personne_id, quart_id) empêche de proposer deux
 * fois le même quart à la même personne. Les chevauchements horaires
 * (deux quarts différents qui se télescopent) sont détectés par le
 * service au moment de la création — ils ne sont pas bloqués mais
 * déclenchent une notification au gestionnaire.
 */
final readonly class Assignation {

	public const STATUT_PROPOSEE = 'proposee';
	public const STATUT_ACCEPTEE = 'acceptee';
	public const STATUT_REFUSEE  = 'refusee';

	public function __construct(
		public ?int $id,
		public int $personneId,
		public int $quartId,
		public string $statut,
		public DateTimeImmutable $dateCreation,
		public ?DateTimeImmutable $dateDecision,
		public ?int $creePar,
		public ?string $motifRefus,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		$dateDecision = isset( $row['date_decision'] ) && null !== $row['date_decision']
			? new DateTimeImmutable( (string) $row['date_decision'], $tz )
			: null;

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			personneId: (int) $row['personne_id'],
			quartId: (int) $row['quart_id'],
			statut: (string) ( $row['statut'] ?? self::STATUT_PROPOSEE ),
			dateCreation: new DateTimeImmutable( (string) $row['date_creation'], $tz ),
			dateDecision: $dateDecision,
			creePar: isset( $row['cree_par'] ) && null !== $row['cree_par'] ? (int) $row['cree_par'] : null,
			motifRefus: isset( $row['motif_refus'] ) && '' !== $row['motif_refus'] ? (string) $row['motif_refus'] : null,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'            => $this->id,
			'personne_id'   => $this->personneId,
			'quart_id'      => $this->quartId,
			'statut'        => $this->statut,
			'date_creation' => $this->dateCreation->format( 'c' ),
			'date_decision' => $this->dateDecision?->format( 'c' ),
			'cree_par'      => $this->creePar,
			'motif_refus'   => $this->motifRefus,
		);
	}
}
