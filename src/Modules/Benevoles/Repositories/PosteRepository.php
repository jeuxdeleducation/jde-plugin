<?php
/**
 * Repository pour les postes (blocs d'implication).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use JDE\Modules\Benevoles\Models\Poste;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des postes d'une édition RH.
 */
class PosteRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_rh_postes';
	}

	public function findById( int $id ): ?Poste {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Poste::fromRow( $row ) : null;
	}

	/**
	 * @return Poste[]
	 */
	public function findByEvenement( int $evenementRhId, ?string $typeRole = null ): array {
		if ( null === $typeRole ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE evenement_rh_id = %d ORDER BY type_role, nom",
					$evenementRhId
				),
				ARRAY_A
			);
			// phpcs:enable
		} else {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE evenement_rh_id = %d AND type_role = %s ORDER BY nom",
					$evenementRhId,
					$typeRole
				),
				ARRAY_A
			);
			// phpcs:enable
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Poste => Poste::fromRow( $row ), $rows );
	}

	public function save( Poste $poste ): Poste {
		$data = array(
			'evenement_rh_id'       => $poste->evenementRhId,
			'nom'                   => $poste->nom,
			'description'           => $poste->description,
			'lieu'                  => $poste->lieu,
			'nb_personnes_souhaite' => $poste->nbPersonnesSouhaite,
			'responsable_user_id'   => $poste->responsableUserId,
			'type_role'             => $poste->typeRole,
		);

		if ( null === $poste->id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->insert( $this->table, $data );

			return Poste::fromRow( array_merge( array( 'id' => $this->wpdb->insert_id ), $data ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table, $data, array( 'id' => $poste->id ) );

		return $poste;
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
