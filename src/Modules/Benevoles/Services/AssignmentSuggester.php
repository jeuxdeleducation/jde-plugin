<?php
/**
 * Algorithme de suggestion d'assignations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use JDE\Modules\Benevoles\Models\Assignation;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Repositories\AssignationRepository;
use JDE\Modules\Benevoles\Repositories\DisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Repositories\PlageDisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;
use JDE\Modules\Benevoles\Repositories\QuartRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Propose des affectations sans les écrire en BD.
 *
 * Algorithme greedy déterministe :
 *  1. Pour chaque quart d'un poste donné (ou de tous les postes de
 *     l'événement), calculer les places restantes
 *     `nb_personnes_souhaite - count(acceptees)`.
 *  2. Construire la liste des candidats : personnes acceptées du même
 *     `type_role` que le poste, dont au moins une plage de disponibilité
 *     couvre `[date_debut, date_fin]` du quart, et qui n'ont aucune
 *     assignation acceptée chevauchant le quart.
 *  3. Trier les candidats par charge croissante (moins d'assignations
 *     totales d'abord) puis par ancienneté d'inscription.
 *  4. Retenir le top N (= places restantes).
 *
 * Le résultat est consommé par l'écran admin qui peut éditer la
 * proposition avant de l'appliquer (création réelle côté
 * {@see AssignmentService}).
 */
final class AssignmentSuggester {

	public function __construct(
		private readonly PosteRepository $postes,
		private readonly QuartRepository $quarts,
		private readonly PersonneRepository $personnes,
		private readonly AssignationRepository $assignations,
		private readonly DisponibiliteRepository $disponibilites,
		private readonly PlageDisponibiliteRepository $plages,
	) {}

	/**
	 * @return array<int, array{
	 *     poste_id: int,
	 *     poste_nom: string,
	 *     type_role: string,
	 *     quart_id: int,
	 *     date_debut: string,
	 *     date_fin: string,
	 *     places_restantes: int,
	 *     suggestions: array<int, array{personne_id: int, nom: string, prenom: string}>
	 * }>
	 */
	public function suggest( int $evenementRhId, ?int $posteId = null ): array {
		$postes = null === $posteId
			? $this->postes->findByEvenement( $evenementRhId )
			: array_filter(
				array( $this->postes->findById( $posteId ) ),
				static fn ( $p ): bool => null !== $p
			);

		if ( array() === $postes ) {
			return array();
		}

		$plagesByEv = $this->indexPlages( $evenementRhId );

		$personnesByRole = array();
		foreach ( $postes as $poste ) {
			if ( isset( $personnesByRole[ $poste->typeRole ] ) ) {
				continue;
			}
			$personnesByRole[ $poste->typeRole ] = $this->personnes->findByEvenement(
				$evenementRhId,
				array(
					'statut'    => Personne::STATUT_ACCEPTEE,
					'type_role' => $poste->typeRole,
				)
			);
		}

		// Compteur d'assignations totales par personne (toutes statuts confondus).
		$loadByPersonne = $this->buildLoadIndex( $personnesByRole );

		$result = array();

		foreach ( $postes as $poste ) {
			$quartsDuPoste = $this->quarts->findByPoste( $poste->id ?? 0 );
			foreach ( $quartsDuPoste as $quart ) {
				$accepted = $this->assignations->countAcceptedByQuart( $quart->id ?? 0 );
				$places   = max( 0, $poste->nbPersonnesSouhaite - $accepted );

				$candidats = $this->candidatesForQuart(
					$personnesByRole[ $poste->typeRole ] ?? array(),
					$quart->id ?? 0,
					$quart->dateDebut->format( 'Y-m-d H:i:s' ),
					$quart->dateFin->format( 'Y-m-d H:i:s' ),
					$plagesByEv,
					$loadByPersonne
				);

				$top = array_slice( $candidats, 0, $places );

				$result[] = array(
					'poste_id'         => (int) ( $poste->id ?? 0 ),
					'poste_nom'        => $poste->nom,
					'type_role'        => $poste->typeRole,
					'quart_id'         => (int) ( $quart->id ?? 0 ),
					'date_debut'       => $quart->dateDebut->format( 'c' ),
					'date_fin'         => $quart->dateFin->format( 'c' ),
					'places_restantes' => $places,
					'suggestions'      => array_map(
						static fn ( Personne $p ): array => array(
							'personne_id' => (int) ( $p->id ?? 0 ),
							'prenom'      => $p->prenom,
							'nom'         => $p->nom,
						),
						$top
					),
				);
			}
		}

		return $result;
	}

