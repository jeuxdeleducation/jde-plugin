<?php
/**
 * Service de gestion des assignations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\Models\Assignation;
use JDE\Modules\Benevoles\Models\Notification;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Repositories\AssignationRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;
use JDE\Modules\Benevoles\Repositories\QuartRepository;
use JDE\Modules\Kiosques\Repositories\AuditRepository;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Crée et fait évoluer les assignations.
 *
 * Détection de conflits non bloquante : on accepte la création même en
 * cas de chevauchement personne ou de sur-effectif, mais on pousse une
 * notification pour que le gestionnaire arbitre.
 */
final class AssignmentService {

	public function __construct(
		private readonly AssignationRepository $assignations,
		private readonly QuartRepository $quarts,
		private readonly PosteRepository $postes,
		private readonly PersonneRepository $personnes,
		private readonly BenevoleEmailService $emails,
		private readonly NotificationService $notifications,
		private readonly AuditRepository $audit,
	) {}

	/**
	 * Proposer une assignation (statut `proposee`).
	 *
	 * @throws RuntimeException
	 */
	public function propose( int $personneId, int $quartId, int $creePar ): Assignation {
		$personne = $this->personnes->findById( $personneId );
		if ( null === $personne ) {
			throw new RuntimeException( __( 'Personne introuvable.', 'jde-plugin' ) );
		}
		if ( Personne::STATUT_ACCEPTEE !== $personne->statut ) {
			throw new RuntimeException( __( 'Seules les personnes acceptées peuvent être assignées.', 'jde-plugin' ) );
		}

		$quart = $this->quarts->findById( $quartId );
		if ( null === $quart ) {
			throw new RuntimeException( __( 'Quart introuvable.', 'jde-plugin' ) );
		}

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$assignation = $this->assignations->save(
			new Assignation(
				id: null,
				personneId: $personneId,
				quartId: $quartId,
				statut: Assignation::STATUT_PROPOSEE,
				dateCreation: $now,
				dateDecision: null,
				creePar: $creePar,
				motifRefus: null,
			)
		);

		$this->detectConflicts( $personne, $quartId );

		$this->emails->sendToPersonne(
			BenevoleEmailService::TPL_ASSIGNATION,
			$personne,
			$this->emailVarsForQuart( $quartId )
		);

		$this->audit->log(
			userId: $creePar,
			action: 'benevole.assignation.propose',
			entityType: 'assignation',
			entityId: $assignation->id ?? 0,
			payload: array(
				'personne_id' => $personneId,
				'quart_id'    => $quartId,
			)
		);

		return $assignation;
	}

	/**
	 * Décision côté personne : accepte ou refuse une assignation.
	 *
	 * @throws RuntimeException
	 */
	public function decide( int $assignationId, string $decision, ?string $motif = null ): Assignation {
		$assignation = $this->assignations->findById( $assignationId );
		if ( null === $assignation ) {
			throw new RuntimeException( __( 'Assignation introuvable.', 'jde-plugin' ) );
		}
		if ( Assignation::STATUT_PROPOSEE !== $assignation->statut ) {
			throw new RuntimeException( __( 'Cette assignation a déjà été décidée.', 'jde-plugin' ) );
		}

		$statut = Assignation::STATUT_ACCEPTEE === $decision
			? Assignation::STATUT_ACCEPTEE
			: Assignation::STATUT_REFUSEE;

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$this->assignations->updateStatut( $assignationId, $statut, $now, $motif );

		$personne = $this->personnes->findById( $assignation->personneId );

		if ( Assignation::STATUT_REFUSEE === $statut && null !== $personne ) {
			$this->notifications->push(
				Notification::TYPE_ASSIGNATION_REFUSEE,
				'assignation',
				$assignationId,
				$personne->evenementRhId,
				array(
					'personne' => $personne->prenom . ' ' . $personne->nom,
					'quart_id' => $assignation->quartId,
					'motif'    => $motif ?? '',
				)
			);
		}

		// Si acceptée, vérifier le sur-effectif a posteriori.
		if ( Assignation::STATUT_ACCEPTEE === $statut && null !== $personne ) {
			$this->detectConflicts( $personne, $assignation->quartId );
		}

		$currentUser = (int) get_current_user_id();
		$this->audit->log(
			userId: $currentUser > 0 ? $currentUser : 0,
			action: 'benevole.assignation.' . $statut,
			entityType: 'assignation',
			entityId: $assignationId,
			payload: array(
				'motif' => $motif,
			)
		);

		return new Assignation(
			id: $assignation->id,
			personneId: $assignation->personneId,
			quartId: $assignation->quartId,
			statut: $statut,
			dateCreation: $assignation->dateCreation,
			dateDecision: $now,
			creePar: $assignation->creePar,
			motifRefus: $motif,
		);
	}

	/**
	 * Détecter chevauchements et sur-effectif. Push une notification pour
	 * chaque conflit. Ne bloque pas la création.
	 */
	private function detectConflicts( Personne $personne, int $quartId ): void {
		$overlapping = $this->assignations->findOverlappingForPersonne( $personne->id ?? 0, $quartId );
		if ( array() !== $overlapping ) {
			$this->notifications->push(
				Notification::TYPE_CHEVAUCHEMENT_PERSONNE,
				'personne',
				$personne->id ?? 0,
				$personne->evenementRhId,
				array(
					'personne'       => $personne->prenom . ' ' . $personne->nom,
					'quart_id_cible' => $quartId,
					'chevauchements' => count( $overlapping ),
				)
			);
		}

		$quart = $this->quarts->findById( $quartId );
		if ( null === $quart ) {
			return;
		}
		$poste = $this->postes->findById( $quart->posteId );
		if ( null === $poste ) {
			return;
		}

		$accepted = $this->assignations->countAcceptedByQuart( $quartId );
		if ( $accepted > $poste->nbPersonnesSouhaite ) {
			$this->notifications->push(
				Notification::TYPE_SUR_EFFECTIF_POSTE,
				'poste',
				$poste->id ?? 0,
				$personne->evenementRhId,
				array(
					'poste'                 => $poste->nom,
					'quart_id'              => $quartId,
					'nb_acceptees'          => $accepted,
					'nb_personnes_souhaite' => $poste->nbPersonnesSouhaite,
				)
			);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function emailVarsForQuart( int $quartId ): array {
		$quart = $this->quarts->findById( $quartId );
		if ( null === $quart ) {
			return array();
		}
		$poste = $this->postes->findById( $quart->posteId );

		$responsableNom = '';
		if ( null !== $poste && null !== $poste->responsableUserId ) {
			$user = get_user_by( 'id', $poste->responsableUserId );
			if ( false !== $user ) {
				$responsableNom = (string) $user->display_name;
			}
		}

		return array(
			'poste_nom'         => null !== $poste ? $poste->nom : '',
			'lieu'              => null !== $poste ? (string) $poste->lieu : '',
			'quart_date'        => $quart->dateDebut->format( 'Y-m-d' ),
			'quart_heure_debut' => $quart->dateDebut->format( 'H:i' ),
			'quart_heure_fin'   => $quart->dateFin->format( 'H:i' ),
			'responsable_nom'   => $responsableNom,
		);
	}
}
