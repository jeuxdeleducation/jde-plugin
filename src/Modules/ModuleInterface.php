<?php
/**
 * Contrat des modules du plugin JDE.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules;

use JDE\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Tout module doit implémenter cette interface.
 *
 * Un module est une unité fonctionnelle autonome (CPT, intégration, surcouche
 * sur le cœur, etc.) qui branche ses propres hooks WordPress dans
 * {@see register()}. Les modules sont enregistrés explicitement par
 * {@see \JDE\Plugin::registerModules()} pour garder le contrôle de l'ordre
 * de chargement et faciliter l'activation conditionnelle.
 */
interface ModuleInterface {

	/**
	 * Identifiant unique du module (lowercase, kebab-case).
	 * Utilisé pour les filtres, les options et les logs.
	 */
	public function id(): string;

	/**
	 * Branchement des hooks WordPress.
	 *
	 * Appelé une seule fois, au hook plugins_loaded, après le chargement
	 * du domaine de traduction. Le module reçoit le conteneur partagé pour
	 * accéder aux services (Assets, Template, Logger, etc.).
	 */
	public function register( Container $container ): void;
}
