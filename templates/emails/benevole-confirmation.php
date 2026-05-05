<?php
/**
 * Modèle de courriel : confirmation d'inscription.
 *
 * Variables : prenom, nom, evenement_titre, contact_email.
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;

return array(
	'subject'   => __( 'Confirmation de votre candidature — {{evenement_titre}}', 'jde-plugin' ),
	'html_body' => '
<p>Bonjour <strong>{{prenom}}</strong>,</p>

<p>Nous avons bien reçu votre candidature pour <strong>{{evenement_titre}}</strong>. Merci de votre intérêt&nbsp;!</p>

<p>Notre équipe l\'examinera dans les prochains jours et vous recevrez une réponse par courriel — acceptation ou refus — accompagnée des prochaines étapes.</p>

<p>Si vous avez des questions, n\'hésitez pas à écrire à <a href="mailto:{{contact_email}}">{{contact_email}}</a>.</p>

<p style="margin-top:24px;">Au plaisir,<br/>L\'équipe Jeux de l\'Éducation</p>
',
);
