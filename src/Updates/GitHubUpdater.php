<?php
/**
 * Mécanisme de mise à jour depuis GitHub.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Updates;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Configure plugin-update-checker pour suivre les releases GitHub du plugin.
 *
 * Stratégie : seules les *releases* GitHub publiées comptent comme nouvelles
 * versions. La branche `main` peut donc recevoir du travail en cours sans
 * déclencher de mise à jour sur les sites en production. Quand une version
 * est prête :
 *   1. Bumper « Version: » dans jde-plugin.php et JDE_PLUGIN_VERSION.
 *   2. Mettre à jour CHANGELOG.md et readme.txt.
 *   3. Tagger « vX.Y.Z » et pousser le tag.
 *   4. GitHub Actions (release.yml) attache le ZIP construit à la release.
 *   5. PUC détecte la release et propose la mise à jour dans wp-admin.
 */
final class GitHubUpdater {

	private bool $initialized = false;

	/**
	 * @param string $pluginFile Chemin absolu vers le fichier principal du plugin.
	 * @param string $repoUrl    URL HTTPS du dépôt GitHub (avec slash final).
	 * @param string $branch     Branche stable suivie (par défaut « main »).
	 */
	public function __construct(
		private readonly string $pluginFile,
		private readonly string $repoUrl,
		private readonly string $branch = 'main'
	) {}

	/**
	 * Initialiser le checker. Idempotent et sécurisé contre l'absence de la lib.
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$this->initialized = true;

		$checker = PucFactory::buildUpdateChecker(
			$this->repoUrl,
			$this->pluginFile,
			'jde-plugin'
		);

		$checker->setBranch( $this->branch );

		// Utiliser les ZIP attachés aux releases plutôt que l'archive auto-générée
		// par GitHub : notre release.yml inclut vendor/ et exclut les fichiers de dev.
		$vcs = $checker->getVcsApi();
		if ( $vcs && method_exists( $vcs, 'enableReleaseAssets' ) ) {
			$vcs->enableReleaseAssets();
		}
	}

	/**
	 * Indique si le checker a été initialisé avec succès.
	 */
	public function isInitialized(): bool {
		return $this->initialized;
	}
}