	/**
	 * @param Personne[]                                 $personnes
	 * @param array<int, array{debut: string, fin: string}> $plages indexées par id
	 * @param array<int, int>                            $loadByPersonne
	 * @return Personne[] ordonnée
	 */
	private function candidatesForQuart(
		array $personnes,
		int $quartId,
		string $debut,
		string $fin,
		array $plages,
		array $loadByPersonne
	): array {
		$candidats = array();

		foreach ( $personnes as $personne ) {
			if ( null === $personne->id ) {
				continue;
			}

			// Couverture par les plages cochées.
			$couvert = false;
			foreach ( $this->disponibilites->findPlageIdsByPersonne( $personne->id ) as $plageId ) {
				if ( ! isset( $plages[ $plageId ] ) ) {
					continue;
				}
				if ( $plages[ $plageId ]['debut'] <= $debut && $plages[ $plageId ]['fin'] >= $fin ) {
					$couvert = true;
					break;
				}
			}
			if ( ! $couvert ) {
				continue;
			}

			// Pas d'assignation acceptée chevauchant ce quart.
			$overlap = $this->assignations->findOverlappingForPersonne( $personne->id, $quartId );
			if ( array() !== $overlap ) {
				continue;
			}

			$candidats[] = $personne;
		}

		usort(
			$candidats,
			static function ( Personne $a, Personne $b ) use ( $loadByPersonne ): int {
				$la = $loadByPersonne[ $a->id ?? 0 ] ?? 0;
				$lb = $loadByPersonne[ $b->id ?? 0 ] ?? 0;
				if ( $la !== $lb ) {
					return $la <=> $lb;
				}
				return $a->dateInscription <=> $b->dateInscription;
			}
		);

		return $candidats;
	}

	/**
	 * Construire l'index `personne_id => nb_assignations` (acceptées et proposées).
	 *
	 * @param array<string, Personne[]> $personnesByRole
	 * @return array<int, int>
	 */
	private function buildLoadIndex( array $personnesByRole ): array {
		$load = array();
		foreach ( $personnesByRole as $personnes ) {
			foreach ( $personnes as $personne ) {
				if ( null === $personne->id ) {
					continue;
				}
				$count = 0;
				foreach ( $this->assignations->findByPersonne( $personne->id ) as $a ) {
					if ( in_array(
						$a->statut,
						array( Assignation::STATUT_PROPOSEE, Assignation::STATUT_ACCEPTEE ),
						true
					) ) {
						++$count;
					}
				}
				$load[ $personne->id ] = $count;
			}
		}
		return $load;
	}

	/**
	 * Indexer les plages de l'événement par id.
	 *
	 * @return array<int, array{debut: string, fin: string}>
	 */
	private function indexPlages( int $evenementRhId ): array {
		$out = array();
		foreach ( $this->plages->findByEvenement( $evenementRhId ) as $plage ) {
			if ( null === $plage->id ) {
				continue;
			}
			$out[ $plage->id ] = array(
				'debut' => $plage->dateDebut->format( 'Y-m-d H:i:s' ),
				'fin'   => $plage->dateFin->format( 'Y-m-d H:i:s' ),
			);
		}
		return $out;
	}
}
