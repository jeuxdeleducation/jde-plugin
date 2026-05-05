/**
 * Wrapper fetch pour les endpoints REST `jde/v1`.
 *
 * Lit la configuration runtime injectée par PHP (`window.jdeKiosques`),
 * ajoute automatiquement le header `X-WP-Nonce`, et normalise les erreurs
 * en {@link ApiError} pour faciliter la gestion côté composants React.
 */

import type { ApiError, JdeRuntimeConfig } from './types';

/**
 * Erreur typée lancée par les helpers de cette API.
 *
 * Permet aux composants React de faire `catch (e: unknown) { if (e instanceof ApiClientError)` ...
 * pour distinguer les erreurs serveur structurées des erreurs réseau.
 */
export class ApiClientError extends Error {
	public readonly status: number;
	public readonly code: string;
	public readonly data: ApiError[ 'data' ];

	constructor( error: ApiError, status: number ) {
		super( error.message );
		this.name = 'ApiClientError';
		this.status = status;
		this.code = error.code;
		this.data = error.data;
	}
}

function getConfig(): JdeRuntimeConfig {
	if ( ! window.jdeKiosques ) {
		throw new Error(
			"Configuration JDE absente. La page n'a pas chargé les variables runtime."
		);
	}
	return window.jdeKiosques;
}

/**
 * GET / DELETE / POST / PUT typé.
 *
 * @param method Méthode HTTP.
 * @param path   Chemin relatif au namespace `jde/v1` (ex. "auth/code").
 * @param body   Corps JSON optionnel.
 */
async function request< T >(
	method: 'GET' | 'POST' | 'PUT' | 'DELETE',
	path: string,
	body?: unknown
): Promise< T > {
	const config = getConfig();
	const url =
		config.restUrl.replace( /\/$/, '' ) + '/' + path.replace( /^\//, '' );

	const init: RequestInit = {
		method,
		credentials: 'same-origin',
		headers: {
			Accept: 'application/json',
			'X-WP-Nonce': config.restNonce,
		},
	};

	if ( body !== undefined ) {
		init.body = JSON.stringify( body );
		( init.headers as Record< string, string > )[ 'Content-Type' ] =
			'application/json';
	}

	const response = await fetch( url, init );

	if ( response.status === 204 ) {
		return undefined as T;
	}

	let payload: unknown;
	try {
		payload = await response.json();
	} catch {
		throw new ApiClientError(
			{
				code: 'invalid_response',
				message: `Réponse non-JSON du serveur (HTTP ${ response.status }).`,
			},
			response.status
		);
	}

	if ( ! response.ok ) {
		const err = ( payload ?? {} ) as Partial< ApiError >;
		throw new ApiClientError(
			{
				code: err.code ?? 'unknown_error',
				message: err.message ?? 'Erreur inconnue.',
				data: err.data,
			},
			response.status
		);
	}

	return payload as T;
}

export const api = {
	get: < T >( path: string ): Promise< T > => request< T >( 'GET', path ),
	post: < T >( path: string, body?: unknown ): Promise< T > =>
		request< T >( 'POST', path, body ),
	put: < T >( path: string, body?: unknown ): Promise< T > =>
		request< T >( 'PUT', path, body ),
	delete: < T >( path: string ): Promise< T > =>
		request< T >( 'DELETE', path ),
};
