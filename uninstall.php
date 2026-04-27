<?php
/**
 * Script de désinstallation du plugin JDE.
 *
 * Exécuté par WordPress quand l'utilisateur supprime le plugin
 * via l'écran d'administration. Doit nettoyer toute trace persistante :
 * options, métadonnées, tables personnalisées, transients.
 *
 * @package JDE
 */

declare(strict_types=1);

// Sécurité : ne s'exécuter que dans le contexte de désinstallation WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Réservé : nettoyage à venir lorsque le plugin créera des données persistantes.
//
// Exemples :
//   delete_option( 'jde_plugin_settings' );
//   $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jde_xxx" );
//   delete_metadata( 'post', 0, '_jde_xxx', '', true );
