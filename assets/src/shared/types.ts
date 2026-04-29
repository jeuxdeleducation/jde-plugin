/**
 * Types TypeScript partagés entre les bundles admin et public.
 *
 * Ces types miroitent les sorties JSON des modèles PHP correspondants
 * (cf. `src/Modules/Kiosques/Models/`). En cas de changement côté PHP,
 * mettre à jour ces types pour conserver la cohérence du contrat.
 */

export type KiosqueStatut = 'disponible' | 'indisponible';

export interface Kiosque {
	id: number | null;
	evenement_id: number;
	numero: string;
	pos_x: number;
	pos_y: number;
	largeur: number;
	hauteur: number;
	dimensions_texte: string | null;
	notes: string | null;
	statut: KiosqueStatut;
	date_creation?: string;
	date_modification?: string;
}

export interface Exposant {
	id: number;
	evenement_id: number;
	nom_entreprise: string;
	nb_kiosques_max: number;
}

export interface Evenement {
	id: number;
	titre: string;
	plan_url: string | null;
	plan_verrouille: boolean;
	afficher_noms_entreprises: boolean;
}

export interface ReservationLite {
	kiosque_id: number;
	exposant_id: number;
	nom_entreprise?: string;
	date_reservation: string;
}

export interface PublicState {
	exposant: Exposant;
	evenement: Evenement;
	kiosques: Kiosque[];
	reservations: ReservationLite[];
	mes_reservations: ReservationLite[];
	kiosques_restants: number;
}

/**
 * Erreur structurée renvoyée par les contrôleurs REST du module.
 * Format inspiré de WP_REST_Response.
 */
export interface ApiError {
	code: string;
	message: string;
	data?: {
		status?: number;
		[key: string]: unknown;
	};
}

/**
 * Constantes de configuration injectées par PHP via `wp_add_inline_script`.
 * Disponibles globalement sur `window.jdeKiosques`.
 */
export interface JdeRuntimeConfig {
	restUrl: string; // ex. "https://site.com/wp-json/jde/v1/"
	restNonce: string; // X-WP-Nonce
	evenementId?: number;
	planUrl?: string;
	planVerrouille?: boolean;
	containerId: string; // id du <div> dans lequel monter l'app
	contactEmail: string; // info@jeuxdeleducation.com
	logoUrl?: string;
}

declare global {
	interface Window {
		jdeKiosques?: JdeRuntimeConfig;
	}
}
