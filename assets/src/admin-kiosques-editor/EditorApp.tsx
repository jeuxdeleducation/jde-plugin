/**
 * Application React principale de l'éditeur de kiosques admin.
 *
 * Charge l'état initial via REST GET, gère localement les ajouts/édits/
 * suppressions, et persiste l'ensemble via REST POST quand l'admin
 * clique sur « Enregistrer le plan ».
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { api, ApiClientError } from '../shared/api';
import { PlanCanvas } from '../shared/PlanCanvas';
import type { Kiosque, ReservationDetail } from '../shared/types';
import { T } from '../shared/i18n';
import { KiosqueEditModal, type KiosqueDraft } from './KiosqueEditModal';
import { Toolbar } from './Toolbar';

interface ListResponse {
	kiosques: Kiosque[];
}

interface ReservationsResponse {
	reservations: ReservationDetail[];
}

interface EditorAppProps {
	evenementId: number;
	planUrl: string | null;
	planVerrouille: boolean;
}

let nextLocalKey = 1;
const newClientKey = (): string => `local-${ nextLocalKey++ }`;

function withClientKey( kiosque: Kiosque ): KiosqueDraft {
	return { ...kiosque, clientKey: newClientKey() };
}

function defaultDraft( evenementId: number ): KiosqueDraft {
	return {
		id: null,
		evenement_id: evenementId,
		numero: '',
		pos_x: 45,
		pos_y: 45,
		largeur: 10,
		hauteur: 10,
		dimensions_texte: null,
		notes: null,
		statut: 'disponible',
		clientKey: newClientKey(),
	};
}

export function EditorApp( props: EditorAppProps ): JSX.Element {
	const { evenementId, planUrl, planVerrouille } = props;

	const [ kiosques, setKiosques ] = useState< KiosqueDraft[] >( [] );
	const [ reservedIds, setReservedIds ] = useState< ReadonlySet< number > >(
		new Set()
	);
	const [ loading, setLoading ] = useState< boolean >( true );
	const [ saving, setSaving ] = useState< boolean >( false );
	const [ dirty, setDirty ] = useState< boolean >( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ editingKey, setEditingKey ] = useState< string | null >( null );

	useEffect( () => {
		void ( async () => {
			try {
				const [ kiosquesResp, reservationsResp ] = await Promise.all( [
					api.get< ListResponse >(
						`admin/evenements/${ evenementId }/kiosques`
					),
					api
						.get< ReservationsResponse >(
							`admin/evenements/${ evenementId }/reservations`
						)
						.catch( () => ( { reservations: [] } ) ),
				] );
				setKiosques( kiosquesResp.kiosques.map( withClientKey ) );
				setReservedIds(
					new Set(
						reservationsResp.reservations.map(
							( r ) => r.kiosque_id
						)
					)
				);
				setLoading( false );
			} catch ( e ) {
				setError(
					e instanceof ApiClientError
						? e.message
						: T.public.errors.generic
				);
				setLoading( false );
			}
		} )();
	}, [ evenementId ] );

	const editingDraft = useMemo(
		() =>
			editingKey === null
				? null
				: kiosques.find( ( k ) => k.clientKey === editingKey ) ?? null,
		[ editingKey, kiosques ]
	);

	const handleAdd = useCallback( (): void => {
		const draft = defaultDraft( evenementId );
		setKiosques( ( current ) => [ ...current, draft ] );
		setEditingKey( draft.clientKey );
		setDirty( true );
	}, [ evenementId ] );

	const handleClick = useCallback(
		( kiosque: Kiosque ): void => {
			const local = kiosques.find(
				( k ) =>
					k.id === kiosque.id &&
					( null !== k.id || k.numero === kiosque.numero )
			);
			if ( local ) {
				setEditingKey( local.clientKey );
			}
		},
		[ kiosques ]
	);

	/**
	 * Aperçu temps réel pendant le drag : on met à jour pos_x/pos_y du
	 * kiosque concerné. La sauvegarde côté serveur attend un clic sur
	 * « Enregistrer le plan » (`dirty`).
	 */
	const handleKiosqueDrag = useCallback(
		( kiosque: Kiosque, posX: number, posY: number ): void => {
			setKiosques( ( current ) =>
				current.map( ( k ) =>
					k.id === kiosque.id &&
					( null !== k.id || k.numero === kiosque.numero )
						? { ...k, pos_x: posX, pos_y: posY }
						: k
				)
			);
		},
		[]
	);

	const handleKiosqueDragEnd = useCallback(
		( kiosque: Kiosque, posX: number, posY: number ): void => {
			setKiosques( ( current ) =>
				current.map( ( k ) =>
					k.id === kiosque.id &&
					( null !== k.id || k.numero === kiosque.numero )
						? { ...k, pos_x: posX, pos_y: posY }
						: k
				)
			);
			setDirty( true );
		},
		[]
	);

	const handleSaveDraft = useCallback( ( updated: KiosqueDraft ): void => {
		setKiosques( ( current ) =>
			current.map( ( k ) =>
				k.clientKey === updated.clientKey ? updated : k
			)
		);
		setEditingKey( null );
		setDirty( true );
	}, [] );

	const handleDeleteDraft = useCallback( ( clientKey: string ): void => {
		setKiosques( ( current ) =>
			current.filter( ( k ) => k.clientKey !== clientKey )
		);
		setEditingKey( null );
		setDirty( true );
	}, [] );

	const handleCloseModal = useCallback( (): void => {
		// Si on annule l'édition d'un nouveau kiosque (id null + numero vide),
		// le retirer pour ne pas laisser un kiosque fantôme.
		setKiosques( ( current ) =>
			current.filter( ( k ) => {
				if ( k.clientKey !== editingKey ) {
					return true;
				}
				if ( k.id !== null ) {
					return true;
				}
				return k.numero.trim().length > 0;
			} )
		);
		setEditingKey( null );
	}, [ editingKey ] );

	const handlePersist = useCallback( async (): Promise< void > => {
		setSaving( true );
		setError( null );
		try {
			const payload = {
				kiosques: kiosques.map( ( k ) => ( {
					id: k.id,
					numero: k.numero,
					pos_x: k.pos_x,
					pos_y: k.pos_y,
					largeur: k.largeur,
					hauteur: k.hauteur,
					dimensions_texte: k.dimensions_texte,
					notes: k.notes,
					statut: k.statut,
				} ) ),
			};
			const response = await api.post< ListResponse >(
				`admin/evenements/${ evenementId }/kiosques`,
				payload
			);
			setKiosques( response.kiosques.map( withClientKey ) );
			setDirty( false );
		} catch ( e ) {
			setError(
				e instanceof ApiClientError
					? e.message
					: T.public.errors.generic
			);
		} finally {
			setSaving( false );
		}
	}, [ kiosques, evenementId ] );

	if ( loading ) {
		return <div className="jde-editor__loading">{ T.loading }</div>;
	}

	if ( ! planUrl ) {
		return <div className="jde-editor__empty">{ T.admin.emptyState }</div>;
	}

	return (
		<div className="jde-editor">
			{ planVerrouille && (
				<div className="jde-editor__locked-banner">
					{ T.admin.lockedBanner }
				</div>
			) }

			<Toolbar
				dirty={ dirty }
				saving={ saving }
				locked={ planVerrouille }
				onAddKiosque={ handleAdd }
				onSave={ () => {
					void handlePersist();
				} }
			/>

			{ error && (
				<div className="jde-editor__error notice notice-error">
					<p>{ error }</p>
				</div>
			) }

			<PlanCanvas
				mode="edit"
				planUrl={ planUrl }
				kiosques={ kiosques }
				editingId={
					editingDraft && editingDraft.id !== null
						? editingDraft.id
						: null
				}
				takenIds={ reservedIds }
				onKiosqueClick={ handleClick }
				onKiosqueDrag={ planVerrouille ? undefined : handleKiosqueDrag }
				onKiosqueDragEnd={
					planVerrouille ? undefined : handleKiosqueDragEnd
				}
			/>

			{ editingDraft && (
				<KiosqueEditModal
					draft={ editingDraft }
					onSave={ handleSaveDraft }
					onDelete={ handleDeleteDraft }
					onClose={ handleCloseModal }
				/>
			) }
		</div>
	);
}
