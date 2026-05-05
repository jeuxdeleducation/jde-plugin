<?php
/**
 * Modèle de courriel : nouvelle assignation à un quart.
 *
 * Variables : prenom, evenement_titre, poste_nom, lieu, quart_date,
 *             quart_heure_debut, quart_heure_fin, responsable_nom (optionnel).
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;

return array(
	'subject'   => __( 'Nouvelle assignation — {{evenement_titre}}', 'jde-plugin' ),
	'html_body' => '
<p>Bonjour <strong>{{prenom}}</strong>,</p>

<p>Une nouvelle assignation vous a été proposée pour <strong>{{evenement_titre}}</strong>&nbsp;:</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="background:#f5faee;border-radius:8px;padding:20px;margin:24px 0;width:100%;">
	<tr>
		<td>
			<p style="margin:0 0 8px;font-size:13px;color:#008285;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Poste</p>
			<p style="margin:0 0 16px;font-size:18px;font-weight:600;">{{poste_nom}}</p>

			{{#lieu}}
			<p style="margin:0 0 8px;font-size:13px;color:#008285;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Lieu</p>
			<p style="margin:0 0 16px;">{{lieu}}</p>
			{{/lieu}}

			<p style="margin:0 0 8px;font-size:13px;color:#008285;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Quart</p>
			<p style="margin:0 0 16px;">{{quart_date}} de {{quart_heure_debut}} à {{quart_heure_fin}}</p>

			{{#responsable_nom}}
			<p style="margin:0 0 8px;font-size:13px;color:#008285;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Personne responsable</p>
			<p style="margin:0;">{{responsable_nom}}</p>
			{{/responsable_nom}}
		</td>
	</tr>
</table>

<p>Connectez-vous à votre profil pour <strong>accepter ou refuser</strong> cette assignation. Si vous n\'avez pas répondu d\'ici quelques jours, votre gestionnaire pourra vous relancer.</p>

<p>Merci&nbsp;!<br/>L\'équipe Jeux de l\'Éducation</p>
',
);
