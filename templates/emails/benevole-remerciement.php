<?php
/**
 * Modèle de courriel : remerciement après l'événement.
 *
 * Variables : prenom, evenement_titre, contact_email.
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;

return array(
	'subject'   => __( 'Merci pour votre engagement&nbsp;!', 'jde-plugin' ),
	'html_body' => '
<p>Bonjour <strong>{{prenom}}</strong>,</p>

<p>L\'équipe des <strong>Jeux de l\'Éducation</strong> tient à vous remercier chaleureusement pour votre implication lors de <strong>{{evenement_titre}}</strong>.</p>

<p>Sans des personnes comme vous, ces moments uniques ne pourraient exister. Vos sourires, votre énergie et votre disponibilité ont fait toute la différence.</p>

<p>Si vous souhaitez nous accompagner pour le prochain événement, gardez l\'œil ouvert sur nos communications — votre profil sera réutilisable pour les prochaines éditions.</p>

<p>Merci, encore et toujours.</p>

<p>Avec gratitude,<br/>L\'équipe Jeux de l\'Éducation</p>
',
);
