/**
 * Vue principale après authentification : header + canvas + barre de sélection.
 */

import { useMemo, useState } from 'react';
import { PlanCanvas } from '../shared/PlanCanvas';
import type { Kiosque, PublicState, ReservationLite } from '../shared/types';
import { T } from '../shared/i18n';
import { Header } from './Header';
import { KiosqueBubble, type KiosqueState } from './KiosqueBubble';

interface ReservationViewProps {
	state: PublicState;
	contactEmail: string;
	onLogout: () => void;
	onConfirm: ( selectedIds: number[] ) => void;
}

export function ReservationView( props: ReservationViewProps ): JSX.Element {
	const { state, contactEmail, onLogout, onConfirm } = props;

	const [ selectedIds, setSelectedIds ] = useState< Set< number > >( new Set() );
	const [ activeBubble, setActiveBubble ] = useState< Kiosque | null >( null );

	// Indexer les sets pour des lookups O(1).
	const myReservationIds = useMemo< Set< number > >(
		() => new Set( state.mes_reservations.map( ( r ) => r.kiosque_id ) ),
		[ state.mes_reservations ]
	);

	const takenIds = useMemo< Set< number > >( () => {
		const taken = new Set< number >();
		for ( const r of state.reservations ) {
			if ( r.exposant_id !== state.exposant.id ) {
				taken.add( r.kiosque_id );
			}
		}
		return taken;
	}, [ state.reservations, state.exposant.id ] );

	const reservationByKiosque = useMemo< Map< number, ReservationLite > >( () => {
		const map = new Map< number, ReservationLite >();
		for ( const r of state.reservations ) {
			map.set( r.kiosque_id, r );
		}
		return map;
	}, [ state.reservations ] );

	const handleKiosqueClick = ( kiosque: Kiosque ): void => {
		setActiveBubble( kiosque );
	};

	const handleBubbleClose = (): void => setActiveBubble( null );

	const handleSelect = (): void => {
		if ( ! activeBubble || activeBubble.id === null ) {
			return;
		}
		const id = activeBubble.id;

		// Empêcher de dépasser le quota.
		const willBe = selectedIds.size + state.mes_reservations.length + 1;
		if ( willBe > state.exposant.nb_kiosques_max ) {
			setActiveBubble( null );
			return;
		}

		setSelectedIds( ( current ) => {
			const next = new Set( current );
			next.add( id );
			return next;
		} );
		setActiveBubble( null );
	};

	const handleDeselect = (): void => {
		if ( ! activeBubble || activeBubble.id === null ) {
			return;
		}
		const id = activeBubble.id;
		setSelectedIds( ( current ) => {
			const next = new Set( current );
			next.delete( id );
			return next;
		} );
		setActiveBubble( null );
	};

	const handleConfirm = (): void => {
		onConfirm( Array.from( selectedIds ) );
	};

	const computeKiosqueState = ( kiosque: Kiosque ): KiosqueState => {
		if ( kiosque.statut === 'indisponible' ) {
			return 'unavailable';
		}
		if ( kiosque.id !== null && myReservationIds.has( kiosque.id ) ) {
			return 'mine';
		}
		if ( kiosque.id !== null && takenIds.has( kiosque.id ) ) {
			return 'taken';
		}
		return 'available';
	};

	return (
		<>
			<Header
				evenementTitre={ state.evenement.titre }
				nomEntreprise={ state.exposant.nom_entreprise }
				kiosquesRestants={ state.kiosques_restants - selectedIds.size }
				onLogout={ onLogout }
			/>

			{ state.evenement.description_html && (
				<div
					className="jde-public__event-description"
					/* eslint-disable-next-line react/no-danger -- contenu rédigé par un admin du site */
					dangerouslySetInnerHTML={ {
						__html: state.evenement.description_html,
					} }
				/>
			) }

			{ ! state.evenement.plan_url ? (
				<div className="jde-public__empty">
					{ T.public.errors.generic }
				</div>
			) : (
				<>
					<PlanCanvas
						mode="select"
						planUrl={ state.evenement.plan_url }
						kiosques={ state.kiosques }
						selectedIds={ selectedIds }
						myReservationIds={ myReservationIds }
						takenIds={ takenIds }
						onKiosqueClick={ handleKiosqueClick }
					/>

					{ selectedIds.size > 0 && (
						<div className="jde-public__selection-bar">
							<span>
								{ T.public.selectionBar.label( selectedIds.size ) }
							</span>
							<button
								type="button"
								className="jde-button jde-button--primary"
								onClick={ handleConfirm }
							>
								{ T.public.selectionBar.confirm }
							</button>
						</div>
					) }
				</>
			) }

			{ activeBubble && (
				<KiosqueBubble
					kiosque={ activeBubble }
					state={ computeKiosqueState( activeBubble ) }
					isSelected={
						activeBubble.id !== null && selectedIds.has( activeBubble.id )
					}
					reservation={
						activeBubble.id !== null
							? reservationByKiosque.get( activeBubble.id ) ?? null
							: null
					}
					contactEmail={ contactEmail }
					showCompanyNames={ state.evenement.afficher_noms_entreprises }
					onSelect={ handleSelect }
					onDeselect={ handleDeselect }
					onClose={ handleBubbleClose }
				/>
			) }
		</>
	);
}
