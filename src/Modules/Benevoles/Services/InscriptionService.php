<?php
/**
 * Service d'inscription d'une personne.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\Models\InscriptionReponse;
use JDE\Modules\Benevoles\Models\Notification;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Repositories\DisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\InscriptionReponseRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Kiosques\Repositories\AuditRepository;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Crée une candidature à partir des données du formulaire public.
 *
 * Orchestrateur : valide qu'un événement est actif, refuse les doublons
 * de courriel pour le même événement, persiste la personne, ses réponses
 * libres et les plages de disponibilité cochées, déclenche le courriel
 * de confirmation, pousse une notification au gestionnaire, journalise
 * l'action.
 */
final class InscriptionService {

	public function __construct(
		private readonly EvenementRhService $evenementService,
		private readonly PersonneRepository $personnes,
		private readonly InscriptionReponseRepository $reponses,
		private readonly DisponibiliteRepository $disponibilites,
		private readonly BenevoleEmailService $emails,
		private readonly NotificationService $notifications,
		private readonly AuditRepository $audit,
	) {}

	/**
	 * Créer une candidature.
	 *
	 * @param array{
	 *     type_role: string,
	 *     prenom: string,
	 *     nom: string,
	 *     courriel: string,
	 *     telephone?: string,
	 *     reponses?: array<int, array{key: string, label: string, value: string|null}>,
	 *     plages?: int[]
	 * } $payload
	 *
	 * @throws RuntimeException Si aucun événement actif ou doublon courriel.
	 */
	public function create( array $payload ): Personne {
		$evenementId = $this->evenementService->getActiveId();
		if ( null === $evenementId ) {
			throw new RuntimeException( __( 'Aucune édition RH active actuellement.', 'jde-plugin' ) );
		}

		$courriel = sanitize_email( (string) ( $payload['courriel'] ?? '' ) );
		if ( '' === $courriel || ! is_email( $courriel ) ) {
			throw new RuntimeException( __( 'Adresse courriel invalide.', 'jde-plugin' ) );
		}

		if ( $this->personnes->existsForEvenement( $evenementId, $courriel ) ) {
			throw new RuntimeException( __( 'Une candidature existe déjà pour cette adresse courriel.', 'jde-plugin' ) );
		}

		$typeRole = $this->validateTypeRole( (string) ( $payload['type_role'] ?? '' ) );

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$personne = $this->personnes->save(
			new Personne(
				id: null,
				evenementRhId: $evenementId,
				typeRole: $typeRole,
				prenom: sanitize_text_field( (string) ( $payload['prenom'] ?? '' ) ),
				nom: sanitize_text_field( (string) ( $payload['nom'] ?? '' ) ),
				courriel: $courriel,
				telephone: isset( $payload['telephone'] )
					? sanitize_text_field( (string) $payload['telephone'] )
					: null,
				statut: Personne::STATUT_EN_ATTENTE,
				wpUserId: null,
				onedriveUrl: null,
				decidePar: null,
				dateInscription: $now,
				dateDecision: null,
				dateFinEvenement: $this->evenementService->getDateFin( $evenementId ),
			)
		);

		// Réponses libres.
		if ( null !== $personne->id && ! empty( $payload['reponses'] ) && is_array( $payload['reponses'] ) ) {
			$models = array();
			foreach ( $payload['reponses'] as $r ) {
				if ( ! is_array( $r ) ) {
					continue;
				}
				$key = isset( $r['key'] ) ? sanitize_key( (string) $r['key'] ) : '';
				if ( '' === $key ) {
					continue;
				}
				$models[] = new InscriptionReponse(
					id: null,
					personneId: $personne->id,
					fieldKey: $key,
					fieldLabel: sanitize_text_field( (string) ( $r['label'] ?? '' ) ),
					fieldValue: isset( $r['value'] ) ? sanitize_textarea_field( (string) $r['value'] ) : null,
				);
			}
			if ( array() !== $models ) {
				$this->reponses->saveBatch( $personne->id, $models );
			}
		}

		// Disponibilités cochées.
		if ( null !== $personne->id && ! empty( $payload['plages'] ) && is_array( $payload['plages'] ) ) {
			$plageIds = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', $payload['plages'] ),
						static fn ( int $id ): bool => $id > 0
					)
				)
			);
			if ( array() !== $plageIds ) {
				$this->disponibilites->saveBatch( $personne->id, $plageIds );
			}
		}

		// Courriel de confirmation.
		$this->emails->sendToPersonne(
			BenevoleEmailService::TPL_CONFIRMATION,
			$personne,
			array( 'url_profil' => '' ) // pas encore de compte WP, donc pas d'URL profil.
		);

		// Notification gestionnaire.
		if ( null !== $personne->id ) {
			$this->notifications->push(
				Notification::TYPE_INSCRIPTION_NOUVELLE,
				'personne',
				$personne->id,
				$evenementId,
				array(
					'prenom'    => $personne->prenom,
					'nom'       => $personne->nom,
					'type_role' => $personne->typeRole,
				)
			);

			$this->audit->log(
				userId: 0,
				action: 'benevole.inscription',
				entityType: 'personne',
				entityId: $personne->id,
				payload: array(
					'evenement_rh_id' => $evenementId,
					'type_role'       => $personne->typeRole,
				)
			);
		}

		return $personne;
	}

	private function validateTypeRole( string $typeRole ): string {
		$allowed = array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE );
		if ( ! in_array( $typeRole, $allowed, true ) ) {
			throw new RuntimeException( __( 'Type de rôle inconnu.', 'jde-plugin' ) );
		}
		return $typeRole;
	}
}
