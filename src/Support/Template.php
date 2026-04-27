<?php
/**
 * Chargeur de templates surclassables par le thème.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Charge un template en cherchant d'abord dans le thème actif (sous
 * `wp-content/themes/<thème>/jde-plugin/`) puis dans le dossier
 * `templates/` du plugin. Cela permet aux thèmes de surclasser n'importe
 * quel template du plugin sans modifier le plugin lui-même.
 */
final class Template {

	/**
	 * @param string $defaultDir Chemin absolu vers le dossier templates du plugin.
	 */
	public function __construct( private readonly string $defaultDir ) {}

	/**
	 * Localiser un template (thème en priorité, puis plugin).
	 *
	 * @param string $relativePath Chemin relatif vers le fichier (ex. : « parts/header.php »).
	 * @return string|null Chemin absolu, ou null si introuvable.
	 */
	public function locate( string $relativePath ): ?string {
		$relativePath = ltrim( $relativePath, '/' );

		$themeOverride = locate_template( array( 'jde-plugin/' . $relativePath ) );
		if ( ! empty( $themeOverride ) ) {
			return $themeOverride;
		}

		$pluginPath = $this->defaultDir . $relativePath;
		if ( file_exists( $pluginPath ) ) {
			return $pluginPath;
		}

		return null;
	}

	/**
	 * Inclure un template en lui passant des variables.
	 *
	 * @param string               $relativePath Chemin relatif vers le template.
	 * @param array<string, mixed> $context      Variables exposées au template.
	 */
	public function render( string $relativePath, array $context = array() ): void {
		$path = $this->locate( $relativePath );
		if ( null === $path ) {
			return;
		}

		// Filtre permettant d'altérer le contexte (ex. : pour des extensions).
		$context = apply_filters( 'jde_plugin_template_context', $context, $relativePath );

		( static function ( string $__path, array $__context ): void {
			extract( $__context, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $__path;
		} )( $path, $context );
	}

	/**
	 * Variante qui retourne le rendu sous forme de chaîne plutôt que de l'imprimer.
	 *
	 * @param string               $relativePath Chemin relatif vers le template.
	 * @param array<string, mixed> $context      Variables exposées au template.
	 */
	public function capture( string $relativePath, array $context = array() ): string {
		ob_start();
		$this->render( $relativePath, $context );
		return (string) ob_get_clean();
	}
}
