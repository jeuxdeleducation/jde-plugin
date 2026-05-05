<?php
/**
 * Repository pour les personnes (bénévoles, jurys, arbitres).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use DateTimeImmutable;
use JDE\Modules\Benevoles\Models\Personne;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des personnes inscrites à une édition RH.
 */
class PersonneRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_rh_personnes';
	}

	public function findById( int $id ): ?Personne {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Personne::fromRow( $row ) : null;
	}

	public function findByWpUserId( int $wpUserId ): ?Personne {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE wp_user_id = %d ORDER BY date_inscription DESC LIMIT 1",
				$wpUserId
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Personne::fromRow( $row ) : null;
	}

	/**
	 * Vérifier qu'une adresse n'a pas déjà été utilisée pour le même
	 * événement RH (peu importe le rôle ciblé).
	 */
	public function existsForEvenement( int $evenementRhId, string $courriel ): bool {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$found = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT 1 FROM {$this->table} WHERE evenement_rh_id = %d AND LOWER(courriel) = LOWER(%s) LIMIT 1",
				$evenementRhId,
				$courriel
			)
		);
		// phpcs:enable

		return null !== $found;
	}

	/**
	 * Lister les personnes d'un événement RH avec filtres optionnels.
	 *
	 * @param array{statut?: string, type_role?: string} $filters
	 * @return Personne[]
	 */
	public function findByEvenement( int $evenementRhId, array $filters = array() ): array {
		$where  = array( 'evenement_rh_id = %d' );
		$params = array( $evenementRhId );

		if ( ! empty( $filters['statut'] ) ) {
			$where[]  = 'statut = %s';
			$params[] = (string) $filters['statut'];
		}

		if ( ! empty( $filters['type_role'] ) ) {
			$where[]  = 'type_role = %s';
			$params[] = (string) $filters['type_role'];
		}

		$whereSql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$whereSql} ORDER BY date_inscription DESC",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Personne => Personne::fromRow( $row ), $rows );
	}

	/**
	 * IDs des événements dont la date de fin est antérieure ou égale à la
	 * borne fournie. Utilisé par la rétention (purge 2 ans après la fin).
	 *
	 * @return int[]
	 */
	public function findEvenementIdsExpired( DateTimeImmutable $cutoff ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT evenement_rh_id FROM {$this->table} WHERE date_fin_evenement IS NOT NULL AND date_fin_evenement <= %s",
				$cutoff->format( 'Y-m-d' )
			)
		);
		// phpcs:enable

		return is_array( $rows ) ? array_map( 'intval', $rows ) : array();
	}

	public function save( Personne $personne ): Personne {
		if ( null === $personne->id ) {
			$data = array(
				'evenement_rh_id'    => $personne->evenementRhId,
				'type_role'          => $personne->typeRole,
				'prenom'             => $personne->prenom,
				'nom'                => $personne->nom,
				'courriel'           => $personne->courriel,
				'telephone'          => $personne->telephone,
				'statut'             => $personne->statut,
				'wp_user_id'         => $personne->wpUserId,
				'onedrive_url'       => $personne->onedriveUrl,
				'decide_par'         => $personne->decidePar,
				'date_inscription'   => $personne->dateInscription->format( 'Y-m-d H:i:s' ),
				'date_decision'      => $personne->dateDecision?->format( 'Y-m-d H:i:s' ),
				'date_fin_evenement' => $personne->dateFinEvenement,
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->insert( $this->table, $data );

			return Personne::fromRow(
				array_merge( array( 'id' => $this->wpdb->insert_id ), $data )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table,
			array(
				'type_role'    => $personne->typeRole,
				'prenom'       => $personne->prenom,
				'nom'          => $personne->nom,
				'courriel'     => $personne->courriel,
				'telephone'    => $personne->telephone,
				'statut'       => $personne->statut,
				'wp_user_id'   => $personne->wpUserId,
				'onedrive_url' => $personne->onedriveUrl,
				'decide_par'   => $personne->decidePar,
			),
			array( 'id' => $personne->id )
		);

		return $personne;
	}

	public function updateStatut(
		int $id,
		string $statut,
		?int $decidePar,
		?DateTimeImmutable $dateDecision,
		?int $wpUserId = null
	): bool {
		$data = array(
			'statut'        => $statut,
			'decide_par'    => $decidePar,
			'date_decision' => $dateDecision?->format( 'Y-m-d H:i:s' ),
		);
		if ( null !== $wpUserId ) {
			$data['wp_user_id'] = $wpUserId;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );

		return false !== $result;
	}

	public function updateTelephone( int $id, ?string $telephone ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update(
			$this->table,
			array( 'telephone' => $telephone ),
			array( 'id' => $id )
		);

		return false !== $result;
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
