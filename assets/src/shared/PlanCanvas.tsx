/**
 * Canvas central — affiche un plan en arrière-plan et y superpose
 * les kiosques en pourcentage.
 *
 * Modes supportés :
 *   - `view`   : lecture seule, aucune interaction.
 *   - `edit`   : sélection d'un kiosque pour édition + drag à la souris
 *                pour déplacer un kiosque sur le plan.
 *   - `select` : sélection multiple par un exposant côté public (mêmes
 *                interactions de clic, mais avec règles de couleurs
 *                différentes selon l'état).
 *
 * Pinch-zoom et pan tactiles via `react-zoom-pan-pinch` (mobile-first).
 * En mode `edit`, le pan global est désactivé pendant qu'on drag un
 * kiosque pour éviter que la carte se déplace en même temps.
 */

import { type ReactNode, useCallback, useMemo, useRef, useState } from 'react';
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

	/**
	 * Mode `edit` uniquement : appelé pendant le drag (pour aperçu
	 * temps réel) et à sa fin (pour persister la nouvelle position).
	 */
	onKiosqueDrag?: ( kiosque: Kiosque, posXPercent: number, posYPercent: number ) => void;
	onKiosqueDragEnd?: ( kiosque: Kiosque, posXPercent: number, posYPercent: number ) => void;

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
		onKiosqueDrag,
		onKiosqueDragEnd,
		overlay,
	} = props;

	const stageRef = useRef< HTMLDivElement | null >( null );
	const [ panDisabled, setPanDisabled ] = useState< boolean >( false );

	const computeVariant = useMemo(
		() => buildVariantResolver( mode, { selectedIds, myReservationIds, takenIds, editingId } ),
		[ mode, selectedIds, myReservationIds, takenIds, editingId ]
	);

	const getStageBounds = useCallback( (): DOMRect | null => {
		return stageRef.current ? stageRef.current.getBoundingClientRect() : null;
	}, [] );

	const handleDragStart = useCallback( (): void => {
		setPanDisabled( true );
	}, [] );

	const handleDragEnd = useCallback(
		( kiosque: Kiosque, x: number, y: number ): void => {
			setPanDisabled( false );
			onKiosqueDragEnd?.( kiosque, x, y );
		},
		[ onKiosqueDragEnd ]
	);

	const draggable = mode === 'edit' && undefined !== onKiosqueDragEnd;

	return (
		<div className={ `jde-canvas jde-canvas--${ mode }` }>
			<TransformWrapper
				initialScale={ 1 }
				minScale={ 0.5 }
				maxScale={ 4 }
				doubleClick={ { disabled: true } }
				wheel={ { step: 0.1 } }
				panning={ { disabled: panDisabled } }
			>
				<TransformComponent
					wrapperClass="jde-canvas__viewport"
					contentClass="jde-canvas__content"
				>
					<div className="jde-canvas__stage" ref={ stageRef }>
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
								draggable={ draggable }
								getBounds={ draggable ? getStageBounds : undefined }
								onDragStart={ draggable ? handleDragStart : undefined }
								onDrag={ draggable ? onKiosqueDrag : undefined }
								onDragEnd={ draggable ? handleDragEnd : undefined }
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
