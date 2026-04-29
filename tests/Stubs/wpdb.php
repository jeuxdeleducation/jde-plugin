<?php
/**
 * Stub minimal de wpdb pour les tests unitaires.
 *
 * @package JDE
 */

declare(strict_types=1);

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable WordPress.NamingConventions.ValidVariableName

if ( ! class_exists( 'wpdb', false ) ) {
	/**
	 * Stub `wpdb` exposant les méthodes utilisées par les repositories.
	 * Les sous-classes anonymes dans les tests surchargent ce qu'elles
	 * ont besoin pour simuler des comportements particuliers.
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	class wpdb {

		public string $prefix = 'wp_';
		public int $insert_id = 0; // phpcs:ignore WordPress.NamingConventions
		public string $last_error = ''; // phpcs:ignore WordPress.NamingConventions

		public function get_charset_collate(): string { // phpcs:ignore WordPress.NamingConventions
			return '';
		}

		public function prepare( string $query, mixed ...$args ): string {
			return $query;
		}

		public function get_results( string $query, mixed $output = null ): array { // phpcs:ignore WordPress.NamingConventions
			return array();
		}

		public function get_row( string $query, mixed $output = null ): mixed { // phpcs:ignore WordPress.NamingConventions
			return null;
		}

		public function get_var( string $query ): mixed { // phpcs:ignore WordPress.NamingConventions
			return null;
		}

		public function insert( string $table, array $data, array|string $format = '' ): int|false {
			return 1;
		}

		public function update(
			string $table,
			array $data,
			array $where,
			array|string $format = '',
			array|string $whereFormat = ''
		): int|false {
			return 1;
		}

		public function delete( string $table, array $where, array|string $format = '' ): int|false {
			return 1;
		}
	}
}
