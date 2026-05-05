<?php
/**
 * Corps du courriel « Code d'accès ».
 *
 * Variables attendues :
 *   $nom_entreprise       (string)
 *   $code_acces           (string)
 *   $evenement_titre      (string)
 *   $url_reservation      (string) — URL de la page publique de réservation.
 *   $contact_email        (string)
 *   $message_personnalise (string) — message de l'admin (vide = section masquée).
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
		esc_html__( 'Vous avez été inscrit(e) comme exposant pour l\'événement %s. Voici votre code d\'accès pour réserver votre ou vos kiosques.', 'jde-plugin' ),
		'<strong>' . esc_html( $evenement_titre ) . '</strong>'
	);
	?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
	<tr>
		<td align="center">
			<div style="display:inline-block;background:#cfdd27;padding:16px 32px;border-radius:6px;">
				<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#333333;text-transform:uppercase;letter-spacing:1px;">
					<?php esc_html_e( 'Votre code d\'accès', 'jde-plugin' ); ?>
				</p>
				<p style="margin:0;font-size:28px;font-weight:700;color:#1a1a1a;letter-spacing:4px;font-family:monospace;">
					<?php echo esc_html( $code_acces ); ?>
				</p>
			</div>
		</td>
	</tr>
</table>

<p style="margin:0 0 16px;font-size:15px;color:#333333;line-height:1.6;">
	<?php esc_html_e( 'Pour réserver vos kiosques, rendez-vous sur la page de réservation et entrez ce code lorsqu\'il vous sera demandé.', 'jde-plugin' ); ?>
</p>

<?php if ( ! empty( $url_reservation ) ) : ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
	<tr>
		<td align="center">
			<a href="<?php echo esc_url( $url_reservation ); ?>"
				style="display:inline-block;background:#00b0a8;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:5px;font-size:15px;font-weight:700;">
				<?php esc_html_e( 'Accéder à la page de réservation', 'jde-plugin' ); ?>
			</a>
		</td>
	</tr>
</table>
<?php endif; ?>

<?php if ( ! empty( $message_personnalise ) ) : ?>
<div style="background:#f0faf9;border-left:3px solid #00b0a8;padding:12px 16px;margin:20px 0;border-radius:3px;">
	<p style="margin:0;font-size:14px;color:#333333;line-height:1.6;">
		<?php echo nl2br( esc_html( $message_personnalise ) ); ?>
	</p>
</div>
<?php endif; ?>

<hr style="border:none;border-top:1px solid #eeeeee;margin:24px 0;">

<p style="margin:0;font-size:13px;color:#666666;line-height:1.5;">
	<?php esc_html_e( 'Des questions ? Contactez-nous à ', 'jde-plugin' ); ?>
	<a href="mailto:<?php echo esc_attr( $contact_email ); ?>"
		style="color:#00b0a8;text-decoration:none;">
		<?php echo esc_html( $contact_email ); ?>
	</a>.
</p>
