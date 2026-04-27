<?php
/**
 * Conteneur de services.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE;

use Closure;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Conteneur léger de services partagés.
 *
 * Permet d'enregistrer des services sous forme de fabriques (closures) et
 * de les résoudre paresseusement à la première demande. Les instances sont
 * mises en cache, donc chaque service est instancié une seule fois.
 */
final class Container {

	/**
	 * Fabriques enregistrées, indexées par identifiant.
	 *
	 * @var array<string, Closure>
	 */
	private array $factories = array();

	/**
	 * Instances déjà résolues, indexées par identifiant.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Enregistrer un service.
	 *
	 * @param string  $id      Identifiant du service (ex. : nom de classe).
	 * @param Closure $factory Fabrique qui reçoit le conteneur et retourne l'instance.
	 */
	public function set( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Enregistrer une instance déjà construite.
	 *
	 * @param string $id       Identifiant du service.
	 * @param mixed  $instance Instance à mémoriser.
	 */
	public function instance( string $id, mixed $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Récupérer un service.
	 *
	 * @param string $id Identifiant du service.
	 * @return mixed
	 *
	 * @throws RuntimeException Si le service n'est pas enregistré.
	 */
	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new RuntimeException(
				sprintf( 'Service « %s » introuvable dans le conteneur JDE.', $id )
			);
		}

		$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		return $this->instances[ $id ];
	}

	/**
	 * Vérifier qu'un service est enregistré.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->instances );
	}
}
