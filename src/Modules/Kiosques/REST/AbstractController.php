<?php
/**
 * Classe de base pour les contrôleurs REST du module Kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\REST;

use JDE\Modules\Kiosques\Capabilities;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Fournit le namespace REST commun et des helpers d'autorisation/erreur.
 *
 * Toutes les routes du module sont enregistrées sous `jde/v1`.
 */
abstract class AbstractController {

	public const NAMESPACE = 'jde/v1';

	/**
	 * Enregistrer les routes du contrôleur.
	 */
	abstract public function registerRoutes(): void;

	/**
	 * Permission callback pour les routes admin (capacité `jde_manage_kiosques`).
	 *
	 * @return bool|WP_Error true si autorisé, WP_Error sinon.
	 */
	public function adminPermissionCheck(): bool|WP_Error {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Permission insuffisante pour gérer les kiosques.', 'jde-plugin' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Helper pour construire une réponse d'erreur structurée.
	 *
	 * @param string                    $code    Identifiant court de l'erreur.
	 * @param string                    $message Message lisible.
	 * @param int                       $status  Code HTTP.
	 * @param array<string, mixed>|null $extra   Données additionnelles (ex. : kiosque_id en cas de conflit).
	 */
	protected function errorResponse(
		string $code,
		string $message,
		int $status,
		?array $extra = null
	): WP_Error {
		$data = array( 'status' => $status );
		if ( null !== $extra ) {
			$data = array_merge( $data, $extra );
		}
		return new WP_Error( $code, $message, $data );
	}
}
