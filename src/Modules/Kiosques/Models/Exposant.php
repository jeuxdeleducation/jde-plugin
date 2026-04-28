<?php
/**
 * Modèle de données : Exposant.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Représente un exposant autorisé pour un événement.
 *
 * Le `codeAcces` est unique sur l'ensemble des exposants (pas seulement
 * par événement) afin que la page publique puisse identifier sans
 * ambiguïté l'événement à partir du code seul.
 */
final readonly class Exposant {

	/**
	 * @param int|null $id Null si pas encore persisté.
	 */
	public function __construct(
		public ?int $id,
		public int $evenementId,
		public string $nomEntreprise,
		public int $nbKiosquesMax,
		public string $codeAcces,
		public DateTimeImmutable $dateCreation,
		public int $creePar,
	) {}

	/**
	 * Construire à partir d'une ligne de la table `wp_jde_exposants`.
	 *
	 * @param array<string, mixed> $row Ligne brute de wpdb.
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			evenementId: (int) $row['evenement_id'],
			nomEntreprise: (string) $row['nom_entreprise'],
			nbKiosquesMax: (int) $row['nb_kiosques_max'],
			codeAcces: (string) $row['code_acces'],
			dateCreation: new DateTimeImmutable( (string) $row['date_creation'], $tz ),
			creePar: (int) $row['cree_par'],
		);
	}

	/**
	 * Sérialisation pour usage admin (sans le code, par défaut).
	 *
	 * @param bool $includeCode Inclure le code d'accès dans la sortie.
	 * @return array<string, mixed>
	 */
	public function toArray( bool $includeCode = false ): array {
		$out = array(
			'id'              => $this->id,
			'evenement_id'    => $this->evenementId,
			'nom_entreprise'  => $this->nomEntreprise,
			'nb_kiosques_max' => $this->nbKiosquesMax,
			'date_creation'   => $this->dateCreation->format( 'c' ),
			'cree_par'        => $this->creePar,
		);

		if ( $includeCode ) {
			$out['code_acces'] = $this->codeAcces;
		}

		return $out;
	}
}
