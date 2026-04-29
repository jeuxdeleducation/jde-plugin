<?php
/**
 * Bootstrap PHPUnit.
 *
 * Charge l'autoloader Composer et définit ABSPATH afin que les fichiers
 * source du plugin (qui contiennent `defined( 'ABSPATH' ) || exit;`) puissent
 * être inclus dans les tests sans déclencher l'arrêt.
 *
 * @package JDE
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$autoload = __DIR__ . '/../vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "Les dépendances Composer ne sont pas installées. Exécuter « composer install ».\n" );
	exit( 1 );
}

require_once $autoload;

// Charger les stubs WordPress utilisés par les tests unitaires.
require_once __DIR__ . '/Stubs/WP_Query.php';
require_once __DIR__ . '/Stubs/wpdb.php';
