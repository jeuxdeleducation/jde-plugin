/**
 * Centralise toutes les chaînes UI en français.
 *
 * Le plugin sert un public francophone (fr_CA), donc pas de système i18n
 * runtime — les chaînes sont des constantes TypeScript. Si une vraie
 * internationalisation devenait nécessaire (multi-langues), remplacer
 * par `@wordpress/i18n` (`__()` côté JS).
 */

export const T = {
	// Communs
	cancel: 'Annuler',
	close: 'Fermer',
	confirm: 'Confirmer',
	save: 'Enregistrer',
	delete: 'Supprimer',
	loading: 'Chargement…',
	error: 'Erreur',

	// Admin — éditeur de kiosques
	admin: {
		toolbarSave: 'Enregistrer le plan',
		toolbarSaved: 'Plan enregistré',
		toolbarUnsaved: 'Modifications non enregistrées',
		toolbarZoomIn: 'Zoomer',
		toolbarZoomOut: 'Dézoomer',
		toolbarFit: 'Adapter',
		toolbarHelp:
			'Cliquer-glisser sur une zone vide pour créer un kiosque. Cliquer un kiosque pour le modifier.',
		emptyState:
			'Téléverse un plan ci-dessus, puis clique sur « Mettre à jour » en haut à droite pour activer l\'éditeur.',
		lockedBanner:
			'Plan verrouillé : des réservations existent déjà. Pour modifier les positions, supprime d\'abord les réservations.',
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
			heading: 'Entre ton code d\'accès',
			subheading:
				'Tu dois avoir reçu un code d\'accès par courriel pour réserver tes kiosques.',
			label: 'Code d\'accès',
			placeholder: 'XXXX-XXXX',
			submit: 'Continuer',
			submitting: 'Vérification…',
			errorInvalid: 'Code invalide ou événement non actif.',
			errorRateLimit:
				'Trop de tentatives. Réessaie dans 15 minutes.',
			errorNetwork: 'Impossible de joindre le serveur. Vérifie ta connexion.',
		},
		header: {
			remaining: ( n: number ): string =>
				n === 1
					? '1 kiosque restant'
					: `${ n } kiosques restants`,
			noneRemaining: 'Tous tes kiosques sont réservés',
			logout: 'Quitter',
		},
		bubble: {
			selectButton: 'Sélectionner',
			deselectButton: 'Retirer de ma sélection',
			alreadyMineHeading: 'Tu as réservé ce kiosque',
			alreadyMineMessage: ( email: string ): string =>
				`Pour modifier, contacte notre équipe à ${ email }.`,
			takenHeading: 'Réservé',
			takenByGeneric: 'Ce kiosque est déjà pris.',
			unavailable: 'Indisponible',
			unavailableMessage: 'Ce kiosque n\'est pas disponible pour l\'événement.',
		},
		selectionBar: {
			label: ( n: number ): string =>
				n === 1 ? '1 kiosque sélectionné' : `${ n } kiosques sélectionnés`,
			confirm: 'Confirmer ma sélection',
		},
		confirmDialog: {
			title: 'Confirmer ta sélection',
			body: 'Une fois confirmé, tu ne pourras plus modifier ta réservation. Pour tout changement, il faudra contacter notre équipe.',
			cancel: 'Annuler',
			confirm: 'Confirmer définitivement',
			submitting: 'Enregistrement…',
		},
		conflictModal: {
			title: 'Conflit de réservation',
			body: ( numero: string ): string =>
				`Le kiosque « ${ numero } » vient d\'être réservé par un autre exposant. Le plan a été mis à jour.`,
			body_generic: 'Un ou plusieurs kiosques sélectionnés viennent d\'être pris. Le plan a été mis à jour.',
			button: 'Voir le plan à jour',
		},
		success: {
			title: 'Réservation enregistrée',
			body: 'Merci ! Tes kiosques sont maintenant officiellement réservés.',
		},
		errors: {
			noActiveEvent:
				'Aucun événement n\'est actuellement en cours. Reviens plus tard.',
			quotaExceeded:
				'Tu as atteint le nombre maximum de kiosques que tu peux réserver.',
			eventInactive: 'L\'événement n\'est plus actif. Contacte notre équipe.',
			generic: 'Une erreur s\'est produite. Réessaie ou contacte-nous.',
		},
	},
};

export type Translations = typeof T;
