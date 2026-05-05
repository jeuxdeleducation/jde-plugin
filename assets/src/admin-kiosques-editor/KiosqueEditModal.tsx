/**
 * Modale d'édition d'un kiosque (admin).
 *
 * Couvre tous les champs d'un kiosque : numéro, position/dimensions en %,
 * dimensions texte, notes, statut. Permet aussi la suppression.
 */

import { useEffect, useState } from 'react';
import type { Kiosque, KiosqueStatut } from '../shared/types';
import { T } from '../shared/i18n';

export interface KiosqueDraft extends Kiosque {
	clientKey: string;
}

interface KiosqueEditModalProps {
	draft: KiosqueDraft;
	onSave: ( updated: KiosqueDraft ) => void;
	onDelete: ( clientKey: string ) => void;
	onClose: () => void;
}

export function KiosqueEditModal( props: KiosqueEditModalProps ): JSX.Element {
	const { draft, onSave, onDelete, onClose } = props;
	const [ form, setForm ] = useState< KiosqueDraft >( draft );

	useEffect( () => {
		setForm( draft );
	}, [ draft ] );

	const isNew = draft.id === null;

	const update = < K extends keyof KiosqueDraft >(
		key: K,
		value: KiosqueDraft[ K ]
	): void => {
		setForm( ( current ) => ( { ...current, [ key ]: value } ) );
	};

	const handleSubmit = ( event: React.FormEvent ): void => {
		event.preventDefault();
		onSave( form );
	};

	const handleDelete = (): void => {
		// eslint-disable-next-line no-alert
		if ( window.confirm( T.admin.modal.deleteConfirm ) ) {
			onDelete( draft.clientKey );
		}
	};

	return (
		<div
			className="jde-modal-overlay"
			onClick={ onClose }
			role="presentation"
		>
			<div
				className="jde-modal"
				role="dialog"
				aria-modal="true"
				aria-labelledby="jde-modal-title"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<header className="jde-modal__header">
					<h2 id="jde-modal-title">
						{ isNew ? T.admin.modal.titleNew : T.admin.modal.title }
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

				<form onSubmit={ handleSubmit } className="jde-modal__body">
					<label className="jde-field">
						<span className="jde-field__label">
							{ T.admin.modal.fieldNumero }
						</span>
						<input
							type="text"
							value={ form.numero }
							onChange={ ( e ) =>
								update( 'numero', e.target.value )
							}
							placeholder={ T.admin.modal.fieldNumeroPlaceholder }
							maxLength={ 32 }
							required
							autoFocus
						/>
					</label>

					<div className="jde-field-row">
						<label className="jde-field">
							<span className="jde-field__label">
								Position X (%)
							</span>
							<input
								type="number"
								min={ 0 }
								max={ 100 }
								step={ 0.1 }
								value={ form.pos_x }
								onChange={ ( e ) =>
									update(
										'pos_x',
										parseFloat( e.target.value ) || 0
									)
								}
							/>
						</label>
						<label className="jde-field">
							<span className="jde-field__label">
								Position Y (%)
							</span>
							<input
								type="number"
								min={ 0 }
								max={ 100 }
								step={ 0.1 }
								value={ form.pos_y }
								onChange={ ( e ) =>
									update(
										'pos_y',
										parseFloat( e.target.value ) || 0
									)
								}
							/>
						</label>
					</div>

					<div className="jde-field-row">
						<label className="jde-field">
							<span className="jde-field__label">
								Largeur (%)
							</span>
							<input
								type="number"
								min={ 0.5 }
								max={ 100 }
								step={ 0.1 }
								value={ form.largeur }
								onChange={ ( e ) =>
									update(
										'largeur',
										parseFloat( e.target.value ) || 0
									)
								}
								required
							/>
						</label>
						<label className="jde-field">
							<span className="jde-field__label">
								Hauteur (%)
							</span>
							<input
								type="number"
								min={ 0.5 }
								max={ 100 }
								step={ 0.1 }
								value={ form.hauteur }
								onChange={ ( e ) =>
									update(
										'hauteur',
										parseFloat( e.target.value ) || 0
									)
								}
								required
							/>
						</label>
					</div>

					<label className="jde-field">
						<span className="jde-field__label">
							{ T.admin.modal.fieldDimensions }
						</span>
						<input
							type="text"
							value={ form.dimensions_texte ?? '' }
							onChange={ ( e ) =>
								update(
									'dimensions_texte',
									e.target.value.length > 0
										? e.target.value
										: null
								)
							}
							placeholder={
								T.admin.modal.fieldDimensionsPlaceholder
							}
							maxLength={ 64 }
						/>
					</label>

					<label className="jde-field">
						<span className="jde-field__label">
							{ T.admin.modal.fieldNotes }
						</span>
						<textarea
							value={ form.notes ?? '' }
							onChange={ ( e ) =>
								update(
									'notes',
									e.target.value.length > 0
										? e.target.value
										: null
								)
							}
							rows={ 3 }
						/>
					</label>

					<label className="jde-field">
						<span className="jde-field__label">
							{ T.admin.modal.fieldStatut }
						</span>
						<select
							value={ form.statut }
							onChange={ ( e ) =>
								update(
									'statut',
									e.target.value as KiosqueStatut
								)
							}
						>
							<option value="disponible">
								{ T.admin.modal.statutDisponible }
							</option>
							<option value="indisponible">
								{ T.admin.modal.statutIndisponible }
							</option>
						</select>
					</label>

					<footer className="jde-modal__footer">
						{ ! isNew && (
							<button
								type="button"
								className="button button-link-delete"
								onClick={ handleDelete }
							>
								{ T.delete }
							</button>
						) }
						<div className="jde-modal__footer-end">
							<button
								type="button"
								className="button"
								onClick={ onClose }
							>
								{ T.cancel }
							</button>
							<button
								type="submit"
								className="button button-primary"
							>
								{ T.save }
							</button>
						</div>
					</footer>
				</form>
			</div>
		</div>
	);
}
