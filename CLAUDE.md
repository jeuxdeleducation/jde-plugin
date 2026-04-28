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

## Release Process

The plugin auto-updates on production sites via `plugin-update-checker` watching the GitHub repo's releases. Only **published GitHub releases** count as new versions — `main` is free to receive in-progress work without affecting production.

When the user says **"this version is ready for production"**:

1. Bump the version in **two places** (the release workflow validates they match the tag):
   - `Version:` in the `jde-plugin.php` header
   - `JDE_PLUGIN_VERSION` constant in `jde-plugin.php`
2. Update `CHANGELOG.md` (move "Non publié" entries into a new dated section) and `readme.txt` (Stable tag + Changelog).
3. Commit: `git commit -m "Préparer la version X.Y.Z"`
4. Tag and push: `git tag vX.Y.Z && git push && git push --tags`
5. The `.github/workflows/release.yml` workflow runs automatically: it validates version coherence, runs `composer install --no-dev`, runs `npm run build`, applies `.distignore`, builds a clean ZIP, and publishes a GitHub release with the ZIP attached.
6. WordPress sites will detect the update within ~12h or instantly via *Vérifier les mises à jour*.

For day-to-day development on `main` (between releases), no action is needed beyond regular commits and pushes.

## Language

Conversations with Claude may happen in English, but **all produced content must be in French (fr_CA)**. This applies to:

- All user-facing strings in PHP and JavaScript
- Comments and docblocks in code
- Documentation files (README, changelogs, inline docs)
- Commit messages
- Admin UI labels, error messages, and help text

## Version Control

Commit and push to `origin/main` regularly so work is never lost. Guidelines:

- Commit after each logical unit of work (new feature, bug fix, config change) — do not batch unrelated changes into one commit.
- Commit messages must be in French, in the imperative mood, and describe *why* when the reason is not obvious. Example: `Ajouter le type de contenu Équipe avec champs ACF`.
- Always `git push` after committing — a local-only commit is not backed up.
- Never commit build artifacts (`/build`, `/vendor`) unless they are explicitly required in the repo.
