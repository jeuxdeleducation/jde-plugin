/**
 * Modale de création / modification d'une réservation côté admin.
 */

import { useMemo, useState, type FormEvent } from 'react';
import { api, ApiClientError } from '../shared/api';
import type { Exposant, Kiosque, ReservationDetail } from '../shared/types';
import { T } from '../shared/i18n';

type Mode = 'create' | 'edit';

interface ReservationFormModalProps {
	mode: Mode;
	reservation?: ReservationDetail;
	kiosques: Kiosque[];
	exposants: Exposant[];
	reservedKiosqueIds: ReadonlySet< number >;
	onClose: () => void;
	onSaved: () => void | Promise< void >;
}

export function ReservationFormModal( props: ReservationFormModalProps ): JSX.Element {
	const {
		mode,
		reservation,
		kiosques,
		exposants,
		reservedKiosqueIds,
		onClose,
		onSaved,
	} = props;

	const isEdit = mode === 'edit';

	const [ kiosqueId, setKiosqueId ] = useState< number | null >(
		reservation?.kiosque_id ?? null
	);
	const [ exposantId, setExposantId ] = useState< number | null >(
		reservation?.exposant_id ?? null
	);
	const [ notes, setNotes ] = useState< string >(
		reservation?.notes_admin ?? ''
	);
	const [ bypassQuota, setBypassQuota ] = useState< boolean >( false );
	const [ submitting, setSubmitting ] = useState< boolean >( false );
	const [ error, setError ] = useState< string | null >( null );

	// En création : kiosques libres uniquement (statut disponible et non réservés).
	// En édition : on permet aussi le kiosque actuellement assigné.
	const availableKiosques = useMemo< Kiosque[] >( () => {
		const filtered = kiosques.filter( ( k ) => {
			if ( k.statut !== 'disponible' ) {
				return false;
			}
			if ( k.id === null ) {
				return false;
			}
			if ( reservation && k.id === reservation.kiosque_id ) {
				return true;
			}
			return ! reservedKiosqueIds.has( k.id );
		} );

		// En édition, si la liste des kiosques n'a pas pu être chargée
		// (réseau, 403, etc.), on injecte un fallback minimal pour le
		// kiosque actuel : ça évite de bloquer la modification des notes
		// quand la liste est vide pour une raison non liée à l'édition.
		if (
			isEdit &&
			reservation &&
			! filtered.some( ( k ) => k.id === reservation.kiosque_id )
		) {
			filtered.push( {
				id: reservation.kiosque_id,
				evenement_id: 0,
				numero: reservation.kiosque_numero,
				pos_x: 0,
				pos_y: 0,
				largeur: 0,
				hauteur: 0,
				dimensions_texte: null,
				notes: null,
				statut: 'disponible',
			} );
		}

		return filtered;
	}, [ kiosques, reservedKiosqueIds, reservation, isEdit ] );

	const handleSubmit = async ( event: FormEvent ): Promise< void > => {
		event.preventDefault();
		if ( null === kiosqueId || ( ! isEdit && null === exposantId ) ) {
			return;
		}
		setSubmitting( true );
		setError( null );
		try {
			if ( isEdit && reservation ) {
				await api.put( `admin/reservations/${ reservation.id }`, {
					kiosque_id: kiosqueId,
					notes_admin: notes.length > 0 ? notes : null,
				} );
			} else {
				await api.post( 'admin/reservations', {
					kiosque_id: kiosqueId,
					exposant_id: exposantId,
					notes_admin: notes.length > 0 ? notes : null,
					bypass_quota: bypassQuota,
				} );
			}
			await onSaved();
		} catch ( e ) {
			setError(
				e instanceof ApiClientError ? e.message : T.public.errors.generic
			);
			setSubmitting( false );
		}
	};

	return (
		<div className="jde-modal-overlay" onClick={ submitting ? undefined : onClose } role="presentation">
			<div
				className="jde-modal"
				role="dialog"
				aria-modal="true"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<header className="jde-modal__header">
					<h2>
						{ isEdit
							? T.reservations.form.titleEdit
							: T.reservations.form.titleAdd }
					</h2>
					<button
						type="button"
						className="jde-modal__close"
						onClick={ onClose }
						aria-label={ T.close }
					>
						×
					</button>
				</header>

				<form onSubmit={ ( e ) => void handleSubmit( e ) } className="jde-modal__body">
					<label className="jde-field">
						<span className="jde-field__label">
							{ T.reservations.form.fieldKiosque }
						</span>
						<select
							value={ kiosqueId ?? '' }
							onChange={ ( e ) =>
								setKiosqueId( e.target.value ? parseInt( e.target.value, 10 ) : null )
							}
							required
						>
							<option value="">{ T.reservations.form.selectKiosquePlaceholder }</option>
							{ availableKiosques.map( ( k ) => (
								<option key={ k.id } value={ k.id ?? '' }>
									{ k.numero }
								</option>
							) ) }
						</select>
						{ availableKiosques.length === 0 && (
							<small style={ { color: '#dc2626' } }>
								{ T.reservations.form.noFreeKiosques }
							</small>
						) }
					</label>

					{ ! isEdit && (
						<label className="jde-field">
							<span className="jde-field__label">
								{ T.reservations.form.fieldExposant }
							</span>
							<select
								value={ exposantId ?? '' }
								onChange={ ( e ) =>
									setExposantId( e.target.value ? parseInt( e.target.value, 10 ) : null )
								}
								required
							>
								<option value="">{ T.reservations.form.selectExposantPlaceholder }</option>
								{ exposants.map( ( exp ) => (
									<option key={ exp.id } value={ exp.id }>
										{ exp.nom_entreprise }
									</option>
								) ) }
							</select>
						</label>
					) }

					<label className="jde-field">
						<span className="jde-field__label">
							{ T.reservations.form.fieldNotes }
						</span>
						<textarea
							value={ notes }
							onChange={ ( e ) => setNotes( e.target.value ) }
							rows={ 3 }
						/>
					</label>

					{ ! isEdit && (
						<label className="jde-field" style={ { flexDirection: 'row', gap: '8px' } }>
							<input
								type="checkbox"
								checked={ bypassQuota }
								onChange={ ( e ) => setBypassQuota( e.target.checked ) }
							/>
							<span style={ { fontSize: '13px' } }>
								{ T.reservations.form.fieldBypassQuota }
							</span>
						</label>
					) }

					{ error && (
						<div className="jde-public__error" role="alert">
							{ error }
						</div>
					) }

					<footer className="jde-modal__footer jde-modal__footer--end">
						<button
							type="button"
							className="button"
							onClick={ onClose }
							disabled={ submitting }
						>
							{ T.cancel }
						</button>
						<button
							type="submit"
							className="button button-primary"
							disabled={
								submitting ||
								null === kiosqueId ||
								( ! isEdit && null === exposantId )
							}
						>
							{ submitting
								? T.reservations.form.submitting
								: isEdit
									? T.reservations.form.submitEdit
									: T.reservations.form.submitAdd }
						</button>
					</footer>
				</form>
			</div>
		</div>
	);
}
