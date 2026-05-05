/**
 * Surcharge de la config ESLint par défaut de @wordpress/scripts (v32+).
 *
 * Format flat config (ESLint 9). On reprend la config par défaut puis
 * on coupe explicitement les règles que l'on ne veut pas voir remontées
 * à la CI bêta — les violations sous-jacentes sont pré-existantes dans
 * le code Kiosques et seront reprises lors d'un nettoyage dédié.
 *
 * Règles désactivées :
 *  - `import/no-extraneous-dependencies` : `react` / `react-dom` sont
 *    fournis par WordPress au runtime, pas par npm.
 *  - `jsx-a11y/*` : labels orphelins, boutons sans listener clavier,
 *    `autoFocus`. À reprendre lors d'un audit accessibilité.
 *  - `react/no-unescaped-entities` : fr-CA utilise les apostrophes ; le
 *    HTML les gère parfaitement en UTF-8.
 *  - `no-nested-ternary`, `@typescript-eslint/no-shadow` : style.
 */

const wpDefault = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wpDefault,
	{
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
	},
];
