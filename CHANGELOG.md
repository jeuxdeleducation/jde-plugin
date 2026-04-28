# Journal des modifications

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le plugin respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

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
