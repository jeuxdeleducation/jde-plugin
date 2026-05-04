<?php
/**
 * Construit le payload PublicState retourné aux exposants.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

use JDE\Modules\Kiosques\Models\Exposant;
use JDE\Modules\Kiosques\Models\Kiosque;
use JDE\Modules\Kiosques\Models\Reservation;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Repositories\KiosqueRepository;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Réunit les données nécessaires à l'affichage de la page publique :
 * infos exposant, événement, plan, kiosques, réservations existantes
 * (avec ou sans noms d'entreprises selon le paramètre événement),
 * et nombre de kiosques restants pour l'exposant courant.
 *
 * Utilisé par AuthController et ReservationController pour assurer la
 * cohérence du contrat avec le client.
 */
class PublicStateBuilder {

	public function __construct(
		private readonly KiosqueRepository $kiosques,
		private readonly ReservationRepository $reservations,
		private readonly ExposantRepository $exposants,
	) {}

	/**
	 * Construire le payload pour un exposant authentifié.
	 *
	 * @return array<string, mixed>|null Null si l'événement n'existe plus.
	 */
	public function build( Exposant $exposant ): ?array {
		$eventPost = get_post( $exposant->evenementId );
		if ( ! $eventPost ) {
			return null;
		}

		$kiosques        = $this->kiosques->findByEvenement( $exposant->evenementId );
		$allReservations = $this->reservations->findByEvenement( $exposant->evenementId );

		$afficherNoms     = (bool) get_post_meta(
			$exposant->evenementId,
			EvenementPostType::META_AFFICHER_NOMS,
			true
		);
		$planAttachmentId = (int) get_post_meta(
			$exposant->evenementId,
			EvenementPostType::META_PLAN_ATTACHMENT_ID,
			true
		);
		$planUrlRaw       = $planAttachmentId > 0
			? wp_get_attachment_image_url( $planAttachmentId, 'full' )
			: false;
		$planUrl          = ( false === $planUrlRaw || null === $planUrlRaw ) ? null : $planUrlRaw;
		$planVerrouille   = (bool) get_post_meta(
			$exposant->evenementId,
			EvenementPostType::META_PLAN_VERROUILLE,
			true
		);

		$companyNames = array();
		if ( $afficherNoms ) {
			foreach ( $allReservations as $reservation ) {
				if ( ! isset( $companyNames[ $reservation->exposantId ] ) ) {
					$other = $this->exposants->findById( $reservation->exposantId );
					if ( null !== $other ) {
						$companyNames[ $reservation->exposantId ] = $other->nomEntreprise;
					}
				}
			}
		}

		$mesReservations = array();
		foreach ( $allReservations as $reservation ) {
			if ( $reservation->exposantId === $exposant->id ) {
				$mesReservations[] = $reservation;
			}
		}

		return array(
			'exposant'          => array(
				'id'              => $exposant->id,
				'evenement_id'    => $exposant->evenementId,
				'nom_entreprise'  => $exposant->nomEntreprise,
				'nb_kiosques_max' => $exposant->nbKiosquesMax,
			),
			'evenement'         => array(
				'id'                        => $exposant->evenementId,
				'titre'                     => $eventPost->post_title,
				// `the_content` applique les filtres standards (paragraphes,
				// shortcodes, embeds…) — ce qui est OK puisque seul un admin
				// du site peut écrire la description de l'événement.
				'description_html'          => '' !== $eventPost->post_content
					? (string) apply_filters( 'the_content', $eventPost->post_content )
					: '',
				'plan_url'                  => $planUrl,
				'plan_verrouille'           => $planVerrouille,
				'afficher_noms_entreprises' => $afficherNoms,
			),
			'kiosques'          => array_map( static fn ( Kiosque $k ): array => $k->toArray(), $kiosques ),
			'reservations'      => array_map(
				static function ( Reservation $r ) use ( $afficherNoms, $companyNames ): array {
					$entry = array(
						'kiosque_id'       => $r->kiosqueId,
						'exposant_id'      => $r->exposantId,
						'date_reservation' => $r->dateReservation->format( 'c' ),
					);
					if ( $afficherNoms && isset( $companyNames[ $r->exposantId ] ) ) {
						$entry['nom_entreprise'] = $companyNames[ $r->exposantId ];
					}
					return $entry;
				},
				$allReservations
			),
			'mes_reservations'  => array_map(
				static fn ( Reservation $r ): array => array(
					'kiosque_id'       => $r->kiosqueId,
					'exposant_id'      => $r->exposantId,
					'date_reservation' => $r->dateReservation->format( 'c' ),
				),
				$mesReservations
			),
			'kiosques_restants' => max( 0, $exposant->nbKiosquesMax - count( $mesReservations ) ),
		);
	}
}
