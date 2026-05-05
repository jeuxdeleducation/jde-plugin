<?php
/**
 * Moteur de rendu des courriels (mini-Mustache).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Implémentation minimale du sous-ensemble Mustache utilisé par les
 * courriels du module.
 *
 * Syntaxe supportée :
 *  - `{{var}}`       — interpolation échappée HTML.
 *  - `{{!var}}`      — interpolation brute (utile pour du HTML déjà
 *                      sécurisé, ex. corps d'un courriel composé).
 *  - `{{#var}}…{{/var}}` — bloc rendu si la variable existe et n'est
 *                          pas vide.
 *  - `{{^var}}…{{/var}}` — bloc rendu si la variable est manquante ou
 *                          vide (negative section).
 *
 * Volontairement non récursif sur les sections : les blocs imbriqués ne
 * sont pas supportés. Si le besoin émerge, basculer sur Mustache.php.
 *
 * Ce renderer est délibérément exempt de dépendances WordPress pour
 * permettre des tests purement unitaires.
 */
final class EmailRenderer {

	/**
	 * Rendre un gabarit avec un jeu de variables.
	 *
	 * @param string               $template Gabarit contenant les balises {{...}}.
	 * @param array<string, mixed> $vars     Tableau associatif des variables.
	 */
	public function render( string $template, array $vars ): string {
		$output = $this->renderSections( $template, $vars );
		return $this->renderVariables( $output, $vars );
	}

	/**
	 * Substituer les sections positives ({{#var}}…{{/var}}) et négatives
	 * ({{^var}}…{{/var}}) en première passe.
	 *
	 * @param array<string, mixed> $vars
	 */
	private function renderSections( string $template, array $vars ): string {
		// Sections positives.
		$template = preg_replace_callback(
			'/\{\{#([a-zA-Z0-9_]+)\}\}(.*?)\{\{\/\1\}\}/s',
			static function ( array $captured ) use ( $vars ): string {
				$key = $captured[1];
				return self::isTruthy( $vars[ $key ] ?? null ) ? $captured[2] : '';
			},
			$template
		);

		if ( ! is_string( $template ) ) {
			return '';
		}

		// Sections négatives.
		$template = preg_replace_callback(
			'/\{\{\^([a-zA-Z0-9_]+)\}\}(.*?)\{\{\/\1\}\}/s',
			static function ( array $captured ) use ( $vars ): string {
				$key = $captured[1];
				return self::isTruthy( $vars[ $key ] ?? null ) ? '' : $captured[2];
			},
			$template
		);

		return is_string( $template ) ? $template : '';
	}

	/**
	 * Substituer les balises de variable simples.
	 *
	 * @param array<string, mixed> $vars
	 */
	private function renderVariables( string $template, array $vars ): string {
		$template = preg_replace_callback(
			'/\{\{!([a-zA-Z0-9_]+)\}\}/',
			static function ( array $captured ) use ( $vars ): string {
				$key = $captured[1];
				return isset( $vars[ $key ] ) ? (string) $vars[ $key ] : '';
			},
			$template
		);

		if ( ! is_string( $template ) ) {
			return '';
		}

		$template = preg_replace_callback(
			'/\{\{([a-zA-Z0-9_]+)\}\}/',
			static function ( array $captured ) use ( $vars ): string {
				$key = $captured[1];
				if ( ! isset( $vars[ $key ] ) ) {
					return '';
				}
				return htmlspecialchars( (string) $vars[ $key ], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			},
			$template
		);

		return is_string( $template ) ? $template : '';
	}

	/**
	 * Une valeur est considérée « rendue » si elle n'est ni null, ni une
	 * chaîne vide, ni 0, ni false, ni un tableau vide.
	 */
	private static function isTruthy( mixed $value ): bool {
		if ( null === $value || false === $value ) {
			return false;
		}
		if ( is_string( $value ) ) {
			return '' !== trim( $value );
		}
		if ( is_array( $value ) ) {
			return array() !== $value;
		}
		if ( is_numeric( $value ) ) {
			return 0 !== (int) $value && 0.0 !== (float) $value;
		}
		return true;
	}
}
