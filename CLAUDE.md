# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

WordPress plugin for internal use by **Jeux de l'Éducation (JDE)**, a non-profit from Québec. The plugin slug is `jde-plugin` and the main file is `jde-plugin.php`.

## Requirements

- **PHP** 8.1+ (declared in `composer.json` and checked at runtime by `jde_plugin_check_requirements()`)
- **WordPress** 6.4+
- **Composer** for PHP dependencies (PSR-4 autoload under namespace `JDE\`)
- **Node 20+** for the front-end build (`@wordpress/scripts`)

## Development Environment

This plugin is developed against a local WordPress installation. The recommended local stack is [LocalWP](https://localwp.com/) or [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

With `wp-env` (requires Docker):
```bash
npm install                  # install JS dependencies
npx wp-env start             # start local WordPress at http://localhost:8888
npx wp-env stop              # stop
npx wp-env destroy           # destroy containers and volumes
```

## Commands

### PHP

```bash
composer install             # install PHP dependencies (PHPUnit, PHPCS)
composer test                # run PHPUnit test suite
composer test -- --filter TestClassName   # run a single test class
composer lint                # run PHP_CodeSniffer against WordPress Coding Standards
composer lint:fix            # auto-fix PHPCS violations
```

### JavaScript / CSS

```bash
npm install                  # install JS dependencies
npm run build                # production build (minified assets in /build)
npm run start                # development build with file watcher
npm run lint:js              # ESLint
npm run lint:css             # Stylelint
```

### WP-CLI (inside wp-env)

```bash
npx wp-env run cli wp plugin activate jde-plugin
npx wp-env run cli wp plugin deactivate jde-plugin
```

## Architecture

The plugin uses a **modular architecture** built around three things: a singleton bootstrap, a service container, and a module registry. All business logic lives inside *modules* — independent units that register their own WordPress hooks.

```
jde-plugin.php                  # Plugin header, constants, requirements check, bootstrap
uninstall.php                   # Cleanup on plugin deletion (guarded by WP_UNINSTALL_PLUGIN)
src/                            # PSR-4 autoloaded under namespace JDE\
  Plugin.php                    # Singleton — orchestrates lifecycle, registers core services & modules
  Container.php                 # Lightweight DI container (lazy factories + cached instances)
  Modules/
    ModuleInterface.php         # Contract: id() + register(Container)
    AbstractModule.php          # Base class (stores container reference)
    ModuleRegistry.php          # Adds modules, prevents duplicates, calls register() in batch
  Support/
    Logger.php                  # error_log wrapper, gated by WP_DEBUG_LOG
    Assets.php                  # Enqueues @wordpress/scripts builds (reads .asset.php)
    Template.php                # Locates templates (theme override → plugin default)
  Updates/
    GitHubUpdater.php           # Wraps plugin-update-checker for GitHub releases
assets/
  src/                          # Source JS (ESModules) and SCSS — input to wp-scripts
  build/                        # Compiled output — gitignored
templates/                      # Frontend templates, surchargeable from the active theme
languages/                      # .pot / .po / .mo (text-domain: jde-plugin)
tests/
  bootstrap.php                 # PHPUnit bootstrap (autoload + ABSPATH stub)
  Unit/                         # Pure unit tests (no WP, Brain Monkey for hooks)
  Integration/                  # Tests against the WP test suite (future)
.github/workflows/
  ci.yml                        # PHPCS + PHPUnit (matrix 8.1/8.2/8.3) + lint JS/CSS on PR
  release.yml                   # On tag vX.Y.Z: build, ZIP, attach to GitHub release
```

### Lifecycle

1. `jde-plugin.php` runs on every request. It defines constants, checks PHP/WP versions, loads the Composer autoloader, registers activation/deactivation hooks, then calls `JDE\Plugin::instance()->boot()`.
2. `Plugin::boot()` populates the container with shared services and hooks into `plugins_loaded`, `init`, `admin_init`, `rest_api_init`.
3. On `plugins_loaded`: textdomain loads, then `ModuleRegistry::registerAll()` calls `register(Container)` on every module.
4. Modules attach their own WordPress hooks inside `register()` and respond to `init`, `admin_init`, `rest_api_init`, etc.

### Adding a new module

1. Create a class under `src/Modules/<Feature>/<Feature>Module.php` extending `AbstractModule`.
2. Implement `id()` (kebab-case identifier) and `register(Container $c)` (call `parent::register()` first).
3. Inside `register()`, attach hooks: `add_action('init', [$this, 'onInit'])`, etc.
4. Register the module in `JDE\Plugin::registerModules()` by adding `$this->modules->add(new \JDE\Modules\<Feature>\<Feature>Module());`.
5. Pull shared services from `$this->container()` (e.g., `$this->container()->get(\JDE\Support\Logger::class)`).

### Modules with custom database tables

The Kiosques module is the canonical reference for modules that need their own SQL tables. Pattern:

1. **Schema** (`src/Modules/<Feature>/Database/Schema.php`) — one method per `CREATE TABLE` returning the SQL string for `dbDelta()`. dbDelta is picky: each column on its own line, two spaces between `PRIMARY KEY` and the parenthesis, indexes declared with `KEY` not `INDEX`.
2. **Migrator** (`src/Modules/<Feature>/Database/Migrator.php`) — versioned via the option `jde_plugin_db_version`. `run()` is idempotent and gets called both at activation (via `ActivatableModule::onActivate()`) and on every `plugins_loaded` (as a safety net for code-only updates without re-activation).
3. **ActivatableModule interface** — module's `onActivate()` method instantiates Schema + Migrator manually (the container isn't populated yet at activation time).
4. **Repositories** (`src/Modules/<Feature>/Repositories/`) — non-final classes that wrap `wpdb`. All SELECTs use `$wpdb->prepare()` inlined into the call site (not stored in a variable; PHPCS can't follow flow). For multi-line prepared queries, wrap in `// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared` blocks.
5. **Models** (`src/Modules/<Feature>/Models/`) — final readonly classes with `fromRow(array)` factory and `toArray()` serializer. PHP 8.1 named-argument constructors keep call sites readable.

### Modules with activation hooks

Implement `JDE\Modules\ActivatableModule` (interface in `src/Modules/ActivatableModule.php`) in addition to `ModuleInterface`. `Plugin::activate()` and `Plugin::deactivate()` iterate over registered modules and call `onActivate()` / `onDeactivate()` on those that implement it. Capability creation, table creation, etc. go in `onActivate()`.

### Modules with TypeScript / React bundles

The Kiosques module ships two React/TypeScript bundles (`admin-kiosques-editor`, `public-reservation`):

1. **`tsconfig.json`** at root — strict mode, `jsx: react-jsx`, `noEmit: true` (Babel does the actual transpilation; `tsc` only type-checks via `npm run typecheck`).
2. **`webpack.config.js`** at root — extends the default `@wordpress/scripts` config with multiple entry points pointing to `assets/src/<bundle>/index.tsx`.
3. **Source layout**: `assets/src/shared/` for components/utilities reused by multiple bundles; `assets/src/<bundle>/` for bundle-specific code.
4. **Runtime config**: PHP injects `window.jdeKiosques` via `wp_add_inline_script(..., $config, 'before')` containing `restUrl`, `restNonce`, `containerId`, `contactEmail`, plus context-specific fields (e.g. `evenementId`, `planUrl`).
5. **Brand identity**: all colors/fonts go through CSS variables defined in `assets/src/shared/brand.scss`. Components reference `var(--jde-color-primary)` etc., never hardcoded values. Replacing the brand = updating that one file.

### Modules with REST API endpoints

Pattern: extend `JDE\Modules\Kiosques\REST\AbstractController`, register routes in `KiosquesModule` at the `rest_api_init` hook. All routes must:

- Set `permission_callback` (use `[$this, 'adminPermissionCheck']` for admin routes that need `jde_manage_kiosques`, or `__return_true` for public routes that handle their own auth via cookie).
- Provide an `args` JSON Schema for body validation (sanitization is done by WordPress before the callback runs).
- Return `WP_REST_Response` on success, `WP_Error` on failure (use `errorResponse()` helper).
- All public routes use the `X-WP-Nonce` header (the JS `api.ts` wrapper adds it automatically).

For non-JSON responses (CSV, file streams, etc.), the callback method can return `never`: do `nocache_headers()`, set custom headers, write to `php://output`, then `exit;` — this short-circuits the WordPress REST pipeline cleanly. Reference implementation: `AdminReservationsController::exportCsv` + `Services/CsvExporter`.

### Audit logging

All admin write operations are logged through `AuditRepository::log($userId, $action, $entityType, $entityId, $payload)`. Action names follow `<entity>.<verb>` convention (e.g., `reservation.create`, `evenement.activate`, `kiosque.save_batch`). The `wp_jde_audit` table is queryable through `AuditRepository::query($filters, $limit, $offset)` with filters by user, entity type, entity ID, or action prefix. The `AuditPage` (admin → Kiosques → Historique) renders a paginated, filterable view of the journal.

Self-serve (anonymous) operations log with `user_id = 0` to distinguish them from admin actions.

### Long-running admin pages with polling

Pattern used by the Réservations page (`assets/src/admin-reservations/`): mount React, fetch initial state, then `setInterval(pollFn, 30_000)` in a `useEffect` that returns a cleanup. State is replaced wholesale on each poll, no diffing needed. The `lastRefreshAt` timestamp + a per-second `setInterval` displays a "Mis à jour il y a Xs" indicator without re-rendering the data.

### Key conventions

- **PHP autoloading**: PSR-4 via Composer. `JDE\Modules\Adhesions\AdhesionsModule` → `src/Modules/Adhesions/AdhesionsModule.php`.
- **File guard**: every PHP source file starts with `defined( 'ABSPATH' ) || exit;`.
- **Strict types**: every PHP file declares `declare(strict_types=1);` directly under the file docblock.
- **Naming**: methods, properties and local variables use **camelCase** (modern PSR style). WordPress hooks and global helper functions remain `snake_case`. The relevant WPCS naming rules are deliberately disabled in `phpcs.xml.dist` — do not "fix" camelCase to snake_case.
- **Capability checks**: every admin action and REST endpoint calls `current_user_can()` before acting.
- **Nonces**: all forms and AJAX calls use `wp_nonce_field()` / `check_admin_referer()` / `wp_verify_nonce()`.
- **Database**: `$wpdb->prepare()` for every query that touches user input; never interpolate.
- **Translations**: wrap user-facing strings in `__()` / `esc_html__()` / `esc_attr__()` with text-domain `jde-plugin`. Locale: `fr_CA`.
- **Assets**: enqueue via the `JDE\Support\Assets` service, which reads the `.asset.php` produced by `@wordpress/scripts`.

## Coding Standards

PHP follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) enforced by PHPCS with the `WordPress-Extra` ruleset (configured in `phpcs.xml.dist`) plus PHPCompatibilityWP for PHP 8.1+ compatibility. JavaScript follows the `@wordpress/eslint-plugin` ruleset.

