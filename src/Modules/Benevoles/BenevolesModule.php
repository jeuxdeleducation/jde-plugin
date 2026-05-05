<?php
/**
 * Module Bénévoles — gestion du personnel d'événement (bénévoles, jurys, arbitres).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles;

use JDE\Container;
use JDE\Modules\AbstractModule;
use JDE\Modules\ActivatableModule;
use JDE\Modules\Benevoles\Database\Migrator;
use JDE\Modules\Benevoles\Database\Schema;
use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Point d'entrée du module Bénévoles.
 *
 * Responsabilités progressives au fil des phases :
 *  - Phase 1 (en cours) : capacités, rôles WP, CPT.
 *  - Phase 2 : schéma BD versionné.
 *  - Phase 3 : modèles + dépôts.
 *  - Phase 4 : services métier (inscription, acceptation, courriels, suggestions).
 *  - Phase 5 : interfaces admin et publique (REST, shortcodes, bundles).
 *  - Phase 6 : templates de courriels et finalisation.
 */
final class BenevolesModule extends AbstractModule implements ActivatableModule {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'benevoles';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		parent::register( $container );

		$this->registerServices( $container );

		// Filet de sécurité : à chaque chargement, s'assurer que le schéma est
		// à jour, que les capacités sont attribuées et que les rôles existent.
		// Idempotent — coût négligeable. Couvre le cas où le hook d'activation
		// n'a pas tourné (installation manuelle, mise à jour du plugin, etc.).
		add_action(
			'plugins_loaded',
			static function () use ( $container ): void {
				$container->get( Migrator::class )->run();
				Capabilities::addToAdministrator();
				Capabilities::createRoles();
			},
			11
		);

		// Enregistrer le CPT et ses champs meta dès « init ».
		$container->get( EvenementRhPostType::class )->register();
	}

	/**
	 * {@inheritDoc}
	 *
	 * À l'activation : crée les tables BD, ajoute les capacités au rôle
	 * administrateur et crée les trois rôles WP (bénévole, jury, arbitre).
	 * Le conteneur n'étant pas encore peuplé, on instancie Schema et
	 * Migrator manuellement (pattern Kiosques).
	 */
	public function onActivate(): void {
		global $wpdb;

		$schema   = new Schema( $wpdb );
		$migrator = new Migrator( $schema );
		$migrator->run();

		Capabilities::addToAdministrator();
		Capabilities::createRoles();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Désactivation : on conserve capacités et rôles (au cas où le plugin
	 * serait réactivé). La suppression définitive est gérée par `uninstall.php`.
	 */
	public function onDeactivate(): void {
		// Aucune action.
	}

	/**
	 * Enregistrer les services Bénévoles dans le conteneur partagé.
	 */
	private function registerServices( Container $container ): void {
		$container->set(
			Schema::class,
			static function (): Schema {
				global $wpdb;
				return new Schema( $wpdb );
			}
		);

		$container->set(
			Migrator::class,
			static fn ( Container $c ): Migrator => new Migrator( $c->get( Schema::class ) )
		);

		$container->set(
			EvenementRhPostType::class,
			static fn (): EvenementRhPostType => new EvenementRhPostType()
		);
	}
}
