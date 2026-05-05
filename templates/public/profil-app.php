<?php
/**
 * Wrapper HTML du shortcode `[jde_profil_benevole]`.
 *
 * Surchargeable depuis le thème actif via :
 *   `<theme>/jde-plugin/public/profil-app.php`.
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="jde-profil-app">
	<div id="jde-profil-app-root"></div>

	<noscript>
		<p class="jde-profil-app__noscript">
			<?php esc_html_e( 'Cette page nécessite JavaScript pour fonctionner.', 'jde-plugin' ); ?>
		</p>
	</noscript>
</div>
