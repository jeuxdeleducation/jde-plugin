<?php
/**
 * Service de duplication d'une édition RH.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use JDE\Modules\Benevoles\Models\PlageDisponibilite;
use JDE\Modules\Benevoles\Models\Poste;
use JDE\Modules\Benevoles\Models\Quart;
use JDE\Modules\Benevoles\Repositories\PlageDisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;
use JDE\Modules\Benevoles\Repositories\QuartRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Copie postes/quarts/plages d'une édition source vers une édition cible.
 *
 * Utilisé pour réutiliser la structure d'un événement passé sans
 * recopier les personnes ni les assignations (qui sont propres à
 * l'édition). Les options du clonage : avec ou sans les quarts,
 * avec ou sans les plages de disponibilité.
 */
final class CloneService {

	public function __construct(
		private readonly PosteRepository $postes,
		private readonly QuartRepository $quarts,
		private readonly PlageDisponibiliteRepository $plages,
	) {}

	/**
	 * @return array{postes: int, quarts: int, plages: int}
	 */
	public function clone(
		int $sourceEvenementId,
		int $targetEvenementId,
		bool $copyQuarts = true,
		bool $copyPlages = true
	): array {
		$nbPostes = 0;
		$nbQuarts = 0;
		$nbPlages = 0;

		foreach ( $this->postes->findByEvenement( $sourceEvenementId ) as $sourcePoste ) {
			$newPoste = $this->postes->save(
				new Poste(
					id: null,
					evenementRhId: $targetEvenementId,
					nom: $sourcePoste->nom,
					description: $sourcePoste->description,
					lieu: $sourcePoste->lieu,
					nbPersonnesSouhaite: $sourcePoste->nbPersonnesSouhaite,
					responsableUserId: $sourcePoste->responsableUserId,
					typeRole: $sourcePoste->typeRole,
				)
			);
			++$nbPostes;

			if ( $copyQuarts && null !== $sourcePoste->id && null !== $newPoste->id ) {
				foreach ( $this->quarts->findByPoste( $sourcePoste->id ) as $sourceQuart ) {
					$this->quarts->save(
						new Quart(
							id: null,
							posteId: $newPoste->id,
							dateDebut: $sourceQuart->dateDebut,
							dateFin: $sourceQuart->dateFin,
						)
					);
					++$nbQuarts;
				}
			}
		}

		if ( $copyPlages ) {
			foreach ( $this->plages->findByEvenement( $sourceEvenementId ) as $sourcePlage ) {
				$this->plages->save(
					new PlageDisponibilite(
						id: null,
						evenementRhId: $targetEvenementId,
						libelle: $sourcePlage->libelle,
						dateDebut: $sourcePlage->dateDebut,
						dateFin: $sourcePlage->dateFin,
						ordre: $sourcePlage->ordre,
					)
				);
				++$nbPlages;
			}
		}

		return array(
			'postes' => $nbPostes,
			'quarts' => $nbQuarts,
			'plages' => $nbPlages,
		);
	}
}
