/**
 * En-tête de l'app publique : titre événement, nom entreprise,
 * kiosques restants, bouton Quitter.
 */

import { T } from '../shared/i18n';

interface HeaderProps {
	evenementTitre: string;
	nomEntreprise: string;
	kiosquesRestants: number;
	onLogout: () => void;
}

export function Header( props: HeaderProps ): JSX.Element {
	const { evenementTitre, nomEntreprise, kiosquesRestants, onLogout } = props;

	const remainingLabel =
		kiosquesRestants > 0
			? T.public.header.remaining( kiosquesRestants )
			: T.public.header.noneRemaining;

	return (
		<header className="jde-public__header">
			<div className="jde-public__header-info">
				{ evenementTitre && (
					<div className="jde-public__event-title">
						{ evenementTitre }
					</div>
				) }
				<div className="jde-public__company-name">
					{ nomEntreprise }
				</div>
				<div className="jde-public__quota">{ remainingLabel }</div>
			</div>
			<button
				type="button"
				className="jde-button jde-button--ghost"
				onClick={ onLogout }
			>
				{ T.public.header.logout }
			</button>
		</header>
	);
}
