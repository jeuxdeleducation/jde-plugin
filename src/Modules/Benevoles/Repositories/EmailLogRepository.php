<?php
/**
 * Repository pour le journal d'envois de courriels.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use JDE\Modules\Benevoles\Models\EmailLog;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des envois de courriels (transactionnels et ciblés).
 */
class EmailLogRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_rh_email_log';
	}

	public function insert( EmailLog $log ): EmailLog {
		$data = array(
			'evenement_rh_id' => $log->evenementRhId,
			'template'        => $log->template,
			'subject'         => $log->subject,
			'recipient_count' => $log->recipientCount,
			'sent_at'         => $log->sentAt->format( 'Y-m-d H:i:s' ),
			'sent_by'         => $log->sentBy,
			'filters_json'    => wp_json_encode( $log->filters ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->insert( $this->table, $data );

		return EmailLog::fromRow( array_merge( array( 'id' => $this->wpdb->insert_id ), $data ) );
	}

	/**
	 * @return EmailLog[]
	 */
	public function findByEvenement( int $evenementRhId, int $limit = 50 ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE evenement_rh_id = %d ORDER BY sent_at DESC LIMIT %d",
				$evenementRhId,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): EmailLog => EmailLog::fromRow( $row ), $rows );
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
