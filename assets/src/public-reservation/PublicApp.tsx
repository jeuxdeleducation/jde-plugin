/**
 * Application React publique de réservation des kiosques.
 *
 * Orchestre les écrans (CodeEntryForm vs ReservationView vs QuotaReached),
 * la confirmation, la création de réservation et la gestion du conflit 409.
 */

import { useCallback, useEffect, useState } from 'react';
import { api, ApiClientError } from '../shared/api';
import type { PublicState } from '../shared/types';
import { T } from '../shared/i18n';
import { CodeEntryForm } from './CodeEntryForm';
import { ConfirmDialog } from './ConfirmDialog';
import { ConflictModal } from './ConflictModal';
import { ReadOnlyPlanView } from './ReadOnlyPlanView';
import { ReservationView } from './ReservationView';

interface PublicAppProps {
	contactEmail: string;
}

export function PublicApp( { contactEmail }: PublicAppProps ): JSX.Element {
	const [ state, setState ] = useState< PublicState | null >( null );
	const [ loading, setLoading ] = useState< boolean >( true );
	const [ pendingIds, setPendingIds ] = useState< number[] | null >( null );
	const [ submitting, setSubmitting ] = useState< boolean >( false );
	const [ submitError, setSubmitError ] = useState< string | null >( null );
	const [ success, setSuccess ] = useState< boolean >( false );
	const [ conflictNumero, setConflictNumero ] = useState< string | null >(
		null
	);

	useEffect( () => {
		void ( async () => {
			try {
				const fresh = await api.get< PublicState >( 'me' );
				setState( fresh );
			} catch {
				// Pas authentifié : on affichera CodeEntryForm.
			} finally {
				setLoading( false );
			}
		} )();
	}, [] );

	const handleAuthenticated = useCallback( ( fresh: PublicState ): void => {
		setState( fresh );
	}, [] );

	const handleLogout = useCallback( async (): Promise< void > => {
		try {
			await api.delete( 'auth/session' );
		} catch {
			// Best effort.
		}
		setState( null );
		setPendingIds( null );
		setSuccess( false );
		setSubmitError( null );
	}, [] );

	const handleStartConfirm = useCallback( ( ids: number[] ): void => {
		setSubmitError( null );
		setPendingIds( ids );
	}, [] );

	const handleCancelConfirm = useCallback( (): void => {
		setPendingIds( null );
	}, [] );

	const handleFinalConfirm = useCallback( async (): Promise< void > => {
		if ( ! pendingIds || pendingIds.length === 0 || ! state ) {
			return;
		}
		setSubmitting( true );
		setSubmitError( null );

		let latestState: PublicState = state;

		try {
			for ( const kiosqueId of pendingIds ) {
				try {
					latestState = await api.post< PublicState >(
						'reservations',
						{
							kiosque_id: kiosqueId,
						}
					);
				} catch ( e ) {
					if ( e instanceof ApiClientError && e.status === 409 ) {
						const fresh = (
							e.data as { fresh_state?: PublicState } | undefined
						 )?.fresh_state;
						if ( fresh ) {
							latestState = fresh;
						}
						const numero =
							latestState.kiosques.find(
								( k ) => k.id === kiosqueId
							)?.numero ?? null;
						setConflictNumero( numero );
						setState( latestState );
						setPendingIds( null );
						return;
					}
					throw e;
				}
			}
			setState( latestState );
			setPendingIds( null );
			setSuccess( true );
		} catch ( e ) {
			// Toute autre erreur (réseau, 422 quota, 500, etc.) — on l'affiche
			// dans la modale au lieu de l'avaler silencieusement.
			const message =
				e instanceof ApiClientError && e.message
					? e.message
					: T.public.submitError;
			setSubmitError( message );
			// eslint-disable-next-line no-console
			console.error( 'JDE — erreur de réservation :', e );
		} finally {
			setSubmitting( false );
		}
	}, [ pendingIds, state ] );

	if ( loading ) {
		return <div className="jde-public__loading">{ T.loading }</div>;
	}

	if ( ! state ) {
		return (
			<div className="jde-public">
				<CodeEntryForm onAuthenticated={ handleAuthenticated } />
			</div>
		);
	}

	const quotaReached =
		state.mes_reservations.length >= state.exposant.nb_kiosques_max &&
		state.exposant.nb_kiosques_max > 0;

	if ( success && quotaReached ) {
		// Une fois la dernière réservation confirmée, on bascule directement
		// sur l'écran « quota atteint » pour éviter qu'on revienne au plan.
		return (
			<div className="jde-public">
				<QuotaReachedView
					state={ state }
					contactEmail={ contactEmail }
					onLogout={ () => void handleLogout() }
				/>
			</div>
		);
	}

	if ( success ) {
		return (
			<div className="jde-public">
				<div className="jde-public__card jde-public__card--success">
					<h2 className="jde-public__heading">
						{ T.public.success.title }
					</h2>
					<p>{ T.public.success.body }</p>
					<button
						type="button"
						className="jde-button jde-button--ghost"
						onClick={ () => setSuccess( false ) }
					>
						Voir le plan
					</button>
				</div>
			</div>
		);
	}

	if ( quotaReached ) {
		return (
			<div className="jde-public">
				<QuotaReachedView
					state={ state }
					contactEmail={ contactEmail }
					onLogout={ () => void handleLogout() }
				/>
			</div>
		);
	}

	return (
		<div className="jde-public">
			<ReservationView
				state={ state }
				contactEmail={ contactEmail }
				onLogout={ () => void handleLogout() }
				onConfirm={ handleStartConfirm }
			/>

			{ pendingIds !== null && (
				<ConfirmDialog
					count={ pendingIds.length }
					submitting={ submitting }
					errorMessage={ submitError }
					onCancel={ handleCancelConfirm }
					onConfirm={ () => void handleFinalConfirm() }
				/>
			) }

			{ conflictNumero !== null && (
				<ConflictModal
					kiosqueNumero={ conflictNumero }
					onClose={ () => setConflictNumero( null ) }
				/>
			) }
		</div>
	);
}

