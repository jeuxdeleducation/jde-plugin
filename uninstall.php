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

require_once __DIR__ . '/vendor/autoload.php';

// Module Kiosques : retirer la capacité custom de tous les rôles.
\JDE\Modules\Kiosques\Capabilities::removeFromAllRoles();

// Ancien module Bénévoles : exécuter la purge complète au cas où le site
// ne se serait pas encore mis à jour vers la version qui retirait le module
// (la routine est idempotente grâce à son drapeau persistant et ne fera
// rien si elle a déjà tourné).
\JDE\Support\BenevolesPurge::run();
delete_option( \JDE\Support\BenevolesPurge::FLAG_OPTION );

// Note : les tables BD du module Kiosques ne sont volontairement pas
// supprimées ici pour éviter une perte de données accidentelle. Pour un
// nettoyage complet, utiliser WP-CLI : `wp db query "DROP TABLE wp_jde_kiosques_*"`.
