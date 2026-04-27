<?php
/**
 * Helper d'enregistrement et d'enqueue d'assets.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Centralise l'enqueue de scripts et styles construits par @wordpress/scripts.
 *
 * @wordpress/scripts produit pour chaque entrée :
 *   - assets/build/<nom>.js
 *   - assets/build/<nom>.asset.php   (dépendances + version)
 *
 * Cette classe lit le .asset.php pour passer les bonnes dépendances et la
 * version exacte (basée sur le hash du build) à wp_enqueue_script(). Cela
 * garantit l'invalidation du cache navigateur à chaque rebuild.
 */
final class Assets {

	public function __construct(
		private readonly string $pluginUrl,
		private readonly string $pluginDir,
		private readonly string $fallbackVersion
	) {}

	/**
	 * Enregistrer puis enqueuer un script construit par @wordpress/scripts.
	 *
	 * @param string   $handle    Identifiant WordPress du script.
	 * @param string   $entry     Nom de l'entrée dans assets/build/ (sans extension).
	 * @param string[] $extraDeps Dépendances supplémentaires à fusionner avec celles détectées.
	 * @param bool     $inFooter  Charger dans le pied de page (true par défaut).
	 */
	public function enqueueScript( string $handle, string $entry, array $extraDeps = array(), bool $inFooter = true ): void {
		$asset = $this->loadAssetMeta( $entry );

		wp_enqueue_script(
			$handle,
			$this->pluginUrl . 'assets/build/' . $entry . '.js',
			array_merge( $asset['dependencies'], $extraDeps ),
			$asset['version'],
			$inFooter
		);
	}

	/**
	 * Enregistrer puis enqueuer une feuille de style construite par @wordpress/scripts.
	 *
	 * @param string   $handle    Identifiant WordPress du style.
	 * @param string   $entry     Nom de l'entrée dans assets/build/ (sans extension).
	 * @param string[] $extraDeps Dépendances supplémentaires.
	 * @param string   $media     Type de média CSS.
	 */
	public function enqueueStyle( string $handle, string $entry, array $extraDeps = array(), string $media = 'all' ): void {
		$asset = $this->loadAssetMeta( $entry );

		wp_enqueue_style(
			$handle,
			$this->pluginUrl . 'assets/build/' . $entry . '.css',
			$extraDeps,
			$asset['version'],
			$media
		);
	}

	/**
	 * URL absolue vers un fichier dans le dossier du plugin.
	 */
	public function url( string $relativePath ): string {
		return $this->pluginUrl . ltrim( $relativePath, '/' );
	}

	/**
	 * Lire le fichier .asset.php produit par @wordpress/scripts.
	 *
	 * @return array{dependencies: string[], version: string}
	 */
	private function loadAssetMeta( string $entry ): array {
		$path = $this->pluginDir . 'assets/build/' . $entry . '.asset.php';

		if ( file_exists( $path ) ) {
			$asset = require $path;
			if ( is_array( $asset ) ) {
				return array(
					'dependencies' => $asset['dependencies'] ?? array(),
					'version'      => $asset['version'] ?? $this->fallbackVersion,
				);
			}
		}

		return array(
			'dependencies' => array(),
			'version'      => $this->fallbackVersion,
		);
	}
}
