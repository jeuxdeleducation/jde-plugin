/**
 * Vue du plan en lecture seule pour l'écran « quota atteint ».
 *
 * Affiche le plan avec les kiosques colorés (les miens en bleu, les
 * autres réservés en gris, les disponibles en vert) sans aucune
 * possibilité d'interaction.
 */

import { PlanCanvas } from '../shared/PlanCanvas';
import type { Kiosque, ReservationLite } from '../shared/types';
import { T } from '../shared/i18n';

interface ReadOnlyPlanViewProps {
	planUrl: string;
	kiosques: Kiosque[];
	mesReservations: ReservationLite[];
	allReservations: ReservationLite[];
	exposantId: number;
	onClose: () => void;
}

export function ReadOnlyPlanView( props: ReadOnlyPlanViewProps ): JSX.Element {
	const { planUrl, kiosques, mesReservations, allReservations, exposantId, onClose } = props;

	const myIds = new Set< number >(
		mesReservations.map( ( r ) => r.kiosque_id ).filter( ( id ): id is number => id !== null )
	);
	const takenIds = new Set< number >(
		allReservations
			.filter( ( r ) => r.exposant_id !== exposantId )
			.map( ( r ) => r.kiosque_id )
			.filter( ( id ): id is number => id !== null )
	);

	return (
		<div className="jde-readonly-plan-wrapper">
			<PlanCanvas
				mode="view"
				planUrl={ planUrl }
				kiosques={ kiosques }
				myReservationIds={ myIds }
				takenIds={ takenIds }
			/>
			<div className="jde-readonly-plan-footer">
				<button
					type="button"
					className="jde-button jde-button--ghost"
					onClick={ onClose }
				>
					{ T.public.quotaReached.closePlan }
				</button>
			</div>
		</div>
	);
}
