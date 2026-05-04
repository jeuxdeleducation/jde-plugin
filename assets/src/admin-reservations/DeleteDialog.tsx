/**
 * Modale de suppression d'une réservation avec motif obligatoire.
 */

import { useState, type FormEvent } from 'react';
import { api, ApiClientError } from '../shared/api';
import type { ReservationDetail } from '../shared/types';
import { T } from '../shared/i18n';

interface DeleteDialogProps {
	reservation: ReservationDetail;
	onClose: () => void;
	onDeleted: () => void | Promise< void >;
}

export function DeleteDialog( { reservation, onClose, onDeleted }: DeleteDialogProps ): JSX.Element {
	const [ reason, setReason ] = useState< string >( '' );
	const [ submitting, setSubmitting ] = useState< boolean >( false );
	const [ error, setError ] = useState< string | null >( null );

	const handleSubmit = async ( event: FormEvent ): Promise< void > => {
		event.preventDefault();
		if ( reason.trim().length === 0 ) {
			return;
		}
		setSubmitting( true );
		setError( null );
		try {
			await api.delete( `admin/reservations/${ reservation.id }?reason=${ encodeURIComponent( reason ) }` );
			await onDeleted();
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
				className="jde-modal jde-modal--danger"
				role="alertdialog"
				aria-modal="true"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<header className="jde-modal__header">
					<h2>{ T.reservations.deleteDialog.title }</h2>
				</header>

				<form onSubmit={ ( e ) => void handleSubmit( e ) } className="jde-modal__body">
					<p>
						{ T.reservations.deleteDialog.body(
							reservation.nom_entreprise,
							reservation.kiosque_numero
						) }
					</p>

					<label className="jde-field">
						<span className="jde-field__label">
							{ T.reservations.deleteDialog.fieldReason }
						</span>
						<textarea
							value={ reason }
							onChange={ ( e ) => setReason( e.target.value ) }
							rows={ 3 }
							required
							autoFocus
						/>
					</label>

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
							disabled={ submitting || reason.trim().length === 0 }
						>
							{ submitting
								? T.reservations.deleteDialog.submitting
								: T.reservations.deleteDialog.submit }
						</button>
					</footer>
				</form>
			</div>
		</div>
	);
}
