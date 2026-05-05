<?php
/**
 * Service métier des éditions RH (CPT jde_evenement_rh).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Logique métier autour des éditions RH.
 *
 * Contrainte fonctionnelle : une seule édition active à la fois.
 * `activate()` désactive les autres avant de marquer la cible active —
 * pas de race condition critique car une seule personne (un gestionnaire)
 * pilote ce flux. La contrainte n'est pas appliquée par la BD : c'est
 * ce service qui la maintient. Toute lecture passant par `getActiveId()`
 * retournera donc systématiquement l'unique candidat.
 */
final class EvenementRhService {

	/**
	 * Activer une édition RH (et désactiver toutes les autres).
	 */
	public function activate( int $evenementRhId ): void {
		$this->deactivateAll( $evenementRhId );
		update_post_meta( $evenementRhId, EvenementRhPostType::META_ACTIF, true );
	}

	/**
	 * Désactiver une édition RH.
	 */
	public function deactivate( int $evenementRhId ): void {
		update_post_meta( $evenementRhId, EvenementRhPostType::META_ACTIF, false );
	}

	public function isActive( int $evenementRhId ): bool {
		return (bool) get_post_meta( $evenementRhId, EvenementRhPostType::META_ACTIF, true );
	}

	/**
	 * ID de l'édition RH active, ou null s'il n'y en a aucune.
	 */
	public function getActiveId(): ?int {
		$query = new WP_Query(
			array(
				'post_type'      => EvenementRhPostType::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => EvenementRhPostType::META_ACTIF,
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		return (int) $query->posts[0];
	}

	/**
	 * Le post WP de l'édition active (ou null).
	 */
	public function getActivePost(): ?WP_Post {
		$id = $this->getActiveId();
		if ( null === $id ) {
			return null;
		}

		$post = get_post( $id );
		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Date de fin (Y-m-d) de l'édition courante, ou null si non définie.
	 *
	 * Utilisée par {@see InscriptionService} pour dénormaliser sur la
	 * personne au moment de l'inscription, ce qui permet à la rétention
	 * 2 ans de purger sans avoir à rejoindre le CPT.
	 */
	public function getDateFin( int $evenementRhId ): ?string {
		$value = get_post_meta( $evenementRhId, EvenementRhPostType::META_DATE_FIN, true );
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Désactiver toutes les éditions actives sauf une (ou toutes si null).
	 */
	public function deactivateAll( ?int $exceptId = null ): void {
		$query = new WP_Query(
			array(
				'post_type'      => EvenementRhPostType::SLUG,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => EvenementRhPostType::META_ACTIF,
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
			update_post_meta( $id, EvenementRhPostType::META_ACTIF, false );
		}
	}
}
