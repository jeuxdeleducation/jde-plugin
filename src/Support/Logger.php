<?php
/**
 * Service de journalisation.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Wrapper minimal sur error_log().
 *
 * Préfixe chaque message avec « [JDE] [niveau] » et un identifiant de
 * canal optionnel pour faciliter le filtrage dans debug.log. N'écrit que
 * si WP_DEBUG_LOG est activé pour éviter de polluer les logs en prod.
 */
final class Logger {

	public const LEVEL_DEBUG   = 'debug';
	public const LEVEL_INFO    = 'info';
	public const LEVEL_WARNING = 'warning';
	public const LEVEL_ERROR   = 'error';

	public function debug( string $message, string $channel = 'plugin' ): void {
		$this->log( self::LEVEL_DEBUG, $message, $channel );
	}

	public function info( string $message, string $channel = 'plugin' ): void {
		$this->log( self::LEVEL_INFO, $message, $channel );
	}

	public function warning( string $message, string $channel = 'plugin' ): void {
		$this->log( self::LEVEL_WARNING, $message, $channel );
	}

	public function error( string $message, string $channel = 'plugin' ): void {
		$this->log( self::LEVEL_ERROR, $message, $channel );
	}

	/**
	 * Écrire une entrée dans le journal.
	 */
	public function log( string $level, string $message, string $channel = 'plugin' ): void {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[JDE] [%s] [%s] %s', $level, $channel, $message ) );
	}
}
