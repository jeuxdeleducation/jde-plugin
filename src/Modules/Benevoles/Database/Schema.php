<?php
/**
 * Schéma BD du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Database;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Définit et crée les 10 tables du module via dbDelta().
 *
 * Préfixe commun : `wp_jde_rh_*`. Le pluriel `rh` (ressources humaines)
 * isole les tables du module et évite les collisions avec celles du
 * module Kiosques.
 *
 * Rappel dbDelta : chaque colonne sur sa propre ligne, deux espaces
 * entre PRIMARY KEY et la parenthèse, indexes en `KEY` (pas `INDEX`).
 */
class Schema {

	public function __construct( private readonly wpdb $wpdb ) {}

	/**
	 * Créer (ou mettre à jour) toutes les tables du module.
	 */
	public function createAllTables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->wpdb->get_charset_collate();

		dbDelta( $this->personnesTable( $charset ) );
		dbDelta( $this->inscriptionReponsesTable( $charset ) );
		dbDelta( $this->postesTable( $charset ) );
		dbDelta( $this->quartsTable( $charset ) );
		dbDelta( $this->plagesDispoTable( $charset ) );
		dbDelta( $this->disponibilitesTable( $charset ) );
		dbDelta( $this->assignationsTable( $charset ) );
		dbDelta( $this->notificationsTable( $charset ) );
		dbDelta( $this->signaturesTable( $charset ) );
		dbDelta( $this->emailLogTable( $charset ) );
	}

	/**
	 * Nom complet d'une table du module (avec préfixe wpdb).
	 */
	public function tableName( string $suffix ): string {
		return $this->wpdb->prefix . 'jde_rh_' . $suffix;
	}

	private function personnesTable( string $charset ): string {
		$table = $this->tableName( 'personnes' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			evenement_rh_id BIGINT UNSIGNED NOT NULL,
			type_role VARCHAR(20) NOT NULL,
			prenom VARCHAR(100) NOT NULL,
			nom VARCHAR(100) NOT NULL,
			courriel VARCHAR(255) NOT NULL,
			telephone VARCHAR(40) NULL,
			statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
			wp_user_id BIGINT UNSIGNED NULL,
			onedrive_url VARCHAR(500) NULL,
			decide_par BIGINT UNSIGNED NULL,
			date_inscription DATETIME NOT NULL,
			date_decision DATETIME NULL,
			date_fin_evenement DATE NULL,
			PRIMARY KEY  (id),
			KEY evenement_statut_role (evenement_rh_id, statut, type_role),
			KEY wp_user_id (wp_user_id),
			KEY courriel (courriel),
			KEY date_fin_evenement (date_fin_evenement)
		) {$charset};";
	}

	private function inscriptionReponsesTable( string $charset ): string {
		$table = $this->tableName( 'inscription_reponses' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			personne_id BIGINT UNSIGNED NOT NULL,
			field_key VARCHAR(64) NOT NULL,
			field_label VARCHAR(255) NOT NULL,
			field_value LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY personne_id (personne_id)
		) {$charset};";
	}

	private function postesTable( string $charset ): string {
		$table = $this->tableName( 'postes' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			evenement_rh_id BIGINT UNSIGNED NOT NULL,
			nom VARCHAR(255) NOT NULL,
			description TEXT NULL,
			lieu VARCHAR(255) NULL,
			nb_personnes_souhaite SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			responsable_user_id BIGINT UNSIGNED NULL,
			type_role VARCHAR(20) NOT NULL,
			PRIMARY KEY  (id),
			KEY evenement_role (evenement_rh_id, type_role)
		) {$charset};";
	}

	private function quartsTable( string $charset ): string {
		$table = $this->tableName( 'quarts' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			poste_id BIGINT UNSIGNED NOT NULL,
			date_debut DATETIME NOT NULL,
			date_fin DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY poste_debut (poste_id, date_debut)
		) {$charset};";
	}

	private function plagesDispoTable( string $charset ): string {
		$table = $this->tableName( 'plages_dispo' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			evenement_rh_id BIGINT UNSIGNED NOT NULL,
			libelle VARCHAR(255) NOT NULL,
			date_debut DATETIME NOT NULL,
			date_fin DATETIME NOT NULL,
			ordre SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY evenement_ordre (evenement_rh_id, ordre)
		) {$charset};";
	}

	private function disponibilitesTable( string $charset ): string {
		$table = $this->tableName( 'disponibilites' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			personne_id BIGINT UNSIGNED NOT NULL,
			plage_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY personne_plage (personne_id, plage_id)
		) {$charset};";
	}

	private function assignationsTable( string $charset ): string {
		$table = $this->tableName( 'assignations' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			personne_id BIGINT UNSIGNED NOT NULL,
			quart_id BIGINT UNSIGNED NOT NULL,
			statut VARCHAR(20) NOT NULL DEFAULT 'proposee',
			date_creation DATETIME NOT NULL,
			date_decision DATETIME NULL,
			cree_par BIGINT UNSIGNED NULL,
			motif_refus TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY personne_quart (personne_id, quart_id),
			KEY quart_statut (quart_id, statut)
		) {$charset};";
	}

	private function notificationsTable( string $charset ): string {
		$table = $this->tableName( 'notifications' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(40) NOT NULL,
			entity_type VARCHAR(32) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			evenement_rh_id BIGINT UNSIGNED NOT NULL,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			read_at DATETIME NULL,
			read_by_user_id BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY evenement_read (evenement_rh_id, read_at),
			KEY created_at (created_at)
		) {$charset};";
	}

	private function signaturesTable( string $charset ): string {
		$table = $this->tableName( 'signatures' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			personne_id BIGINT UNSIGNED NOT NULL,
			type_document VARCHAR(40) NOT NULL,
			signed_at DATETIME NOT NULL,
			ip_address VARCHAR(45) NULL,
			user_agent VARCHAR(255) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY personne_document (personne_id, type_document)
		) {$charset};";
	}

	private function emailLogTable( string $charset ): string {
		$table = $this->tableName( 'email_log' );
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			evenement_rh_id BIGINT UNSIGNED NOT NULL,
			template VARCHAR(64) NOT NULL,
			subject VARCHAR(255) NOT NULL,
			recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
			sent_at DATETIME NOT NULL,
			sent_by BIGINT UNSIGNED NULL,
			filters_json LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY evenement_sent (evenement_rh_id, sent_at)
		) {$charset};";
	}
}
