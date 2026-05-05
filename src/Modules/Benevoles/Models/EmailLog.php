<?php
/**
 * Modèle de données : Journal d'un envoi de courriel.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Trace un envoi de courriel transactionnel ou ciblé.
 *
 * Le composer admin et les services métier (acceptation, refus,
 * assignation, etc.) consignent ici chaque envoi : modèle utilisé, sujet,
 * nombre de destinataires effectifs, qui a déclenché l'envoi et avec
 * quels filtres. Sert d'historique d'audit et de point de départ pour
 * reprendre un envoi qui aurait échoué.
 */
final readonly class EmailLog {

	/**
	 * @param array<string, mixed> $filters
	 */
	public function __construct(
		public ?int $id,
		public int $evenementRhId,
		public string $template,
		public string $subject,
		public int $recipientCount,
		public DateTimeImmutable $sentAt,
		public ?int $sentBy,
		public array $filters,
	) {}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		$filters = array();
		if ( isset( $row['filters_json'] ) && '' !== $row['filters_json'] ) {
			$decoded = json_decode( (string) $row['filters_json'], true );
			if ( is_array( $decoded ) ) {
				$filters = $decoded;
			}
		}

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			evenementRhId: (int) $row['evenement_rh_id'],
			template: (string) $row['template'],
			subject: (string) $row['subject'],
			recipientCount: (int) $row['recipient_count'],
			sentAt: new DateTimeImmutable( (string) $row['sent_at'], $tz ),
			sentBy: isset( $row['sent_by'] ) && null !== $row['sent_by'] ? (int) $row['sent_by'] : null,
			filters: $filters,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'              => $this->id,
			'evenement_rh_id' => $this->evenementRhId,
			'template'        => $this->template,
			'subject'         => $this->subject,
			'recipient_count' => $this->recipientCount,
			'sent_at'         => $this->sentAt->format( 'c' ),
			'sent_by'         => $this->sentBy,
			'filters'         => $this->filters,
		);
	}
}
