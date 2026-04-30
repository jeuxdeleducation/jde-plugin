/**
 * Modale rouge bloquante en cas de conflit de réservation (HTTP 409).
 *
 * Présentée à l'exposant qui a perdu la course concurrente. Le message
 * indique le numéro du kiosque pris si connu, ou un message générique
 * sinon. Le bouton ferme la modal et le state est déjà rafraîchi
 * (le PublicApp a appliqué le fresh_state contenu dans le 409).
 */

import { T } from '../shared/i18n';

interface ConflictModalProps {
	kiosqueNumero: string | null;
	onClose: () => void;
}

export function ConflictModal( { kiosqueNumero, onClose }: ConflictModalProps ): JSX.Element {
	return (
		<div className="jde-modal-overlay" onClick={ onClose } role="presentation">
			<div
				className="jde-modal jde-modal--danger"
				role="alertdialog"
				aria-modal="true"
				aria-labelledby="jde-conflict-title"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<header className="jde-modal__header">
					<h2 id="jde-conflict-title">{ T.public.conflictModal.title }</h2>
				</header>
				<div className="jde-modal__body">
					<p>
						{ kiosqueNumero
							? T.public.conflictModal.body( kiosqueNumero )
							: T.public.conflictModal.body_generic }
					</p>
				</div>
				<footer className="jde-modal__footer jde-modal__footer--end">
					<button
						type="button"
						className="jde-button jde-button--primary"
						onClick={ onClose }
						autoFocus
					>
						{ T.public.conflictModal.button }
					</button>
				</footer>
			</div>
		</div>
	);
}
