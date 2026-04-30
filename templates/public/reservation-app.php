<?php
/**
 * Wrapper HTML du shortcode `[jde_reservation_kiosques]`.
 *
 * Variables exposées par le shortcode :
 *
 * @var string|null $logoUrl URL du logo JDE (à remplir par la charte).
 *
 * Surchargeable depuis le thème actif via :
 *   `<theme>/jde-plugin/public/reservation-app.php`.
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="jde-reservation-app">
	<header class="jde-reservation-app__brand">
		<?php if ( ! empty( $logoUrl ) ) : ?>
			<img
				src="<?php echo esc_url( $logoUrl ); ?>"
				alt="<?php esc_attr_e( 'Jeux de l\'Éducation', 'jde-plugin' ); ?>"
				class="jde-reservation-app__logo"
			/>
		<?php else : ?>
			<span class="jde-reservation-app__brand-text">
				<?php esc_html_e( 'Jeux de l\'Éducation', 'jde-plugin' ); ?>
			</span>
		<?php endif; ?>
	</header>

	<div id="jde-reservation-app-root"></div>

	<noscript>
		<p class="jde-reservation-app__noscript">
			<?php esc_html_e( 'Cette page nécessite JavaScript pour fonctionner. Active-le dans ton navigateur ou contacte info@jeuxdeleducation.com pour de l\'assistance.', 'jde-plugin' ); ?>
		</p>
	</noscript>
</div>
