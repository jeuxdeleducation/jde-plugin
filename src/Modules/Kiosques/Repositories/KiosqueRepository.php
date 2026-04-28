<?php
/**
 * Repository pour les kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Kiosques\Models\Kiosque;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des kiosques associés à un événement.
 *
 * Utilise les méthodes typées de `wpdb` (insert/update/delete) qui
 * appellent `prepare()` en interne pour échapper les valeurs. Les
 * SELECT utilisent explicitement `prepare()` avec placeholders.
 */
class KiosqueRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_kiosques';
	}

	/**
	 * Tous les kiosques d'un événement, triés par numéro.
	 *
	 * @return Kiosque[]
	 */
	public function findByEvenement( int $evenementId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE evenement_id = %d ORDER BY numero ASC",
				$evenementId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Kiosque => Kiosque::fromRow( $row ), $rows );
	}

	/**
	 * Trouver un kiosque par son identifiant.
	 */
	public function findById( int $id ): ?Kiosque {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Kiosque::fromRow( $row ) : null;
	}

	/**
	 * Insérer ou mettre à jour un kiosque.
	 *
	 * Retourne le modèle persisté (avec id renseigné si insert).
	 */
	public function save( Kiosque $kiosque ): Kiosque {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$data = array(
			'evenement_id'      => $kiosque->evenementId,
			'numero'            => $kiosque->numero,
			'pos_x'             => $kiosque->posX,
			'pos_y'             => $kiosque->posY,
			'largeur'           => $kiosque->largeur,
			'hauteur'           => $kiosque->hauteur,
			'dimensions_texte'  => $kiosque->dimensionsTexte,
			'notes'             => $kiosque->notes,
			'statut'            => $kiosque->statut,
			'date_modification' => $now->format( 'Y-m-d H:i:s' ),
		);

		$format = array( '%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s' );

		if ( null === $kiosque->id ) {
			$data['date_creation'] = $now->format( 'Y-m-d H:i:s' );
			$format[]              = '%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->insert( $this->table, $data, $format );
			$id = (int) $this->wpdb->insert_id;

			return new Kiosque(
				id: $id,
				evenementId: $kiosque->evenementId,
				numero: $kiosque->numero,
				posX: $kiosque->posX,
				posY: $kiosque->posY,
				largeur: $kiosque->largeur,
				hauteur: $kiosque->hauteur,
				dimensionsTexte: $kiosque->dimensionsTexte,
				notes: $kiosque->notes,
				statut: $kiosque->statut,
				dateCreation: $now,
				dateModification: $now,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table,
			$data,
			array( 'id' => $kiosque->id ),
			$format,
			array( '%d' )
		);

		return new Kiosque(
			id: $kiosque->id,
			evenementId: $kiosque->evenementId,
			numero: $kiosque->numero,
			posX: $kiosque->posX,
			posY: $kiosque->posY,
			largeur: $kiosque->largeur,
			hauteur: $kiosque->hauteur,
			dimensionsTexte: $kiosque->dimensionsTexte,
			notes: $kiosque->notes,
			statut: $kiosque->statut,
			dateCreation: $kiosque->dateCreation,
			dateModification: $now,
		);
	}

	/**
	 * Supprimer un kiosque par identifiant.
	 */
	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result && $result > 0;
	}

	/**
	 * Supprimer tous les kiosques d'un événement (ex. à la suppression de l'événement).
	 *
	 * @return int Nombre de lignes supprimées.
	 */
	public function deleteByEvenement( int $evenementId ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$this->table,
			array( 'evenement_id' => $evenementId ),
			array( '%d' )
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Compter les kiosques d'un événement.
	 */
	public function countByEvenement( int $evenementId ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE evenement_id = %d",
				$evenementId
			)
		);
		// phpcs:enable

		return (int) $count;
	}
}
