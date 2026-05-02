# Journal des modifications

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le plugin respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

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
