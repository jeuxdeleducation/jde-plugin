<?php
/**
 * Contrôleur REST : profil personnel (bénévole/jury/arbitre).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\REST;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\Models\Assignation;
use JDE\Modules\Benevoles\Models\Notification;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Models\Signature;
use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;
use JDE\Modules\Benevoles\Repositories\AssignationRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;
use JDE\Modules\Benevoles\Repositories\QuartRepository;
use JDE\Modules\Benevoles\Repositories\SignatureRepository;
use JDE\Modules\Benevoles\Services\AssignmentService;
use JDE\Modules\Benevoles\Services\NotificationService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints alimentant le bundle React du profil.
 *
 *  - GET    /benevoles/profil/me                          — état complet
 *  - PATCH  /benevoles/profil/me                          — màj téléphone
 *  - POST   /benevoles/profil/assignations/{id}/decision  — accepter/refuser
 *  - POST   /benevoles/profil/signatures                  — signer un doc
 */
final class PublicProfileController extends AbstractController {

	public function __construct(
		private readonly PersonneRepository $personnes,
		private readonly AssignationRepository $assignations,
		private readonly QuartRepository $quarts,
		private readonly PosteRepository $postes,
		private readonly SignatureRepository $signatures,
		private readonly AssignmentService $assignmentService,
		private readonly NotificationService $notifications,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/profil/me',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'getMe' ),
					'permission_callback' => array( $this, 'profilePermissionCheck' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'updateMe' ),
					'permission_callback' => array( $this, 'profilePermissionCheck' ),
					'args'                => array(
						'telephone' => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/profil/assignations/(?P<id>\d+)/decision',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'decideAssignation' ),
				'permission_callback' => array( $this, 'profilePermissionCheck' ),
				'args'                => array(
					'id'       => array(
						'type'     => 'integer',
						'required' => true,
					),
					'decision' => array(
						'type'     => 'string',
						'required' => true,
					),
					'motif'    => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/profil/signatures',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'sign' ),
				'permission_callback' => array( $this, 'profilePermissionCheck' ),
				'args'                => array(
					'type_document' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	public function getMe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$personne = $this->personnes->findByWpUserId( (int) get_current_user_id() );
		if ( null === $personne ) {
			return $this->errorResponse( 'rest_no_profile', __( 'Aucune candidature liée à votre compte.', 'jde-plugin' ), 404 );
		}

		$assignations = null !== $personne->id ? $this->assignations->findByPersonne( $personne->id ) : array();
		$detailed     = array_map(
			function ( Assignation $a ): array {
				$quart = $this->quarts->findById( $a->quartId );
				$poste = null !== $quart ? $this->postes->findById( $quart->posteId ) : null;
				return array(
					'id'     => $a->id,
					'statut' => $a->statut,
					'motif'  => $a->motifRefus,
					'quart'  => null !== $quart ? array(
						'date_debut' => $quart->dateDebut->format( 'c' ),
						'date_fin'   => $quart->dateFin->format( 'c' ),
					) : null,
					'poste'  => null !== $poste ? array(
						'nom'  => $poste->nom,
						'lieu' => $poste->lieu,
					) : null,
				);
			},
			$assignations
		);

		$signatures = null !== $personne->id ? $this->signatures->findByPersonne( $personne->id ) : array();

		$doitSignerEntente = (bool) get_post_meta( $personne->evenementRhId, EvenementRhPostType::META_DOIT_SIGNER_ENTENTE, true );
		$doitSignerLettre  = (bool) get_post_meta( $personne->evenementRhId, EvenementRhPostType::META_DOIT_SIGNER_LETTRE, true );

		return new WP_REST_Response(
			array(
				'personne'        => $personne->toArray(),
				'assignations'    => $detailed,
				'signatures'      => array_map( static fn ( Signature $s ): array => $s->toArray(), $signatures ),
				'doit_signer'     => array(
					Signature::TYPE_ENTENTE => $doitSignerEntente,
					Signature::TYPE_LETTRE  => $doitSignerLettre,
				),
				'evenement_titre' => get_the_title( $personne->evenementRhId ),
			),
			200
		);
	}

	public function updateMe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$personne = $this->personnes->findByWpUserId( (int) get_current_user_id() );
		if ( null === $personne || null === $personne->id ) {
			return $this->errorResponse( 'rest_no_profile', __( 'Profil introuvable.', 'jde-plugin' ), 404 );
		}

		$telephone = sanitize_text_field( (string) $request->get_param( 'telephone' ) );
		$this->personnes->updateTelephone( $personne->id, '' !== $telephone ? $telephone : null );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function decideAssignation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$personne = $this->personnes->findByWpUserId( (int) get_current_user_id() );
		if ( null === $personne || null === $personne->id ) {
			return $this->errorResponse( 'rest_no_profile', __( 'Profil introuvable.', 'jde-plugin' ), 404 );
		}

		$assignationId = (int) $request->get_param( 'id' );
		$assignation   = $this->assignations->findById( $assignationId );
		if ( null === $assignation || $assignation->personneId !== $personne->id ) {
			return $this->errorResponse( 'rest_forbidden', __( 'Assignation introuvable.', 'jde-plugin' ), 404 );
		}

		$decision = sanitize_key( (string) $request->get_param( 'decision' ) );
		$motif    = sanitize_textarea_field( (string) ( $request->get_param( 'motif' ) ?? '' ) );

		try {
			$result = $this->assignmentService->decide( $assignationId, $decision, '' !== $motif ? $motif : null );
		} catch ( \Throwable $e ) {
			return $this->errorResponse( 'rest_decide_failed', $e->getMessage(), 422 );
		}

		return new WP_REST_Response(
			array(
				'ok'     => true,
				'statut' => $result->statut,
			),
			200
		);
	}

	public function sign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$personne = $this->personnes->findByWpUserId( (int) get_current_user_id() );
		if ( null === $personne || null === $personne->id ) {
			return $this->errorResponse( 'rest_no_profile', __( 'Profil introuvable.', 'jde-plugin' ), 404 );
		}

		$type = sanitize_key( (string) $request->get_param( 'type_document' ) );
		if ( ! in_array( $type, array( Signature::TYPE_ENTENTE, Signature::TYPE_LETTRE ), true ) ) {
			return $this->errorResponse( 'rest_invalid_document', __( 'Type de document invalide.', 'jde-plugin' ), 400 );
		}

		$now       = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : null;
		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : null;

		$this->signatures->save(
			new Signature(
				id: null,
				personneId: $personne->id,
				typeDocument: $type,
				signedAt: $now,
				ipAddress: $ip,
				userAgent: $userAgent,
			)
		);

		$this->notifications->push(
			Notification::TYPE_SIGNATURE_COMPLETEE,
			'personne',
			$personne->id,
			$personne->evenementRhId,
			array(
				'personne'      => $personne->prenom . ' ' . $personne->nom,
				'type_document' => $type,
			)
		);

		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}
}
