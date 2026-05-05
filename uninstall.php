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

// Module Bénévoles : retirer les capacités custom et supprimer les rôles WP.
\JDE\Modules\Benevoles\Capabilities::removeFromAllRoles();
\JDE\Modules\Benevoles\Capabilities::removeRoles();

// Note : les tables BD des modules ne sont volontairement pas supprimées ici
// pour éviter une perte de données accidentelle. Pour un nettoyage complet,
// utiliser un outil comme WP-CLI : `wp db query "DROP TABLE wp_jde_kiosques, wp_jde_rh_*, ..."`.
