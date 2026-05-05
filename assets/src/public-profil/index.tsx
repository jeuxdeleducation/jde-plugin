/**
 * Point d'entrée du bundle React du profil Bénévoles.
 *
 * Le wrapper PHP fournit le conteneur `#jde-profil-app-root` ainsi que
 * la configuration `window.jdeBenevoles`.
 */

import { createRoot } from 'react-dom/client';
import { ProfilApp } from './ProfilApp';
import './styles.scss';

const cfg = window.jdeBenevoles;
if ( cfg ) {
	const target = document.getElementById( cfg.containerId );
	if ( target ) {
		const root = createRoot( target );
		root.render( <ProfilApp /> );
	}
}
