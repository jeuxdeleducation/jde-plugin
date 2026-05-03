<?php
/**
 * Modèle d'une entrée du journal d'audit.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Une entrée de la table `wp_jde_audit`, enrichie du login de l'utilisateur.
 */
final readonly class AuditEntry {

	public function __construct(
		public int $id,
		public int $userId,
		public ?string $userLogin,
		public string $action,
		public string $entityType,
		public int $entityId,
		public ?array $payload,
		public DateTimeImmutable $createdAt,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		$payload = null;
		if ( isset( $row['payload'] ) && '' !== $row['payload'] && null !== $row['payload'] ) {
			$decoded = json_decode( (string) $row['payload'], true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}

		return new self(
			id: (int) $row['id'],
			userId: (int) $row['user_id'],
			userLogin: isset( $row['user_login'] ) && '' !== $row['user_login']
				? (string) $row['user_login']
				: null,
			action: (string) $row['action'],
			entityType: (string) $row['entity_type'],
			entityId: (int) $row['entity_id'],
			payload: $payload,
			createdAt: new DateTimeImmutable( (string) $row['created_at'], $tz ),
		);
	}
}
