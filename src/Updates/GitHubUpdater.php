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
 * Deux canaux de mise à jour :
 *
 * - **Production** (défaut) : seules les releases GitHub publiées comptent.
 *   PUC utilise l'endpoint /releases/latest qui ignore les pre-releases.
 *
 * - **Bêta** (constante JDE_BETA_CHANNEL = true dans wp-config.php) : PUC
 *   reçoit toutes les releases via /releases (pre-releases incluses). Le CI
 *   bêta publie une pre-release à chaque push sur la branche `beta`.
 */
final class GitHubUpdater {

	private bool $initialized = false;

	/**
	 * @param string $pluginFile   Chemin absolu vers le fichier principal du plugin.
	 * @param string $repoUrl      URL HTTPS du dépôt GitHub (avec slash final).
	 * @param string $branch       Branche stable suivie (par défaut « main »).
	 * @param bool   $betaChannel  Activer le canal bêta (pre-releases incluses).
	 */
	public function __construct(
		private readonly string $pluginFile,
		private readonly string $repoUrl,
		private readonly string $branch = 'main',
		private readonly bool $betaChannel = false
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

		if ( $this->betaChannel ) {
			$this->enablePreReleases();
		}
	}

	/**
	 * Intercepte la requête PUC vers /releases/latest et la redirige vers
	 * /releases (toutes releases, pre-releases incluses) pour le canal bêta.
	 *
	 * Sans cette interception, PUC n'utilise que /releases/latest qui exclut
	 * systématiquement les pre-releases, peu importe la configuration.
	 */
	private function enablePreReleases(): void {
		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args, string $url ) {
				if ( ! str_contains( $url, '/releases/latest' )
					|| ! str_contains( $url, 'api.github.com' )
				) {
					return $preempt;
				}

				$listUrl  = str_replace( '/releases/latest', '/releases', $url );
				$response = wp_remote_get( $listUrl, $args );

				if ( is_wp_error( $response )
					|| 200 !== wp_remote_retrieve_response_code( $response )
				) {
					return $preempt;
				}

				$releases = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! is_array( $releases ) || empty( $releases ) ) {
					return $preempt;
				}

				// Retourner la release la plus récente (premier élément) au format
				// attendu par PUC, comme si c'était une réponse de /releases/latest.
				return array(
					'body'          => wp_json_encode( $releases[0] ),
					'headers'       => wp_remote_retrieve_headers( $response ),
					'response'      => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'       => array(),
					'http_response' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Indique si le checker a été initialisé avec succès.
	 */
	public function isInitialized(): bool {
		return $this->initialized;
	}
}
