/**
 * Centralise toutes les chaînes UI en français.
 *
 * Le plugin sert un public francophone (fr_CA), donc pas de système i18n
 * runtime — les chaînes sont des constantes TypeScript. Si une vraie
 * internationalisation devenait nécessaire (multi-langues), remplacer
 * par `@wordpress/i18n` (`__()` côté JS).
 */

// Strings éventuellement surchargées depuis la page Paramètres (injectées via window.jdeKiosques.strings).
const s: Record< string, string | null > =
	(
		window as unknown as {
			jdeKiosques?: { strings?: Record< string, string | null > };
		}
	 ).jdeKiosques?.strings ?? {};

const str = ( key: string, fallback: string ): string =>
	typeof s[ key ] === 'string' && s[ key ] !== null
		? ( s[ key ] as string )
		: fallback;

export const T = {
	// Communs
	cancel: 'Annuler',
	close: 'Fermer',
	confirm: 'Confirmer',
	save: 'Enregistrer',
	delete: 'Supprimer',
	loading: 'Chargement…',
	error: 'Erreur',

	// Admin — réservations
	reservations: {
		title: 'Réservations',
		titleFor: ( evenement: string ): string =>
			`Réservations — ${ evenement }`,
		back: "← Retour à l'événement",
		exportCsv: 'Exporter en CSV',
		add: 'Ajouter manuellement',
		refresh: 'Rafraîchir',
		updatedSecondsAgo: ( s: number ): string =>
			s < 60
				? `Mis à jour il y a ${ s } s`
				: `Mis à jour il y a ${ Math.floor( s / 60 ) } min`,
		stats: ( count: number, total: number ): string =>
			`${ count } réservation(s) sur ${ total } kiosque(s)`,
		empty: 'Aucune réservation pour le moment.',
		columns: {
			entreprise: 'Entreprise',
			kiosque: 'Kiosque',
			date: 'Date',
			source: 'Source',
			notes: 'Notes',
			actions: 'Actions',
		},
		sourceAdmin: ( login: string | null ): string =>
			null !== login ? `Admin (${ login })` : 'Admin',
		sourceExposant: 'Exposant (auto)',
		actionEdit: 'Modifier',
		actionDelete: 'Supprimer',
		form: {
			titleAdd: 'Ajouter une réservation',
			titleEdit: 'Modifier la réservation',
			fieldKiosque: 'Kiosque',
			fieldExposant: 'Exposant',
			fieldNotes: 'Notes admin (optionnelles)',
			fieldBypassQuota:
				"Forcer même si le quota de l'exposant est dépassé",
			selectKiosquePlaceholder: '— choisir un kiosque libre —',
			selectExposantPlaceholder: '— choisir un exposant —',
			noFreeKiosques:
				'Aucun kiosque disponible. Tous sont déjà réservés ou indisponibles.',
			submitting: 'Enregistrement…',
			submitAdd: 'Créer la réservation',
			submitEdit: 'Enregistrer',
		},
		deleteDialog: {
			title: 'Supprimer la réservation',
			body: ( entreprise: string, kiosque: string ): string =>
				`Confirmer la suppression de la réservation de « ${ entreprise } » sur le kiosque ${ kiosque } ?`,
			fieldReason: "Motif (obligatoire, sera consigné dans l'historique)",
			submitting: 'Suppression…',
			submit: 'Supprimer définitivement',
		},
	},

	// Admin — éditeur de kiosques
	admin: {
		toolbarSave: 'Enregistrer le plan',
		toolbarSaved: 'Plan enregistré',
		toolbarUnsaved: 'Modifications non enregistrées',
		toolbarZoomIn: 'Zoomer',
		toolbarZoomOut: 'Dézoomer',
		toolbarFit: 'Adapter',
		toolbarHelp:
			'Cliquer un kiosque pour le modifier. Glisser-déposer un kiosque sur le plan pour le repositionner.',
		emptyState:
			"Téléverse un plan ci-dessus, puis clique sur « Mettre à jour » en haut à droite pour activer l'éditeur.",
		lockedBanner:
			"Plan verrouillé : des réservations existent déjà. Pour modifier les positions, supprime d'abord les réservations.",
		modal: {
			title: 'Modifier le kiosque',
			titleNew: 'Nouveau kiosque',
			fieldNumero: 'Numéro',
			fieldNumeroPlaceholder: 'A-12',
			fieldDimensions: 'Dimensions (optionnel)',
			fieldDimensionsPlaceholder: "10' × 10'",
			fieldNotes: 'Notes (optionnelles)',
			fieldStatut: 'Statut',
			statutDisponible: 'Disponible',
			statutIndisponible: 'Indisponible',
			deleteConfirm:
				'Supprimer ce kiosque ? Toutes les réservations associées seront orphelines.',
		},
	},

	// Public — réservation
	public: {
		title: 'Réservation de kiosques',
		codeForm: {
			heading: str( 'public_code_heading', "Entrez votre code d'accès" ),
			subheading: str(
				'public_code_subheading',
				"Vous devez avoir reçu un code d'accès par courriel pour réserver vos kiosques."
			),
			label: "Code d'accès",
			placeholder: 'XXXX-XXXX',
			submit: 'Continuer',
			submitting: 'Vérification…',
			errorInvalid: str(
				'public_code_error_invalid',
				'Code invalide ou événement non actif.'
			),
			errorRateLimit: str(
				'public_code_error_ratelimit',
				'Trop de tentatives. Réessayez dans 15 minutes.'
			),
			errorNetwork:
				'Impossible de joindre le serveur. Vérifiez votre connexion.',
		},
		header: {
			remaining: ( n: number ): string =>
				n === 1 ? '1 kiosque restant' : `${ n } kiosques restants`,
			noneRemaining: 'Tous vos kiosques sont réservés',
			logout: 'Quitter',
		},
		bubble: {
			selectButton: 'Sélectionner',
			deselectButton: 'Retirer de ma sélection',
			alreadyMineHeading: 'Vous avez réservé ce kiosque',
			alreadyMineMessage: ( email: string ): string =>
				`Pour modifier, contactez notre équipe à ${ email }.`,
			takenHeading: 'Réservé',
			takenByGeneric: 'Ce kiosque est déjà pris.',
			unavailable: 'Indisponible',
			unavailableMessage:
				"Ce kiosque n'est pas disponible pour l'événement.",
		},
		selectionBar: {
			label: ( n: number ): string =>
				n === 1
					? '1 kiosque sélectionné'
					: `${ n } kiosques sélectionnés`,
			confirm: 'Confirmer ma sélection',
		},
		confirmDialog: {
			title: 'Confirmer votre sélection',
			body: 'Une fois confirmé, vous ne pourrez plus modifier votre réservation. Pour tout changement, il faudra contacter notre équipe.',
			cancel: 'Annuler',
			confirm: 'Confirmer définitivement',
			submitting: 'Enregistrement…',
		},
		conflictModal: {
			title: 'Conflit de réservation',
			body: ( numero: string ): string =>
				`Le kiosque « ${ numero } » vient d\'être réservé par un autre exposant. Le plan a été mis à jour.`,
			body_generic:
				"Un ou plusieurs kiosques sélectionnés viennent d'être pris. Le plan a été mis à jour.",
			button: 'Voir le plan à jour',
		},
		success: {
			title: 'Réservation enregistrée',
			body: 'Merci ! Vos kiosques sont maintenant officiellement réservés.',
		},
		quotaReached: {
			title: str(
				'public_quota_title',
				'Tous vos kiosques sont réservés'
			),
			intro: ( n: number ): string => {
				if ( n === 1 ) {
					return str(
						'public_quota_intro_single',
						'Votre kiosque est officiellement réservé.'
					);
				}
				const tpl = str( 'public_quota_intro_plural', '' );
				return tpl !== ''
					? tpl.replace( '{n}', String( n ) )
					: `Vos ${ n } kiosques sont officiellement réservés.`;
			},
			yourBooths: 'Vos kiosques :',
			contactBefore: 'Pour modifier votre réservation, contactez-nous à ',
			contactAfter: '.',
			viewPlan: 'Voir le plan',
			closePlan: 'Fermer le plan',
			logout: 'Quitter',
		},
		submitError:
			"Impossible d'enregistrer la réservation. Réessayez ou contactez-nous.",
		errors: {
			noActiveEvent:
				"Aucun événement n'est actuellement en cours. Revenez plus tard.",
			quotaExceeded:
				'Vous avez atteint le nombre maximum de kiosques que vous pouvez réserver.',
			eventInactive:
				"L'événement n'est plus actif. Contactez notre équipe.",
			generic: "Une erreur s'est produite. Réessayez ou contactez-nous.",
		},
	},
};

export type Translations = typeof T;
