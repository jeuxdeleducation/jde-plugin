/**
 * Application React publique de réservation des kiosques.
 *
 * Orchestre les écrans (CodeEntryForm vs ReservationView), la
 * confirmation, la création de réservation et la gestion du conflit 409.
 */

import { useCallback, useEffect, useState } from 'react';
import { api, ApiClientError } from '../shared/api';
import type { PublicState } from '../shared/types';
import { T } from '../shared/i18n';
import { CodeEntryForm } from './CodeEntryForm';
import { ConfirmDialog } from './ConfirmDialog';
import { ConflictModal } from './ConflictModal';
import { ReservationView } from './ReservationView';

interface PublicAppProps {
	contactEmail: string;
}

export function PublicApp( { contactEmail }: PublicAppProps ): JSX.Element {
	const [ state, setState ] = useState< PublicState | null >( null );
	const [ loading, setLoading ] = useState< boolean >( true );
	const [ pendingIds, setPendingIds ] = useState< number[] | null >( null );
	const [ submitting, setSubmitting ] = useState< boolean >( false );
	const [ success, setSuccess ] = useState< boolean >( false );
	const [ conflictNumero, setConflictNumero ] = useState< string | null >( null );

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
	}, [] );

	const handleStartConfirm = useCallback( ( ids: number[] ): void => {
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

		let latestState: PublicState = state;

		try {
			for ( const kiosqueId of pendingIds ) {
				try {
					latestState = await api.post< PublicState >( 'reservations', {
						kiosque_id: kiosqueId,
					} );
				} catch ( e ) {
					if ( e instanceof ApiClientError && e.status === 409 ) {
						const fresh = ( e.data as { fresh_state?: PublicState } | undefined )
							?.fresh_state;
						if ( fresh ) {
							latestState = fresh;
						}
						const numero = latestState.kiosques.find(
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
			// Erreur autre : affiche message générique et ferme le dialog.
			setPendingIds( null );
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
