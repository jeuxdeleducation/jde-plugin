<?php
/**
 * Repository pour les signatures électroniques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use JDE\Modules\Benevoles\Models\Signature;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des signatures de documents (entente, lettre).
 */
class SignatureRepository {

	private string $table;
	private string $personnesTable;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table          = $wpdb->prefix . 'jde_rh_signatures';
		$this->personnesTable = $wpdb->prefix . 'jde_rh_personnes';
	}

	public function hasSigned( int $personneId, string $typeDocument ): bool {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$found = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT 1 FROM {$this->table} WHERE personne_id = %d AND type_document = %s LIMIT 1",
				$personneId,
				$typeDocument
			)
		);
		// phpcs:enable

		return null !== $found;
	}

	/**
	 * @return Signature[]
	 */
	public function findByPersonne( int $personneId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE personne_id = %d ORDER BY signed_at",
				$personneId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Signature => Signature::fromRow( $row ), $rows );
	}

	/**
	 * Insérer une signature de manière idempotente. Si la personne avait
	 * déjà signé ce type de document, l'opération est sans effet (la
	 * contrainte UNIQUE bloquerait sinon avec une erreur).
	 */
	public function save( Signature $signature ): Signature {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT IGNORE INTO {$this->table}
				(personne_id, type_document, signed_at, ip_address, user_agent)
				VALUES (%d, %s, %s, %s, %s)",
				$signature->personneId,
				$signature->typeDocument,
				$signature->signedAt->format( 'Y-m-d H:i:s' ),
				$signature->ipAddress,
				$signature->userAgent
			)
		);
		// phpcs:enable

		return $signature;
	}

	public function deleteByEvenementId( int $evenementRhId ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE s FROM {$this->table} s
				INNER JOIN {$this->personnesTable} p ON p.id = s.personne_id
				WHERE p.evenement_rh_id = %d",
				$evenementRhId
			)
		);
		// phpcs:enable

		return (int) ( false === $result ? 0 : $result );
	}
}
