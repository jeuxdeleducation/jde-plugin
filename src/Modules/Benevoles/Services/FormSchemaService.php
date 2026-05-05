<?php
/**
 * Service de gestion des schémas de formulaire d'inscription.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use JDE\Modules\Benevoles\Models\Personne;

defined( 'ABSPATH' ) || exit;

/**
 * Gère les définitions de champs des trois formulaires d'inscription.
 *
 * Stockage : option WP unique `jde_plugin_benevoles_form_schemas` (JSON)
 * indexée par type de rôle. Chaque entrée est une liste ordonnée de
 * définitions de champs au format :
 *   {
 *     "key": "experience_jeux",     // identifiant interne (slug)
 *     "label": "Avez-vous déjà ...", // libellé affiché
 *     "type": "textarea",            // text|email|tel|textarea|select|radio|checkbox|date
 *     "required": true,
 *     "options": ["oui","non"]       // pour select/radio uniquement
 *   }
 *
 * Les champs « fixes » (prénom, nom, courriel, téléphone, dispos) sont
 * toujours rendus en plus du schéma — ils ne s'éditent pas ici.
 */
final class FormSchemaService {

	public const OPTION_KEY = 'jde_plugin_benevoles_form_schemas';

	public const TYPES_AUTORISES = array(
		'text',
		'email',
		'tel',
		'textarea',
		'select',
		'radio',
		'checkbox',
		'date',
	);

	private const ROLES = array(
		Personne::TYPE_BENEVOLE,
		Personne::TYPE_JURY,
		Personne::TYPE_ARBITRE,
	);

	/**
	 * Retourne le schéma d'un rôle (liste vide si jamais configuré).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getSchemaForRole( string $typeRole ): array {
		$all = $this->getAllSchemas();
		return $all[ $typeRole ] ?? array();
	}

	/**
	 * Retourne les trois schémas indexés par rôle.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function getAllSchemas(): array {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$out = array();
		foreach ( self::ROLES as $role ) {
			$out[ $role ] = isset( $raw[ $role ] ) && is_array( $raw[ $role ] )
				? array_values( $raw[ $role ] )
				: array();
		}
		return $out;
	}

	/**
	 * Persister le schéma d'un rôle après normalisation.
	 *
	 * Les champs invalides ou de type inconnu sont silencieusement
	 * ignorés ; les autres sont normalisés (clef en slug, libellé trimmé,
	 * options nettoyées). Toujours appelé depuis la page admin (capacité
	 * vérifiée en amont).
	 *
	 * @param array<int, array<string, mixed>> $schema
	 */
	public function saveSchemaForRole( string $typeRole, array $schema ): void {
		if ( ! in_array( $typeRole, self::ROLES, true ) ) {
			return;
		}

		$normalized = array();
		foreach ( $schema as $field ) {
			$valid = $this->normalizeField( is_array( $field ) ? $field : array() );
			if ( null !== $valid ) {
				$normalized[] = $valid;
			}
		}

		$all              = $this->getAllSchemas();
		$all[ $typeRole ] = $normalized;

		update_option( self::OPTION_KEY, $all, false );
	}

	/**
	 * Normaliser un champ. Retourne null si le champ est invalide.
	 *
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>|null
	 */
	private function normalizeField( array $field ): ?array {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		if ( ! in_array( $type, self::TYPES_AUTORISES, true ) ) {
			return null;
		}

		$key = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
		if ( '' === $key ) {
			return null;
		}

		$label = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
		if ( '' === $label ) {
			return null;
		}

		$out = array(
			'key'      => $key,
			'label'    => $label,
			'type'     => $type,
			'required' => ! empty( $field['required'] ),
		);

		if ( in_array( $type, array( 'select', 'radio' ), true ) ) {
			$options = isset( $field['options'] ) && is_array( $field['options'] )
				? array_values(
					array_filter(
						array_map(
							static fn ( $opt ): string => trim( (string) $opt ),
							$field['options']
						),
						static fn ( string $opt ): bool => '' !== $opt
					)
				)
				: array();
			if ( array() === $options ) {
				return null;
			}
			$out['options'] = $options;
		}

		return $out;
	}
}
