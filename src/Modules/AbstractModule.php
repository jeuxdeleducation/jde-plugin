<?php
/**
 * Classe de base pour les modules.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules;

use JDE\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Squelette commun pour la plupart des modules.
 *
 * Mémorise le conteneur de services pour que les classes filles puissent
 * accéder aux services partagés via {@see container()}. Les classes filles
 * doivent impérativement définir {@see id()} et la logique de
 * {@see register()} (en appelant parent::register() en premier).
 */
abstract class AbstractModule implements ModuleInterface {

	protected Container $container;

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$this->container = $container;
	}

	/**
	 * Conteneur de services partagés.
	 */
	protected function container(): Container {
		return $this->container;
	}
}
