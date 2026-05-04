/**
 * Tableau des réservations admin.
 */

import type { ReservationDetail } from '../shared/types';
import { T } from '../shared/i18n';

interface ReservationsTableProps {
	reservations: ReservationDetail[];
	onEdit: ( r: ReservationDetail ) => void;
	onDelete: ( r: ReservationDetail ) => void;
}

export function ReservationsTable( props: ReservationsTableProps ): JSX.Element {
	const { reservations, onEdit, onDelete } = props;

	if ( reservations.length === 0 ) {
		return <p className="jde-resa__empty">{ T.reservations.empty }</p>;
	}

	return (
		<table className="wp-list-table widefat fixed striped jde-resa__table">
			<thead>
				<tr>
					<th>{ T.reservations.columns.entreprise }</th>
					<th style={ { width: '90px' } }>{ T.reservations.columns.kiosque }</th>
					<th style={ { width: '140px' } }>{ T.reservations.columns.date }</th>
					<th style={ { width: '140px' } }>{ T.reservations.columns.source }</th>
					<th>{ T.reservations.columns.notes }</th>
					<th style={ { width: '180px' } }>{ T.reservations.columns.actions }</th>
				</tr>
			</thead>
			<tbody>
				{ reservations.map( ( r ) => (
					<tr key={ r.id }>
						<td>
							<strong>{ r.nom_entreprise }</strong>
							<br />
							<code style={ { fontSize: '11px', color: '#6b7280' } }>
								{ r.code_acces }
							</code>
						</td>
						<td>
							<strong>{ r.kiosque_numero }</strong>
						</td>
						<td>{ formatDate( r.date_reservation ) }</td>
						<td>
							{ r.source === 'admin'
								? T.reservations.sourceAdmin( r.cree_par_login )
								: T.reservations.sourceExposant }
						</td>
						<td style={ { fontSize: '12px', color: '#6b7280' } }>
							{ r.notes_admin ?? '—' }
						</td>
						<td>
							<button
								type="button"
								className="button button-small"
								onClick={ () => onEdit( r ) }
							>
								{ T.reservations.actionEdit }
							</button>
							{ ' ' }
							<button
								type="button"
								className="button button-small button-link-delete"
								onClick={ () => onDelete( r ) }
							>
								{ T.reservations.actionDelete }
							</button>
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

function formatDate( iso: string ): string {
	const d = new Date( iso );
	return d.toLocaleString( 'fr-CA', {
		dateStyle: 'short',
		timeStyle: 'short',
	} );
}
