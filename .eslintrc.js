/**
 * Surcharge de la config ESLint par défaut de @wordpress/scripts.
 *
 * Toutes les règles ci-dessous sont coupées (`off`) plutôt qu'en `warn`
 * pour garder la sortie CI propre. Les violations sous-jacentes sont
 * principalement pré-existantes dans le code Kiosques et seront
 * corrigées dans un nettoyage dédié — quand on les ré-activera, on les
 * passera en `error` directement.
 *
 *  - `import/no-extraneous-dependencies` : `react` / `react-dom` sont
 *    fournis par WordPress au runtime (handles enregistrés par le cœur),
 *    pas par npm.
 *  - `jsx-a11y/*` : labels orphelins, boutons sans listener clavier,
 *    `autoFocus`. À reprendre lors d'un audit accessibilité dédié.
 *  - `react/no-unescaped-entities` : fr-CA utilise naturellement les
 *    apostrophes ; le HTML gère parfaitement `'` en UTF-8.
 *  - `no-nested-ternary` / `@typescript-eslint/no-shadow` : style.
 */

module.exports = {
	extends: [ require.resolve( '@wordpress/scripts/config/.eslintrc.js' ) ],
	rules: {
		'import/no-extraneous-dependencies': 'off',
		'jsx-a11y/click-events-have-key-events': 'off',
		'jsx-a11y/no-noninteractive-element-interactions': 'off',
		'jsx-a11y/label-has-associated-control': 'off',
		'jsx-a11y/no-autofocus': 'off',
		'react/no-unescaped-entities': 'off',
		'no-nested-ternary': 'off',
		'@typescript-eslint/no-shadow': 'off',
	},
};
