<?php
/**
 * Modèle de données : réponse à une question d'inscription.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Représente une réponse libre stockée pour une personne donnée.
 *
 * Chaque champ du formulaire d'inscription (autre que les champs fixes
 * prénom, nom, courriel, téléphone) est persisté ici. `fieldKey` est la
 * clef interne (slug du champ dans le schéma) et `fieldLabel` la
 * formulation présentée au candidat — on la sauvegarde aussi pour que
 * l'admin puisse relire la réponse même si le schéma a été modifié
 * depuis.
 */
final readonly class InscriptionReponse {

	public function __construct(
		public ?int $id,
		public int $personneId,
		public string $fieldKey,
		public string $fieldLabel,
		public ?string $fieldValue,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			personneId: (int) $row['personne_id'],
			fieldKey: (string) $row['field_key'],
			fieldLabel: (string) $row['field_label'],
			fieldValue: isset( $row['field_value'] ) ? (string) $row['field_value'] : null,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'          => $this->id,
			'personne_id' => $this->personneId,
			'field_key'   => $this->fieldKey,
			'field_label' => $this->fieldLabel,
			'field_value' => $this->fieldValue,
		);
	}
}
