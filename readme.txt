=== JDE Plugin ===
Contributors: jeuxdeleducation
Tags: jde, interne
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin interne de Jeux de l'Éducation.

== Description ==

Ce plugin regroupe les personnalisations et fonctionnalités sur mesure du site WordPress de Jeux de l'Éducation (JDE), un organisme à but non lucratif québécois.

Il est destiné à un usage strictement interne. Sa structure modulaire permet d'ajouter de nouvelles fonctionnalités au fil du temps sans alourdir le cœur.

== Installation ==

1. Téléverser le dossier `jde-plugin` dans `wp-content/plugins/` (ou utiliser le ZIP de release).
2. Activer le plugin via le menu *Extensions* dans WordPress.
3. Les futures mises à jour sont gérées automatiquement à partir des releases GitHub.

== Changelog ==

= 0.3.1 =
* Correctif critique : le ZIP de release v0.3.0 ne contenait pas le dossier assets/build (les bundles React étaient absents → éditeur de kiosques invisible). Le release.yml a été corrigé pour exclure uniquement le dossier de staging build/ à la racine, pas assets/build/.
* Charte graphique JDE intégrée : couleurs officielles (sarcelle #00b0a8, lime #cfdd27, etc.), polices Space Grotesk + Inter embarquées localement, logo affiché sur la page publique de réservation.
* Message d'aide amélioré dans l'éditeur quand aucun plan n'est encore enregistré : indique de cliquer sur « Mettre à jour » après le téléversement.

= 0.3.0 =
* Phase B livrée : module Kiosques fonctionnel de bout en bout. Canvas React TS pour le placement des kiosques côté admin, application publique de réservation accessible via le shortcode [jde_reservation_kiosques], API REST jde/v1 (auth, état, réservations), sessions par cookie HttpOnly+Secure 7 jours, rate limit 5 tentatives/IP/15min, concurrence atomique avec UI dédiée pour les conflits, verrouillage automatique du plan à la 1ʳᵉ réservation. Identité visuelle prête à recevoir la charte JDE via variables CSS et slot logo.

= 0.2.4 =
* Correctif principal : retrait de la surcharge custom des capacités du CPT qui causait l'invisibilité du menu (current_user_can('jde_manage_kiosques') retournait toujours false). Les capacités primitives auto-générées du CPT (edit_jde_evenements, etc.) sont désormais correctement attribuées au rôle administrateur.

= 0.2.3 =
* Investigation menu invisible : position du menu mise à NULL (fin de la sidebar) pour exclure les conflits de position. Ajout d'un diagnostic temporaire activable via ?jde_debug=1 sur n'importe quelle URL admin.

= 0.2.2 =
* Correctif : retour à un slug custom (jde-kiosques) avec callback de redirection pour le menu top-level. Le pattern « URL comme slug » testé en 0.2.1 cause l'invisibilité du menu sur certaines installations.

= 0.2.1 =
* Correctif : menu admin « Kiosques » qui n'apparaissait pas dans certaines installations (conflit de position avec Commentaires + filet de sécurité pour la capacité jde_manage_kiosques au cas où le hook d'activation ne tournerait pas).

= 0.2.0 =
* Module Kiosques (Phase A, partie 1) : type de contenu Événements, capacité custom jde_manage_kiosques, gestion des plans, génération et copie de codes d'accès uniques pour les exposants. Le canvas de placement visuel des kiosques est reporté à 0.3.0.

= 0.1.0 =
* Version initiale : échafaudage du plugin (architecture modulaire, conteneur de services, mécanisme de mise à jour depuis GitHub).
