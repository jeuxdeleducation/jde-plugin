/**
 * Rectangle représentant un kiosque sur le plan.
 *
 * Position et dimensions sont en pourcentage du conteneur parent. Le
 * style visuel dépend de l'état (disponible, mes choix, pris, etc.)
 * via la prop `variant`.
 *
 * En mode édition, le composant supporte le drag à la souris et au
 * doigt via les Pointer Events natifs : on capture le pointer pour
 * recevoir tous les `pointermove` même hors du rectangle, et on
 * convertit le delta px → % via le `bounds` (rect du `.jde-canvas__stage`)
 * fourni par PlanCanvas.
 */

import {
	type CSSProperties,
	type MouseEvent,
	type PointerEvent,
	useRef,
	useState,
} from 'react';
import type { Kiosque } from './types';

export type KiosqueVariant =
	| 'available' // mode select : disponible
	| 'mine' // mode select : déjà réservé par moi
	| 'selected' // mode select : sélectionné mais pas encore confirmé
	| 'taken' // mode select : pris par un autre exposant
	| 'unavailable' // tout mode : statut indisponible
	| 'default' // mode edit/view : neutre
	| 'edit-selected'; // mode edit : kiosque sélectionné pour édition

interface KiosqueRectProps {
	kiosque: Kiosque;
	variant: KiosqueVariant;
	label?: string;
	onClick?: ( kiosque: Kiosque, event: MouseEvent ) => void;

	/**
	 * Active le drag à la souris/au doigt. Quand fournie :
	 *   - `getBounds()` doit retourner le DOMRect du stage parent (ou null
	 *     si non monté) pour la conversion px → %.
	 *   - `onDragStart` / `onDragEnd` permettent à PlanCanvas de désactiver
	 *     temporairement le pan global (react-zoom-pan-pinch).
	 *   - `onDrag(kiosque, posX%, posY%)` est appelé pendant le drag.
	 */
	draggable?: boolean;
	getBounds?: () => DOMRect | null;
	onDragStart?: () => void;
	onDrag?: (
		kiosque: Kiosque,
		posXPercent: number,
		posYPercent: number
	) => void;
	onDragEnd?: (
		kiosque: Kiosque,
		posXPercent: number,
		posYPercent: number
	) => void;
}

const DRAG_THRESHOLD_PX = 4;

interface DragSession {
	pointerId: number;
	startClientX: number;
	startClientY: number;
	startPosX: number; // %
	startPosY: number; // %
	moved: boolean;
}