interface QuotaReachedViewProps {
	state: PublicState;
	contactEmail: string;
	onLogout: () => void;
}

function QuotaReachedView( props: QuotaReachedViewProps ): JSX.Element {
	const { state, contactEmail, onLogout } = props;
	const [ showPlan, setShowPlan ] = useState< boolean >( false );

	const myNumeros = state.mes_reservations
		.map(
			( r ) =>
				state.kiosques.find( ( k ) => k.id === r.kiosque_id )?.numero ??
				null
		)
		.filter( ( n ): n is string => null !== n );

	const hasPlan =
		null !== state.evenement.plan_url && state.kiosques.length > 0;

	return (
		<div className="jde-public__quota-reached">
			<h2 className="jde-public__quota-reached-title">
				{ T.public.quotaReached.title }
			</h2>
			<p>
				{ T.public.quotaReached.intro( state.mes_reservations.length ) }
			</p>

			{ myNumeros.length > 0 && (
				<>
					<p>
						<strong>{ T.public.quotaReached.yourBooths }</strong>
					</p>
					<ul className="jde-public__quota-reached-list">
						{ myNumeros.map( ( n ) => (
							<li key={ n }>{ n }</li>
						) ) }
					</ul>
				</>
			) }

			<p>
				{ T.public.quotaReached.contactBefore }
				<a href={ `mailto:${ contactEmail }` }>{ contactEmail }</a>
				{ T.public.quotaReached.contactAfter }
			</p>

			<div className="jde-public__quota-reached-actions">
				{ hasPlan && ! showPlan && (
					<button
						type="button"
						className="jde-button jde-button--secondary"
						onClick={ () => setShowPlan( true ) }
					>
						{ T.public.quotaReached.viewPlan }
					</button>
				) }
				<button
					type="button"
					className="jde-button jde-button--ghost"
					onClick={ onLogout }
				>
					{ T.public.quotaReached.logout }
				</button>
			</div>

			{ showPlan && null !== state.evenement.plan_url && (
				<ReadOnlyPlanView
					planUrl={ state.evenement.plan_url }
					kiosques={ state.kiosques }
					mesReservations={ state.mes_reservations }
					allReservations={ state.reservations }
					exposantId={ state.exposant.id }
					onClose={ () => setShowPlan( false ) }
				/>
			) }
		</div>
	);
}
