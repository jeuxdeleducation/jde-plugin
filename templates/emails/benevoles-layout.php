<?php
/**
 * Gabarit HTML de base pour les courriels du module Bénévoles.
 *
 * Reprend la structure du layout du module Kiosques, avec un sous-titre
 * adapté. Variables attendues (locales du `include`) :
 *   $subject  (string) — titre du courriel.
 *   $bodyHtml (string) — contenu HTML principal (déjà rendu par
 *                        EmailRenderer, peut contenir des sections
 *                        conditionnelles déjà résolues).
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
<title><?php echo esc_html( (string) $subject ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#1c1c1c;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 16px;">
	<tr>
		<td align="center">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
				<tr>
					<td style="background:#00b0a8;padding:24px 32px;">
						<p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">
							Jeux de l'Éducation
						</p>
						<p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,0.85);">
							<?php esc_html_e( 'Personnel d\'événement', 'jde-plugin' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<td style="padding:32px;font-size:15px;line-height:1.55;">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo (string) $bodyHtml;
						?>
					</td>
				</tr>
				<tr>
					<td style="background:#f8f8f8;padding:20px 32px;border-top:1px solid #eeeeee;">
						<p style="margin:0;font-size:12px;color:#888888;line-height:1.5;">
							<?php esc_html_e( 'Ce courriel a été envoyé automatiquement. Pour toute question, écrivez à votre gestionnaire.', 'jde-plugin' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
