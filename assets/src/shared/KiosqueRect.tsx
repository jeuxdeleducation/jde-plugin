/**
 * Rectangle représentant un kiosque sur le plan.
 *
 * Position et dimensions sont en pourcentage du conteneur parent. Le
 * style visuel dépend de l'état (disponible, mes choix, pris, etc.)
 * via la prop `variant`.
 */

import { type CSSProperties, type MouseEvent } from 'react';
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
}

export function KiosqueRect( {
	kiosque,
	variant,
	label,
	onClick,
}: KiosqueRectProps ): JSX.Element {
	const style: CSSProperties = {
		left: `${ kiosque.pos_x }%`,
		top: `${ kiosque.pos_y }%`,
		width: `${ kiosque.largeur }%`,
		height: `${ kiosque.hauteur }%`,
	};

	const handleClick = onClick
		? ( event: MouseEvent ) => onClick( kiosque, event )
		: undefined;

	return (
		<button
			type="button"
			className={ `jde-kiosque jde-kiosque--${ variant }` }
			style={ style }
			onClick={ handleClick }
			disabled={ ! onClick }
			aria-label={ `Kiosque ${ kiosque.numero }` }
		>
			<span className="jde-kiosque__label">{ label ?? kiosque.numero }</span>
		</button>
	);
}
