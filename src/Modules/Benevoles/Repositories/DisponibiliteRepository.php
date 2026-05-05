<?php
/**
 * Repository pour les disponibilités cochées par les personnes.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use JDE\Modules\Benevoles\Models\Disponibilite;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance du lien plusieurs-à-plusieurs personne ↔ plage.
 */
class DisponibiliteRepository {

	private string $table;
	private string $personnesTable;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table          = $wpdb->prefix . 'jde_rh_disponibilites';
		$this->personnesTable = $wpdb->prefix . 'jde_rh_personnes';
	}

	/**
	 * Insérer en lot les plages cochées par une personne.
	 *
	 * Idempotent grâce à la contrainte UNIQUE (personne_id, plage_id) :
	 * un INSERT IGNORE évite les erreurs sur réinsertion (utile lorsqu'un
	 * candidat resoumet le formulaire ou modifie ses dispos).
	 *
	 * @param int[] $plageIds
	 */
	public function saveBatch( int $personneId, array $plageIds ): void {
		foreach ( $plageIds as $plageId ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->query(
				$this->wpdb->prepare(
					"INSERT IGNORE INTO {$this->table} (personne_id, plage_id) VALUES (%d, %d)",
					$personneId,
					$plageId
				)
			);
			// phpcs:enable
		}
	}

	/**
	 * @return Disponibilite[]
	 */
	public function findByPersonneId( int $personneId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE personne_id = %d",
				$personneId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Disponibilite => Disponibilite::fromRow( $row ), $rows );
	}

	/**
	 * Liste des IDs de plages cochées par une personne (raccourci).
	 *
	 * @return int[]
	 */
	public function findPlageIdsByPersonne( int $personneId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$col = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT plage_id FROM {$this->table} WHERE personne_id = %d",
				$personneId
			)
		);
		// phpcs:enable

		return is_array( $col ) ? array_map( 'intval', $col ) : array();
	}

	public function deleteByPersonneId( int $personneId ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$this->table,
			array( 'personne_id' => $personneId ),
			array( '%d' )
		);

		return (int) ( false === $result ? 0 : $result );
	}

	public function deleteByEvenementId( int $evenementRhId ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE d FROM {$this->table} d
				INNER JOIN {$this->personnesTable} p ON p.id = d.personne_id
				WHERE p.evenement_rh_id = %d",
				$evenementRhId
			)
		);
		// phpcs:enable

		return (int) ( false === $result ? 0 : $result );
	}
}
