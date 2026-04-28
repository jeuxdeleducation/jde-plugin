# Journal des modifications

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le plugin respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

### Ajouté — Module Kiosques (Phase A)

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
