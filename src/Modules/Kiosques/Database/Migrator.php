<?php
/**
 * Migrator versionné du plugin JDE.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Versionne le schéma BD via l'option `jde_plugin_db_version`.
 *
 * Stratégie : à chaque démarrage (`plugins_loaded`) et à l'activation,
 * on compare la version installée à la version courante du code. Si
 * inférieure, on applique les migrations manquantes par paliers, puis
 * on met à jour l'option. Idempotent : exécuter `run()` plusieurs fois
 * de suite est sans effet une fois la migration faite.
 *
 * Pour ajouter une migration : incrémenter {@see CURRENT_VERSION}, puis
 * gérer le palier dans {@see applyMigrations()}.
 */
final class Migrator {

	public const OPTION_KEY      = 'jde_plugin_db_version';
	public const CURRENT_VERSION = 2;

	public function __construct( private readonly Schema $schema ) {}

	/**
	 * Appliquer les migrations manquantes.
	 */
	public function run(): void {
		$installed = $this->installedVersion();

		if ( $installed >= self::CURRENT_VERSION ) {
			return;
		}

		$this->applyMigrations( $installed );

		update_option( self::OPTION_KEY, (string) self::CURRENT_VERSION, false );
	}

	/**
	 * Version actuellement installée (0 si jamais migrée).
	 */
	public function installedVersion(): int {
		return (int) get_option( self::OPTION_KEY, 0 );
	}

	/**
	 * Appliquer les paliers de migration depuis la version installée.
	 */
	private function applyMigrations( int $from ): void {
		if ( $from < 1 ) {
			// Installation initiale : le schéma courant inclut déjà toutes les colonnes.
			$this->schema->createAllTables();
			return;
		}

		if ( $from < 2 ) {
			// Ajout des colonnes courriel et email_envoye_le sur wp_jde_exposants.
			// dbDelta() gère les colonnes manquantes sans toucher aux données existantes.
			$this->schema->createAllTables();
		}
	}
}
