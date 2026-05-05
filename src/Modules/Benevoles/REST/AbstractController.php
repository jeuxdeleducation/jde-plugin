<?php
/**
 * Classe de base pour les contrôleurs REST du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\REST;

use JDE\Modules\Benevoles\Capabilities;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Fournit le namespace REST commun et des helpers d'autorisation/erreur.
 *
 * Toutes les routes du module utilisent le préfixe `jde/v1/benevoles`.
 */
abstract class AbstractController {

	public const NAMESPACE = 'jde/v1';
	public const REST_BASE = 'benevoles';

	abstract public function registerRoutes(): void;

	/**
	 * Permission callback pour les routes admin.
	 */
	public function adminPermissionCheck(): bool|WP_Error {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Permission insuffisante pour gérer les bénévoles.', 'jde-plugin' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Permission callback pour le profil personnel : utilisateur connecté
	 * disposant de la capacité partagée par les trois rôles JDE.
	 */
	public function profilePermissionCheck(): bool|WP_Error {
		if ( ! is_user_logged_in() || ! current_user_can( Capabilities::ACCES_PROFIL ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Connectez-vous avec votre compte bénévole/jury/arbitre.', 'jde-plugin' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * @param array<string, mixed>|null $extra
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
