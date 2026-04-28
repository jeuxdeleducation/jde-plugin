<?php
/**
 * Contrat des modules qui ont besoin d'une logique d'activation/désactivation.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * À implémenter en plus de {@see ModuleInterface} quand un module a besoin
 * de réaliser une opération à l'activation ou la désactivation du plugin
 * (création de tables, capabilities, options par défaut, nettoyage de cron…).
 *
 * Important : ces méthodes sont exécutées dans un contexte d'activation,
 * donc avant le hook `plugins_loaded`. La méthode `register()` du module
 * n'a pas encore été appelée. Les services de l'instance ne doivent donc
 * pas être supposés présents dans le conteneur — utiliser des dépendances
 * locales ou globales (`$wpdb`) au besoin.
 */
interface ActivatableModule {

	/**
	 * Appelé une fois lors de l'activation du plugin.
	 */
	public function onActivate(): void;

	/**
	 * Appelé une fois lors de la désactivation du plugin.
	 */
	public function onDeactivate(): void;
}
