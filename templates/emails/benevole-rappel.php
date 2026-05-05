<?php
/**
 * Modèle de courriel : rappel avant l'événement.
 *
 * Variables : prenom, evenement_titre, compte_a_rebours_jours (optionnel),
 *             contact_email.
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;

return array(
	'subject'   => __( 'Rappel — {{evenement_titre}}', 'jde-plugin' ),
	'html_body' => '
<p>Bonjour <strong>{{prenom}}</strong>,</p>

<p>Petit rappel amical&nbsp;: <strong>{{evenement_titre}}</strong> arrive à grands pas{{#compte_a_rebours_jours}} (encore {{compte_a_rebours_jours}}&nbsp;jours){{/compte_a_rebours_jours}}.</p>

<p>Quelques points à vérifier avant le jour&nbsp;J&nbsp;:</p>
<ul>
	<li>Consultez votre profil pour confirmer vos assignations.</li>
	<li>Si des documents doivent être signés, faites-le dès maintenant.</li>
	<li>Préparez votre trajet et notez l\'horaire de votre premier quart.</li>
</ul>

<p>Pour toute question de dernière minute, écrivez à <a href="mailto:{{contact_email}}">{{contact_email}}</a>.</p>

<p>À très bientôt&nbsp;!<br/>L\'équipe Jeux de l\'Éducation</p>
',
);
