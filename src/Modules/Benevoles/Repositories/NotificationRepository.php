<?php
/**
 * Repository pour les notifications gestionnaires.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\Models\Notification;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des notifications du widget gestionnaire.
 */
class NotificationRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_rh_notifications';
	}

	public function insert( Notification $notification ): Notification {
		$data = array(
			'type'            => $notification->type,
			'entity_type'     => $notification->entityType,
			'entity_id'       => $notification->entityId,
			'evenement_rh_id' => $notification->evenementRhId,
			'payload'         => wp_json_encode( $notification->payload ),
			'created_at'      => $notification->createdAt->format( 'Y-m-d H:i:s' ),
			'read_at'         => $notification->readAt?->format( 'Y-m-d H:i:s' ),
			'read_by_user_id' => $notification->readByUserId,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->insert( $this->table, $data );

		return Notification::fromRow( array_merge( array( 'id' => $this->wpdb->insert_id ), $data ) );
	}

	/**
	 * @return Notification[]
	 */
	public function findUnread( int $evenementRhId, int $limit = 10 ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE evenement_rh_id = %d AND read_at IS NULL
				ORDER BY created_at DESC
				LIMIT %d",
				$evenementRhId,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Notification => Notification::fromRow( $row ), $rows );
	}

	public function countUnread( int $evenementRhId ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE evenement_rh_id = %d AND read_at IS NULL",
				$evenementRhId
			)
		);
		// phpcs:enable

		return (int) $count;
	}

	public function markAllRead( int $evenementRhId, int $userId ): int {
		$now = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				SET read_at = %s, read_by_user_id = %d
				WHERE evenement_rh_id = %d AND read_at IS NULL",
				$now,
				$userId,
				$evenementRhId
			)
		);
		// phpcs:enable

		return (int) ( false === $result ? 0 : $result );
	}

	public function deleteByEvenementId( int $evenementRhId ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$this->table,
			array( 'evenement_rh_id' => $evenementRhId ),
			array( '%d' )
		);

		return (int) ( false === $result ? 0 : $result );
	}
}
