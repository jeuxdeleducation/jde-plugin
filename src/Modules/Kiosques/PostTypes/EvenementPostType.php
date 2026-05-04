<?php
/**
 * Type de contenu personnalisé « jde_evenement ».
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\PostTypes;

use JDE\Modules\Kiosques\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre le CPT « jde_evenement » et ses champs meta.
 *
 * Le CPT est :
 *  - non public (pas d'archive ni de page single accessible côté frontend) ;
 *  - mappé sur la capacité `jde_manage_kiosques` pour toutes les opérations CRUD ;
 *  - associé à plusieurs champs meta (plan, actif, paramètres de visibilité,
 *    verrouillage du plan).
 *
 * Le menu d'administration est enregistré séparément (voir Admin/AdminMenu.php
 * dans une étape ultérieure) : ici on désactive `show_in_menu` pour garder le
 * contrôle de l'emplacement.
 */
final class EvenementPostType {

	public const SLUG = 'jde_evenement';

	public const META_PLAN_ATTACHMENT_ID = '_jde_plan_attachment_id';
	public const META_ACTIF              = '_jde_actif';
	public const META_AFFICHER_NOMS      = '_jde_afficher_noms_entreprises';
	public const META_PLAN_VERROUILLE    = '_jde_plan_verrouille';

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
				'description'       => __( 'Événements de Jeux de l\'Éducation avec gestion des kiosques d\'exposants.', 'jde-plugin' ),
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
				'menu_icon'         => 'dashicons-grid-view',
				'supports'          => array( 'title', 'editor', 'revisions' ),
				'capability_type'   => array( 'jde_evenement', 'jde_evenements' ),
				'map_meta_cap'      => true,
				// Pas de surcharge `capabilities` : on laisse WP auto-générer les
				// noms standards (edit_jde_evenements, etc.) et on les accorde au
				// rôle administrateur via Capabilities::addToAdministrator(). Une
				// surcharge custom où plusieurs clés pointent sur la même valeur
				// brise current_user_can() pour cette valeur (WP la prend pour une
				// meta cap à re-mapper sans contexte, retournant do_not_allow).
				'delete_with_user'  => false,
			)
		);
	}

	/**
	 * Enregistrer les champs meta du CPT.
	 */
	public function registerMeta(): void {
		register_post_meta(
			self::SLUG,
			self::META_PLAN_ATTACHMENT_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( $this, 'metaAuthCallback' ),
				'show_in_rest'      => false,
			)
		);

		register_post_meta(
			self::SLUG,
			self::META_ACTIF,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( $this, 'metaAuthCallback' ),
				'show_in_rest'      => false,
			)
		);

		register_post_meta(
			self::SLUG,
			self::META_AFFICHER_NOMS,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( $this, 'metaAuthCallback' ),
				'show_in_rest'      => false,
			)
		);

		register_post_meta(
			self::SLUG,
			self::META_PLAN_VERROUILLE,
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

	/**
	 * Autorisation d'écriture sur les champs meta.
	 */
	public function metaAuthCallback(): bool {
		return current_user_can( Capabilities::MANAGE );
	}

	/**
	 * Étiquettes en français pour l'admin WordPress.
	 *
	 * @return array<string, string>
	 */
	private function labels(): array {
		return array(
			'name'                   => _x( 'Événements', 'CPT général', 'jde-plugin' ),
			'singular_name'          => _x( 'Événement', 'CPT singulier', 'jde-plugin' ),
			'menu_name'              => _x( 'Événements', 'menu admin', 'jde-plugin' ),
			'name_admin_bar'         => _x( 'Événement', 'barre admin', 'jde-plugin' ),
			'add_new'                => __( 'Ajouter', 'jde-plugin' ),
			'add_new_item'           => __( 'Ajouter un événement', 'jde-plugin' ),
			'new_item'               => __( 'Nouvel événement', 'jde-plugin' ),
			'edit_item'              => __( 'Modifier l\'événement', 'jde-plugin' ),
			'view_item'              => __( 'Voir l\'événement', 'jde-plugin' ),
			'all_items'              => __( 'Tous les événements', 'jde-plugin' ),
			'search_items'           => __( 'Rechercher un événement', 'jde-plugin' ),
			'not_found'              => __( 'Aucun événement trouvé.', 'jde-plugin' ),
			'not_found_in_trash'     => __( 'Aucun événement dans la corbeille.', 'jde-plugin' ),
			'archives'               => __( 'Archives des événements', 'jde-plugin' ),
			'attributes'             => __( 'Attributs de l\'événement', 'jde-plugin' ),
			'item_published'         => __( 'Événement publié.', 'jde-plugin' ),
			'item_updated'           => __( 'Événement mis à jour.', 'jde-plugin' ),
			'item_reverted_to_draft' => __( 'Événement remis en brouillon.', 'jde-plugin' ),
		);
	}
}
