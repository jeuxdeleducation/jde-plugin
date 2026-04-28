<?php
/**
 * Modèle de données : Evenement.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Models;

use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Vue typée d'un événement (post WP + champs meta du module Kiosques).
 *
 * Sert de DTO pour transporter les données entre repositories, services et
 * écrans admin sans dépendre directement de WP_Post (pratique pour les tests).
 */
final readonly class Evenement {

	public function __construct(
		public int $id,
		public string $titre,
		public string $statut,
		public ?int $planAttachmentId,
		public bool $actif,
		public bool $afficherNomsEntreprises,
		public bool $planVerrouille,
	) {}

	/**
	 * Construire à partir d'un WP_Post (les meta sont chargées dans la foulée).
	 */
	public static function fromPost( WP_Post $post ): self {
		$attachmentId = (int) get_post_meta( $post->ID, EvenementPostType::META_PLAN_ATTACHMENT_ID, true );

		return new self(
			id: $post->ID,
			titre: $post->post_title,
			statut: $post->post_status,
			planAttachmentId: $attachmentId > 0 ? $attachmentId : null,
			actif: (bool) get_post_meta( $post->ID, EvenementPostType::META_ACTIF, true ),
			afficherNomsEntreprises: (bool) get_post_meta( $post->ID, EvenementPostType::META_AFFICHER_NOMS, true ),
			planVerrouille: (bool) get_post_meta( $post->ID, EvenementPostType::META_PLAN_VERROUILLE, true ),
		);
	}
}
