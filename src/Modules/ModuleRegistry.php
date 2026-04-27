<?php
/**
 * Registre des modules du plugin JDE.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules;

use JDE\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Conserve la liste des modules actifs et les fait s'enregistrer en bloc.
 *
 * Le registre détecte les doublons d'identifiant (lève une exception) et
 * exécute {@see ModuleInterface::register()} sur chaque module au moment
 * choisi par {@see \JDE\Plugin}, après le chargement du textdomain.
 */
final class ModuleRegistry {

	/**
	 * Modules indexés par identifiant.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $modules = array();

	private bool $registered = false;

	public function __construct( private readonly Container $container ) {}

	/**
	 * Ajouter un module au registre.
	 *
	 * @throws \LogicException Si un module portant le même identifiant existe déjà.
	 * @throws \LogicException Si on tente d'ajouter un module après registerAll().
	 */
	public function add( ModuleInterface $module ): void {
		if ( $this->registered ) {
			throw new \LogicException(
				'Impossible d\'ajouter un module après l\'appel à ModuleRegistry::registerAll().'
			);
		}

		$id = $module->id();

		if ( isset( $this->modules[ $id ] ) ) {
			throw new \LogicException(
				sprintf( 'Le module « %s » est déjà enregistré.', $id )
			);
		}

		$this->modules[ $id ] = $module;
	}

	/**
	 * Brancher tous les modules enregistrés.
	 *
	 * Idempotent : un appel ultérieur est ignoré.
	 */
	public function registerAll(): void {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		foreach ( $this->modules as $module ) {
			$module->register( $this->container );
		}
	}

	/**
	 * Obtenir un module par identifiant.
	 */
	public function get( string $id ): ?ModuleInterface {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Liste des identifiants de modules enregistrés.
	 *
	 * @return string[]
	 */
	public function ids(): array {
		return array_keys( $this->modules );
	}

	/**
	 * Indique si le registre a déjà branché ses modules.
	 */
	public function isRegistered(): bool {
		return $this->registered;
	}
}
