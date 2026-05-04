/**
 * Vue du plan annoté des réservations.
 *
 * Réutilise PlanCanvas en mode 'select'. Les kiosques réservés sont
 * surchargés en couleur "mine" (bleu poudre dans la palette sémantique)
 * pour les distinguer visuellement des kiosques libres.
 */

import { useMemo } from 'react';
import { PlanCanvas } from '../shared/PlanCanvas';
import type { Kiosque } from '../shared/types';

interface PlanViewProps {
	planUrl: string | null;
	kiosques: Kiosque[];
	reservedKiosqueIds: ReadonlySet< number >;
}

export function PlanView( props: PlanViewProps ): JSX.Element {
	const { planUrl, kiosques, reservedKiosqueIds } = props;

	const memoSet = useMemo( () => reservedKiosqueIds, [ reservedKiosqueIds ] );

	if ( ! planUrl ) {
		return (
			<div className="jde-resa__plan-empty">
				Aucun plan associé à cet événement.
			</div>
		);
	}

	if ( kiosques.length === 0 ) {
		// Le plan existe mais aucun kiosque n'a encore été placé dessus
		// (ou le chargement de la liste a échoué — l'erreur est alors
		// affichée par ReservationsApp dans le bandeau au-dessus).
		return (
			<div className="jde-resa__plan-wrapper">
				<img
					src={ planUrl }
					alt="Plan de l'événement"
					className="jde-resa__plan-fallback"
				/>
				<p className="jde-resa__plan-empty">
					Aucun kiosque sur ce plan. Ouvre l'éditeur de l'événement
					pour en ajouter.
				</p>
			</div>
		);
	}

	return (
		<PlanCanvas
			mode="select"
			planUrl={ planUrl }
			kiosques={ kiosques }
			myReservationIds={ memoSet }
		/>
	);
}
