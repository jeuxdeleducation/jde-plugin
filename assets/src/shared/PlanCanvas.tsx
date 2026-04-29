/**
 * Canvas central — affiche un plan en arrière-plan et y superpose
 * les kiosques en pourcentage.
 *
 * Modes supportés :
 *   - `view`   : lecture seule, aucune interaction (utilisé pour Phase A
 *                tant que l'éditeur admin n'est pas branché).
 *   - `edit`   : sélection d'un kiosque pour édition (drag/resize/modal
 *                seront ajoutés en Phase B.4).
 *   - `select` : sélection multiple par un exposant côté public (mêmes
 *                interactions de clic, mais avec règles de couleurs
 *                différentes selon l'état).
 *
 * Pinch-zoom et pan tactiles via `react-zoom-pan-pinch` (mobile-first).
 */

import { type ReactNode, useMemo } from 'react';
import {
	TransformComponent,
	TransformWrapper,
} from 'react-zoom-pan-pinch';
import type { Kiosque } from './types';
import { KiosqueRect, type KiosqueVariant } from './KiosqueRect';

export type CanvasMode = 'edit' | 'select' | 'view';

interface PlanCanvasProps {
	mode: CanvasMode;
	planUrl: string;
	kiosques: Kiosque[];

	/** Mode `select` : ids sélectionnés dans le panier courant. */
	selectedIds?: ReadonlySet< number >;
	/** Mode `select` : ids déjà réservés par l'exposant connecté. */
	myReservationIds?: ReadonlySet< number >;
	/** Mode `select` : ids pris par d'autres. */
	takenIds?: ReadonlySet< number >;

	/** Mode `edit` : id du kiosque actuellement en édition. */
	editingId?: number | null;

	/** Callback de clic sur un kiosque (utilisé en `edit` et `select`). */
	onKiosqueClick?: ( kiosque: Kiosque ) => void;

	/** Surcouches additionnelles (ex. : rectangles fantômes pendant un dessin). */
	overlay?: ReactNode;
}

export function PlanCanvas( props: PlanCanvasProps ): JSX.Element {
	const {
		mode,
		planUrl,
		kiosques,
		selectedIds,
		myReservationIds,
		takenIds,
		editingId,
		onKiosqueClick,
		overlay,
	} = props;

	const computeVariant = useMemo(
		() => buildVariantResolver( mode, { selectedIds, myReservationIds, takenIds, editingId } ),
		[ mode, selectedIds, myReservationIds, takenIds, editingId ]
	);

	return (
		<div className={ `jde-canvas jde-canvas--${ mode }` }>
			<TransformWrapper
				initialScale={ 1 }
				minScale={ 0.5 }
				maxScale={ 4 }
				doubleClick={ { disabled: true } }
				wheel={ { step: 0.1 } }
			>
				<TransformComponent
					wrapperClass="jde-canvas__viewport"
					contentClass="jde-canvas__content"
				>
					<div className="jde-canvas__stage">
						<img
							src={ planUrl }
							alt=""
							className="jde-canvas__plan"
							draggable={ false }
						/>

						{ kiosques.map( ( kiosque ) => (
							<KiosqueRect
								key={ kiosque.id ?? `tmp-${ kiosque.numero }` }
								kiosque={ kiosque }
								variant={ computeVariant( kiosque ) }
								onClick={ onKiosqueClick }
							/>
						) ) }

						{ overlay }
					</div>
				</TransformComponent>
			</TransformWrapper>
		</div>
	);
}

interface VariantContext {
	selectedIds?: ReadonlySet< number >;
	myReservationIds?: ReadonlySet< number >;
	takenIds?: ReadonlySet< number >;
	editingId?: number | null;
}

/**
 * Calcule la classe visuelle d'un kiosque en fonction du mode et de
 * l'état courant.
 */
function buildVariantResolver(
	mode: CanvasMode,
	ctx: VariantContext
): ( k: Kiosque ) => KiosqueVariant {
	return ( kiosque: Kiosque ): KiosqueVariant => {
		if ( kiosque.statut === 'indisponible' ) {
			return 'unavailable';
		}

		if ( mode === 'edit' ) {
			if ( ctx.editingId !== undefined && ctx.editingId === kiosque.id ) {
				return 'edit-selected';
			}
			return 'default';
		}

		if ( mode === 'select' ) {
			const id = kiosque.id;
			if ( id !== null ) {
				if ( ctx.myReservationIds?.has( id ) ) {
					return 'mine';
				}
				if ( ctx.selectedIds?.has( id ) ) {
					return 'selected';
				}
				if ( ctx.takenIds?.has( id ) ) {
					return 'taken';
				}
			}
			return 'available';
		}

		return 'default';
	};
}
