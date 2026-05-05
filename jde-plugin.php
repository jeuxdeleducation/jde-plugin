<?php
/**
 * Plugin Name:       JDE Plugin
 * Plugin URI:        https://github.com/jeuxdeleducation/jde-plugin
 * Description:       Plugin interne des Jeux de l'Éducation : regroupe les personnalisations et fonctionnalités sur mesure du site WordPress.
 * Version:           0.5.5
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Jeux de l'Éducation
 * Author URI:        https://jeuxdeleducation.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jde-plugin
 * Domain Path:       /languages
 * Update URI:        https://github.com/jeuxdeleducation/jde-plugin
 *
 * @package JDE
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// Constantes du plugin.
define( 'JDE_PLUGIN_VERSION', '0.5.5' );
define( 'JDE_PLUGIN_FILE', __FILE__ );
define( 'JDE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JDE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JDE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'JDE_PLUGIN_MIN_PHP', '8.2' );
define( 'JDE_PLUGIN_MIN_WP', '6.4' );

/**
 * Vérifier que l'environnement répond aux exigences minimales.
 * Retourne true si tout est correct, sinon false (et affiche un avis admin).
 */
function jde_plugin_check_requirements(): bool {
	global $wp_version;

	$errors = array();

	if ( version_compare( PHP_VERSION, JDE_PLUGIN_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: version PHP requise, 2: version PHP actuelle */
			__( 'Le plugin JDE requiert PHP %1$s ou supérieur. Version actuelle : %2$s.', 'jde-plugin' ),
			JDE_PLUGIN_MIN_PHP,
			PHP_VERSION
		);
	}

	if ( version_compare( $wp_version, JDE_PLUGIN_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: version WordPress requise, 2: version WordPress actuelle */
			__( 'Le plugin JDE requiert WordPress %1$s ou supérieur. Version actuelle : %2$s.', 'jde-plugin' ),
			JDE_PLUGIN_MIN_WP,
			$wp_version
		);
	}

	if ( ! file_exists( JDE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		$errors[] = __( 'Les dépendances Composer du plugin JDE ne sont pas installées. Exécuter « composer install » dans le dossier du plugin.', 'jde-plugin' );
	}

	if ( empty( $errors ) ) {
		return true;
	}

	add_action(
		'admin_notices',
		static function () use ( $errors ): void {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'JDE Plugin', 'jde-plugin' ) . '</strong></p><ul>';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}
	);

	return false;
}

if ( ! jde_plugin_check_requirements() ) {
	return;
}

require_once JDE_PLUGIN_DIR . 'vendor/autoload.php';

// Hooks d'activation, désactivation et démarrage.
register_activation_hook( __FILE__, array( \JDE\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \JDE\Plugin::class, 'deactivate' ) );

\JDE\Plugin::instance()->boot();
