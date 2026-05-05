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
use JDE\Modules\Benevoles\Cron\RetentionCron;
use JDE\Modules\Benevoles\Database\Migrator;
use JDE\Modules\Benevoles\Database\Schema;
use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;
use JDE\Modules\Benevoles\Repositories\AssignationRepository;
use JDE\Modules\Benevoles\Repositories\DisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\EmailLogRepository;
use JDE\Modules\Benevoles\Repositories\InscriptionReponseRepository;
use JDE\Modules\Benevoles\Repositories\NotificationRepository;
use JDE\Modules\Benevoles\Repositories\PersonneRepository;
use JDE\Modules\Benevoles\Repositories\PlageDisponibiliteRepository;
use JDE\Modules\Benevoles\Repositories\PosteRepository;
use JDE\Modules\Benevoles\Repositories\QuartRepository;
use JDE\Modules\Benevoles\Repositories\SignatureRepository;
use JDE\Modules\Benevoles\Services\AcceptanceService;
use JDE\Modules\Benevoles\Services\AssignmentService;
use JDE\Modules\Benevoles\Services\AssignmentSuggester;
use JDE\Modules\Benevoles\Services\BenevoleEmailService;
use JDE\Modules\Benevoles\Services\CloneService;
use JDE\Modules\Benevoles\Services\EmailRenderer;
use JDE\Modules\Benevoles\Services\EvenementRhService;
use JDE\Modules\Benevoles\Services\FormSchemaService;
use JDE\Modules\Benevoles\Services\InscriptionService;
use JDE\Modules\Benevoles\Services\NotificationService;
use JDE\Modules\Benevoles\Services\RetentionService;
use JDE\Modules\Kiosques\Repositories\AuditRepository;

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

		// Brancher le handler de la tâche cron de rétention.
		$container->get( RetentionCron::class )->register();
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

		RetentionCron::schedule();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Désactivation : on conserve capacités et rôles (au cas où le plugin
	 * serait réactivé) mais on annule la tâche cron. La suppression
	 * définitive est gérée par `uninstall.php`.
	 */
	public function onDeactivate(): void {
		RetentionCron::unschedule();
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

		$repos = array(
			PersonneRepository::class,
			InscriptionReponseRepository::class,
			PosteRepository::class,
			QuartRepository::class,
			PlageDisponibiliteRepository::class,
			DisponibiliteRepository::class,
			AssignationRepository::class,
			NotificationRepository::class,
			SignatureRepository::class,
			EmailLogRepository::class,
		);
		foreach ( $repos as $repoClass ) {
			$container->set(
				$repoClass,
				static function () use ( $repoClass ) {
					global $wpdb;
					return new $repoClass( $wpdb );
				}
			);
		}

		// Services métier sans dépendance.
		$container->set(
			EvenementRhService::class,
			static fn (): EvenementRhService => new EvenementRhService()
		);
		$container->set(
			EmailRenderer::class,
			static fn (): EmailRenderer => new EmailRenderer()
		);
		$container->set(
			FormSchemaService::class,
			static fn (): FormSchemaService => new FormSchemaService()
		);

		// Service d'envoi des courriels.
		$container->set(
			BenevoleEmailService::class,
			static fn ( Container $c ): BenevoleEmailService => new BenevoleEmailService(
				$c->get( EmailRenderer::class ),
				$c->get( EmailLogRepository::class ),
			)
		);

		// Notifications gestionnaires.
		$container->set(
			NotificationService::class,
			static fn ( Container $c ): NotificationService => new NotificationService(
				$c->get( NotificationRepository::class ),
			)
		);

		// Inscription publique.
		$container->set(
			InscriptionService::class,
			static fn ( Container $c ): InscriptionService => new InscriptionService(
				$c->get( EvenementRhService::class ),
				$c->get( PersonneRepository::class ),
				$c->get( InscriptionReponseRepository::class ),
				$c->get( DisponibiliteRepository::class ),
				$c->get( BenevoleEmailService::class ),
				$c->get( NotificationService::class ),
				$c->get( AuditRepository::class ),
			)
		);

		// Acceptation / refus.
		$container->set(
			AcceptanceService::class,
			static fn ( Container $c ): AcceptanceService => new AcceptanceService(
				$c->get( PersonneRepository::class ),
				$c->get( BenevoleEmailService::class ),
				$c->get( AuditRepository::class ),
			)
		);

		// Auto-assignation et CRUD assignations.
		$container->set(
			AssignmentSuggester::class,
			static fn ( Container $c ): AssignmentSuggester => new AssignmentSuggester(
				$c->get( PosteRepository::class ),
				$c->get( QuartRepository::class ),
				$c->get( PersonneRepository::class ),
				$c->get( AssignationRepository::class ),
				$c->get( DisponibiliteRepository::class ),
				$c->get( PlageDisponibiliteRepository::class ),
			)
		);

		$container->set(
			AssignmentService::class,
			static fn ( Container $c ): AssignmentService => new AssignmentService(
				$c->get( AssignationRepository::class ),
				$c->get( QuartRepository::class ),
				$c->get( PosteRepository::class ),
				$c->get( PersonneRepository::class ),
				$c->get( BenevoleEmailService::class ),
				$c->get( NotificationService::class ),
				$c->get( AuditRepository::class ),
			)
		);

		$container->set(
			CloneService::class,
			static fn ( Container $c ): CloneService => new CloneService(
				$c->get( PosteRepository::class ),
				$c->get( QuartRepository::class ),
				$c->get( PlageDisponibiliteRepository::class ),
			)
		);

		// Rétention.
		$container->set(
			RetentionService::class,
			static fn ( Container $c ): RetentionService => new RetentionService(
				$c->get( PersonneRepository::class ),
				$c->get( InscriptionReponseRepository::class ),
				$c->get( PosteRepository::class ),
				$c->get( QuartRepository::class ),
				$c->get( PlageDisponibiliteRepository::class ),
				$c->get( DisponibiliteRepository::class ),
				$c->get( AssignationRepository::class ),
				$c->get( NotificationRepository::class ),
				$c->get( SignatureRepository::class ),
				$c->get( EmailLogRepository::class ),
				$c->get( AuditRepository::class ),
			)
		);

		$container->set(
			RetentionCron::class,
			static fn ( Container $c ): RetentionCron => new RetentionCron(
				$c->get( RetentionService::class ),
			)
		);
	}
}
