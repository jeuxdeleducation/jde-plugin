<?php
/**
 * Modèle de courriel : acceptation d'une candidature.
 *
 * Variables : prenom, nom, evenement_titre, lien_activation_compte,
 *             message_personnalise (optionnel, conditionnel).
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;

return array(
	'subject'   => __( 'Bienvenue dans l\'équipe — {{evenement_titre}}', 'jde-plugin' ),
	'html_body' => '
<p>Bonjour <strong>{{prenom}}</strong>,</p>

<p>Excellente nouvelle&nbsp;! Votre candidature pour <strong>{{evenement_titre}}</strong> a été <strong>acceptée</strong>. 🎉</p>

<p>Un compte a été créé pour vous sur notre site. Pour le configurer (choisir votre mot de passe et accéder à votre profil), utilisez le lien suivant&nbsp;:</p>

<p style="text-align:center;margin:32px 0;">
	<a href="{{lien_activation_compte}}" style="background:#00b0a8;color:#ffffff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">
		Activer mon compte
	</a>
</p>

{{#message_personnalise}}
<div style="background:#f5faee;border-left:4px solid #008285;padding:16px 20px;margin:24px 0;border-radius:4px;">
	{{!message_personnalise}}
</div>
{{/message_personnalise}}

<p>Une fois connecté·e, vous pourrez consulter vos assignations, signer les documents requis et accéder à vos documents personnels.</p>

<p>À très bientôt,<br/>L\'équipe Jeux de l\'Éducation</p>
',
);
