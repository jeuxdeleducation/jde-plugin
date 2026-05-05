# Journal des modifications

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le plugin respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

## [0.5.0] — 2026-05-04

### Ajouté

- **Plusieurs événements actifs simultanément** : la contrainte applicative « un seul événement actif à la fois » a été levée. Activer un événement n'en désactive plus aucun autre.
- **Page Statistiques globale** (admin → Kiosques → Statistiques) : tableau récapitulatif par événement publié — kiosques réservés / total (barre de progression), répartition exposants (complétés / en cours / sans réservation), réservations par source (admin vs exposant), lien vers la page de réservations.
- **Champ courriel sur les exposants** : saisie et modification de l'adresse courriel directement depuis la page Exposants. Stocké en BD (`wp_jde_exposants.courriel`).
- **Bouton « Envoyer le code »** : envoie l'email de code d'accès à l'exposant en un clic. Affiche l'horodatage du dernier envoi (`email_envoye_le`) à côté du bouton.
- **Courriel de confirmation automatique** : quand un exposant self-serve atteint son quota de kiosques, un courriel HTML de confirmation lui est envoyé automatiquement avec la liste de ses kiosques réservés.
- **Templates HTML de courriels** aux couleurs JDE (en-tête sarcelle `#00b0a8`, code d'accès en lime `#cfdd27`) pour le code d'accès et la confirmation de réservation.
- **Page Paramètres** (admin → Kiosques → Paramètres) : configuration de l'expéditeur des courriels, des objets et corps des deux types de courriels, et des messages publics de l'application de réservation (titre, sous-titres, erreurs) — tous éditables sans toucher au code.
- **Messages publics configurables** : les messages de l'app JS (code d'accès, quota atteint, erreurs) sont désormais injectés depuis la page Paramètres via `window.jdeKiosques.strings`. Les valeurs par défaut restent en fallback.
- **Voir le plan en lecture seule** sur l'écran « Tous tes kiosques sont réservés » : bouton « Voir le plan » qui affiche le plan annoté en mode non-interactif (mes kiosques en bleu, pris en gris, disponibles en vert). Bouton « Fermer le plan » pour replier.

### Infrastructure

- Migration BD v2 : ajout des colonnes `courriel` et `email_envoye_le` sur `wp_jde_exposants` via `dbDelta()` (aucune perte de données).
- Nouveau service `EmailService` (wraps `wp_mail()`) avec templates PHP capturés via `ob_start()`.
- Nouveau composant React `ReadOnlyPlanView` réutilisant `PlanCanvas` en mode `view`.

## [0.4.2] — 2026-05-04

### Corrigé

- **Page « Suivi des réservations » sans kiosques visibles** : `fetchKiosques` et `fetchExposants` avalaient leurs erreurs avec un `catch {}` silencieux. Conséquence : si l'une des deux requêtes échouait, le plan apparaissait sans aucun kiosque superposé et le sélecteur du modal de modification était vide (donc impossible de modifier ou changer un kiosque). Les deux fetches surfacent maintenant leurs erreurs dans le bandeau d'avertissement de la page (préfixées « Plan : » ou « Exposants : »).
- **Modification de réservation impossible quand la liste des kiosques est vide** : le sélecteur de kiosque dans le modal d'édition filtrait la liste reçue ; si elle était vide, le kiosque actuel ne pouvait pas être pré-sélectionné. Le modal injecte maintenant un fallback minimal (numéro + id) basé sur la réservation en cours pour qu'on puisse au moins éditer les notes.
- **Plan sans kiosques placés** : nouvel affichage explicite dans `PlanView` (image du plan + message « Aucun kiosque sur ce plan ») au lieu d'un canvas vide silencieux.

## [0.4.1] — 2026-05-04

### Corrigé

- **Confirmation de réservation côté public sans effet** : la modale `.jde-modal` n'avait aucune règle CSS de positionnement dans le bundle public (overlay, footer, body). Le « pop-up » s'affichait inline sous la barre de sélection, le bouton « Confirmer définitivement » était caché derrière la barre sticky et le clic n'aboutissait pas. Les vraies règles `.jde-modal-overlay` (fixed, z-index 100000), `.jde-modal__body` et `.jde-modal__footer` (gap entre Annuler/Confirmer) sont maintenant déclarées. Au passage : la bordure jaune qui « collait » à la section a été retirée.
- **Export CSV des réservations en 403** : le lien `<a target="_blank">` n'envoie pas le header `X-WP-Nonce`, ce qui faisait basculer `rest_cookie_check_errors()` sur l'utilisateur anonyme et bloquait `current_user_can( 'jde_manage_kiosques' )`. Le nonce REST est maintenant ajouté en query string (`?_wpnonce=…`) à `csvUrl`.

