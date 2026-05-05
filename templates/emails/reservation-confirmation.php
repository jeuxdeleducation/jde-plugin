<?php
/**
 * Corps du courriel « Confirmation de réservation ».
 *
 * Variables attendues :
 *   $nom_entreprise   (string)
 *   $evenement_titre  (string)
 *   $kiosque_numeros  (string[]) — tableau des numéros réservés.
 *   $contact_email    (string)
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:20px;color:#1a1a1a;">
	<?php
	printf(
		/* translators: %s: nom de l'entreprise */
		esc_html__( 'Bonjour %s,', 'jde-plugin' ),
		esc_html( $nom_entreprise )
	);
	?>
</h2>

<p style="margin:0 0 16px;font-size:15px;color:#333333;line-height:1.6;">
	<?php
	printf(
		/* translators: %s: titre de l'événement */
		esc_html__( 'Votre réservation pour l\'événement %s est confirmée.', 'jde-plugin' ),
		'<strong>' . esc_html( $evenement_titre ) . '</strong>'
	);
	?>
</p>

<?php if ( ! empty( $kiosque_numeros ) ) : ?>
<div style="background:#f0faf9;border:1px solid #b0e4e2;border-radius:6px;padding:20px 24px;margin:20px 0;">
	<p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#00b0a8;text-transform:uppercase;letter-spacing:0.5px;">
		<?php
		echo esc_html(
			count( $kiosque_numeros ) === 1
				? __( 'Kiosque réservé', 'jde-plugin' )
				: __( 'Kiosques réservés', 'jde-plugin' )
		);
		?>
	</p>
	<ul style="margin:0;padding:0 0 0 20px;">
		<?php foreach ( $kiosque_numeros as $numero ) : ?>
		<li style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:4px;">
			<?php echo esc_html( $numero ); ?>
		</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>

<p style="margin:0 0 16px;font-size:15px;color:#333333;line-height:1.6;">
	<?php esc_html_e( 'Si vous avez besoin de modifier votre réservation, veuillez contacter notre équipe directement.', 'jde-plugin' ); ?>
</p>

<hr style="border:none;border-top:1px solid #eeeeee;margin:24px 0;">

<p style="margin:0;font-size:13px;color:#666666;line-height:1.5;">
	<?php esc_html_e( 'Des questions ? Contactez-nous à ', 'jde-plugin' ); ?>
	<a href="mailto:<?php echo esc_attr( $contact_email ); ?>"
		style="color:#00b0a8;text-decoration:none;">
		<?php echo esc_html( $contact_email ); ?>
	</a>.
</p>
