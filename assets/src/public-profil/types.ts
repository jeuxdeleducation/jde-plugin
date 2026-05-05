/**
 * Types TS du bundle profil Bénévoles.
 */

export type TypeRole = 'benevole' | 'jury' | 'arbitre';

export interface JdeBenevolesConfig {
	restUrl: string;
	restNonce: string;
	containerId: string;
	contactEmail: string;
	profilContent: Record< TypeRole, string >;
}

export interface PersonneApi {
	id: number;
	evenement_rh_id: number;
	type_role: TypeRole;
	prenom: string;
	nom: string;
	courriel: string;
	telephone: string | null;
	statut: string;
	wp_user_id: number | null;
	onedrive_url: string | null;
}

export interface AssignationApi {
	id: number;
	statut: 'proposee' | 'acceptee' | 'refusee';
	motif: string | null;
	quart: { date_debut: string; date_fin: string } | null;
	poste: { nom: string; lieu: string | null } | null;
}

export interface SignatureApi {
	id: number;
	personne_id: number;
	type_document: 'entente' | 'lettre';
	signed_at: string;
}

export interface ProfileResponse {
	personne: PersonneApi;
	assignations: AssignationApi[];
	signatures: SignatureApi[];
	doit_signer: { entente: boolean; lettre: boolean };
	evenement_titre: string;
}
