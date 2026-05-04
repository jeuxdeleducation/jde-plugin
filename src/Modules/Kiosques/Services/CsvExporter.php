<?php
/**
 * Export CSV des réservations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

use JDE\Modules\Kiosques\Models\ReservationDetail;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Stream un fichier CSV des réservations directement sur `php://output`.
 *
 * Format : UTF-8 avec BOM (`\xEF\xBB\xBF`) pour qu'Excel sur Windows
 * reconnaisse l'encodage et affiche correctement les accents.
 *
 * Le contrôleur appelant doit terminer avec `exit;` après cet appel
 * pour empêcher WordPress d'ajouter du contenu après le CSV.
 */
final class CsvExporter {

	public function __construct( private readonly ReservationRepository $reservations ) {}

	/**
	 * Streamer le CSV des réservations d'un événement.
	 *
	 * @param int    $evenementId Identifiant de l'événement.
	 * @param string $eventTitle  Titre de l'événement (pour le nom de fichier).
	 */
	public function streamReservations( int $evenementId, string $eventTitle ): void {
		$slug     = sanitize_title( $eventTitle );
		$today    = wp_date( 'Y-m-d' );
		$filename = sprintf( 'reservations-%s-%s.csv', '' !== $slug ? $slug : 'evenement', $today );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// BOM UTF-8 pour Excel.
		echo "\xEF\xBB\xBF";

		// php://output est un stream, pas un fichier — WP_Filesystem n'est pas applicable.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			return;
		}

		// En-têtes de colonnes.
		fputcsv(
			$out,
			array(
				'Entreprise',
				'Code exposant',
				'Numéro kiosque',
				'Date de réservation',
				'Source',
				'Notes admin',
			)
		);

		$reservations = $this->reservations->findDetailedByEvenement( $evenementId );

		$wpTimezone = wp_timezone();
		foreach ( $reservations as $reservation ) {
			fputcsv( $out, $this->row( $reservation, $wpTimezone ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $out );
	}

	/**
	 * Construire une ligne CSV à partir d'une réservation détaillée.
	 *
	 * @return array<int, string>
	 */
	private function row( ReservationDetail $reservation, \DateTimeZone $tz ): array {
		$localDate = $reservation->dateReservation->setTimezone( $tz );

		$source = null === $reservation->creePar
			? 'Auto'
			: ( null !== $reservation->creeParLogin
				? sprintf( 'Admin (%s)', $reservation->creeParLogin )
				: 'Admin' );

		return array(
			$reservation->nomEntreprise,
			$reservation->codeAcces,
			$reservation->kiosqueNumero,
			wp_date( 'Y-m-d H:i', $localDate->getTimestamp() ),
			$source,
			$reservation->notesAdmin ?? '',
		);
	}
}