export function KiosqueRect( props: KiosqueRectProps ): JSX.Element {
	const {
		kiosque,
		variant,
		label,
		onClick,
		draggable,
		getBounds,
		onDragStart,
		onDrag,
		onDragEnd,
	} = props;

	const dragRef = useRef< DragSession | null >( null );
	const [ isDragging, setIsDragging ] = useState< boolean >( false );

	const style: CSSProperties = {
		left: `${ kiosque.pos_x }%`,
		top: `${ kiosque.pos_y }%`,
		width: `${ kiosque.largeur }%`,
		height: `${ kiosque.hauteur }%`,
		touchAction: draggable ? 'none' : undefined,
	};

	const handlePointerDown = (
		event: PointerEvent< HTMLButtonElement >
	): void => {
		if ( ! draggable || ! getBounds ) {
			return;
		}
		// Bouton autre que primaire (ex. clic droit) : ignorer.
		if ( event.button !== 0 && event.pointerType === 'mouse' ) {
			return;
		}

		dragRef.current = {
			pointerId: event.pointerId,
			startClientX: event.clientX,
			startClientY: event.clientY,
			startPosX: kiosque.pos_x,
			startPosY: kiosque.pos_y,
			moved: false,
		};

		// Empêche react-zoom-pan-pinch de démarrer son propre pan.
		event.stopPropagation();
		( event.currentTarget as HTMLButtonElement ).setPointerCapture(
			event.pointerId
		);
	};

	const handlePointerMove = (
		event: PointerEvent< HTMLButtonElement >
	): void => {
		const session = dragRef.current;
		if ( ! session || session.pointerId !== event.pointerId ) {
			return;
		}

		const deltaX = event.clientX - session.startClientX;
		const deltaY = event.clientY - session.startClientY;

		if ( ! session.moved ) {
			if (
				Math.abs( deltaX ) < DRAG_THRESHOLD_PX &&
				Math.abs( deltaY ) < DRAG_THRESHOLD_PX
			) {
				return;
			}
			session.moved = true;
			setIsDragging( true );
			onDragStart?.();
		}

		const bounds = getBounds?.() ?? null;
		if ( ! bounds || bounds.width === 0 || bounds.height === 0 ) {
			return;
		}

		const newXPercent = clampPercent(
			session.startPosX + ( deltaX / bounds.width ) * 100,
			kiosque.largeur
		);
		const newYPercent = clampPercent(
			session.startPosY + ( deltaY / bounds.height ) * 100,
			kiosque.hauteur
		);

		onDrag?.( kiosque, newXPercent, newYPercent );
	};

	const finishDrag = ( event: PointerEvent< HTMLButtonElement > ): void => {
		const session = dragRef.current;
		if ( ! session || session.pointerId !== event.pointerId ) {
			return;
		}

		const button = event.currentTarget as HTMLButtonElement;
		try {
			button.releasePointerCapture( event.pointerId );
		} catch {
			// pointer déjà relâché — ok
		}

		dragRef.current = null;

		if ( session.moved ) {
			setIsDragging( false );
			const bounds = getBounds?.() ?? null;
			if ( bounds && bounds.width > 0 && bounds.height > 0 ) {
				const finalX = clampPercent(
					session.startPosX +
						( ( event.clientX - session.startClientX ) /
							bounds.width ) *
							100,
					kiosque.largeur
				);
				const finalY = clampPercent(
					session.startPosY +
						( ( event.clientY - session.startClientY ) /
							bounds.height ) *
							100,
					kiosque.hauteur
				);
				onDragEnd?.( kiosque, finalX, finalY );
			} else {
				onDragEnd?.( kiosque, session.startPosX, session.startPosY );
			}
		}
	};

	const handleClick = ( event: MouseEvent< HTMLButtonElement > ): void => {
		// Si on vient de finir un drag, ne pas déclencher le onClick (qui
		// ouvrirait la modale d'édition par exemple).
		if ( isDragging ) {
			event.preventDefault();
			event.stopPropagation();
			return;
		}
		onClick?.( kiosque, event );
	};

	const buttonDisabled = ! onClick && ! draggable;

	return (
		<button
			type="button"
			className={ `jde-kiosque jde-kiosque--${ variant }${
				isDragging ? ' jde-kiosque--dragging' : ''
			}` }
			style={ style }
			onClick={ buttonDisabled ? undefined : handleClick }
			onPointerDown={ draggable ? handlePointerDown : undefined }
			onPointerMove={ draggable ? handlePointerMove : undefined }
			onPointerUp={ draggable ? finishDrag : undefined }
			onPointerCancel={ draggable ? finishDrag : undefined }
			disabled={ buttonDisabled }
			aria-label={ `Kiosque ${ kiosque.numero }` }
		>
			<span className="jde-kiosque__label">
				{ label ?? kiosque.numero }
			</span>
		</button>
	);
}

/**
 * Clamper une position en % pour que le rectangle reste dans le plan
 * (la dimension est aussi en %, ce qui rend la borne haute simple).
 * @param value
 * @param dimensionPercent
 */
function clampPercent( value: number, dimensionPercent: number ): number {
	const max = Math.max( 0, 100 - dimensionPercent );
	return Math.max( 0, Math.min( max, Math.round( value * 1000 ) / 1000 ) );
}
