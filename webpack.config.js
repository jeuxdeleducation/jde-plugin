/**
 * Configuration webpack pour @wordpress/scripts.
 *
 * Étend la config par défaut pour déclarer trois points d'entrée :
 *  - admin-kiosques-editor : canvas TS pour placer les kiosques côté admin.
 *  - admin-reservations    : tableau React des réservations admin.
 *  - public-reservation    : application publique de réservation.
 *
 * Tous les bundles sont écrits dans assets/build/ accompagnés de leur
 * fichier .asset.php (lu par JDE\Support\Assets pour fournir les
 * dépendances WP et la version cache-busting).
 *
 * `performance.hints` est désactivé : les fichiers de polices Inter et
 * Space Grotesk pèsent ~850 KiB chacun (variable fonts complets) et
 * dépassent volontairement le seuil de 244 KiB. Ils sont chargés en
 * `font-display: swap` et embarqués pour garantir le rendu offline.
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin-kiosques-editor': path.resolve(
			__dirname,
			'assets/src/admin-kiosques-editor/index.tsx'
		),
		'admin-reservations': path.resolve(
			__dirname,
			'assets/src/admin-reservations/index.tsx'
		),
		'public-reservation': path.resolve(
			__dirname,
			'assets/src/public-reservation/index.tsx'
		),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
		filename: '[name].js',
	},
	performance: {
		hints: false,
	},
};
