<?php
/**
 * Modèle de données : Reservation.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Représente la réservation d'un kiosque par un exposant.
 *
 * La table `wp_jde_reservations` a une contrainte UNIQUE sur
 * `kiosque_id` qui garantit atomiquement qu'un kiosque ne peut être
 * réservé que par un seul exposant. {@see ReservationRepository::create}.
 */
final readonly class Reservation {

	/**
	 * @param int|null $id        Null si pas encore persisté.
	 * @param int|null $creePar   Null = self-serve par l'exposant ; sinon user_id de l'admin.
	 */
	public function __construct(
		public ?int $id,
		public int $kiosqueId,
		public int $exposantId,
		public DateTimeImmutable $dateReservation,
		public ?int $creePar,
		public ?string $notesAdmin,
	) {}

	/**
	 * Construire à partir d'une ligne brute de wpdb.
	 *
	 * @param array<string, mixed> $row Ligne brute.
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			kiosqueId: (int) $row['kiosque_id'],
			exposantId: (int) $row['exposant_id'],
			dateReservation: new DateTimeImmutable( (string) $row['date_reservation'], $tz ),
			creePar: isset( $row['cree_par'] ) && null !== $row['cree_par']
				? (int) $row['cree_par']
				: null,
			notesAdmin: isset( $row['notes_admin'] ) && '' !== $row['notes_admin']
				? (string) $row['notes_admin']
				: null,
		);
	}

	/**
	 * Sérialisation pour usage public/REST.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'               => $this->id,
			'kiosque_id'       => $this->kiosqueId,
			'exposant_id'      => $this->exposantId,
			'date_reservation' => $this->dateReservation->format( 'c' ),
		);
	}
}
