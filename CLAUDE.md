# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

WordPress plugin for internal use by **Jeux de l'Éducation (JDE)**, a non-profit from Québec. The plugin slug is `jde-plugin` and the main file is `jde-plugin.php`.

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

WordPress plugins follow a hook-based architecture. All business logic is wired to WordPress actions and filters rather than called directly.

```
jde-plugin.php          # Plugin header + bootstrap (defines constants, loads autoloader)
includes/
  class-jde-plugin.php  # Core singleton — registers all hooks on init
  admin/                # WP_List_Table subclasses, settings pages, AJAX handlers
  frontend/             # Shortcodes, blocks, public-facing output
  models/               # Custom post types, taxonomies, and DB table abstractions
  api/                  # REST API endpoints (extend WP_REST_Controller)
assets/
  src/                  # Uncompiled JS (ESModules) and SCSS
  build/                # Compiled output — never edit by hand
languages/              # .pot / .po / .mo translation files (text-domain: jde-plugin)
tests/
  bootstrap.php         # PHPUnit bootstrap that loads WP test suite
  unit/                 # Tests that do not need a DB (mock WP functions)
  integration/          # Tests that run against a real WP test database
```

### Key conventions

- **PHP autoloading**: PSR-4 via Composer; class `JDE\Admin\Settings` lives in `includes/admin/class-settings.php`.
- **Capability checks**: every admin action and REST endpoint must call `current_user_can()` before acting.
- **Nonces**: all forms and AJAX calls must use `wp_nonce_field()` / `check_admin_referer()`.
- **Database**: use `$wpdb` with prepared statements; never interpolate raw user input into queries.
- **Translations**: wrap all user-facing strings in `__()` / `esc_html__()` with text-domain `jde-plugin`. French (fr_CA) is the primary locale.
- **Asset versioning**: pass `filemtime()` of the built file as the version argument to `wp_enqueue_*` in development; use a plugin constant in production.

## Coding Standards

PHP follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) enforced by PHPCS with the `WordPress` ruleset. JavaScript follows the `@wordpress/eslint-plugin` ruleset.

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
