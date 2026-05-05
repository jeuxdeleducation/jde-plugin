<?php
/**
 * Type de contenu personnalisé « jde_evenement_rh ».
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\PostTypes;

use JDE\Modules\Benevoles\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre le CPT « jde_evenement_rh » et ses champs meta.
 *
 * Le CPT représente une édition d'événement côté ressources humaines
 * (recrutement de bénévoles, jurys et arbitres). Une seule édition est
 * « active » à la fois — la contrainte est appliquée par le service
 * `EvenementRhService` au moment de l'activation. Les inscriptions du
 * formulaire public sont automatiquement rattachées à l'événement actif.
 *
 * Le CPT est non public, géré via la capacité `jde_manage_benevoles`,
 * et son menu est rendu par {@see \JDE\Modules\Benevoles\Admin\AdminMenu}.
 */
final class EvenementRhPostType {

	public const SLUG = 'jde_evenement_rh';

	public const META_ACTIF               = '_jde_rh_actif';
	public const META_DATE_DEBUT          = '_jde_rh_date_debut';
	public const META_DATE_FIN            = '_jde_rh_date_fin';
	public const META_ONEDRIVE_BASE_URL   = '_jde_rh_onedrive_base_url';
	public const META_DOIT_SIGNER_ENTENTE = '_jde_rh_doit_signer_entente';
	public const META_DOIT_SIGNER_LETTRE  = '_jde_rh_doit_signer_lettre';
	public const META_INTRO_BENEVOLE      = '_jde_rh_intro_benevole';
	public const META_INTRO_JURY          = '_jde_rh_intro_jury';
	public const META_INTRO_ARBITRE       = '_jde_rh_intro_arbitre';

	/**
	 * Brancher les hooks d'enregistrement.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'registerPostType' ) );
		add_action( 'init', array( $this, 'registerMeta' ) );
	}

	/**
	 * Enregistrer le type de contenu.
	 */
	public function registerPostType(): void {
		register_post_type(
			self::SLUG,
			array(
				'labels'            => $this->labels(),
				'description'       => __( 'Éditions d\'événement pour la gestion du personnel (bénévoles, jurys, arbitres).', 'jde-plugin' ),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_admin_bar' => false,
				'show_in_nav_menus' => false,
				'show_in_rest'      => false,
				'has_archive'       => false,
				'rewrite'           => false,
				'query_var'         => false,
				'hierarchical'      => false,
				'menu_icon'         => 'dashicons-groups',
				'supports'          => array( 'title', 'revisions' ),
				'capability_type'   => array( 'jde_evenement_rh', 'jde_evenements_rh' ),
				'map_meta_cap'      => true,
				'delete_with_user'  => false,
			)
		);
	}

	/**
	 * Enregistrer les champs meta du CPT.
	 */
	public function registerMeta(): void {
		$booleanMetas = array(
			self::META_ACTIF,
			self::META_DOIT_SIGNER_ENTENTE,
			self::META_DOIT_SIGNER_LETTRE,
		);
		foreach ( $booleanMetas as $key ) {
			register_post_meta(
				self::SLUG,
				$key,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => array( $this, 'metaAuthCallback' ),
					'show_in_rest'      => false,
				)
			);
		}

		$dateMetas = array( self::META_DATE_DEBUT, self::META_DATE_FIN );
		foreach ( $dateMetas as $key ) {
			register_post_meta(
				self::SLUG,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'sanitize_callback' => array( $this, 'sanitizeDate' ),
					'auth_callback'     => array( $this, 'metaAuthCallback' ),
					'show_in_rest'      => false,
				)
			);
		}

		register_post_meta(
			self::SLUG,
			self::META_ONEDRIVE_BASE_URL,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => array( $this, 'metaAuthCallback' ),
				'show_in_rest'      => false,
			)
		);

		$introMetas = array(
			self::META_INTRO_BENEVOLE,
			self::META_INTRO_JURY,
			self::META_INTRO_ARBITRE,
		);
		foreach ( $introMetas as $key ) {
			register_post_meta(
				self::SLUG,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'sanitize_callback' => 'wp_kses_post',
					'auth_callback'     => array( $this, 'metaAuthCallback' ),
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Autorisation d'écriture sur les champs meta.
	 */
	public function metaAuthCallback(): bool {
		return current_user_can( Capabilities::MANAGE );
	}

	/**
	 * Normaliser une date au format Y-m-d. Retourne '' si la valeur est invalide.
	 */
	public function sanitizeDate( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}
		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Étiquettes en français pour l'admin WordPress.
	 *
	 * @return array<string, string>
	 */
	private function labels(): array {
		return array(
			'name'                   => _x( 'Éditions RH', 'CPT général', 'jde-plugin' ),
			'singular_name'          => _x( 'Édition RH', 'CPT singulier', 'jde-plugin' ),
			'menu_name'              => _x( 'Éditions RH', 'menu admin', 'jde-plugin' ),
			'name_admin_bar'         => _x( 'Édition RH', 'barre admin', 'jde-plugin' ),
			'add_new'                => __( 'Ajouter', 'jde-plugin' ),
			'add_new_item'           => __( 'Ajouter une édition RH', 'jde-plugin' ),
			'new_item'               => __( 'Nouvelle édition RH', 'jde-plugin' ),
			'edit_item'              => __( 'Modifier l\'édition RH', 'jde-plugin' ),
			'view_item'              => __( 'Voir l\'édition RH', 'jde-plugin' ),
			'all_items'              => __( 'Toutes les éditions RH', 'jde-plugin' ),
			'search_items'           => __( 'Rechercher une édition RH', 'jde-plugin' ),
			'not_found'              => __( 'Aucune édition RH trouvée.', 'jde-plugin' ),
			'not_found_in_trash'     => __( 'Aucune édition RH dans la corbeille.', 'jde-plugin' ),
			'archives'               => __( 'Archives des éditions RH', 'jde-plugin' ),
			'attributes'             => __( 'Attributs de l\'édition RH', 'jde-plugin' ),
			'item_published'         => __( 'Édition RH publiée.', 'jde-plugin' ),
			'item_updated'           => __( 'Édition RH mise à jour.', 'jde-plugin' ),
			'item_reverted_to_draft' => __( 'Édition RH remise en brouillon.', 'jde-plugin' ),
		);
	}
}
