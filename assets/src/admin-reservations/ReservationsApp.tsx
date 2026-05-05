/**
 * Application React principale de la page Réservations admin.
 *
 * - Charge les données via REST (réservations + kiosques + exposants).
 * - Polling toutes les 30 s pour rafraîchir l'état.
 * - Orchestre les modales d'ajout, modification, suppression.
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { api, ApiClientError } from '../shared/api';
import type { Exposant, Kiosque, ReservationDetail } from '../shared/types';
import { T } from '../shared/i18n';
import { ReservationsTable } from './ReservationsTable';
import { PlanView } from './PlanView';
import { ReservationFormModal } from './ReservationFormModal';
import { DeleteDialog } from './DeleteDialog';

const POLL_INTERVAL_MS = 30_000;

interface ReservationsAppProps {
	evenementId: number;
	evenementTitre: string;
	planUrl: string | null;
	csvUrl: string | null;
	backUrl: string | null;
}

interface ListResponse {
	reservations: ReservationDetail[];
}

interface KiosquesResponse {
	kiosques: Kiosque[];
}

interface ExposantsResponse {
	exposants: Exposant[];
}

export function ReservationsApp( props: ReservationsAppProps ): JSX.Element {
	const { evenementId, evenementTitre, planUrl, csvUrl, backUrl } = props;

	const [ reservations, setReservations ] = useState< ReservationDetail[] >(
		[]
	);
	const [ kiosques, setKiosques ] = useState< Kiosque[] >( [] );
	const [ exposants, setExposants ] = useState< Exposant[] >( [] );
	const [ loading, setLoading ] = useState< boolean >( true );
	const [ error, setError ] = useState< string | null >( null );
	const [ lastRefreshAt, setLastRefreshAt ] = useState< number >(
		Date.now()
	);
	const [ secondsAgo, setSecondsAgo ] = useState< number >( 0 );

	const [ creating, setCreating ] = useState< boolean >( false );
	const [ editing, setEditing ] = useState< ReservationDetail | null >(
		null
	);
	const [ deleting, setDeleting ] = useState< ReservationDetail | null >(
		null
	);

	const refreshTimer = useRef< number | null >( null );

	const fetchReservations = useCallback( async (): Promise< void > => {
		try {
			const response = await api.get< ListResponse >(
				`admin/evenements/${ evenementId }/reservations`
			);
			setReservations( response.reservations );
			setLastRefreshAt( Date.now() );
			setError( null );
		} catch ( e ) {
			setError(
				e instanceof ApiClientError
					? e.message
					: T.public.errors.generic
			);
		}
	}, [ evenementId ] );

	const fetchKiosques = useCallback( async (): Promise< void > => {
		try {
			const response = await api.get< KiosquesResponse >(
				`admin/evenements/${ evenementId }/kiosques`
			);
			setKiosques( response.kiosques );
		} catch ( e ) {
			// Ne PAS avaler : la liste alimente le plan ET les sélecteurs
			// des modales. Sans elle, ni l'aperçu ni les modifications ne
			// fonctionnent.
			setError(
				e instanceof ApiClientError
					? `Plan : ${ e.message }`
					: T.public.errors.generic
			);
		}
	}, [ evenementId ] );

	const fetchExposants = useCallback( async (): Promise< void > => {
		try {
			const response = await api.get< ExposantsResponse >(
				`admin/evenements/${ evenementId }/exposants`
			);
			setExposants( response.exposants );
		} catch ( e ) {
			setError(
				e instanceof ApiClientError
					? `Exposants : ${ e.message }`
					: T.public.errors.generic
			);
		}
	}, [ evenementId ] );

	// Chargement initial.
	useEffect( () => {
		void ( async () => {
			await Promise.all( [
				fetchReservations(),
				fetchKiosques(),
				fetchExposants(),
			] );
			setLoading( false );
		} )();
	}, [ fetchReservations, fetchKiosques, fetchExposants ] );

	// Polling 30 s.
	useEffect( () => {
		refreshTimer.current = window.setInterval( () => {
			void fetchReservations();
		}, POLL_INTERVAL_MS );
		return () => {
			if ( null !== refreshTimer.current ) {
				window.clearInterval( refreshTimer.current );
			}
		};
	}, [ fetchReservations ] );

	// Compteur visuel "mis à jour il y a Xs".
	useEffect( () => {
		const tick = window.setInterval( () => {
			setSecondsAgo(
				Math.floor( ( Date.now() - lastRefreshAt ) / 1000 )
			);
		}, 1000 );
		return () => window.clearInterval( tick );
	}, [ lastRefreshAt ] );

	const reservedKiosqueIds = useMemo< Set< number > >(
		() => new Set( reservations.map( ( r ) => r.kiosque_id ) ),
		[ reservations ]
	);

	const handleAfterMutation = useCallback( async (): Promise< void > => {
		await Promise.all( [ fetchReservations(), fetchKiosques() ] );
	}, [ fetchReservations, fetchKiosques ] );

	if ( loading ) {
		return <div className="jde-public__loading">{ T.loading }</div>;
	}

	return (
		<div className="jde-resa">
			<header className="jde-resa__header">
				<div>
					<h1 className="jde-resa__title">
						{ T.reservations.titleFor( evenementTitre ) }
					</h1>
					<p className="jde-resa__stats">
						{ T.reservations.stats(
							reservations.length,
							kiosques.length
						) }
						<span className="jde-resa__refresh">
							{ ' · ' +
								T.reservations.updatedSecondsAgo( secondsAgo ) }
						</span>
					</p>
				</div>
				<div className="jde-resa__header-actions">
					{ backUrl && (
						<a href={ backUrl } className="button">
							{ T.reservations.back }
						</a>
					) }
					{ csvUrl && (
						<a
							href={ csvUrl }
							className="button"
							target="_blank"
							rel="noopener noreferrer"
						>
							{ T.reservations.exportCsv }
						</a>
					) }
					<button
						type="button"
						className="button button-primary"
						onClick={ () => setCreating( true ) }
					>
						+ { T.reservations.add }
					</button>
				</div>
			</header>

			{ error && (
				<div className="notice notice-error">
					<p>{ error }</p>
				</div>
			) }

			<div className="jde-resa__layout">
				<div className="jde-resa__col-plan">
					<PlanView
						planUrl={ planUrl }
						kiosques={ kiosques }
						reservedKiosqueIds={ reservedKiosqueIds }
					/>
				</div>
				<div className="jde-resa__col-table">
					<ReservationsTable
						reservations={ reservations }
						onEdit={ ( r ) => setEditing( r ) }
						onDelete={ ( r ) => setDeleting( r ) }
					/>
				</div>
			</div>

			{ creating && (
				<ReservationFormModal
					mode="create"
					kiosques={ kiosques }
					exposants={ exposants }
					reservedKiosqueIds={ reservedKiosqueIds }
					onClose={ () => setCreating( false ) }
					onSaved={ async () => {
						setCreating( false );
						await handleAfterMutation();
					} }
				/>
			) }

			{ editing && (
				<ReservationFormModal
					mode="edit"
					reservation={ editing }
					kiosques={ kiosques }
					exposants={ exposants }
					reservedKiosqueIds={ reservedKiosqueIds }
					onClose={ () => setEditing( null ) }
					onSaved={ async () => {
						setEditing( null );
						await handleAfterMutation();
					} }
				/>
			) }

			{ deleting && (
				<DeleteDialog
					reservation={ deleting }
					onClose={ () => setDeleting( null ) }
					onDeleted={ async () => {
						setDeleting( null );
						await handleAfterMutation();
					} }
				/>
			) }
		</div>
	);
}