### Ajouté — Côté public

- **Titre et description de l'événement visibles** : le titre apparaît au-dessus du nom de l'exposant dans le header, la description (Markdown/HTML rédigée dans l'éditeur WP) s'affiche dans un encart au-dessus du plan. Sert à donner des consignes aux exposants.
- **Écran « Tous tes kiosques sont réservés »** : quand l'exposant atteint son quota, l'app bascule sur un écran final qui liste ses kiosques + invite à contacter l'équipe pour toute modification. Plus de barre de sélection ni de boutons trompeurs.
- **Erreurs de réservation visibles** : les erreurs non-409 (réseau, quota dépassé, etc.) s'affichent désormais dans la modale de confirmation au lieu d'être avalées dans la console.

### Modifié — Côté public

- **Logo retiré du shortcode** : le wrapper du shortcode n'affiche plus le logo JDE — laissé au thème de la page hôte qui le gère déjà.
- **Espace entre le champ « Code d'accès » et le bouton « Continuer »** : le formulaire utilise maintenant un `gap` vertical pour aérer la mise en page.
- **Couleurs sémantiques pour les statuts de kiosques** : palette indépendante de la charte JDE (vert sage / orange terracotta / bleu poudre / gris / rouge brique) pour distinguer disponible / sélectionné / le mien / pris / indisponible d'un coup d'œil.

### Ajouté — Côté admin

- **Drag & drop dans l'éditeur de kiosques** : un kiosque peut maintenant être déplacé sur le plan à la souris (ou au doigt sur tablette), en plus de la saisie des coordonnées en %. Le pan global de la carte est désactivé pendant un drag pour éviter les conflits. Désactivé quand le plan est verrouillé.
- **Édition d'un exposant** : nouvelle ligne d'édition inline dans la page « Gérer les exposants » pour modifier le nom et le nombre de kiosques alloués. Le code d'accès reste immuable.
- **Unicité du nom d'entreprise par événement** : la création et la modification rejettent un nom déjà utilisé pour le même événement (comparaison insensible à la casse).
- **Colonne « Réservés / alloués »** dans la liste des exposants (ex. `2 / 5`) avec mise en évidence si le compte des réservations dépasse le quota — utile après une baisse du quota.
- **Page « Suivi des réservations » sur une seule colonne** : le plan en temps réel est désormais en haut, le tableau des réservations occupe toute la largeur en dessous pour faciliter la lecture.

### Modifié — Côté admin

- **Journal d'audit borné à 100 entrées** : à chaque écriture, `AuditRepository::pruneOldEntries()` supprime les plus anciennes pour éviter la croissance illimitée de la table `wp_jde_audit`.
- **Champ « image de couverture » retiré du CPT Événement** : il ne servait à rien côté public. Suppression de `'thumbnail'` dans le `supports` du CPT.

## [0.4.0] — 2026-05-04

### Ajouté — Module Kiosques (Phase C)

**Page « Réservations » (admin) :**
- Nouvelle page React TS accessible depuis le bouton « Gérer les réservations → » de l'écran d'édition d'événement.
- Layout deux colonnes : tableau des réservations (entreprise, kiosque, date, source, notes, actions) à gauche, plan annoté à droite.
- Polling REST 30 s avec indicateur visuel « Mis à jour il y a Xs ».
- Création manuelle (modale avec sélecteurs Kiosque + Exposant + Notes + case « bypass quota »).
- Modification : permet de déplacer une réservation vers un autre kiosque (delete + create atomique côté serveur).
- Suppression avec motif obligatoire (consigné dans le journal d'audit).
- Bouton « Exporter en CSV » qui télécharge `reservations-<slug>-<date>.csv` (UTF-8 + BOM pour Excel).

**REST API étendue (`namespace jde/v1`) :**
- `GET    /admin/evenements/{id}/reservations` — liste enrichie (jointures entreprise + kiosque + admin login).
- `GET    /admin/evenements/{id}/reservations.csv` — export CSV.
- `GET    /admin/evenements/{id}/exposants` — pour les sélecteurs de modale.
- `POST   /admin/reservations` — création manuelle avec `bypass_quota` optionnel.
- `PUT    /admin/reservations/{id}` — modification (kiosque ou notes).
- `DELETE /admin/reservations/{id}` — suppression avec `reason` requis.

**Journal d'audit complet :**
- Toutes les actions admin sont désormais loggées dans `wp_jde_audit` :
  - `reservation.create` / `reservation.update` / `reservation.transfer` / `reservation.delete`
  - `exposant.create` / `exposant.delete`
  - `evenement.activate` / `evenement.deactivate`
  - `kiosque.save_batch` (avec compteur d'inserts/updates/deletes)
- Nouvelle page « Historique » (admin → Kiosques → Historique) avec filtres (type d'entité, ID entité, préfixe d'action, ID utilisateur) et pagination 50/page. Payload JSON brut consultable via `<details>` repliable.

**Verrouillage automatique du plan :**
- Le plan se déverrouille automatiquement (`_jde_plan_verrouille = false`) quand la dernière réservation d'un événement est supprimée. Symétrique avec le verrouillage à la 1ʳᵉ réservation.

**Améliorations :**
- La colonne « Réservations » de la liste des événements affiche désormais le vrai compteur (au lieu de `0` fixe).

**Infrastructure :**
- Nouveau modèle `ReservationDetail` (jointures pour les vues admin).
- Nouveau modèle `AuditEntry` (entrées de journal avec user_login joint).
- Nouveau service `CsvExporter` qui stream sur `php://output`.
- Extension de `ReservationService` avec `update()` et `delete()`.
- 3ᵉ bundle TS : `admin-reservations` (42 KB minifié).

## [0.3.1] — 2026-05-02

### Corrigé (critique)

- **Bundles React absents du ZIP de release v0.3.0** : `release.yml` utilisait `--exclude='build'` pour exclure le dossier de staging, mais ce pattern relatif excluait aussi `assets/build/` qui contient les bundles compilés. L'éditeur de kiosques apparaissait donc vide après installation. Correctif : ancrer l'exclusion à la racine avec `--exclude='/build'`.

### Ajouté

- **Charte graphique Jeux de l'Éducation intégrée** :
  - Palette officielle (sarcelle `#00b0a8`, sarcelle profonde `#008285`, lime `#cfdd27`, jaune `#ffe300`, fond crème vert `#f5faee`, etc.) appliquée via les variables CSS de `assets/src/shared/brand.scss`.
  - Polices Space Grotesk (titres) et Inter (corps) embarquées localement dans `assets/fonts/`.
  - Logo officiel ajouté dans `assets/images/` et affiché en en-tête de la page publique de réservation.
  - États visuels des kiosques mis à jour avec les couleurs JDE (disponible = sarcelle, sélectionné = jaune, etc.).

### Modifié

- Message d'aide quand aucun plan n'est associé à un événement : indique explicitement de cliquer sur « Mettre à jour » après le téléversement (avant, l'utilisateur pouvait croire que l'éditeur ne fonctionnait pas).

## [0.3.0] — 2026-04-30

### Ajouté — Module Kiosques (Phase B)

**Côté admin :**
- Canvas React TypeScript dans la métabox d'édition d'événement (mode `edit`) :
  ajouter/éditer/supprimer des kiosques, formulaire complet (numéro, position en %, taille en %, dimensions texte, notes, statut), sauvegarde groupée.
- Bandeau d'avertissement « plan verrouillé » quand `_jde_plan_verrouille` est vrai.

**Côté public :**
- Shortcode `[jde_reservation_kiosques]` pour afficher l'app de réservation sur n'importe quelle page WordPress.
- Application React TypeScript publique :
  - Écran d'authentification par code (auto-format `XXXX-XXXX`, gestion erreurs 401/429).
  - Header avec nom entreprise, kiosques restants, bouton Quitter.
  - Canvas mode `select` avec pinch-zoom mobile-friendly (`react-zoom-pan-pinch`).
  - Bulle d'info au clic sur un kiosque (numéro, dimensions, notes), avec actions selon l'état.
  - Sélection multiple + barre flottante de confirmation.
  - Modale d'avertissement « tu ne pourras plus modifier ».
  - Modale rouge bloquante en cas de conflit (HTTP 409) avec rafraîchissement automatique du plan.
  - Écran de succès après création réussie.

**REST API (`namespace jde/v1`) :**
- `POST /auth/code` — authentifier avec un code, poser cookie session, retourner état.
- `DELETE /auth/session` — déconnexion.
- `GET /me` — état courant pour la session active.
- `POST /reservations` — créer une réservation atomiquement.
- `GET /admin/evenements/{id}/kiosques` — liste des kiosques (admin).
- `POST /admin/evenements/{id}/kiosques` — sauvegarde groupée (sémantique de remplacement, admin).

**Sécurité :**
- Sessions par cookie `HttpOnly + Secure (si HTTPS) + SameSite=Lax`, durée 7 jours, mappées vers `exposant_id` via transient.
- Rate limit `5 tentatives par IP / 15 min` sur `/auth/code` (fixed window via transients).
- Insertion atomique des réservations via contrainte UNIQUE en BD ; détection MySQL 1062 → HTTP 409 avec `fresh_state` du plan attaché au payload.
- Verrouillage automatique du plan (`_jde_plan_verrouille = true`) à la 1ʳᵉ réservation d'un événement.

**Identité visuelle :**
- Variables CSS dans `assets/src/shared/brand.scss` (couleurs, polices, espacements) — placeholders neutres en attendant la charte officielle JDE.
- Slot logo dans le template public (`templates/public/reservation-app.php`).
- Toutes les chaînes UI centralisées dans `assets/src/shared/i18n.ts` pour faciliter la révision typographique.

**Infrastructure :**
- Configuration TypeScript stricte (`tsconfig.json`) avec `noEmit` (transpilation par Babel via `@wordpress/scripts`).
- `webpack.config.js` avec multi-entrées pour les deux bundles.
- Dépendance `react-zoom-pan-pinch` (10 kB, MIT) pour le canvas tactile.
- `npm run typecheck` valide les types sans toucher aux bundles.

**Tests :**
- 16 nouveaux tests unitaires (ReservationRepository, AuthService, RateLimiter) — 48 tests verts au total.

## [0.2.4] — 2026-04-28

### Corrigé

- **Cause racine du menu admin invisible** : la surcharge custom des capacités du CPT (`'capabilities' => array('edit_post' => 'jde_manage_kiosques', ...)`) où toutes les valeurs étaient identiques empêchait WordPress de distinguer les meta caps des primitives. Lors de `current_user_can('jde_manage_kiosques')`, WP trouvait `jde_manage_kiosques` parmi les valeurs du tableau de capacités du CPT, le traitait comme une meta cap à re-mapper sans contexte, et retournait `do_not_allow` → false. Le menu était donc filtré silencieusement même quand l'utilisateur avait la capacité.
- Solution : retrait de la surcharge `capabilities` sur le CPT. WordPress auto-génère désormais les noms standards (`edit_jde_evenements`, `publish_jde_evenements`, etc.) et `Capabilities::addToAdministrator()` les attribue toutes (10 capacités CPT + `jde_manage_kiosques`) au rôle administrateur.

### Modifié

- `Capabilities::allCaps()` : nouvelle méthode qui retourne la liste complète des capacités gérées par le module.
- `Capabilities::removeFromAllRoles()` : retire désormais toutes les capacités JDE (CPT + custom) au lieu de seulement `jde_manage_kiosques`.

## [0.2.3] — 2026-04-28

### Investigation

- Menu admin toujours invisible sur certaines installations malgré la 0.2.2. Cette version sert à diagnostiquer :
  - Position du menu passée à `null` (fin de la sidebar) pour exclure tout conflit de position.
  - Nouvelle classe `DiagnosticNotice` qui affiche un bandeau diagnostic complet sur toute page admin lorsque `?jde_debug=1` est ajouté à l'URL. Le bandeau montre l'utilisateur, ses rôles, ses capacités `jde_*`, la présence du menu dans `$menu`/`$submenu`, et l'état du CPT.

## [0.2.2] — 2026-04-28

### Corrigé

- Menu admin « Kiosques » toujours invisible dans certaines installations malgré la 0.2.1 : la cause sous-jacente n'était pas la position 25 mais l'utilisation de l'URL `edit.php?post_type=jde_evenement` comme slug d'`add_menu_page`. Retour au pattern standard avec slug custom (`jde-kiosques`) et callback de redirection vers la liste des événements quand le top-level est cliqué.
- Sous-menu auto-généré (qui aurait été un duplicata du parent) explicitement retiré via `remove_submenu_page()`.

## [0.2.1] — 2026-04-28

### Corrigé

- Menu admin « Kiosques » qui n'apparaissait pas dans certaines installations : la position 25 entrait en conflit avec « Commentaires ». Déplacé en position 26 et passage du slug à l'URL `edit.php?post_type=jde_evenement` pour simplifier le routage.
- Mise à jour des filtres `parent_file` et nouveau filtre `submenu_file` pour conserver le surlignage du menu sur les écrans `post.php` et `post-new.php`.

### Ajouté

- Filet de sécurité : `Capabilities::addToAdministrator()` est désormais appelé à chaque hook `plugins_loaded` (idempotent — ne fait rien si la capacité est déjà attribuée). Couvre les cas où le hook d'activation n'a pas tourné correctement (installation manuelle, problème de permissions, etc.).

## [0.2.0] — 2026-04-28

### Ajouté — Module Kiosques (Phase A, partie 1)

- Schéma BD avec 4 tables (`jde_kiosques`, `jde_exposants`, `jde_reservations`, `jde_audit`) versionné via `Migrator` et l'option `jde_plugin_db_version`.
- Contrainte UNIQUE sur `jde_reservations.kiosque_id` pour garantir atomiquement l'absence de double-réservation (Phase B).
- CPT `jde_evenement` non public, capacités mappées sur `jde_manage_kiosques`.
- Capacité custom `jde_manage_kiosques` ajoutée au rôle administrateur à l'activation.
- Modèles immuables (`Kiosque`, `Exposant`, `Evenement`) avec factories `fromRow` / `fromPost`.
- Repositories pour `Kiosque`, `Exposant`, `Audit`.
- Service `CodeGenerator` produisant des codes lisibles (8 caractères, format `XXXX-XXXX`, charset sans 0/O/1/I) avec garantie d'unicité globale.
- Service `EvenementService` enforçant la règle « un seul événement actif à la fois ».
- Menu admin top-level « Kiosques » avec icône, sous-menus pointant sur les écrans CPT natifs.
- Liste des événements enrichie : colonnes Statut (badge + bouton bascule), Plan ✓/✗, # Exposants, # Réservations.
- Action Activer/Désactiver via `admin_post` avec nonce et redirection.
- Métaboxes sur l'écran d'édition d'événement : « Plan » (téléversement via `wp.media`), « Paramètres » (visibilité noms entreprises), « Liens rapides » (bouton vers la page Exposants).
- Page d'admin hors-menu pour la gestion des exposants : formulaire d'ajout, tableau, bouton « Copier le code » (Clipboard API + fallback), suppression avec confirmation, notifications via transients.
- Interface `ActivatableModule` pour brancher proprement les hooks `onActivate` / `onDeactivate` des modules.
- 16 nouveaux tests unitaires (Migrator, modèles, CodeGenerator, EvenementService) — 32 tests verts au total.

### Reporté

- Canvas React/TypeScript pour le placement visuel des kiosques sur le plan : reporté à `0.3.0` (début de Phase B), où il sera mutualisé avec le canvas de la page publique de réservation.

## [0.1.0] — 2026-04-27

### Ajouté

- Échafaudage initial du plugin (en-tête, constantes, chargement Composer).
- Singleton `JDE\Plugin` orchestrant le cycle de vie WordPress.
- Conteneur de services léger (`JDE\Container`).
- Patron modulaire (`ModuleInterface`, `AbstractModule`, `ModuleRegistry`).
- Services partagés : `Logger`, `Assets` (compatible `@wordpress/scripts`), `Template` (surclassable par le thème).
- Mécanisme de mise à jour automatique depuis les releases GitHub via plugin-update-checker.
- Configuration PHPCS (WordPress-Extra), PHPUnit (10), Brain Monkey.
- CI GitHub Actions : PHPCS + PHPUnit (matrice PHP 8.1/8.2/8.3) + lint JS/CSS.
- Workflow de release automatique : sur tag `vX.Y.Z`, construction et publication du ZIP propre.
- Documentation : `README.md`, `readme.txt`, `CHANGELOG.md`, `CLAUDE.md`.
