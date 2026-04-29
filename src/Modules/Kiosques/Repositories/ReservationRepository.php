<?php
/**
 * Repository pour les réservations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Kiosques\Exceptions\KiosqueAlreadyReservedException;
use JDE\Modules\Kiosques\Models\Reservation;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Gestion atomique des réservations.
 *
 * La table `wp_jde_reservations` a une contrainte UNIQUE sur
 * `kiosque_id`. Si deux exposants tentent de réserver le même kiosque
 * simultanément, MySQL en accepte un et rejette l'autre avec l'erreur
 * 1062 (Duplicate entry). {@see create()} traduit cette erreur en
 * {@see KiosqueAlreadyReservedException} pour que le contrôleur REST
 * puisse retourner HTTP 409.
 */
class ReservationRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_reservations';
	}

	/**
	 * Créer une réservation atomiquement.
	 *
	 * @throws KiosqueAlreadyReservedException Si le kiosque est déjà pris.
	 */
	public function create(
		int $kiosqueId,
		int $exposantId,
		?int $creePar = null,
		?string $notesAdmin = null
	): Reservation {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$data = array(
			'kiosque_id'       => $kiosqueId,
			'exposant_id'      => $exposantId,
			'date_reservation' => $now->format( 'Y-m-d H:i:s' ),
			'cree_par'         => $creePar,
			'notes_admin'      => $notesAdmin,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->insert(
			$this->table,
			$data,
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			// Le code MySQL 1062 (Duplicate entry) est détecté par la
			// présence de « Duplicate entry » dans last_error. C'est
			// suffisant car la seule contrainte UNIQUE de la table porte
			// sur kiosque_id.
			if ( '' !== $this->wpdb->last_error
				&& str_contains( $this->wpdb->last_error, 'Duplicate entry' )
			) {
				throw new KiosqueAlreadyReservedException( $kiosqueId );
			}

			throw new \RuntimeException(
				'Échec de la création de réservation : ' . $this->wpdb->last_error
			);
		}

		return new Reservation(
			id: (int) $this->wpdb->insert_id,
			kiosqueId: $kiosqueId,
			exposantId: $exposantId,
			dateReservation: $now,
			creePar: $creePar,
			notesAdmin: $notesAdmin,
		);
	}

	/**
	 * Toutes les réservations d'un exposant donné.
	 *
	 * @return Reservation[]
	 */
	public function findByExposant( int $exposantId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE exposant_id = %d",
				$exposantId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Reservation => Reservation::fromRow( $row ), $rows );
	}

	/**
	 * Toutes les réservations d'un événement (jointure via kiosque_id).
	 *
	 * @return Reservation[]
	 */
	public function findByEvenement( int $evenementId ): array {
		$kiosquesTable = $this->wpdb->prefix . 'jde_kiosques';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT r.* FROM {$this->table} r
				INNER JOIN {$kiosquesTable} k ON k.id = r.kiosque_id
				WHERE k.evenement_id = %d",
				$evenementId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Reservation => Reservation::fromRow( $row ), $rows );
	}

	/**
	 * Récupérer la réservation associée à un kiosque (ou null s'il est libre).
	 */
	public function findByKiosque( int $kiosqueId ): ?Reservation {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE kiosque_id = %d LIMIT 1",
				$kiosqueId
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Reservation::fromRow( $row ) : null;
	}

	/**
	 * Compter les réservations d'un exposant.
	 */
	public function countByExposant( int $exposantId ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE exposant_id = %d",
				$exposantId
			)
		);
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Compter les réservations d'un événement (utile pour le verrouillage du plan).
	 */
	public function countByEvenement( int $evenementId ): int {
		$kiosquesTable = $this->wpdb->prefix . 'jde_kiosques';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} r
				INNER JOIN {$kiosquesTable} k ON k.id = r.kiosque_id
				WHERE k.evenement_id = %d",
				$evenementId
			)
		);
		// phpcs:enable

		return (int) $count;
	}

	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}
}
