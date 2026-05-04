/**
 * Point d'entrée du bundle admin-reservations.
 *
 * Monte ReservationsApp dans le container fourni par
 * `JDE\Modules\Kiosques\Admin\ReservationsPage::renderPage()`.
 */

import { createRoot } from 'react-dom/client';
import { ReservationsApp } from './ReservationsApp';
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
		console.error( 'JDE — evenementId manquant dans la configuration runtime.' );
		return;
	}

	const root = createRoot( container );
	root.render(
		<ReservationsApp
			evenementId={ config.evenementId }
			evenementTitre={ config.evenementTitre ?? '' }
			planUrl={ config.planUrl ?? null }
			csvUrl={ config.csvUrl ?? null }
			backUrl={ config.backUrl ?? null }
		/>
	);
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrap );
} else {
	bootstrap();
}
