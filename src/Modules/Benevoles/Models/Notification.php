<?php
/**
 * Modèle de données : Notification gestionnaire.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Représente un événement nécessitant l'attention des gestionnaires.
 *
 * Émise par les services métier (inscription nouvelle, refus d'assignation,
 * chevauchement détecté, sur-effectif…) et affichée dans le widget
 * dashboard ainsi qu'envoyée par courriel aux détenteurs de la capacité
 * `jde_manage_benevoles`.
 *
 * Le `payload` est un tableau associatif sérialisé en JSON dans la BD,
 * laissant aux services la liberté d'y mettre les détails contextuels
 * (libellés, IDs additionnels) nécessaires au rendu.
 */
final readonly class Notification {

	public const TYPE_INSCRIPTION_NOUVELLE   = 'inscription_nouvelle';
	public const TYPE_ASSIGNATION_REFUSEE    = 'assignation_refusee';
	public const TYPE_CHEVAUCHEMENT_PERSONNE = 'chevauchement_personne';
	public const TYPE_SUR_EFFECTIF_POSTE     = 'sur_effectif_poste';
	public const TYPE_SIGNATURE_COMPLETEE    = 'signature_completee';

	/**
	 * @param array<string, mixed> $payload
	 */
	public function __construct(
		public ?int $id,
		public string $type,
		public string $entityType,
		public int $entityId,
		public int $evenementRhId,
		public array $payload,
		public DateTimeImmutable $createdAt,
		public ?DateTimeImmutable $readAt,
		public ?int $readByUserId,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		$readAt = isset( $row['read_at'] ) && null !== $row['read_at']
			? new DateTimeImmutable( (string) $row['read_at'], $tz )
			: null;

		$payload = array();
		if ( isset( $row['payload'] ) && '' !== $row['payload'] ) {
			$decoded = json_decode( (string) $row['payload'], true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			type: (string) $row['type'],
			entityType: (string) $row['entity_type'],
			entityId: (int) $row['entity_id'],
			evenementRhId: (int) $row['evenement_rh_id'],
			payload: $payload,
			createdAt: new DateTimeImmutable( (string) $row['created_at'], $tz ),
			readAt: $readAt,
			readByUserId: isset( $row['read_by_user_id'] ) && null !== $row['read_by_user_id']
				? (int) $row['read_by_user_id']
				: null,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'              => $this->id,
			'type'            => $this->type,
			'entity_type'     => $this->entityType,
			'entity_id'       => $this->entityId,
			'evenement_rh_id' => $this->evenementRhId,
			'payload'         => $this->payload,
			'created_at'      => $this->createdAt->format( 'c' ),
			'read_at'         => $this->readAt?->format( 'c' ),
			'read_by_user_id' => $this->readByUserId,
		);
	}
}
