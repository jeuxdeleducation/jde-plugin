<?php
/**
 * Stub minimal de WP_Query pour les tests unitaires.
 *
 * @package JDE
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Query', false ) ) {
	/**
	 * Stub très simplifié : retourne les `posts` placés dans la
	 * globale `$__wp_query_posts_stub` par le test, et mémorise les
	 * arguments reçus dans `$__last_wp_query_args`.
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	class WP_Query {

		/**
		 * @var array<int, mixed>
		 */
		public array $posts = array();

		/**
		 * @param array<string, mixed> $args
		 */
		public function __construct( array $args = array() ) {
			$GLOBALS['__last_wp_query_args'] = $args;
			$this->posts                     = isset( $GLOBALS['__wp_query_posts_stub'] )
				? (array) $GLOBALS['__wp_query_posts_stub']
				: array();
		}
	}
}
