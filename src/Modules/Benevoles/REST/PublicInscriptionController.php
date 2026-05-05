<?php
/**
 * Contrôleur REST public : soumission du formulaire d'inscription.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\REST;

use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Services\InscriptionService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoint public pour la soumission du formulaire d'inscription.
 *
 * Anti-spam minimal : honeypot + rate-limiting par IP via transient.
 * L'événement RH cible n'est jamais envoyé par le client — le service
 * interroge `EvenementRhService::getActiveId()` côté serveur.
 */
final class PublicInscriptionController extends AbstractController {

	private const RATE_LIMIT_BUCKET   = 'jde_benevoles_inscription_';
	private const RATE_LIMIT_MAX      = 5;
	private const RATE_LIMIT_WINDOW_S = 900; // 15 minutes.

	public function __construct( private readonly InscriptionService $service ) {}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/inscription',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'type_role' => array(
						'type'     => 'string',
						'required' => true,
					),
					'prenom'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'nom'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'courriel'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'telephone' => array(
						'type'     => 'string',
						'required' => false,
					),
					'reponses'  => array(
						'type'     => 'array',
						'required' => false,
					),
					'plages'    => array(
						'type'     => 'array',
						'required' => false,
					),
					'website'   => array(
						'type'     => 'string',
						'required' => false,
					), // honeypot
				),
			)
		);
	}

	public function submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Honeypot : un bot remplira ce champ caché.
		if ( '' !== (string) $request->get_param( 'website' ) ) {
			return $this->errorResponse( 'rest_spam_detected', __( 'Soumission rejetée.', 'jde-plugin' ), 400 );
		}

		// Rate-limit par IP.
		$ip       = $this->clientIp();
		$bucket   = self::RATE_LIMIT_BUCKET . md5( $ip );
		$attempts = (int) get_transient( $bucket );
		if ( $attempts >= self::RATE_LIMIT_MAX ) {
			return $this->errorResponse( 'rest_rate_limited', __( 'Trop de tentatives, réessayez plus tard.', 'jde-plugin' ), 429 );
		}
		set_transient( $bucket, $attempts + 1, self::RATE_LIMIT_WINDOW_S );

		$typeRole = sanitize_key( (string) $request->get_param( 'type_role' ) );
		if ( ! in_array( $typeRole, array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE ), true ) ) {
			return $this->errorResponse( 'rest_invalid_role', __( 'Type de rôle invalide.', 'jde-plugin' ), 400 );
		}

		$reponses = (array) ( $request->get_param( 'reponses' ) ?? array() );
		$plages   = (array) ( $request->get_param( 'plages' ) ?? array() );

		try {
			$personne = $this->service->create(
				array(
					'type_role' => $typeRole,
					'prenom'    => (string) $request->get_param( 'prenom' ),
					'nom'       => (string) $request->get_param( 'nom' ),
					'courriel'  => (string) $request->get_param( 'courriel' ),
					'telephone' => (string) ( $request->get_param( 'telephone' ) ?? '' ),
					'reponses'  => $reponses,
					'plages'    => array_map( 'intval', $plages ),
				)
			);
		} catch ( \Throwable $e ) {
			return $this->errorResponse( 'rest_inscription_failed', $e->getMessage(), 422 );
		}

		return new WP_REST_Response(
			array(
				'ok'          => true,
				'personne_id' => $personne->id,
			),
			201
		);
	}

	private function clientIp(): string {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
		return sanitize_text_field( wp_unslash( $ip ) );
	}
}
