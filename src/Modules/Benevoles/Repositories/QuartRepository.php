<?php
/**
 * Repository pour les quarts.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use JDE\Modules\Benevoles\Models\Quart;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des quarts (créneaux d'un poste).
 */
class QuartRepository {

	private string $table;
	private string $postesTable;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table       = $wpdb->prefix . 'jde_rh_quarts';
		$this->postesTable = $wpdb->prefix . 'jde_rh_postes';
	}

	public function findById( int $id ): ?Quart {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Quart::fromRow( $row ) : null;
	}

	/**
	 * @return Quart[]
	 */
	public function findByPoste( int $posteId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE poste_id = %d ORDER BY date_debut",
				$posteId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Quart => Quart::fromRow( $row ), $rows );
	}

	/**
	 * Tous les quarts d'un événement RH (joint via les postes).
	 *
	 * @return Quart[]
	 */
	public function findByEvenement( int $evenementRhId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT q.*
				FROM {$this->table} q
				INNER JOIN {$this->postesTable} p ON p.id = q.poste_id
				WHERE p.evenement_rh_id = %d
				ORDER BY q.date_debut",
				$evenementRhId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Quart => Quart::fromRow( $row ), $rows );
	}

	public function save( Quart $quart ): Quart {
		$data = array(
			'poste_id'   => $quart->posteId,
			'date_debut' => $quart->dateDebut->format( 'Y-m-d H:i:s' ),
			'date_fin'   => $quart->dateFin->format( 'Y-m-d H:i:s' ),
		);

		if ( null === $quart->id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->insert( $this->table, $data );

			return Quart::fromRow( array_merge( array( 'id' => $this->wpdb->insert_id ), $data ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table, $data, array( 'id' => $quart->id ) );

		return $quart;
	}

	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result && $result > 0;
	}

	public function deleteByEvenementId( int $evenementRhId ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE q FROM {$this->table} q
				INNER JOIN {$this->postesTable} p ON p.id = q.poste_id
				WHERE p.evenement_rh_id = %d",
				$evenementRhId
			)
		);
		// phpcs:enable

		return (int) ( false === $result ? 0 : $result );
	}
}
