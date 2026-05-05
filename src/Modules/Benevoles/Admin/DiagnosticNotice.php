<?php
/**
 * Avis admin de diagnostic pour le module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Services\EvenementRhService;
use JDE\Modules\Benevoles\Services\FormSchemaService;

defined( 'ABSPATH' ) || exit;

/**
 * Affiche un avis admin si la configuration du module est incomplète.
 *
 * Vérifications :
 *  - aucune édition RH active (le formulaire public retournera une
 *    erreur tant que rien n'est actif) ;
 *  - aucun schéma de formulaire défini pour au moins un rôle (les
 *    candidats ne pourront pas soumettre).
 *
 * Affiché uniquement aux gestionnaires (capacité MANAGE) et seulement
 * sur les écrans du module pour ne pas polluer le tableau de bord.
 */
final class DiagnosticNotice {

	public function __construct(
		private readonly EvenementRhService $evenementService,
		private readonly FormSchemaService $formSchemas,
	) {}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybeRender' ) );
	}

	public function maybeRender(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen ) {
			return;
		}

		// Limiter l'affichage aux écrans du module.
		$relevantPages = array(
			AdminMenu::SLUG,
			SettingsPage::PAGE_SLUG,
			PersonnesPage::PAGE_SLUG,
			PostesPage::PAGE_SLUG,
			AssignationsPage::PAGE_SLUG,
			FormulairesPage::PAGE_SLUG,
			EmailComposerPage::PAGE_SLUG,
			EmailTemplatesPage::PAGE_SLUG,
		);
		if ( ! in_array( $screen->id ?? '', array_map( static fn ( string $s ): string => 'benevoles_page_' . $s, $relevantPages ), true )
			&& 'edit-jde_evenement_rh' !== ( $screen->id ?? '' )
			&& 'jde_evenement_rh' !== ( $screen->post_type ?? '' )
		) {
			return;
		}

		$messages = array();
		if ( null === $this->evenementService->getActiveId() ) {
			$messages[] = __( 'Aucune édition RH active. Le formulaire d\'inscription public refusera les candidatures tant qu\'une édition n\'est pas activée.', 'jde-plugin' );
		}

		$schemas    = $this->formSchemas->getAllSchemas();
		$rolesVides = array();
		foreach ( array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE ) as $role ) {
			if ( array() === ( $schemas[ $role ] ?? array() ) ) {
				$rolesVides[] = $role;
			}
		}
		if ( array() !== $rolesVides ) {
			$messages[] = sprintf(
				/* translators: %s: list of roles separated by commas */
				__( 'Aucun schéma de formulaire défini pour : %s.', 'jde-plugin' ),
				implode( ', ', $rolesVides )
			);
		}

		if ( array() === $messages ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'JDE Bénévoles — configuration incomplète', 'jde-plugin' ) . '</strong></p><ul>';
		foreach ( $messages as $msg ) {
			echo '<li>' . esc_html( $msg ) . '</li>';
		}
		echo '</ul></div>';
	}
}
