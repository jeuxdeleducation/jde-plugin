<?php
/**
 * Modèle de courriel : refus d'une candidature.
 *
 * Variables : prenom, nom, evenement_titre, motif (optionnel).
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;

return array(
	'subject'   => __( 'Réponse à votre candidature — {{evenement_titre}}', 'jde-plugin' ),
	'html_body' => '
<p>Bonjour <strong>{{prenom}}</strong>,</p>

<p>Nous vous remercions sincèrement de l\'intérêt que vous avez manifesté pour <strong>{{evenement_titre}}</strong>.</p>

<p>Nous regrettons de vous annoncer que nous ne sommes pas en mesure de retenir votre candidature pour cette édition.</p>

{{#motif}}
<p><strong>Motif&nbsp;:</strong> {{motif}}</p>
{{/motif}}

<p>Nous conservons vos coordonnées pour vous contacter lors de prochains événements si vous le souhaitez. N\'hésitez pas à postuler à nouveau&nbsp;!</p>

<p>Cordialement,<br/>L\'équipe Jeux de l\'Éducation</p>
',
);
