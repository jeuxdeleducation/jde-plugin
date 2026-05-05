<?php
/**
 * Service d'acceptation/refus d'une candidature.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Kiosques\Repositories\AuditRepository;
use RuntimeException;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Décide d'une candidature en attente : crée le compte WP en cas
 * d'acceptation, met à jour le statut, déclenche le courriel de
 * décision et journalise l'action.
 *
 * Idempotent par sécurité : si une candidature est déjà décidée, le
 * service refuse de la modifier (évite les double-créations de compte).
 */
final class AcceptanceService {

	public function __construct(
		private readonly PersonneRepository $personnes,
		private readonly BenevoleEmailService $emails,
		private readonly AuditRepository $audit,
	) {}

	/**
	 * Accepter une candidature.
	 *
	 * @throws RuntimeException Si la personne est introuvable ou déjà décidée.
	 */
	public function accept( int $personneId, int $decideParUserId ): Personne {
		$personne = $this->personnes->findById( $personneId );
		if ( null === $personne ) {
			throw new RuntimeException( __( 'Candidature introuvable.', 'jde-plugin' ) );
		}
		if ( Personne::STATUT_EN_ATTENTE !== $personne->statut ) {
			throw new RuntimeException( __( 'Cette candidature a déjà été décidée.', 'jde-plugin' ) );
		}

		$wpUserId = $this->ensureWpUser( $personne );

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$this->personnes->updateStatut(
			$personne->id ?? 0,
			Personne::STATUT_ACCEPTEE,
			$decideParUserId,
			$now,
			$wpUserId
		);

		$accepted = new Personne(
			id: $personne->id,
			evenementRhId: $personne->evenementRhId,
			typeRole: $personne->typeRole,
			prenom: $personne->prenom,
			nom: $personne->nom,
			courriel: $personne->courriel,
			telephone: $personne->telephone,
			statut: Personne::STATUT_ACCEPTEE,
			wpUserId: $wpUserId,
			onedriveUrl: $personne->onedriveUrl,
			decidePar: $decideParUserId,
			dateInscription: $personne->dateInscription,
			dateDecision: $now,
			dateFinEvenement: $personne->dateFinEvenement,
		);

		$this->emails->sendToPersonne(
			BenevoleEmailService::TPL_ACCEPTATION,
			$accepted,
			array(
				'lien_activation_compte' => wp_lostpassword_url(),
				'message_personnalise'   => '',
			)
		);

		$this->audit->log(
			userId: $decideParUserId,
			action: 'benevole.acceptation',
			entityType: 'personne',
			entityId: $personne->id ?? 0,
			payload: array(
				'wp_user_id' => $wpUserId,
				'type_role'  => $personne->typeRole,
			)
		);

		return $accepted;
	}

	/**
	 * Refuser une candidature.
	 *
	 * @throws RuntimeException Si la personne est introuvable ou déjà décidée.
	 */
	public function reject( int $personneId, int $decideParUserId, ?string $motif = null ): Personne {
		$personne = $this->personnes->findById( $personneId );
		if ( null === $personne ) {
			throw new RuntimeException( __( 'Candidature introuvable.', 'jde-plugin' ) );
		}
		if ( Personne::STATUT_EN_ATTENTE !== $personne->statut ) {
			throw new RuntimeException( __( 'Cette candidature a déjà été décidée.', 'jde-plugin' ) );
		}

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$this->personnes->updateStatut(
			$personne->id ?? 0,
			Personne::STATUT_REFUSEE,
			$decideParUserId,
			$now
		);

		$rejected = new Personne(
			id: $personne->id,
			evenementRhId: $personne->evenementRhId,
			typeRole: $personne->typeRole,
			prenom: $personne->prenom,
			nom: $personne->nom,
			courriel: $personne->courriel,
			telephone: $personne->telephone,
			statut: Personne::STATUT_REFUSEE,
			wpUserId: $personne->wpUserId,
			onedriveUrl: $personne->onedriveUrl,
			decidePar: $decideParUserId,
			dateInscription: $personne->dateInscription,
			dateDecision: $now,
			dateFinEvenement: $personne->dateFinEvenement,
		);

		$this->emails->sendToPersonne(
			BenevoleEmailService::TPL_REFUS,
			$rejected,
			array( 'motif' => $motif ?? '' )
		);

		$this->audit->log(
			userId: $decideParUserId,
			action: 'benevole.refus',
			entityType: 'personne',
			entityId: $personne->id ?? 0,
			payload: array(
				'motif'     => $motif,
				'type_role' => $personne->typeRole,
			)
		);

		return $rejected;
	}

	/**
	 * Garantir l'existence d'un compte WP pour la personne et lui
	 * appliquer le rôle correspondant à son type.
	 */
	private function ensureWpUser( Personne $personne ): int {
		$roleSlug = Capabilities::ROLE_MAP[ $personne->typeRole ] ?? Capabilities::ROLE_BENEVOLE;

		$existing = get_user_by( 'email', $personne->courriel );
		if ( false !== $existing ) {
			$user = $existing;
			if ( ! in_array( $roleSlug, (array) $user->roles, true ) ) {
				$user->add_role( $roleSlug );
			}
			return (int) $user->ID;
		}

		$login = $this->buildLogin( $personne );

		$result = wp_create_user(
			$login,
			wp_generate_password( 20, true, false ),
			$personne->courriel
		);

		if ( $result instanceof WP_Error ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: WordPress error message */
					__( 'Impossible de créer le compte : %s', 'jde-plugin' ),
					$result->get_error_message()
				)
			);
		}

		$userId = (int) $result;

		wp_update_user(
			array(
				'ID'           => $userId,
				'first_name'   => $personne->prenom,
				'last_name'    => $personne->nom,
				'display_name' => trim( $personne->prenom . ' ' . $personne->nom ),
			)
		);

		$user = get_user_by( 'id', $userId );
		if ( false !== $user ) {
			$user->set_role( $roleSlug );
		}

		return $userId;
	}

	/**
	 * Construire un identifiant de connexion unique à partir du courriel
	 * (suffixé si nécessaire).
	 */
	private function buildLogin( Personne $personne ): string {
		$local = strtok( $personne->courriel, '@' );
		if ( false === $local || '' === $local ) {
			$local = $personne->courriel;
		}
		$base = sanitize_user( strtolower( (string) $local ), true );
		if ( '' === $base ) {
			$base = 'jde_' . bin2hex( random_bytes( 4 ) );
		}

		$candidate = $base;
		$suffix    = 1;
		while ( username_exists( $candidate ) ) {
			$candidate = $base . '_' . (string) $suffix;
			++$suffix;
		}

		return $candidate;
	}
}
