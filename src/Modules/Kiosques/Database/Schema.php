<?php
/**
 * Schéma BD du module Kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Database;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Définit et crée les tables du module via dbDelta().
 *
 * dbDelta() est tatillon sur la mise en forme : chaque colonne sur sa
 * propre ligne, deux espaces entre PRIMARY KEY et la parenthèse. Toute
 * dérive provoque des recréations silencieuses ou des index manqués.
 */
class Schema {

	public function __construct( private readonly wpdb $wpdb ) {}

	/**
	 * Créer (ou mettre à jour) toutes les tables du module.
	 */
	public function createAllTables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->wpdb->get_charset_collate();

		dbDelta( $this->kiosquesTable( $charset ) );
		dbDelta( $this->exposantsTable( $charset ) );
		dbDelta( $this->reservationsTable( $charset ) );
		dbDelta( $this->auditTable( $charset ) );
	}

	/**
	 * Nom complet d'une table du module (avec préfixe wpdb).
	 */
	public function tableName( string $suffix ): string {
		return $this->wpdb->prefix . 'jde_' . $suffix;
	}

	private function kiosquesTable( string $charset ): string {
		$table = $this->tableName( 'kiosques' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			evenement_id BIGINT UNSIGNED NOT NULL,
			numero VARCHAR(32) NOT NULL,
			pos_x DECIMAL(7,4) NOT NULL,
			pos_y DECIMAL(7,4) NOT NULL,
			largeur DECIMAL(7,4) NOT NULL,
			hauteur DECIMAL(7,4) NOT NULL,
			dimensions_texte VARCHAR(64) NULL,
			notes TEXT NULL,
			statut VARCHAR(20) NOT NULL DEFAULT 'disponible',
			date_creation DATETIME NOT NULL,
			date_modification DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY evenement_statut (evenement_id, statut)
		) {$charset};";
	}

	private function exposantsTable( string $charset ): string {
		$table = $this->tableName( 'exposants' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			evenement_id BIGINT UNSIGNED NOT NULL,
			nom_entreprise VARCHAR(255) NOT NULL,
			nb_kiosques_max SMALLINT UNSIGNED NOT NULL,
			code_acces VARCHAR(16) NOT NULL,
			courriel VARCHAR(255) NULL,
			email_envoye_le DATETIME NULL,
			date_creation DATETIME NOT NULL,
			cree_par BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code_acces (code_acces),
			KEY evenement_id (evenement_id)
		) {$charset};";
	}

	private function reservationsTable( string $charset ): string {
		$table = $this->tableName( 'reservations' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			kiosque_id BIGINT UNSIGNED NOT NULL,
			exposant_id BIGINT UNSIGNED NOT NULL,
			date_reservation DATETIME NOT NULL,
			cree_par BIGINT UNSIGNED NULL,
			notes_admin TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY kiosque_id (kiosque_id),
			KEY exposant_id (exposant_id)
		) {$charset};";
	}

	private function auditTable( string $charset ): string {
		$table = $this->tableName( 'audit' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			action VARCHAR(64) NOT NULL,
			entity_type VARCHAR(32) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id),
			KEY user_time (user_id, created_at)
		) {$charset};";
	}
}
