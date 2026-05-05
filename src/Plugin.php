<?php
/**
 * Cœur du plugin JDE.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE;

use JDE\Modules\ActivatableModule;
use JDE\Modules\Benevoles\BenevolesModule;
use JDE\Modules\Kiosques\KiosquesModule;
use JDE\Modules\ModuleRegistry;
use JDE\Support\Assets;
use JDE\Support\Logger;
use JDE\Support\Template;
use JDE\Updates\GitHubUpdater;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton orchestrant le démarrage du plugin.
 *
 * Responsabilités :
 *  - construire le conteneur de services partagés ;
 *  - enregistrer les modules dans le registre ;
 *  - brancher les hooks WordPress du cycle de vie ;
 *  - exposer les hooks d'activation/désactivation.
 */
final class Plugin {

	private static ?self $instance = null;

	private Container $container;

	private ModuleRegistry $modules;

	private bool $booted = false;

	private function __construct() {
		$this->container = new Container();
		$this->modules   = new ModuleRegistry( $this->container );
		$this->registerCoreServices();
	}

	/**
	 * Récupérer l'instance unique.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Démarrer le plugin : brancher les hooks de cycle de vie.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->registerModules();

		add_action( 'plugins_loaded', array( $this, 'onPluginsLoaded' ) );
		add_action( 'init', array( $this, 'onInit' ) );
		add_action( 'admin_init', array( $this, 'onAdminInit' ) );
		add_action( 'rest_api_init', array( $this, 'onRestApiInit' ) );
	}

	/**
	 * Conteneur de services partagés.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Registre des modules.
	 */
	public function modules(): ModuleRegistry {
		return $this->modules;
	}

	/**
	 * Hook plugins_loaded : chargement du domaine de traduction puis enregistrement des modules.
	 */
	public function onPluginsLoaded(): void {
		load_plugin_textdomain( 'jde-plugin', false, dirname( JDE_PLUGIN_BASENAME ) . '/languages' );

		$this->modules->registerAll();

		// Initialiser le mécanisme de mise à jour depuis GitHub (sécuritaire en prod uniquement).
		if ( is_admin() ) {
			$updater = $this->container->get( GitHubUpdater::class );
			if ( $updater instanceof GitHubUpdater ) {
				$updater->init();
			}
		}
	}

	/**
	 * Hook init : laisse les modules enregistrer CPT, taxonomies, shortcodes, etc.
	 */
	public function onInit(): void {
		do_action( 'jde_plugin_init', $this );
	}

	/**
	 * Hook admin_init : laisse les modules enregistrer leurs réglages.
	 */
	public function onAdminInit(): void {
		do_action( 'jde_plugin_admin_init', $this );
	}

	/**
	 * Hook rest_api_init : laisse les modules enregistrer leurs endpoints REST.
	 */
	public function onRestApiInit(): void {
		do_action( 'jde_plugin_rest_api_init', $this );
	}

	/**
	 * Action exécutée à l'activation du plugin.
	 *
	 * Délègue à chaque module qui implémente {@see ActivatableModule}.
	 */
	public static function activate(): void {
		foreach ( self::instance()->modules->all() as $module ) {
			if ( $module instanceof ActivatableModule ) {
				$module->onActivate();
			}
		}

		flush_rewrite_rules();
	}

	/**
	 * Action exécutée à la désactivation du plugin.
	 *
	 * Délègue à chaque module qui implémente {@see ActivatableModule}.
	 */
	public static function deactivate(): void {
		foreach ( self::instance()->modules->all() as $module ) {
			if ( $module instanceof ActivatableModule ) {
				$module->onDeactivate();
			}
		}

		flush_rewrite_rules();
	}

	/**
	 * Enregistrer les services partagés dans le conteneur.
	 */
	private function registerCoreServices(): void {
		$this->container->set(
			Logger::class,
			static fn (): Logger => new Logger()
		);

		$this->container->set(
			Assets::class,
			static fn (): Assets => new Assets( JDE_PLUGIN_URL, JDE_PLUGIN_DIR, JDE_PLUGIN_VERSION )
		);

		$this->container->set(
			Template::class,
			static fn (): Template => new Template( JDE_PLUGIN_DIR . 'templates/' )
		);

		$this->container->set(
			GitHubUpdater::class,
			static fn (): GitHubUpdater => new GitHubUpdater(
				JDE_PLUGIN_FILE,
				'https://github.com/jeuxdeleducation/jde-plugin/',
				'main',
				defined( 'JDE_BETA_CHANNEL' ) && (bool) JDE_BETA_CHANNEL
			)
		);
	}

	/**
	 * Lieu unique pour enregistrer les modules du plugin.
	 *
	 * Pour ajouter une nouvelle fonctionnalité majeure :
	 *  1. Créer une classe sous src/Modules/.../ qui implémente ModuleInterface.
	 *  2. L'instancier ici via $this->modules->add( new MonModule() ).
	 *  3. Le module branchera ses propres hooks dans sa méthode register().
	 */
	private function registerModules(): void {
		$this->modules->add( new KiosquesModule() );
		$this->modules->add( new BenevolesModule() );
	}

	// Empêcher la copie et la sérialisation du singleton.
	private function __clone() {}

	public function __wakeup() {
		throw new \RuntimeException( 'Le singleton JDE\\Plugin ne peut être désérialisé.' );
	}
}
