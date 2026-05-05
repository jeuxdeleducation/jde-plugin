<?php
/**
 * Modèle de données : Personne (bénévole, jury ou arbitre).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Models;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Représente une personne candidate à un rôle pour une édition RH donnée.
 *
 * Une personne est créée à la soumission du formulaire public (statut
 * `en_attente`). À l'acceptation par un gestionnaire, un compte WordPress
 * est créé et associé via `wpUserId`, et `statut` passe à `acceptee`.
 *
 * Les dates `dateInscription` et `dateDecision` sont en UTC. La date
 * `dateFinEvenement` est dénormalisée depuis l'événement RH au moment
 * de l'inscription pour faciliter la purge automatique 2 ans après.
 */
final readonly class Personne {

	public const STATUT_EN_ATTENTE = 'en_attente';
	public const STATUT_ACCEPTEE   = 'acceptee';
	public const STATUT_REFUSEE    = 'refusee';

	public const TYPE_BENEVOLE = 'benevole';
	public const TYPE_JURY     = 'jury';
	public const TYPE_ARBITRE  = 'arbitre';

	public function __construct(
		public ?int $id,
		public int $evenementRhId,
		public string $typeRole,
		public string $prenom,
		public string $nom,
		public string $courriel,
		public ?string $telephone,
		public string $statut,
		public ?int $wpUserId,
		public ?string $onedriveUrl,
		public ?int $decidePar,
		public DateTimeImmutable $dateInscription,
		public ?DateTimeImmutable $dateDecision,
		public ?string $dateFinEvenement,
	) {}

	/**
	 * Construire à partir d'une ligne de la table `wp_jde_rh_personnes`.
	 *
	 * @param array<string, mixed> $row Ligne brute de wpdb.
	 */
	public static function fromRow( array $row ): self {
		$tz = new DateTimeZone( 'UTC' );

		$dateDecision = isset( $row['date_decision'] ) && null !== $row['date_decision']
			? new DateTimeImmutable( (string) $row['date_decision'], $tz )
			: null;

		$dateFin = isset( $row['date_fin_evenement'] ) && null !== $row['date_fin_evenement']
			? (string) $row['date_fin_evenement']
			: null;

		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			evenementRhId: (int) $row['evenement_rh_id'],
			typeRole: (string) $row['type_role'],
			prenom: (string) $row['prenom'],
			nom: (string) $row['nom'],
			courriel: (string) $row['courriel'],
			telephone: isset( $row['telephone'] ) && '' !== $row['telephone'] ? (string) $row['telephone'] : null,
			statut: (string) ( $row['statut'] ?? self::STATUT_EN_ATTENTE ),
			wpUserId: isset( $row['wp_user_id'] ) && null !== $row['wp_user_id'] ? (int) $row['wp_user_id'] : null,
			onedriveUrl: isset( $row['onedrive_url'] ) && '' !== $row['onedrive_url'] ? (string) $row['onedrive_url'] : null,
			decidePar: isset( $row['decide_par'] ) && null !== $row['decide_par'] ? (int) $row['decide_par'] : null,
			dateInscription: new DateTimeImmutable( (string) $row['date_inscription'], $tz ),
			dateDecision: $dateDecision,
			dateFinEvenement: $dateFin,
		);
	}

	/**
	 * Sérialisation pour usage admin / REST.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'                 => $this->id,
			'evenement_rh_id'    => $this->evenementRhId,
			'type_role'          => $this->typeRole,
			'prenom'             => $this->prenom,
			'nom'                => $this->nom,
			'courriel'           => $this->courriel,
			'telephone'          => $this->telephone,
			'statut'             => $this->statut,
			'wp_user_id'         => $this->wpUserId,
			'onedrive_url'       => $this->onedriveUrl,
			'decide_par'         => $this->decidePar,
			'date_inscription'   => $this->dateInscription->format( 'c' ),
			'date_decision'      => $this->dateDecision?->format( 'c' ),
			'date_fin_evenement' => $this->dateFinEvenement,
		);
	}
}