## Branches et canaux de mise à jour

### Branche `beta` — développement par défaut

**Tout le développement se fait sur la branche `beta` sauf accord explicite du user pour merger sur `main`.** Ne jamais commit directement sur `main` sans cette autorisation.

- `beta` : branche de développement active. Chaque push déclenche `.github/workflows/beta-ci.yml` qui : lint, tests, build, puis publie une **pre-release GitHub** (`v{X.Y.Z}-beta.{run_number}`) avec un ZIP prêt à installer.
- `main` : branche de production. Ne reçoit des commits que lors d'une release officielle approuvée par le user.

### Site bêta — mise à jour automatique depuis `beta`

Le site `beta.jeuxdeleducation.com` est configuré dans son `wp-config.php` avec :

```php
define( 'JDE_BETA_CHANNEL', true );
```

Quand cette constante est définie, `GitHubUpdater` intercepte la requête de plugin-update-checker et la redirige vers l'endpoint `/releases` (toutes les releases, y compris les pre-releases), au lieu de `/releases/latest` (releases officielles seulement). Le site bêta voit ainsi la pre-release `beta-latest` et se met à jour automatiquement à chaque build CI.

### Site production — mise à jour depuis `main` seulement

Sans `JDE_BETA_CHANNEL`, plugin-update-checker utilise `/releases/latest` qui n'inclut jamais les pre-releases. Le site de production est donc immunisé contre les builds bêta.

