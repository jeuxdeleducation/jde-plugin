/**
 * Point d'entrée de l'application publique de réservation des kiosques.
 *
 * Monté dans le container `<div id="jde-reservation-app-root">` rendu
 * par le shortcode `[jde_reservation_kiosques]`.
 */

import { createRoot } from 'react-dom/client';
import { PublicApp } from './PublicApp';
import './styles.scss';

function bootstrap(): void {
	const config = window.jdeKiosques;
	if ( ! config ) {
		// eslint-disable-next-line no-console
		console.error( 'JDE — configuration runtime absente.' );
		return;
	}

	const container = document.getElementById( config.containerId );
	if ( ! container ) {
		// eslint-disable-next-line no-console
		console.error(
			`JDE — container #${ config.containerId } introuvable dans la page.`
		);
		return;
	}

	const root = createRoot( container );
	root.render( <PublicApp contactEmail={ config.contactEmail } /> );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrap );
} else {
	bootstrap();
}
