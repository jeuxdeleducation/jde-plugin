<?php
/**
 * Modèle de données : Poste (bloc d'implication).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Représente un bloc d'implication pour une édition RH.
 *
 * Chaque poste est lié à un type de rôle (bénévole / jury / arbitre) et
 * peut comporter plusieurs quarts (voir {@see Quart}). Le `responsableUserId`
 * est l'utilisateur WP responsable du poste sur le terrain ; il peut être
 * absent si non encore désigné.
 */
final readonly class Poste {

	public function __construct(
		public ?int $id,
		public int $evenementRhId,
		public string $nom,
		public ?string $description,
		public ?string $lieu,
		public int $nbPersonnesSouhaite,
		public ?int $responsableUserId,
		public string $typeRole,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			evenementRhId: (int) $row['evenement_rh_id'],
			nom: (string) $row['nom'],
			description: isset( $row['description'] ) && '' !== $row['description'] ? (string) $row['description'] : null,
			lieu: isset( $row['lieu'] ) && '' !== $row['lieu'] ? (string) $row['lieu'] : null,
			nbPersonnesSouhaite: (int) $row['nb_personnes_souhaite'],
			responsableUserId: isset( $row['responsable_user_id'] ) && null !== $row['responsable_user_id']
				? (int) $row['responsable_user_id']
				: null,
			typeRole: (string) $row['type_role'],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'                    => $this->id,
			'evenement_rh_id'       => $this->evenementRhId,
			'nom'                   => $this->nom,
			'description'           => $this->description,
			'lieu'                  => $this->lieu,
			'nb_personnes_souhaite' => $this->nbPersonnesSouhaite,
			'responsable_user_id'   => $this->responsableUserId,
			'type_role'             => $this->typeRole,
		);
	}
}
