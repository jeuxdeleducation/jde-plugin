=== JDE Plugin ===
Contributors: jeuxdeleducation
Tags: jde, interne
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 0.2.2
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

= 0.2.2 =
* Correctif : retour à un slug custom (jde-kiosques) avec callback de redirection pour le menu top-level. Le pattern « URL comme slug » testé en 0.2.1 cause l'invisibilité du menu sur certaines installations.

= 0.2.1 =
* Correctif : menu admin « Kiosques » qui n'apparaissait pas dans certaines installations (conflit de position avec Commentaires + filet de sécurité pour la capacité jde_manage_kiosques au cas où le hook d'activation ne tournerait pas).

= 0.2.0 =
* Module Kiosques (Phase A, partie 1) : type de contenu Événements, capacité custom jde_manage_kiosques, gestion des plans, génération et copie de codes d'accès uniques pour les exposants. Le canvas de placement visuel des kiosques est reporté à 0.3.0.

= 0.1.0 =
* Version initiale : échafaudage du plugin (architecture modulaire, conteneur de services, mécanisme de mise à jour depuis GitHub).
