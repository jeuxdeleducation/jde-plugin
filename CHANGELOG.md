# Journal des modifications

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le plugin respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

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
