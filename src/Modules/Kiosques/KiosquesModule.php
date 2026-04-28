<?php
/**
 * Module Kiosques — gestion des événements et de leurs emplacements.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques;

use JDE\Container;
use JDE\Modules\AbstractModule;
use JDE\Modules\ActivatableModule;
use JDE\Modules\Kiosques\Database\Migrator;
use JDE\Modules\Kiosques\Database\Schema;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Point d'entrée du module Kiosques.
 *
 * Responsabilités progressives au fil des phases :
 *  - Phase A (en cours) : schéma BD, capacité, CPT, écrans admin.
 *  - Phase B : page publique, REST API, concurrence.
 *  - Phase C : vue temps réel, CRUD admin des réservations, export, audit.
 */
final class KiosquesModule extends AbstractModule implements ActivatableModule {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'kiosques';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		parent::register( $container );

		$this->registerServices( $container );

		// Filet de sécurité : à chaque chargement, vérifier que le schéma est à jour.
		// Idempotent — coût négligeable si la version installée est déjà la plus récente.
		add_action(
			'plugins_loaded',
			static function () use ( $container ): void {
				$container->get( Migrator::class )->run();
			},
			11
		);

		// Enregistrer le CPT et ses champs meta dès « init ».
		$container->get( EvenementPostType::class )->register();
	}

	/**
	 * {@inheritDoc}
	 *
	 * À l'activation : crée les tables BD et ajoute la capacité custom à l'admin.
	 */
	public function onActivate(): void {
		global $wpdb;

		$schema   = new Schema( $wpdb );
		$migrator = new Migrator( $schema );
		$migrator->run();

		Capabilities::addToAdministrator();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Désactivation : on conserve la capacité (au cas où le plugin serait
	 * réactivé). La suppression définitive est gérée par uninstall.php.
	 */
	public function onDeactivate(): void {
		// Aucune action.
	}

	/**
	 * Enregistrer les services Kiosques dans le conteneur partagé.
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
			EvenementPostType::class,
			static fn (): EvenementPostType => new EvenementPostType()
		);
	}
}
