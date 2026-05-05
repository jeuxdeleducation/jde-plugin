<?php
/**
 * Modèle de données : Signature d'un document par une personne.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Trace l'horodatage et le contexte d'une signature électronique.
 *
 * Une personne signe une entente ou une lettre d'engagement directement
 * depuis son profil (case à cocher + bouton). On enregistre l'IP et le
 * `user-agent` à des fins de preuve. La contrainte UNIQUE
 * (personne_id, type_document) empêche les doubles signatures.
 */
final readonly class Signature {

	public const TYPE_ENTENTE = 'entente';
	public const TYPE_LETTRE  = 'lettre';

	public function __construct(
		public ?int $id,
		public int $personneId,
		public string $typeDocument,
		public DateTimeImmutable $signedAt,
		public ?string $ipAddress,
		public ?string $userAgent,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			personneId: (int) $row['personne_id'],
			typeDocument: (string) $row['type_document'],
			signedAt: new DateTimeImmutable( (string) $row['signed_at'], $tz ),
			ipAddress: isset( $row['ip_address'] ) && '' !== $row['ip_address'] ? (string) $row['ip_address'] : null,
			userAgent: isset( $row['user_agent'] ) && '' !== $row['user_agent'] ? (string) $row['user_agent'] : null,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'            => $this->id,
			'personne_id'   => $this->personneId,
			'type_document' => $this->typeDocument,
			'signed_at'     => $this->signedAt->format( 'c' ),
			'ip_address'    => $this->ipAddress,
			'user_agent'    => $this->userAgent,
		);
	}
}
