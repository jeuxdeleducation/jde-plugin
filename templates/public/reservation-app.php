<?php
/**
 * Wrapper HTML du shortcode `[jde_reservation_kiosques]`.
 *
 * Le branding (logo, titre du site) est laissé au thème de la page
 * qui héberge le shortcode — on garde ici un wrapper minimal.
 *
 * Surchargeable depuis le thème actif via :
 *   `<theme>/jde-plugin/public/reservation-app.php`.
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="jde-reservation-app">
	<div id="jde-reservation-app-root"></div>

	<noscript>
		<p class="jde-reservation-app__noscript">
			<?php esc_html_e( 'Cette page nécessite JavaScript pour fonctionner. Active-le dans ton navigateur ou contacte info@jeuxdeleducation.com pour de l\'assistance.', 'jde-plugin' ); ?>
		</p>
	</noscript>
</div>
