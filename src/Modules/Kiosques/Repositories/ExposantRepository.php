<?php
/**
 * Repository pour les exposants.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Kiosques\Models\Exposant;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persistance des exposants autorisés.
 *
 * La méthode {@see codeExists()} sert au générateur de codes pour
 * garantir l'unicité globale (la table a une contrainte UNIQUE sur
 * `code_acces`, mais on évite les insert qui échouent en testant avant).
 */
class ExposantRepository {

	private string $table;

	public function __construct( private readonly wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'jde_exposants';
	}

	/**
	 * Tous les exposants d'un événement, triés par nom d'entreprise.
	 *
	 * @return Exposant[]
	 */
	public function findByEvenement( int $evenementId ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE evenement_id = %d ORDER BY nom_entreprise ASC",
				$evenementId
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn ( array $row ): Exposant => Exposant::fromRow( $row ), $rows );
	}

	public function findById( int $id ): ?Exposant {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Exposant::fromRow( $row ) : null;
	}

	/**
	 * Trouver un exposant par son code d'accès (utilisé en Phase B pour l'auth).
	 */
	public function findByCode( string $code ): ?Exposant {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE code_acces = %s LIMIT 1",
				$code
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? Exposant::fromRow( $row ) : null;
	}

	/**
	 * Vérifier si un nom d'entreprise est déjà pris pour un événement donné.
	 *
	 * Les exposants sont scopés par événement, donc le même nom peut très
	 * bien réapparaître sur un autre événement — on n'interdit que les
	 * doublons à l'intérieur d'un même `evenement_id`. La comparaison est
	 * insensible à la casse et aux espaces de bordure.
	 *
	 * @param int      $evenementId Événement dans lequel chercher.
	 * @param string   $nom         Nom à tester.
	 * @param int|null $excludeId   ID d'exposant à exclure (lors d'une édition).
	 */
	public function nameExistsForEvenement( int $evenementId, string $nom, ?int $excludeId = null ): bool {
		$nom = trim( $nom );
		if ( '' === $nom ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		if ( null === $excludeId ) {
			$found = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT 1 FROM {$this->table} WHERE evenement_id = %d AND LOWER(nom_entreprise) = LOWER(%s) LIMIT 1",
					$evenementId,
					$nom
				)
			);
		} else {
			$found = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT 1 FROM {$this->table} WHERE evenement_id = %d AND LOWER(nom_entreprise) = LOWER(%s) AND id <> %d LIMIT 1",
					$evenementId,
					$nom,
					$excludeId
				)
			);
		}
		// phpcs:enable

		return null !== $found;
	}

	/**
	 * Vérifier si un code d'accès est déjà utilisé.
	 */
	public function codeExists( string $code ): bool {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$found = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT 1 FROM {$this->table} WHERE code_acces = %s LIMIT 1",
				$code
			)
		);
		// phpcs:enable

		return null !== $found;
	}

	/**
	 * Insérer ou mettre à jour un exposant.
	 *
	 * Note : le code d'accès est immuable après création (pour éviter les
	 * confusions). Pour le régénérer, utiliser {@see updateCode()}.
	 */
	public function save( Exposant $exposant ): Exposant {
		if ( null === $exposant->id ) {
			$data = array(
				'evenement_id'    => $exposant->evenementId,
				'nom_entreprise'  => $exposant->nomEntreprise,
				'nb_kiosques_max' => $exposant->nbKiosquesMax,
				'code_acces'      => $exposant->codeAcces,
				'courriel'        => $exposant->courriel,
				'date_creation'   => $exposant->dateCreation->format( 'Y-m-d H:i:s' ),
				'cree_par'        => $exposant->creePar,
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->insert(
				$this->table,
				$data,
				array( '%d', '%s', '%d', '%s', '%s', '%s', '%d' )
			);

			return new Exposant(
				id: (int) $this->wpdb->insert_id,
				evenementId: $exposant->evenementId,
				nomEntreprise: $exposant->nomEntreprise,
				nbKiosquesMax: $exposant->nbKiosquesMax,
				codeAcces: $exposant->codeAcces,
				courriel: $exposant->courriel,
				emailEnvoyeLe: null,
				dateCreation: $exposant->dateCreation,
				creePar: $exposant->creePar,
			);
		}

		// Mise à jour : on ne touche pas au code ni à la date de création.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table,
			array(
				'nom_entreprise'  => $exposant->nomEntreprise,
				'nb_kiosques_max' => $exposant->nbKiosquesMax,
				'courriel'        => $exposant->courriel,
			),
			array( 'id' => $exposant->id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		return $exposant;
	}

	/**
	 * Enregistrer l'horodatage du dernier courriel envoyé.
	 */
	public function markEmailSent( int $exposantId, \DateTimeImmutable $date ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table,
			array( 'email_envoye_le' => $date->format( 'Y-m-d H:i:s' ) ),
			array( 'id' => $exposantId ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Répartition des exposants d'un événement selon leur avancement.
	 *
	 * Retourne ['completed' => n, 'partial' => n, 'none' => n].
	 * Un exposant est "completed" si nb_reservations >= nb_kiosques_max.
	 *
	 * @return array{completed: int, partial: int, none: int}
	 */
	public function getBreakdownForEvenement( int $evenementId ): array {
		$reservationsTable = $this->wpdb->prefix . 'jde_reservations';
		$kiosquesTable     = $this->wpdb->prefix . 'jde_kiosques';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT e.nb_kiosques_max,
					COUNT(r.id) AS nb_reservations
				FROM {$this->table} e
				LEFT JOIN {$reservationsTable} r ON r.exposant_id = e.id
				LEFT JOIN {$kiosquesTable} k ON k.id = r.kiosque_id AND k.evenement_id = %d
				WHERE e.evenement_id = %d
				GROUP BY e.id",
				$evenementId,
				$evenementId
			),
			ARRAY_A
		);
		// phpcs:enable

		$breakdown = array(
			'completed' => 0,
			'partial'   => 0,
			'none'      => 0,
		);

		if ( ! is_array( $rows ) ) {
			return $breakdown;
		}

		foreach ( $rows as $row ) {
			$max = (int) $row['nb_kiosques_max'];
			$nb  = (int) $row['nb_reservations'];

			if ( 0 === $nb ) {
				++$breakdown['none'];
			} elseif ( $nb >= $max ) {
				++$breakdown['completed'];
			} else {
				++$breakdown['partial'];
			}
		}

		return $breakdown;
	}

	/**
	 * Régénérer le code d'accès d'un exposant existant.
	 */
	public function updateCode( int $id, string $newCode ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update(
			$this->table,
			array( 'code_acces' => $newCode ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result && $result > 0;
	}

	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result && $result > 0;
	}

	/**
	 * Compter les exposants d'un événement.
	 */
	public function countByEvenement( int $evenementId ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE evenement_id = %d",
				$evenementId
			)
		);
		// phpcs:enable

		return (int) $count;
	}
}
