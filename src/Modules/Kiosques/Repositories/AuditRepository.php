<?php
/**
 * Repository pour le journal d'audit.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Écriture du journal d'audit des actions admin.
 *
 * Phase A ne consomme pas encore le journal (on prépare l'infrastructure).
 * En Phase C, les actions admin sur les réservations utiliseront `log()`
 * pour conserver une trace `qui / quand / quoi`.
 */
class AuditRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_audit';
	}

	/**
	 * Enregistrer une entrée dans le journal.
	 *
	 * @param int                       $userId     Auteur de l'action.
	 * @param string                    $action     Code court (ex. `reservation.delete`).
	 * @param string                    $entityType Type d'entité ciblée (ex. `reservation`).
	 * @param int                       $entityId   ID de l'entité.
	 * @param array<string, mixed>|null $payload    Données contextuelles sérialisées en JSON.
	 */
	public function log( int $userId, string $action, string $entityType, int $entityId, ?array $payload = null ): void {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->insert(
			$this->table,
			array(
				'user_id'     => $userId,
				'action'      => $action,
				'entity_type' => $entityType,
				'entity_id'   => $entityId,
				'payload'     => null === $payload ? null : (string) wp_json_encode( $payload ),
				'created_at'  => $now->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}
}
