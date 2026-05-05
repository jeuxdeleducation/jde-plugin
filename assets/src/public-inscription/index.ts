/**
 * Bundle vanilla JS du formulaire d'inscription public Bénévoles.
 *
 * Le formulaire HTML est rendu côté serveur par
 * `InscriptionShortcode::render()`. Ce script :
 *   1. intercepte la soumission ;
 *   2. recompose le payload JSON attendu par le REST
 *      (`{ type_role, prenom, nom, courriel, telephone, reponses[], plages[] }`) ;
 *   3. envoie POST /jde/v1/benevoles/inscription avec le X-WP-Nonce ;
 *   4. affiche un message de succès ou l'erreur retournée par le serveur.
 *
 * Aucun framework — la cible est un bundle < 5 KB pour les pages
 * publiques où le temps de chargement compte.
 */

import './styles.scss';

interface JdeBenevolesInscriptionConfig {
	restUrl: string;
	restNonce: string;
	role: string;
	isOpen: boolean;
	evenementTitre: string;
}

declare global {
	interface Window {
		jdeBenevolesInscription?: JdeBenevolesInscriptionConfig;
	}
}

const config = window.jdeBenevolesInscription;
if ( config && config.isOpen ) {
	const form = document.getElementById(
		'jde-inscription-form'
	) as HTMLFormElement | null;
	const feedback = document.getElementById( 'jde-inscription-feedback' );

	if ( form && feedback ) {
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();

			const submitBtn = form.querySelector(
				'button[type="submit"]'
			) as HTMLButtonElement | null;
			if ( submitBtn ) {
				submitBtn.disabled = true;
			}
			feedback.textContent = '';
			feedback.className = 'jde-inscription__feedback';

			const data = collectPayload( form, config.role );

			try {
				const response = await fetch( config.restUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.restNonce,
					},
					body: JSON.stringify( data ),
				} );

				const json = await response.json();

				if ( ! response.ok || json.code ) {
					feedback.className =
						'jde-inscription__feedback jde-inscription__feedback--error';
					feedback.textContent = json.message || 'Erreur inattendue.';
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
					return;
				}

				feedback.className =
					'jde-inscription__feedback jde-inscription__feedback--success';
				feedback.textContent =
					'Merci, votre candidature a bien été reçue. Vous recevrez un courriel de confirmation sous peu.';
				form.reset();
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
			} catch {
				feedback.className =
					'jde-inscription__feedback jde-inscription__feedback--error';
				feedback.textContent =
					'Erreur de connexion. Veuillez réessayer dans un instant.';
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
			}
		} );
	}
}

/**
 * Reconstruit le payload JSON depuis le DOM.
 *
 * Les champs personnalisés sont identifiés par `[data-key]` (ajouté par
 * le PHP au moment du rendu). Chaque champ produit une entrée
 * `{ key, label, value }` dans `reponses[]`.
 * @param form
 * @param role
 */
function collectPayload(
	form: HTMLFormElement,
	role: string
): Record< string, unknown > {
	const formData = new FormData( form );

	const plages: number[] = [];
	formData.getAll( 'plages[]' ).forEach( ( v ) => {
		const n = parseInt( String( v ), 10 );
		if ( ! isNaN( n ) ) {
			plages.push( n );
		}
	} );

	const reponses: Array< { key: string; label: string; value: string } > = [];
	const customs = form.querySelectorAll<
		HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
	>( '[data-key]' );
	const seen = new Set< string >();

	customs.forEach( ( el ) => {
		const key = el.getAttribute( 'data-key' );
		const label = el.getAttribute( 'data-label' ) || '';
		if ( ! key || seen.has( key + ':' + el.tagName ) ) {
			return;
		}

		let value = '';

		if ( el instanceof HTMLInputElement ) {
			if ( el.type === 'radio' ) {
				const checked = form.querySelector< HTMLInputElement >(
					'input[type="radio"][data-key="' + key + '"]:checked'
				);
				value = checked ? checked.value : '';
				seen.add( key + ':INPUT' );
			} else if ( el.type === 'checkbox' ) {
				value = el.checked ? '1' : '';
				seen.add( key + ':INPUT' );
			} else {
				value = el.value;
				seen.add( key + ':INPUT' );
			}
		} else {
			value = el.value;
			seen.add( key + ':' + el.tagName );
		}

		reponses.push( { key, label, value } );
	} );

	return {
		type_role: role,
		prenom: formData.get( 'prenom' ) || '',
		nom: formData.get( 'nom' ) || '',
		courriel: formData.get( 'courriel' ) || '',
		telephone: formData.get( 'telephone' ) || '',
		website: formData.get( 'website' ) || '', // honeypot
		plages,
		reponses,
	};
}
