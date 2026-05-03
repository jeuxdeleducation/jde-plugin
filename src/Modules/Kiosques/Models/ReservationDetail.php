<?php
/**
 * Vue enrichie d'une réservation pour les écrans admin.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Réservation jointe avec les infos exposant et kiosque, plus le login
 * de l'utilisateur admin qui l'a créée (le cas échéant).
 *
 * Distinct de {@see Reservation} pour exposer les données nécessaires
 * aux vues admin (table, plan annoté, export CSV) sans surcharger le
 * modèle de base.
 */
final readonly class ReservationDetail {

	public function __construct(
		public int $id,
		public int $kiosqueId,
		public string $kiosqueNumero,
		public int $exposantId,
		public string $nomEntreprise,
		public string $codeAcces,
		public DateTimeImmutable $dateReservation,
		public ?int $creePar,
		public ?string $creeParLogin,
		public ?string $notesAdmin,
	) {}

	/**
	 * Construire à partir d'une ligne jointe par wpdb (dans
	 * ReservationRepository::findDetailedByEvenement).
	 *
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		return new self(
			id: (int) $row['id'],
			kiosqueId: (int) $row['kiosque_id'],
			kiosqueNumero: (string) ( $row['kiosque_numero'] ?? '' ),
			exposantId: (int) $row['exposant_id'],
			nomEntreprise: (string) ( $row['nom_entreprise'] ?? '' ),
			codeAcces: (string) ( $row['code_acces'] ?? '' ),
			dateReservation: new DateTimeImmutable( (string) $row['date_reservation'], $tz ),
			creePar: isset( $row['cree_par'] ) && null !== $row['cree_par']
				? (int) $row['cree_par']
				: null,
			creeParLogin: isset( $row['cree_par_login'] ) && '' !== $row['cree_par_login']
				? (string) $row['cree_par_login']
				: null,
			notesAdmin: isset( $row['notes_admin'] ) && '' !== $row['notes_admin']
				? (string) $row['notes_admin']
				: null,
		);
	}

	/**
	 * Sérialisation pour le REST.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'               => $this->id,
			'kiosque_id'       => $this->kiosqueId,
			'kiosque_numero'   => $this->kiosqueNumero,
			'exposant_id'      => $this->exposantId,
			'nom_entreprise'   => $this->nomEntreprise,
			'code_acces'       => $this->codeAcces,
			'date_reservation' => $this->dateReservation->format( 'c' ),
			'source'           => null === $this->creePar ? 'exposant' : 'admin',
			'cree_par_login'   => $this->creeParLogin,
			'notes_admin'      => $this->notesAdmin,
		);
	}
}
