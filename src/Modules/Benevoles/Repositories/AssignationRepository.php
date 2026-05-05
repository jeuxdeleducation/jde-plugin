<?php
/**
 * Repository pour les assignations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use DateTimeImmutable;
use JDE\Modules\Benevoles\Models\Assignation;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des assignations personne ↔ quart.
 *
 * En plus du CRUD basique, expose les requêtes utilisées par les
 * services de détection de conflit (chevauchement personne, sur-effectif
 * de poste).
 */
class AssignationRepository {

	private string $table;
	private string $quartsTable;
	private string $postesTable;
	private string $personnesTable;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table          = $wpdb->prefix . 'jde_rh_assignations';
		$this->quartsTable    = $wpdb->prefix . 'jde_rh_quarts';
		$this->postesTable    = $wpdb->prefix . 'jde_rh_postes';
		$this->personnesTable = $wpdb->prefix . 'jde_rh_personnes';
	}

	public function findById( int $id ): ?Assignation {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Assignation::fromRow( $row ) : null;
	}

	/**
	 * @return Assignation[]
	 */
	public function findByPersonne( int $personneId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE personne_id = %d ORDER BY date_creation DESC",
				$personneId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Assignation => Assignation::fromRow( $row ), $rows );
	}

	/**
	 * @return Assignation[]
	 */
	public function findByQuart( int $quartId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE quart_id = %d ORDER BY date_creation",
				$quartId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Assignation => Assignation::fromRow( $row ), $rows );
	}

	/**
	 * Compter les assignations acceptées d'un quart (pour détecter le sur-effectif).
	 */
	public function countAcceptedByQuart( int $quartId ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE quart_id = %d AND statut = %s",
				$quartId,
				Assignation::STATUT_ACCEPTEE
			)
		);
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Trouver les assignations acceptées d'une personne qui chevauchent un quart cible.
	 *
	 * Le chevauchement est défini par : `quart.dateDebut < cible.dateFin`
	 * ET `quart.dateFin > cible.dateDebut`. On exclut explicitement
	 * `excludeQuartId` pour ignorer le quart cible lui-même.
	 *
	 * @return Assignation[]
	 */
	public function findOverlappingForPersonne( int $personneId, int $quartIdCible ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT a.*
				FROM {$this->table} a
				INNER JOIN {$this->quartsTable} q ON q.id = a.quart_id
				INNER JOIN {$this->quartsTable} qcible ON qcible.id = %d
				WHERE a.personne_id = %d
				  AND a.statut = %s
				  AND a.quart_id <> %d
				  AND q.date_debut < qcible.date_fin
				  AND q.date_fin > qcible.date_debut",
				$quartIdCible,
				$personneId,
				Assignation::STATUT_ACCEPTEE,
				$quartIdCible
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Assignation => Assignation::fromRow( $row ), $rows );
	}

	public function save( Assignation $assignation ): Assignation {
		$data = array(
			'personne_id'   => $assignation->personneId,
			'quart_id'      => $assignation->quartId,
			'statut'        => $assignation->statut,
			'date_creation' => $assignation->dateCreation->format( 'Y-m-d H:i:s' ),
			'date_decision' => $assignation->dateDecision?->format( 'Y-m-d H:i:s' ),
			'cree_par'      => $assignation->creePar,
			'motif_refus'   => $assignation->motifRefus,
		);

		if ( null === $assignation->id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->insert( $this->table, $data );

			return Assignation::fromRow( array_merge( array( 'id' => $this->wpdb->insert_id ), $data ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table, $data, array( 'id' => $assignation->id ) );

		return $assignation;
	}

	public function updateStatut(
		int $id,
		string $statut,
		DateTimeImmutable $dateDecision,
		?string $motifRefus = null
	): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update(
			$this->table,
			array(
				'statut'        => $statut,
				'date_decision' => $dateDecision->format( 'Y-m-d H:i:s' ),
				'motif_refus'   => $motifRefus,
			),
			array( 'id' => $id )
		);

		return false !== $result;
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
				"DELETE a FROM {$this->table} a
				INNER JOIN {$this->personnesTable} pe ON pe.id = a.personne_id
				WHERE pe.evenement_rh_id = %d",
				$evenementRhId
			)
		);
		// phpcs:enable

		return (int) ( false === $result ? 0 : $result );
	}
}
