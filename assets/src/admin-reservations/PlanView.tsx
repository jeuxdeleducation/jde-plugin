/**
 * Vue du plan annoté des réservations.
 *
 * Réutilise PlanCanvas en mode 'view'. Les kiosques réservés sont
 * surchargés en couleur "mine" (sarcelle profonde) pour les distinguer
 * visuellement des kiosques libres.
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

	// On ré-exploite le mode 'select' avec myReservationIds = tous les
	// kiosques réservés, ce qui les colore en bleu foncé. Les autres
	// apparaissent en vert (disponibles) ou hachuré rouge (indisponibles).
	const memoSet = useMemo( () => reservedKiosqueIds, [ reservedKiosqueIds ] );

	if ( ! planUrl ) {
		return (
			<div className="jde-resa__plan-empty">
				Aucun plan associé à cet événement.
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
