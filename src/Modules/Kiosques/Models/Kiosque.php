<?php
/**
 * Modèle de données : Kiosque.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Représente un emplacement de kiosque sur le plan d'un événement.
 *
 * Position et dimensions stockées en pourcentage (0–100) du plan, ce qui
 * rend les coordonnées résilientes à un changement de résolution de
 * l'image. Toutes les dates sont en UTC.
 */
final readonly class Kiosque {

	public const STATUT_DISPONIBLE   = 'disponible';
	public const STATUT_INDISPONIBLE = 'indisponible';

	/**
	 * @param int|null $id Null si pas encore persisté.
	 */
	public function __construct(
		public ?int $id,
		public int $evenementId,
		public string $numero,
		public float $posX,
		public float $posY,
		public float $largeur,
		public float $hauteur,
		public ?string $dimensionsTexte,
		public ?string $notes,
		public string $statut,
		public DateTimeImmutable $dateCreation,
		public DateTimeImmutable $dateModification,
	) {}

	/**
	 * Construire à partir d'une ligne de la table `wp_jde_kiosques`.
	 *
	 * @param array<string, mixed> $row Ligne brute de wpdb.
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			evenementId: (int) $row['evenement_id'],
			numero: (string) $row['numero'],
			posX: (float) $row['pos_x'],
			posY: (float) $row['pos_y'],
			largeur: (float) $row['largeur'],
			hauteur: (float) $row['hauteur'],
			dimensionsTexte: isset( $row['dimensions_texte'] ) && '' !== $row['dimensions_texte']
				? (string) $row['dimensions_texte']
				: null,
			notes: isset( $row['notes'] ) && '' !== $row['notes'] ? (string) $row['notes'] : null,
			statut: (string) ( $row['statut'] ?? self::STATUT_DISPONIBLE ),
			dateCreation: new DateTimeImmutable( (string) $row['date_creation'], $tz ),
			dateModification: new DateTimeImmutable( (string) $row['date_modification'], $tz ),
		);
	}

	/**
	 * Sérialisation côté JSON / front (pour le canvas React).
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'                => $this->id,
			'evenement_id'      => $this->evenementId,
			'numero'            => $this->numero,
			'pos_x'             => $this->posX,
			'pos_y'             => $this->posY,
			'largeur'           => $this->largeur,
			'hauteur'           => $this->hauteur,
			'dimensions_texte'  => $this->dimensionsTexte,
			'notes'             => $this->notes,
			'statut'            => $this->statut,
			'date_creation'     => $this->dateCreation->format( 'c' ),
			'date_modification' => $this->dateModification->format( 'c' ),
		);
	}
}
