/**
 * Point d'entrée de l'éditeur de kiosques admin.
 *
 * Monte l'app React dans le container `<div id="jde-kiosques-editor">`
 * rendu par EvenementEditScreen. Lit la configuration runtime injectée
 * par PHP via `window.jdeKiosques`.
 */

import { createRoot } from 'react-dom/client';
import { EditorApp } from './EditorApp';
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

	if ( ! config.evenementId ) {
		// eslint-disable-next-line no-console
		console.error(
			'JDE — evenementId manquant dans la configuration runtime.'
		);
		return;
	}

	const root = createRoot( container );
	root.render(
		<EditorApp
			evenementId={ config.evenementId }
			planUrl={ config.planUrl ?? null }
			planVerrouille={ Boolean( config.planVerrouille ) }
		/>
	);
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrap );
} else {
	bootstrap();
}
