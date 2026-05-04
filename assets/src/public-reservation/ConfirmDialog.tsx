/**
 * Modale d'avertissement avant confirmation finale d'une sélection.
 *
 * Affiche le message « tu ne pourras plus modifier ta réservation »
 * pour s'assurer que l'exposant comprend la finalité de l'action.
 */

import { T } from '../shared/i18n';

interface ConfirmDialogProps {
	count: number;
	submitting: boolean;
	errorMessage?: string | null;
	onCancel: () => void;
	onConfirm: () => void;
}

export function ConfirmDialog( props: ConfirmDialogProps ): JSX.Element {
	const { count, submitting, errorMessage, onCancel, onConfirm } = props;

	return (
		<div className="jde-modal-overlay" onClick={ submitting ? undefined : onCancel } role="presentation">
			<div
				className="jde-modal jde-modal--warning"
				role="dialog"
				aria-modal="true"
				aria-labelledby="jde-confirm-title"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<header className="jde-modal__header">
					<h2 id="jde-confirm-title">{ T.public.confirmDialog.title }</h2>
				</header>
				<div className="jde-modal__body">
					<p>{ T.public.confirmDialog.body }</p>
					<p className="jde-public__quota">
						{ T.public.selectionBar.label( count ) }
					</p>
					{ errorMessage && (
						<div className="jde-public__error" role="alert">
							{ errorMessage }
						</div>
					) }
				</div>
				<footer className="jde-modal__footer jde-modal__footer--end">
					<button
						type="button"
						className="jde-button jde-button--ghost"
						onClick={ onCancel }
						disabled={ submitting }
					>
						{ T.public.confirmDialog.cancel }
					</button>
					<button
						type="button"
						className="jde-button jde-button--primary"
						onClick={ onConfirm }
						disabled={ submitting }
					>
						{ submitting
							? T.public.confirmDialog.submitting
							: T.public.confirmDialog.confirm }
					</button>
				</footer>
			</div>
		</div>
	);
}
