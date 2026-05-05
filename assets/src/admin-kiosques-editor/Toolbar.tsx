/**
 * Barre d'outils de l'éditeur de kiosques.
 */

import { T } from '../shared/i18n';

interface ToolbarProps {
	dirty: boolean;
	saving: boolean;
	locked: boolean;
	onAddKiosque: () => void;
	onSave: () => void;
}

export function Toolbar( props: ToolbarProps ): JSX.Element {
	const { dirty, saving, locked, onAddKiosque, onSave } = props;

	return (
		<div className="jde-editor__toolbar">
			<div className="jde-editor__toolbar-start">
				<button
					type="button"
					className="button button-secondary"
					onClick={ onAddKiosque }
					disabled={ locked || saving }
				>
					+ Ajouter un kiosque
				</button>
				<span className="jde-editor__toolbar-help">
					{ T.admin.toolbarHelp }
				</span>
			</div>
			<div className="jde-editor__toolbar-end">
				{ dirty && ! saving && (
					<span className="jde-editor__dirty">
						{ T.admin.toolbarUnsaved }
					</span>
				) }
				<button
					type="button"
					className="button button-primary"
					onClick={ onSave }
					disabled={ ! dirty || saving }
				>
					{ saving ? T.loading : T.admin.toolbarSave }
				</button>
			</div>
		</div>
	);
}
