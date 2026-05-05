<?php
/**
 * Colonnes personnalisées sur la liste des éditions RH.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Ajoute trois colonnes utiles au tableau de liste : actif, # postes,
 * # personnes inscrites.
 */
final class EvenementRhColumns {

	public function __construct(
		private readonly PosteRepository $postes,
		private readonly PersonneRepository $personnes,
	) {}

	public function register(): void {
		$type = EvenementRhPostType::SLUG;
		add_filter( "manage_{$type}_posts_columns", array( $this, 'addColumns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( $this, 'renderColumn' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $cols
	 * @return array<string, string>
	 */
	public function addColumns( array $cols ): array {
		$insert = array(
			'jde_rh_actif'     => __( 'Actif', 'jde-plugin' ),
			'jde_rh_postes'    => __( 'Postes', 'jde-plugin' ),
			'jde_rh_personnes' => __( 'Personnes', 'jde-plugin' ),
		);
		// Insérer juste après la colonne « title ».
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				foreach ( $insert as $k => $v ) {
					$out[ $k ] = $v;
				}
			}
		}
		return $out;
	}

	public function renderColumn( string $column, int $postId ): void {
		switch ( $column ) {
			case 'jde_rh_actif':
				$actif = (bool) get_post_meta( $postId, EvenementRhPostType::META_ACTIF, true );
				echo $actif
					? '<span style="color:#0a7c2d;font-weight:600">' . esc_html__( 'Oui', 'jde-plugin' ) . '</span>'
					: '<span style="color:#888">' . esc_html__( 'Non', 'jde-plugin' ) . '</span>';
				break;
			case 'jde_rh_postes':
				echo (int) count( $this->postes->findByEvenement( $postId ) );
				break;
			case 'jde_rh_personnes':
				echo (int) count( $this->personnes->findByEvenement( $postId ) );
				break;
		}
	}
}
