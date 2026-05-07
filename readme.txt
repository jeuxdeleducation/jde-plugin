=== JDE Plugin ===
Contributors: jeuxdeleducation
Tags: jde, interne
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 0.5.5
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

= 0.5.5 =
* Retrait du module Bénévoles (jamais déployé en production officielle). Une routine de purge ponctuelle nettoie automatiquement toutes les données résiduelles à la mise à jour : 10 tables `wp_jde_rh_*`, options du module, capacités custom, rôles WP, cron de rétention et posts du CPT. Routine idempotente — sera retirée au cycle suivant.

= 0.5.4 =
* Correctif critique : les styles de bouton du thème JDE écrasaient toutes les couleurs de kiosques (affichage lime uniforme). Les règles CSS sont maintenant plus spécifiques que celles du thème — les couleurs sémantiques s'affichent correctement sur la page publique et dans l'admin. Éditeur de plan admin amélioré : les kiosques réservés apparaissent en rouge et les kiosques libres en vert.

= 0.5.3 =
* Correctif : le plan « Voir le plan » (écran quota atteint) affichait tous les kiosques en lime — les couleurs sémantiques s'appliquent maintenant correctement en mode lecture seule. La hachure des kiosques indisponibles n'est plus orange (couleur d'une ancienne palette codée en dur) ; elle s'adapte automatiquement à la couleur de fond.

= 0.5.2 =
* Couleurs des statuts de kiosques remplacées par une palette à fort contraste : vert vif (disponible), ambre (sélectionné), bleu vif (réservé par moi), rouge vif (pris par autrui), gris neutre (indisponible).

= 0.5.1 =
* Vouvoiement complet dans l'app publique de réservation. Label « Code d'accès » retiré au-dessus du champ de saisie. Bouton courriel « Accéder à la page de réservation » pointe vers /reservation-kiosques/. Commentaire personnalisé admin dans l'envoi de code (textarea optionnel — apparaît dans le courriel si renseigné). Sujets et pied de page des courriels mis à jour (vouvoiement + « des Jeux de l'Éducation »).

= 0.5.0 =
* Multi-événements actifs : activer un événement n'en désactive plus aucun autre. Page Statistiques globale par événement (kiosques, exposants, sources). Champ courriel sur les exposants avec bouton « Envoyer le code » (horodatage du dernier envoi). Courriel de confirmation automatique quand le quota est atteint (self-serve). Templates HTML de courriels aux couleurs JDE. Page Paramètres pour configurer expéditeur, objets et corps des courriels, et messages publics de l'app JS. Vue plan en lecture seule sur l'écran quota-atteint. Migration BD v2 (colonnes courriel + email_envoye_le sur wp_jde_exposants).

= 0.4.2 =
* Correctif : la page de suivi des réservations n'affichait aucun kiosque sur le plan et empêchait la modification/suppression d'une réservation. Les requêtes admin/kiosques et admin/exposants avalaient silencieusement leurs erreurs ; elles sont maintenant remontées dans le bandeau d'avertissement. Le modal d'édition fonctionne aussi quand la liste des kiosques n'a pas pu être chargée (fallback sur le kiosque actuel pour permettre l'édition des notes). Affichage explicite quand le plan existe mais qu'aucun kiosque n'a été placé dessus.

= 0.4.1 =
* Correctif : la confirmation de réservation côté public ne fonctionnait pas (modale invisible/cliquable car styles CSS manquants) — la vraie modale centrée est maintenant en place. Correctif : l'export CSV des réservations renvoyait 403 (le lien direct n'envoyait pas le nonce REST) — le nonce est désormais embarqué en query string. Ajouts publics : titre + description de l'événement visibles, écran final « tous tes kiosques sont réservés » avec invitation à contacter l'équipe, couleurs sémantiques des statuts de kiosques (vert/orange/bleu/gris/rouge), suppression du logo JDE du shortcode (laissé au thème), affichage des erreurs de réservation dans la modale au lieu de la console. Ajouts admin : drag & drop des kiosques sur le plan, édition du nom et du quota d'un exposant avec garde-fou sur les noms en double, colonne « Réservés / alloués » dans la liste des exposants, layout 1 colonne (plan en haut) pour la page de suivi, journal d'audit borné à 100 entrées (purge automatique), retrait du champ « image de couverture » du CPT Événement.

= 0.4.0 =
* Phase C livrée : module Kiosques complet pour le jour J. Page Réservations React TS avec polling 30s (suivi temps réel), CRUD manuel admin (créer/modifier/supprimer une réservation), export CSV (UTF-8 + BOM), journal d'audit complet de toutes les actions admin (page Historique avec filtres + pagination), déverrouillage automatique du plan quand la dernière réservation est supprimée. La colonne « Réservations » de la liste des événements affiche maintenant le vrai compteur.

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
