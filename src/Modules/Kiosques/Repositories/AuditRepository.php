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
use JDE\Modules\Kiosques\Models\AuditEntry;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture/écriture du journal d'audit des actions admin.
 *
 * `log()` est appelé depuis tous les écrans admin qui modifient des
 * données pour conserver une trace `qui / quand / quoi`. `query()` et
 * `countMatching()` alimentent la page Historique de Phase C.
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

	/**
	 * Interroger le journal avec des filtres optionnels et pagination.
	 *
	 * @param array{
	 *   user_id?: int,
	 *   entity_type?: string,
	 *   entity_id?: int,
	 *   action_prefix?: string
	 * } $filters
	 * @param int $limit  Nombre max de résultats (50 par défaut).
	 * @param int $offset Décalage pour la pagination.
	 *
	 * @return AuditEntry[]
	 */
	public function query( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		[ $where, $params ] = $this->buildWhereClause( $filters );
		$users              = $this->wpdb->users;

		$sql = "SELECT a.*, u.user_login
				FROM {$this->table} a
				LEFT JOIN {$users} u ON u.ID = a.user_id
				{$where}
				ORDER BY a.created_at DESC, a.id DESC
				LIMIT %d OFFSET %d";

		$params[] = max( 1, $limit );
		$params[] = max( 0, $offset );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn ( array $row ): AuditEntry => AuditEntry::fromRow( $row ),
			$rows
		);
	}

	/**
	 * Compter les entrées correspondant aux filtres (pour la pagination).
	 *
	 * @param array<string, mixed> $filters
	 */
	public function countMatching( array $filters = array() ): int {
		[ $where, $params ] = $this->buildWhereClause( $filters );

		$sql = "SELECT COUNT(*) FROM {$this->table} a {$where}";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = empty( $params )
			? $this->wpdb->get_var( $sql )
			: $this->wpdb->get_var( $this->wpdb->prepare( $sql, ...$params ) );
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Construire la clause WHERE et la liste des paramètres pour `prepare()`.
	 *
	 * @param array<string, mixed> $filters
	 *
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function buildWhereClause( array $filters ): array {
		$conditions = array();
		$params     = array();

		if ( isset( $filters['user_id'] ) && $filters['user_id'] > 0 ) {
			$conditions[] = 'a.user_id = %d';
			$params[]     = (int) $filters['user_id'];
		}

		if ( ! empty( $filters['entity_type'] ) ) {
			$conditions[] = 'a.entity_type = %s';
			$params[]     = (string) $filters['entity_type'];
		}

		if ( isset( $filters['entity_id'] ) && $filters['entity_id'] > 0 ) {
			$conditions[] = 'a.entity_id = %d';
			$params[]     = (int) $filters['entity_id'];
		}

		if ( ! empty( $filters['action_prefix'] ) ) {
			$conditions[] = 'a.action LIKE %s';
			$params[]     = $this->wpdb->esc_like( (string) $filters['action_prefix'] ) . '%';
		}

		$where = empty( $conditions ) ? '' : 'WHERE ' . implode( ' AND ', $conditions );

		return array( $where, $params );
	}
}
