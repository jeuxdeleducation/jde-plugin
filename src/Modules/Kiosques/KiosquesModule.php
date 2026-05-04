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
use JDE\Modules\Kiosques\Admin\AdminMenu;
use JDE\Modules\Kiosques\Admin\AuditPage;
use JDE\Modules\Kiosques\Admin\DiagnosticNotice;
use JDE\Modules\Kiosques\Admin\EvenementColumns;
use JDE\Modules\Kiosques\Admin\EvenementEditScreen;
use JDE\Modules\Kiosques\Admin\ExposantsPage;
use JDE\Modules\Kiosques\Admin\ReservationsPage;
use JDE\Modules\Kiosques\Database\Migrator;
use JDE\Modules\Kiosques\Database\Schema;
use JDE\Modules\Kiosques\Frontend\ReservationShortcode;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Repositories\AuditRepository;
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Repositories\KiosqueRepository;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;
use JDE\Modules\Kiosques\REST\AdminKiosquesController;
use JDE\Modules\Kiosques\REST\AdminReservationsController;
use JDE\Modules\Kiosques\REST\AuthController;
use JDE\Modules\Kiosques\REST\ReservationController;
use JDE\Modules\Kiosques\Services\AuthService;
use JDE\Modules\Kiosques\Services\CodeGenerator;
use JDE\Modules\Kiosques\Services\CookieWriter;
use JDE\Modules\Kiosques\Services\CsvExporter;
use JDE\Modules\Kiosques\Services\EvenementService;
use JDE\Modules\Kiosques\Services\PhpCookieWriter;
use JDE\Modules\Kiosques\Services\PublicStateBuilder;
use JDE\Modules\Kiosques\Services\RateLimiter;
use JDE\Modules\Kiosques\Services\ReservationService;
use JDE\Support\Assets;
use JDE\Support\Template;

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

		// Filet de sécurité : à chaque chargement, vérifier que le schéma est à jour
		// et que la capacité est attribuée au rôle administrateur. Idempotent — coût
		// négligeable si déjà à jour. Couvre le cas où le hook d'activation n'a pas
		// tourné correctement (plugin installé manuellement, problème de permissions, etc.).
		add_action(
			'plugins_loaded',
			static function () use ( $container ): void {
				$container->get( Migrator::class )->run();
				Capabilities::addToAdministrator();
			},
			11
		);

		// Enregistrer le CPT et ses champs meta dès « init ».
		$container->get( EvenementPostType::class )->register();

		// Enregistrer les routes REST au hook rest_api_init.
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( AdminKiosquesController::class )->registerRoutes();
				$container->get( AuthController::class )->registerRoutes();
				$container->get( ReservationController::class )->registerRoutes();
				$container->get( AdminReservationsController::class )->registerRoutes();
			}
		);

		// Enregistrer le shortcode public.
		$container->get( ReservationShortcode::class )->register();

		// Écrans d'administration.
		if ( is_admin() ) {
			$container->get( AdminMenu::class )->register();
			$container->get( EvenementColumns::class )->register();
			$container->get( EvenementEditScreen::class )->register();
			$container->get( ExposantsPage::class )->register();
			$container->get( ReservationsPage::class )->register();
			$container->get( AuditPage::class )->register();
			$container->get( DiagnosticNotice::class )->register();
		}
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

		$container->set(
			KiosqueRepository::class,
			static function (): KiosqueRepository {
				global $wpdb;
				return new KiosqueRepository( $wpdb );
			}
		);

		$container->set(
			ExposantRepository::class,
			static function (): ExposantRepository {
				global $wpdb;
				return new ExposantRepository( $wpdb );
			}
		);

		$container->set(
			AuditRepository::class,
			static function (): AuditRepository {
				global $wpdb;
				return new AuditRepository( $wpdb );
			}
		);

		$container->set(
			ReservationRepository::class,
			static function (): ReservationRepository {
				global $wpdb;
				return new ReservationRepository( $wpdb );
			}
		);

		$container->set(
			CodeGenerator::class,
			static fn ( Container $c ): CodeGenerator => new CodeGenerator(
				$c->get( ExposantRepository::class )
			)
		);

		$container->set(
			EvenementService::class,
			static fn (): EvenementService => new EvenementService()
		);

		$container->set(
			CookieWriter::class,
			static fn (): CookieWriter => new PhpCookieWriter()
		);

		$container->set(
			AuthService::class,
			static fn ( Container $c ): AuthService => new AuthService(
				$c->get( CookieWriter::class )
			)
		);

		$container->set(
			RateLimiter::class,
			static fn (): RateLimiter => new RateLimiter()
		);

		$container->set(
			PublicStateBuilder::class,
			static fn ( Container $c ): PublicStateBuilder => new PublicStateBuilder(
				$c->get( KiosqueRepository::class ),
				$c->get( ReservationRepository::class ),
				$c->get( ExposantRepository::class ),
			)
		);

		$container->set(
			AuthController::class,
			static fn ( Container $c ): AuthController => new AuthController(
				$c->get( AuthService::class ),
				$c->get( RateLimiter::class ),
				$c->get( ExposantRepository::class ),
				$c->get( EvenementService::class ),
				$c->get( PublicStateBuilder::class ),
			)
		);

		$container->set(
			ReservationService::class,
			static fn ( Container $c ): ReservationService => new ReservationService(
				$c->get( ReservationRepository::class ),
				$c->get( KiosqueRepository::class ),
				$c->get( ExposantRepository::class ),
				$c->get( EvenementService::class ),
				$c->get( AuditRepository::class ),
			)
		);

		$container->set(
			ReservationController::class,
			static fn ( Container $c ): ReservationController => new ReservationController(
				$c->get( AuthService::class ),
				$c->get( ReservationService::class ),
				$c->get( ExposantRepository::class ),
				$c->get( PublicStateBuilder::class ),
			)
		);

		$container->set(
			CsvExporter::class,
			static fn ( Container $c ): CsvExporter => new CsvExporter(
				$c->get( ReservationRepository::class )
			)
		);

		$container->set(
			AdminReservationsController::class,
			static fn ( Container $c ): AdminReservationsController => new AdminReservationsController(
				$c->get( ReservationService::class ),
				$c->get( ReservationRepository::class ),
				$c->get( CsvExporter::class ),
				$c->get( ExposantRepository::class ),
			)
		);

		$container->set(
			ReservationShortcode::class,
			static fn ( Container $c ): ReservationShortcode => new ReservationShortcode(
				$c->get( Assets::class ),
				$c->get( Template::class ),
			)
		);

		$container->set(
			AdminMenu::class,
			static fn (): AdminMenu => new AdminMenu()
		);

		$container->set(
			EvenementColumns::class,
			static fn ( Container $c ): EvenementColumns => new EvenementColumns(
				$c->get( EvenementService::class ),
				$c->get( KiosqueRepository::class ),
				$c->get( ExposantRepository::class ),
				$c->get( ReservationRepository::class ),
				$c->get( AuditRepository::class ),
			)
		);

		$container->set(
			EvenementEditScreen::class,
			static fn ( Container $c ): EvenementEditScreen => new EvenementEditScreen(
				$c->get( Assets::class )
			)
		);

		$container->set(
			ExposantsPage::class,
			static fn ( Container $c ): ExposantsPage => new ExposantsPage(
				$c->get( ExposantRepository::class ),
				$c->get( CodeGenerator::class ),
				$c->get( AuditRepository::class ),
			)
		);

		$container->set(
			AuditPage::class,
			static fn ( Container $c ): AuditPage => new AuditPage(
				$c->get( AuditRepository::class )
			)
		);

		$container->set(
			ReservationsPage::class,
			static fn ( Container $c ): ReservationsPage => new ReservationsPage(
				$c->get( Assets::class )
			)
		);

		$container->set(
			DiagnosticNotice::class,
			static fn (): DiagnosticNotice => new DiagnosticNotice()
		);

		$container->set(
			AdminKiosquesController::class,
			static fn ( Container $c ): AdminKiosquesController => new AdminKiosquesController(
				$c->get( KiosqueRepository::class ),
				$c->get( AuditRepository::class ),
			)
		);
	}
}
