<?php
/**
 * Migrator versionné du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Versionne le schéma BD via l'option `jde_plugin_benevoles_db_version`.
 *
 * Clef d'option distincte de celle du module Kiosques pour permettre des
 * cycles de migration indépendants. Stratégie identique : à chaque
 * démarrage et à l'activation, on compare la version installée à la
 * version du code et on applique les paliers manquants.
 */
final class Migrator {

	public const OPTION_KEY      = 'jde_plugin_benevoles_db_version';
	public const CURRENT_VERSION = 1;

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
			$this->schema->createAllTables();
		}
	}
}
