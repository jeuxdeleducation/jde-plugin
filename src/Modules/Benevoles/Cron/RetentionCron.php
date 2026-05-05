<?php
/**
 * Tâche cron de rétention des données RH.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Cron;

use JDE\Modules\Benevoles\Services\RetentionService;

defined( 'ABSPATH' ) || exit;

/**
 * Planifie une exécution quotidienne du `RetentionService`.
 *
 * Le hook `jde_benevoles_retention_cleanup` est planifié à l'activation
 * du plugin (via `BenevolesModule::onActivate()`) et désinstallé via
 * `uninstall.php`. La tâche est idempotente : si rien n'a expiré, le
 * service ne fait que lire et retourner un tableau vide.
 */
final class RetentionCron {

	public const HOOK = 'jde_benevoles_retention_cleanup';

	public function __construct( private readonly RetentionService $service ) {}

	/**
	 * Brancher le handler du hook cron.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Programmer la tâche si elle ne l'est pas déjà. Idempotent.
	 */
	public static function schedule(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Annuler la planification (utilisé à la désinstallation).
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Lancer le service. Appelé par WP-Cron.
	 */
	public function run(): void {
		$this->service->cleanup();
	}
}