### Convention de numérotation

- **Sur `beta`** : la version dans `jde-plugin.php` est toujours la prochaine version à livrer (ex. `0.6.0` si `0.5.0` vient d'être releasé). Le CI patche temporairement la version en `0.6.0-beta.{N}` dans le ZIP distribué, sans modifier le fichier commité.
- **Sur `main`** : version de la dernière release officielle.

**Immédiatement après chaque release officielle**, bumper la version sur `beta` vers le prochain palier (`0.5.0` → `0.5.1` ou `0.6.0` selon la nature du prochain cycle).

## Release Process

### Release bêta (automatique)

Chaque push sur `beta` crée automatiquement une pre-release GitHub. Le site bêta se met à jour de lui-même via WordPress. Aucune action manuelle requise.

### Release officielle (accord du user requis)

Quand le user dit **« cette version est prête pour la production »** :

1. Merger `beta` dans `main` (fast-forward si possible).
2. Bumper la version en **deux endroits** dans `jde-plugin.php` (le workflow `release.yml` valide la cohérence avec le tag) :
   - `Version:` dans l'en-tête du plugin
   - constante `JDE_PLUGIN_VERSION`
3. Mettre à jour `CHANGELOG.md` (déplacer les entrées « Non publié » dans une section datée) et `readme.txt` (Stable tag + Changelog).
4. Commit sur `main` : `git commit -m "Préparer la version X.Y.Z"`
5. Tagger et pousser : `git tag vX.Y.Z && git push && git push --tags`
6. Le workflow `.github/workflows/release.yml` se déclenche automatiquement : valide les versions, installe les dépendances sans `--dev`, build les assets, applique `.distignore`, construit un ZIP propre, publie la release GitHub avec le ZIP en pièce jointe.
7. Les sites WordPress détectent la mise à jour dans ~12h ou immédiatement via *Vérifier les mises à jour*.
8. Immédiatement après : retourner sur `beta`, bumper la version vers le prochain palier, pousser.

## Language

Conversations with Claude may happen in English, but **all produced content must be in French (fr_CA)**. This applies to:

- All user-facing strings in PHP and JavaScript
- Comments and docblocks in code
- Documentation files (README, changelogs, inline docs)
- Commit messages
- Admin UI labels, error messages, and help text

## Version Control

Commit and push to `origin/beta` regularly so work is never lost. **Do not commit to `main` without explicit user approval.** Guidelines:

- Commit after each logical unit of work (new feature, bug fix, config change) — do not batch unrelated changes into one commit.
- Commit messages must be in French, in the imperative mood, and describe *why* when the reason is not obvious. Example: `Ajouter le type de contenu Équipe avec champs ACF`.
- Always `git push` after committing — a local-only commit is not backed up.
- Never commit build artifacts (`/build`, `/vendor`) unless they are explicitly required in the repo.
