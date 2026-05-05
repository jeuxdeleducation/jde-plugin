<?php
/**
 * Service de rétention des données (purge 2 ans).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;
use JDE\Modules\Benevoles\Repositories\AssignationRepository;
use JDE\Modules\Benevoles\Repositories\DisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\EmailLogRepository;
use JDE\Modules\Benevoles\Repositories\InscriptionReponseRepository;
use JDE\Modules\Benevoles\Repositories\NotificationRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Repositories\PlageDisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;
use JDE\Modules\Benevoles\Repositories\QuartRepository;
use JDE\Modules\Benevoles\Repositories\SignatureRepository;
use JDE\Modules\Kiosques\Repositories\AuditRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Purge les données RH 2 ans après la date de fin d'un événement.
 *
 * Cible les éditions dont `META_DATE_FIN <= now() - 2 ans`. Pour
 * chacune, supprime en cascade : assignations → disponibilités →
 * réponses d'inscription → signatures → journal courriels → quarts →
 * postes → plages → personnes → puis le post du CPT lui-même.
 *
 * Les comptes WordPress créés à l'acceptation NE SONT PAS supprimés —
 * une personne peut être réutilisée d'un événement à l'autre.
 */
final class RetentionService {

	public const RETENTION_YEARS = 2;

	public function __construct(
		private readonly PersonneRepository $personnes,
		private readonly InscriptionReponseRepository $reponses,
		private readonly PosteRepository $postes,
		private readonly QuartRepository $quarts,
		private readonly PlageDisponibiliteRepository $plages,
		private readonly DisponibiliteRepository $disponibilites,
		private readonly AssignationRepository $assignations,
		private readonly NotificationRepository $notifications,
		private readonly SignatureRepository $signatures,
		private readonly EmailLogRepository $emailLogs,
		private readonly AuditRepository $audit,
	) {}

	/**
	 * @return array<int, array{evenement_rh_id: int, totaux: array<string, int>}>
	 */
	public function cleanup( ?DateTimeImmutable $now = null ): array {
		$now  ??= new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$cutoff = $now->modify( '-' . self::RETENTION_YEARS . ' years' );

		// Croisement des deux sources : meta du CPT (post non encore inscrit)
		// et personnes (date_fin_evenement dénormalisée).
		$ids = array_unique(
			array_merge(
				$this->idsFromCpt( $cutoff ),
				$this->personnes->findEvenementIdsExpired( $cutoff )
			)
		);

		$result = array();
		foreach ( $ids as $eventId ) {
			$totaux = array(
				'assignations'  => $this->assignations->deleteByEvenementId( $eventId ),
				'dispos'        => $this->disponibilites->deleteByEvenementId( $eventId ),
				'reponses'      => $this->reponses->deleteByPersonneIds(
					array_map(
						static fn ( $p ): int => (int) ( $p->id ?? 0 ),
						$this->personnes->findByEvenement( $eventId )
					)
				),
				'signatures'    => $this->signatures->deleteByEvenementId( $eventId ),
				'email_log'     => $this->emailLogs->deleteByEvenementId( $eventId ),
				'quarts'        => $this->quarts->deleteByEvenementId( $eventId ),
				'postes'        => $this->postes->deleteByEvenementId( $eventId ),
				'plages'        => $this->plages->deleteByEvenementId( $eventId ),
				'notifications' => $this->notifications->deleteByEvenementId( $eventId ),
				'personnes'     => $this->personnes->deleteByEvenementId( $eventId ),
			);

			wp_delete_post( $eventId, true );

			$this->audit->log(
				userId: 0,
				action: 'benevole.retention_purge',
				entityType: 'evenement_rh',
				entityId: $eventId,
				payload: $totaux
			);

			$result[] = array(
				'evenement_rh_id' => $eventId,
				'totaux'          => $totaux,
			);
		}

		return $result;
	}

	/**
	 * IDs des CPT dont la meta `date_fin` est antérieure ou égale au cutoff.
	 *
	 * @return int[]
	 */
	private function idsFromCpt( DateTimeImmutable $cutoff ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => EvenementRhPostType::SLUG,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => EvenementRhPostType::META_DATE_FIN,
						'value'   => $cutoff->format( 'Y-m-d' ),
						'compare' => '<=',
						'type'    => 'DATE',
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}
}
