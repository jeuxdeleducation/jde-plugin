<?php
/**
 * Repository pour les réponses d'inscription.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Repositories;

use JDE\Modules\Benevoles\Models\InscriptionReponse;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des réponses libres associées à une personne.
 */
class InscriptionReponseRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_rh_inscription_reponses';
	}

	/**
	 * Insérer en lot les réponses d'une personne.
	 *
	 * @param InscriptionReponse[] $reponses
	 */
	public function saveBatch( int $personneId, array $reponses ): void {
		foreach ( $reponses as $reponse ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->insert(
				$this->table,
				array(
					'personne_id' => $personneId,
					'field_key'   => $reponse->fieldKey,
					'field_label' => $reponse->fieldLabel,
					'field_value' => $reponse->fieldValue,
				)
			);
		}
	}

	/**
	 * @return InscriptionReponse[]
	 */
	public function findByPersonneId( int $personneId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE personne_id = %d ORDER BY id ASC",
				$personneId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn ( array $row ): InscriptionReponse => InscriptionReponse::fromRow( $row ),
			$rows
		);
	}

	public function deleteByPersonneIds( array $personneIds ): int {
		if ( array() === $personneIds ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $personneIds ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE personne_id IN ({$placeholders})",
				...$personneIds
			)
		);
		// phpcs:enable

		return (int) ( false === $result ? 0 : $result );
	}
}
