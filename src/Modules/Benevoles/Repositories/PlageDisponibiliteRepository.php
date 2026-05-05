<?php
/**
 * Repository pour les plages de disponibilité.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use JDE\Modules\Benevoles\Models\PlageDisponibilite;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des plages de disponibilité prédéfinies.
 */
class PlageDisponibiliteRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_rh_plages_dispo';
	}

	public function findById( int $id ): ?PlageDisponibilite {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? PlageDisponibilite::fromRow( $row ) : null;
	}

	/**
	 * @return PlageDisponibilite[]
	 */
	public function findByEvenement( int $evenementRhId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE evenement_rh_id = %d ORDER BY ordre, date_debut",
				$evenementRhId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn ( array $row ): PlageDisponibilite => PlageDisponibilite::fromRow( $row ),
			$rows
		);
	}

	public function save( PlageDisponibilite $plage ): PlageDisponibilite {
		$data = array(
			'evenement_rh_id' => $plage->evenementRhId,
			'libelle'         => $plage->libelle,
			'date_debut'      => $plage->dateDebut->format( 'Y-m-d H:i:s' ),
			'date_fin'        => $plage->dateFin->format( 'Y-m-d H:i:s' ),
			'ordre'           => $plage->ordre,
		);

		if ( null === $plage->id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->insert( $this->table, $data );

			return PlageDisponibilite::fromRow( array_merge( array( 'id' => $this->wpdb->insert_id ), $data ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table, $data, array( 'id' => $plage->id ) );

		return $plage;
	}

	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result && $result > 0;
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
