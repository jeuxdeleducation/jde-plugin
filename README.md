# JDE Plugin

Plugin WordPress interne de **Jeux de l'Éducation (JDE)**, un organisme à but non lucratif québécois. Il regroupe les personnalisations et fonctionnalités sur mesure de notre site, et grandit au fil de nos besoins.

> **Usage interne uniquement.** Ce plugin n'est pas distribué publiquement sur WordPress.org.

## Exigences

- **PHP** 8.1 ou supérieur
- **WordPress** 6.4 ou supérieur

## Architecture

Le plugin suit un patron modulaire : chaque fonctionnalité majeure (type de contenu, intégration, surcouche du cœur, etc.) est encapsulée dans un **module** indépendant qui branche ses propres *hooks*. Les services partagés (journalisation, *assets*, *templates*, mises à jour) sont injectés via un conteneur léger.

```
JDE\Plugin (singleton)
   └── JDE\Container (services partagés)
         ├── Logger, Assets, Template, GitHubUpdater
         └── ModuleRegistry
                └── Modules (s'ajoutent ici au fur et à mesure)
```

Voir `CLAUDE.md` pour le détail.

## Développement

```bash
composer install        # Dépendances PHP (PHPUnit, PHPCS, PUC)
npm install             # Dépendances front (@wordpress/scripts)

composer test           # Exécuter les tests PHPUnit
composer lint           # Vérifier les normes de code (WPCS)
composer lint:fix       # Corriger automatiquement
npm run build           # Compiler les assets (assets/src → assets/build)
npm run start           # Build en mode développement (watch)
```

## Mises à jour automatiques

Le plugin se met à jour seul depuis [ses releases GitHub](https://github.com/jeuxdeleducation/jde-plugin/releases) grâce à [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker). Les sites en production reçoivent une notification dans `wp-admin` dès qu'une nouvelle release est publiée.

## Publier une nouvelle version

1. Bumper `Version:` dans `jde-plugin.php` et la constante `JDE_PLUGIN_VERSION`.
2. Mettre à jour `CHANGELOG.md` et `readme.txt`.
3. Commit : `git commit -m "Préparer la version X.Y.Z"`.
4. Tag : `git tag vX.Y.Z && git push --tags`.
5. Le workflow `release.yml` construit le ZIP et publie la release GitHub automatiquement.

## Licence

GPL-2.0-or-later, comme WordPress.
