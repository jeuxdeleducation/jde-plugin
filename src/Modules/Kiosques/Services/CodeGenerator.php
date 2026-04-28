<?php
/**
 * Générateur de codes d'accès pour les exposants.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Génère des codes lisibles, mémorisables et uniques.
 *
 * Format : `XXXX-XXXX` (8 caractères + un tiret au milieu, soit 9 au total).
 * Charset : 24 lettres majuscules (A-Z sauf I et O) + 8 chiffres (2-9).
 * On exclut 0/O/1/I pour éviter les confusions visuelles à la dictée ou
 * la recopie ; on garde L et l n'apparaît jamais (majuscules uniquement).
 *
 * Sécurité : `random_int()` pour la randomness (CSPRNG). Avec 32 caractères
 * possibles sur 8 positions on a 32^8 ≈ 1.1 × 10^12 codes, soit largement
 * assez pour un événement annuel sans crainte de collision.
 */
final class CodeGenerator {

	/**
	 * Caractères autorisés : ni 0/O ni 1/I.
	 */
	private const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/**
	 * Longueur du code utile (sans le tiret).
	 */
	private const LENGTH = 8;

	/**
	 * Tentatives maximales avant d'abandonner (collision improbable mais possible).
	 */
	private const MAX_ATTEMPTS = 16;

	public function __construct( private readonly ExposantRepository $exposants ) {}

	/**
	 * Générer un nouveau code unique en BD.
	 *
	 * @throws RuntimeException Si {@see MAX_ATTEMPTS} collisions consécutives.
	 */
	public function generateUnique(): string {
		for ( $attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++ ) {
			$code = $this->randomCode();
			if ( ! $this->exposants->codeExists( $code ) ) {
				return $code;
			}
		}

		throw new RuntimeException(
			sprintf(
				'Impossible de générer un code unique après %d tentatives.',
				self::MAX_ATTEMPTS
			)
		);
	}

	/**
	 * Générer un code aléatoire au bon format (sans vérifier l'unicité).
	 *
	 * Exposé publiquement pour les tests et pour les usages où l'unicité
	 * sera vérifiée différemment.
	 */
	public function randomCode(): string {
		$charset    = self::CHARSET;
		$charsetLen = strlen( $charset );

		$raw = '';
		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			$raw .= $charset[ random_int( 0, $charsetLen - 1 ) ];
		}

		return substr( $raw, 0, 4 ) . '-' . substr( $raw, 4, 4 );
	}

	/**
	 * Vérifier qu'un code respecte le format attendu (utile pour l'auth en Phase B).
	 */
	public static function isValidFormat( string $code ): bool {
		return 1 === preg_match( '/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $code );
	}
}
