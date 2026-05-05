/**
 * Client REST du bundle profil.
 *
 * Toutes les requêtes ajoutent automatiquement `X-WP-Nonce` et utilisent
 * `credentials: 'same-origin'` pour transmettre les cookies WordPress.
 */

import type { JdeBenevolesConfig, ProfileResponse } from './types';

declare global {
	interface Window {
		jdeBenevoles?: JdeBenevolesConfig;
	}
}

function config(): JdeBenevolesConfig {
	if ( ! window.jdeBenevoles ) {
		throw new Error( 'jdeBenevoles config missing' );
	}
	return window.jdeBenevoles;
}

async function request< T >( path: string, init?: RequestInit ): Promise< T > {
	const cfg = config();
	const url = cfg.restUrl.replace( /\/$/, '' ) + path;
	const response = await fetch( url, {
		credentials: 'same-origin',
		...init,
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': cfg.restNonce,
			...( init?.headers || {} ),
		},
	} );
	const json = await response.json();
	if ( ! response.ok || json.code ) {
		throw new Error( json.message || 'Erreur inattendue' );
	}
	return json as T;
}

export const api = {
	getMe: () => request< ProfileResponse >( '/profil/me' ),

	updateMe: ( payload: { telephone?: string } ) =>
		request< { ok: boolean } >( '/profil/me', {
			method: 'PATCH',
			body: JSON.stringify( payload ),
		} ),

	decideAssignation: (
		id: number,
		decision: 'acceptee' | 'refusee',
		motif?: string
	) =>
		request< { ok: boolean; statut: string } >(
			`/profil/assignations/${ id }/decision`,
			{
				method: 'POST',
				body: JSON.stringify( { decision, motif } ),
			}
		),

	sign: ( type: 'entente' | 'lettre' ) =>
		request< { ok: boolean } >( '/profil/signatures', {
			method: 'POST',
			body: JSON.stringify( { type_document: type } ),
		} ),
};
