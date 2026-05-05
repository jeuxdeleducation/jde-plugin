<?php
/**
 * Gabarit HTML de base pour les courriels du plugin JDE.
 *
 * Variables attendues :
 *   $title   (string) — titre du courriel (balise <title>).
 *   $content (string) — contenu HTML principal (déjà échappé ou HTML sûr).
 *   $footer  (string) — texte de pied de page (optionnel).
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="fr-CA">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $title ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 16px;">
	<tr>
		<td align="center">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
				<!-- En-tête -->
				<tr>
					<td style="background:#00b0a8;padding:24px 32px;">
						<p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">
							Jeux de l'Éducation
						</p>
						<p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,0.8);">
							Gestion des kiosques
						</p>
					</td>
				</tr>
				<!-- Contenu -->
				<tr>
					<td style="padding:32px;">
						<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — HTML déjà construit par les templates ?>
					</td>
				</tr>
				<!-- Pied de page -->
				<tr>
					<td style="background:#f8f8f8;padding:20px 32px;border-top:1px solid #eeeeee;">
						<p style="margin:0;font-size:12px;color:#888888;line-height:1.5;">
							<?php
							if ( ! empty( $footer ) ) {
								echo esc_html( $footer );
							} else {
								echo esc_html__( 'Ce courriel a été envoyé automatiquement par le système de gestion des kiosques des Jeux de l\'Éducation. Ne pas répondre à ce message.', 'jde-plugin' );
							}
							?>
						</p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
