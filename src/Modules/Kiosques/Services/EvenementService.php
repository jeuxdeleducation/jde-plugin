<?php
/**
 * Service métier des événements.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

use JDE\Modules\Kiosques\Models\Evenement;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Logique métier autour des événements (CPT `jde_evenement`).
 *
 * Plusieurs événements peuvent être actifs simultanément — chaque exposant
 * est lié à un événement précis via `evenement_id`, donc l'identification
 * de l'événement courant se fait toujours par le code d'accès de l'exposant,
 * sans ambiguïté.
 */
final class EvenementService {

	/**
	 * Activer un événement.
	 */
	public function activate( int $evenementId ): void {
		update_post_meta( $evenementId, EvenementPostType::META_ACTIF, true );
	}

	/**
	 * Désactiver un événement.
	 */
	public function deactivate( int $evenementId ): void {
		update_post_meta( $evenementId, EvenementPostType::META_ACTIF, false );
	}

	/**
	 * Indique si un événement est actuellement actif.
	 */
	public function isActive( int $evenementId ): bool {
		return (bool) get_post_meta( $evenementId, EvenementPostType::META_ACTIF, true );
	}

	/**
	 * Retourner l'événement actif courant (ou null s'il n'y en a aucun).
	 *
	 * Utile pour la page publique en Phase B : un code d'exposant identifie
	 * un événement, mais on veut aussi pouvoir afficher l'événement actif
	 * sans code (page d'accueil).
	 */
	public function getActive(): ?Evenement {
		$query = new WP_Query(
			array(
				'post_type'      => EvenementPostType::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => EvenementPostType::META_ACTIF,
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		$post = $query->posts[0];
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return Evenement::fromPost( $post );
	}

	/**
	 * Désactiver tous les événements actifs sauf un.
	 *
	 * @param int|null $exceptId Identifiant à exclure (le futur actif), ou null pour tous désactiver.
	 */
	public function deactivateAll( ?int $exceptId = null ): void {
		$query = new WP_Query(
			array(
				'post_type'      => EvenementPostType::SLUG,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => EvenementPostType::META_ACTIF,
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		foreach ( $query->posts as $id ) {
			$id = (int) $id;
			if ( null !== $exceptId && $id === $exceptId ) {
				continue;
			}
			update_post_meta( $id, EvenementPostType::META_ACTIF, false );
		}
	}
}
