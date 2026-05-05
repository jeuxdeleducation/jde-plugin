<?php
/**
 * Modèle de courriel : diffusion ad-hoc depuis le composer admin.
 *
 * Variables : prenom, nom, evenement_titre, message (HTML brut, fourni
 * par le gestionnaire). Le message est rendu sans échappement via
 * `{{!message}}` car il est déjà passé par `wp_kses_post()`.
 *
 * @package JDE
 */

defined( 'ABSPATH' ) || exit;

return array(
	'subject'   => __( '{{evenement_titre}} — message de l\'équipe', 'jde-plugin' ),
	'html_body' => '
<p>Bonjour <strong>{{prenom}}</strong>,</p>

{{!message}}

<p style="margin-top:24px;">L\'équipe Jeux de l\'Éducation</p>
',
);
