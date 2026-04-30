/**
 * Bulle d'info qui s'affiche au clic sur un kiosque.
 *
 * Le contenu varie selon l'état du kiosque vu par l'exposant courant :
 *  - disponible       : info + bouton Sélectionner (ou Retirer si déjà sélectionné).
 *  - mes réservations : message « tu ne peux plus modifier, contacte-nous ».
 *  - pris par autrui  : « Réservé » (+ nom entreprise si paramètre activé).
 *  - indisponible     : « Indisponible ».
 */

import type { Kiosque, ReservationLite } from '../shared/types';
import { T } from '../shared/i18n';

export type KiosqueState = 'available' | 'mine' | 'taken' | 'unavailable';

interface KiosqueBubbleProps {
	kiosque: Kiosque;
	state: KiosqueState;
	isSelected: boolean;
	reservation?: ReservationLite | null;
	contactEmail: string;
	showCompanyNames: boolean;
	onSelect: () => void;
	onDeselect: () => void;
	onClose: () => void;
}

export function KiosqueBubble( props: KiosqueBubbleProps ): JSX.Element {
	const {
		kiosque,
		state,
		isSelected,
		reservation,
		contactEmail,
		showCompanyNames,
		onSelect,
		onDeselect,
		onClose,
	} = props;

	return (
		<div className="jde-bubble-overlay" onClick={ onClose } role="presentation">
			<div
				className="jde-bubble"
				role="dialog"
				aria-modal="true"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<button
					type="button"
					className="jde-bubble__close"
					onClick={ onClose }
					aria-label={ T.close }
				>
					×
				</button>

				<div className="jde-bubble__numero">
					Kiosque <strong>{ kiosque.numero }</strong>
				</div>

				{ kiosque.dimensions_texte && (
					<div className="jde-bubble__dimensions">
						{ kiosque.dimensions_texte }
					</div>
				) }

				{ kiosque.notes && (
					<div className="jde-bubble__notes">{ kiosque.notes }</div>
				) }

				{ state === 'available' && (
					<div className="jde-bubble__actions">
						{ isSelected ? (
							<button
								type="button"
								className="jde-button jde-button--ghost"
								onClick={ onDeselect }
							>
								{ T.public.bubble.deselectButton }
							</button>
						) : (
							<button
								type="button"
								className="jde-button jde-button--primary"
								onClick={ onSelect }
							>
								{ T.public.bubble.selectButton }
							</button>
						) }
					</div>
				) }

				{ state === 'mine' && (
					<div className="jde-bubble__message">
						<strong>{ T.public.bubble.alreadyMineHeading }</strong>
						<p>
							{ T.public.bubble.alreadyMineMessage( contactEmail ) }
						</p>
					</div>
				) }

				{ state === 'taken' && (
					<div className="jde-bubble__message">
						<strong>{ T.public.bubble.takenHeading }</strong>
						{ showCompanyNames && reservation?.nom_entreprise ? (
							<p>{ reservation.nom_entreprise }</p>
						) : (
							<p>{ T.public.bubble.takenByGeneric }</p>
						) }
					</div>
				) }

				{ state === 'unavailable' && (
					<div className="jde-bubble__message">
						<strong>{ T.public.bubble.unavailable }</strong>
						<p>{ T.public.bubble.unavailableMessage }</p>
					</div>
				) }
			</div>
		</div>
	);
}
