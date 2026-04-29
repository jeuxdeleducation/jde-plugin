/**
 * Configuration webpack pour @wordpress/scripts.
 *
 * Étend la config par défaut pour déclarer deux points d'entrée :
 *  - admin-kiosques-editor : canvas TS pour placer les kiosques côté admin.
 *  - public-reservation    : application publique de réservation.
 *
 * Les deux bundles sont écrits dans assets/build/ accompagnés de leur
 * fichier .asset.php (lu par JDE\Support\Assets pour fournir les
 * dépendances WP et la version cache-busting).
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
};
