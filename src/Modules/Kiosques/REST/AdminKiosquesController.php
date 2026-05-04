<?php
/**
 * Endpoints REST admin pour la gestion des kiosques d'un événement.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\REST;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Kiosques\Models\Kiosque;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Repositories\AuditRepository;
use JDE\Modules\Kiosques\Repositories\KiosqueRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Routes :
 *   GET  /jde/v1/admin/evenements/{id}/kiosques  → liste les kiosques.
 *   POST /jde/v1/admin/evenements/{id}/kiosques  → remplace l'ensemble.
 *
 * Le POST a une sémantique de remplacement : le client envoie l'état
 * complet ; le serveur diffère contre la BD et applique INSERT/UPDATE/
 * DELETE. C'est la sémantique la plus naturelle pour un éditeur de canvas.
 */
final class AdminKiosquesController extends AbstractController {

	public function __construct(
		private readonly KiosqueRepository $repo,
		private readonly AuditRepository $audit,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/evenements/(?P<id>\d+)/kiosques',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => array( $this, 'adminPermissionCheck' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'replace' ),
					'permission_callback' => array( $this, 'adminPermissionCheck' ),
					'args'                => $this->replaceArgs(),
				),
			)
		);
	}

	/**
	 * GET — lister les kiosques d'un événement.
	 */
	public function list( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$evenementId = (int) $request['id'];

		$post = get_post( $evenementId );
		if ( ! $post || EvenementPostType::SLUG !== $post->post_type ) {
			return $this->errorResponse(
				'evenement_introuvable',
				__( 'Événement introuvable.', 'jde-plugin' ),
				404
			);
		}

		$kiosques = $this->repo->findByEvenement( $evenementId );

		return new WP_REST_Response(
			array(
				'kiosques' => array_map( static fn ( Kiosque $k ): array => $k->toArray(), $kiosques ),
			)
		);
	}

	/**
	 * POST — remplacer l'ensemble des kiosques d'un événement.
	 *
	 * Sémantique : le payload est l'état souhaité complet.
	 * - Kiosques avec id présent en BD → UPDATE.
	 * - Kiosques sans id → INSERT.
	 * - Kiosques en BD mais absents du payload → DELETE.
	 */
	public function replace( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$evenementId = (int) $request['id'];

		$post = get_post( $evenementId );
		if ( ! $post || EvenementPostType::SLUG !== $post->post_type ) {
			return $this->errorResponse(
				'evenement_introuvable',
				__( 'Événement introuvable.', 'jde-plugin' ),
				404
			);
		}

		$payload  = $request->get_json_params();
		$incoming = is_array( $payload['kiosques'] ?? null ) ? $payload['kiosques'] : array();

		$existing    = $this->repo->findByEvenement( $evenementId );
		$existingIds = array();
		foreach ( $existing as $kiosque ) {
			if ( null !== $kiosque->id ) {
				$existingIds[ $kiosque->id ] = $kiosque;
			}
		}

		$preservedIds = array();
		$now          = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		foreach ( $incoming as $clientItem ) {
			if ( ! is_array( $clientItem ) ) {
				continue;
			}

			$clientId = isset( $clientItem['id'] ) && is_int( $clientItem['id'] )
				? $clientItem['id']
				: null;

			// Sécurité : si un id est fourni, il doit appartenir à cet événement.
			if ( null !== $clientId && ! isset( $existingIds[ $clientId ] ) ) {
				continue;
			}

			$dateCreation = null !== $clientId && isset( $existingIds[ $clientId ] )
				? $existingIds[ $clientId ]->dateCreation
				: $now;

			$kiosque = new Kiosque(
				id: $clientId,
				evenementId: $evenementId,
				numero: sanitize_text_field( (string) ( $clientItem['numero'] ?? '' ) ),
				posX: $this->clampPercent( (float) ( $clientItem['pos_x'] ?? 0 ) ),
				posY: $this->clampPercent( (float) ( $clientItem['pos_y'] ?? 0 ) ),
				largeur: $this->clampPercent( (float) ( $clientItem['largeur'] ?? 0 ) ),
				hauteur: $this->clampPercent( (float) ( $clientItem['hauteur'] ?? 0 ) ),
				dimensionsTexte: $this->stringOrNull( $clientItem['dimensions_texte'] ?? null ),
				notes: $this->stringOrNull( $clientItem['notes'] ?? null, true ),
				statut: $this->normalizeStatut( $clientItem['statut'] ?? Kiosque::STATUT_DISPONIBLE ),
				dateCreation: $dateCreation,
				dateModification: $now,
			);

			if ( '' === $kiosque->numero ) {
				continue; // numéro obligatoire
			}

			$saved = $this->repo->save( $kiosque );
			if ( null !== $saved->id ) {
				$preservedIds[ $saved->id ] = true;
			}
		}

		// Supprimer les kiosques retirés par le client.
		$deleted = 0;
		foreach ( array_keys( $existingIds ) as $existingId ) {
			if ( ! isset( $preservedIds[ $existingId ] ) ) {
				$this->repo->delete( $existingId );
				++$deleted;
			}
		}

		$final = $this->repo->findByEvenement( $evenementId );

		$this->audit->log(
			get_current_user_id(),
			'kiosque.save_batch',
			'evenement',
			$evenementId,
			array(
				'preserved' => count( $preservedIds ),
				'deleted'   => $deleted,
				'total'     => count( $final ),
			)
		);

		return new WP_REST_Response(
			array(
				'kiosques' => array_map( static fn ( Kiosque $k ): array => $k->toArray(), $final ),
			)
		);
	}

	/**
	 * Schéma JSON Schema pour la requête POST.
	 *
	 * @return array<string, mixed>
	 */
	private function replaceArgs(): array {
		return array(
			'id'       => array(
				'type'     => 'integer',
				'required' => true,
			),
			'kiosques' => array(
				'type'     => 'array',
				'required' => true,
				'items'    => array(
					'type'       => 'object',
					'properties' => array(
						'id'               => array( 'type' => array( 'integer', 'null' ) ),
						'numero'           => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 32,
						),
						'pos_x'            => array(
							'type'    => 'number',
							'minimum' => 0,
							'maximum' => 100,
						),
						'pos_y'            => array(
							'type'    => 'number',
							'minimum' => 0,
							'maximum' => 100,
						),
						'largeur'          => array(
							'type'    => 'number',
							'minimum' => 0,
							'maximum' => 100,
						),
						'hauteur'          => array(
							'type'    => 'number',
							'minimum' => 0,
							'maximum' => 100,
						),
						'dimensions_texte' => array( 'type' => array( 'string', 'null' ) ),
						'notes'            => array( 'type' => array( 'string', 'null' ) ),
						'statut'           => array(
							'type' => 'string',
							'enum' => array( Kiosque::STATUT_DISPONIBLE, Kiosque::STATUT_INDISPONIBLE ),
						),
					),
					'required'   => array( 'numero', 'pos_x', 'pos_y', 'largeur', 'hauteur', 'statut' ),
				),
			),
		);
	}

	private function clampPercent( float $value ): float {
		return max( 0.0, min( 100.0, $value ) );
	}

	private function normalizeStatut( mixed $value ): string {
		$value = is_string( $value ) ? $value : Kiosque::STATUT_DISPONIBLE;
		return in_array( $value, array( Kiosque::STATUT_DISPONIBLE, Kiosque::STATUT_INDISPONIBLE ), true )
			? $value
			: Kiosque::STATUT_DISPONIBLE;
	}

	private function stringOrNull( mixed $value, bool $multiline = false ): ?string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}
		return $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}
}
