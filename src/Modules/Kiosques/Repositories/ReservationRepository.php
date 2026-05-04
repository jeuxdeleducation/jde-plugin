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
use JDE\Modules\Kiosques\Models\ReservationDetail;
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
	 * Compter les réservations groupées par exposant pour un événement.
	 *
	 * Retourne un tableau associatif `exposant_id => count`. Les exposants
	 * sans réservation ne figurent pas dans le résultat (le caller doit
	 * traiter les clés manquantes comme 0). Une seule requête pour éviter
	 * le N+1 dans la liste des exposants admin.
	 *
	 * @return array<int, int>
	 */
	public function countByExposantsForEvenement( int $evenementId ): array {
		$kiosquesTable = $this->wpdb->prefix . 'jde_kiosques';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT r.exposant_id, COUNT(*) AS nb
				FROM {$this->table} r
				INNER JOIN {$kiosquesTable} k ON k.id = r.kiosque_id
				WHERE k.evenement_id = %d
				GROUP BY r.exposant_id",
				$evenementId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row['exposant_id'] ] = (int) $row['nb'];
		}

		return $counts;
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

	/**
	 * Récupérer une réservation par son identifiant.
	 */
	public function findById( int $id ): ?Reservation {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Reservation::fromRow( $row ) : null;
	}

	/**
	 * Mettre à jour les champs admin d'une réservation existante (notes uniquement).
	 *
	 * Pour déplacer une réservation vers un autre kiosque, le service
	 * effectue un delete + create afin de respecter la contrainte UNIQUE
	 * sur kiosque_id de manière atomique.
	 */
	public function updateNotes( int $id, ?string $notesAdmin ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update(
			$this->table,
			array( 'notes_admin' => $notesAdmin ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		return false !== $result;
	}

	/**
	 * Lister les réservations d'un événement avec les jointures nécessaires
	 * aux écrans admin (numéro de kiosque, nom d'entreprise, login admin).
	 *
	 * Ordre : du plus récent au plus ancien.
	 *
	 * @return ReservationDetail[]
	 */
	public function findDetailedByEvenement( int $evenementId ): array {
		$kiosques  = $this->wpdb->prefix . 'jde_kiosques';
		$exposants = $this->wpdb->prefix . 'jde_exposants';
		$users     = $this->wpdb->users;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT
					r.id,
					r.kiosque_id,
					r.exposant_id,
					r.date_reservation,
					r.cree_par,
					r.notes_admin,
					k.numero AS kiosque_numero,
					e.nom_entreprise,
					e.code_acces,
					u.user_login AS cree_par_login
				FROM {$this->table} r
				INNER JOIN {$kiosques} k ON k.id = r.kiosque_id
				INNER JOIN {$exposants} e ON e.id = r.exposant_id
				LEFT JOIN {$users} u ON u.ID = r.cree_par
				WHERE k.evenement_id = %d
				ORDER BY r.date_reservation DESC",
				$evenementId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn ( array $row ): ReservationDetail => ReservationDetail::fromRow( $row ),
			$rows
		);
	}
}
