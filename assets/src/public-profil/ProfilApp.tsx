/**
 * Composant principal du profil Bénévoles.
 *
 * Sections conditionnées par le `type_role` de la personne :
 *  - Bandeau d'en-tête + bloc d'introduction propre au rôle.
 *  - Mes infos (édition téléphone).
 *  - Mes documents (bouton OneDrive si URL configurée).
 *  - À signer (entente / lettre, si requis et non encore signés).
 *  - Mes assignations (accepter/refuser pour celles `proposee`).
 */

import { useEffect, useState, type FormEvent } from 'react';
import { api } from './api';
import type { ProfileResponse, TypeRole } from './types';

export function ProfilApp(): JSX.Element {
	const [ data, setData ] = useState< ProfileResponse | null >( null );
	const [ loading, setLoading ] = useState< boolean >( true );
	const [ error, setError ] = useState< string | null >( null );

	const refresh = async () => {
		try {
			const fresh = await api.getMe();
			setData( fresh );
			setError( null );
		} catch ( e ) {
			setError( e instanceof Error ? e.message : 'Erreur' );
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		void refresh();
	}, [] );

	if ( loading ) {
		return <p className="jde-profil__loading">Chargement…</p>;
	}
	if ( error ) {
		return <div className="jde-profil__error">{ error }</div>;
	}
	if ( ! data ) {
		return (
			<div className="jde-profil__error">Aucune donnée disponible.</div>
		);
	}

	const role: TypeRole =
		( data.personne.type_role as TypeRole ) || 'benevole';
	const intro = window.jdeBenevoles?.profilContent[ role ] || '';

	return (
		<div className={ `jde-profil jde-profil--${ role }` }>
			<header className="jde-profil__header">
				<h1>{ headerTitle( role ) }</h1>
				<p className="jde-profil__event">{ data.evenement_titre }</p>
				<p className="jde-profil__name">
					{ data.personne.prenom } { data.personne.nom }
				</p>
			</header>

			{ intro && (
				<div
					className="jde-profil__intro"
					// eslint-disable-next-line react/no-danger
					dangerouslySetInnerHTML={ { __html: intro } }
				/>
			) }

			<section className="jde-profil__section">
				<h2>Mes informations</h2>
				<MesInfos data={ data } onSaved={ refresh } />
			</section>

			{ data.personne.onedrive_url && (
				<section className="jde-profil__section">
					<h2>Mes documents</h2>
					<a
						className="jde-btn jde-btn--secondary"
						href={ data.personne.onedrive_url }
						target="_blank"
						rel="noopener noreferrer"
					>
						Ouvrir mes documents OneDrive
					</a>
				</section>
			) }

			<DocumentsASigner data={ data } onSigned={ refresh } />

			<section className="jde-profil__section">
				<h2>Mes assignations</h2>
				<MesAssignations data={ data } onChanged={ refresh } />
			</section>
		</div>
	);
}

function headerTitle( role: TypeRole ): string {
	switch ( role ) {
		case 'jury':
			return 'Espace jury';
		case 'arbitre':
			return 'Espace arbitre';
		default:
			return 'Espace bénévole';
	}
}

function MesInfos( props: {
	data: ProfileResponse;
	onSaved: () => void;
} ): JSX.Element {
	const [ telephone, setTelephone ] = useState< string >(
		props.data.personne.telephone || ''
	);
	const [ saving, setSaving ] = useState< boolean >( false );
	const [ msg, setMsg ] = useState< string | null >( null );

	const submit = async ( e: FormEvent ) => {
		e.preventDefault();
		setSaving( true );
		try {
			await api.updateMe( { telephone } );
			setMsg( 'Enregistré.' );
			props.onSaved();
		} catch ( err ) {
			setMsg( err instanceof Error ? err.message : 'Erreur' );
		} finally {
			setSaving( false );
		}
	};

	return (
		<form onSubmit={ submit } className="jde-profil__form">
			<dl>
				<dt>Courriel</dt>
				<dd>{ props.data.personne.courriel }</dd>
				<dt>Rôle</dt>
				<dd>{ props.data.personne.type_role }</dd>
			</dl>
			<label htmlFor="jde-profil-telephone">
				Téléphone
				<input
					id="jde-profil-telephone"
					type="tel"
					value={ telephone }
					onChange={ ( e ) => setTelephone( e.target.value ) }
				/>
			</label>
			<button
				type="submit"
				className="jde-btn jde-btn--primary"
				disabled={ saving }
			>
				{ saving ? 'Enregistrement…' : 'Mettre à jour' }
			</button>
			{ msg && <p className="jde-profil__msg">{ msg }</p> }
		</form>
	);
}

function DocumentsASigner( props: {
	data: ProfileResponse;
	onSigned: () => void;
} ): JSX.Element | null {
	const items: Array< { type: 'entente' | 'lettre'; label: string } > = [];
	if ( props.data.doit_signer.entente ) {
		items.push( { type: 'entente', label: 'Entente' } );
	}
	if ( props.data.doit_signer.lettre ) {
		items.push( { type: 'lettre', label: "Lettre d'engagement" } );
	}
	if ( items.length === 0 ) {
		return null;
	}

	const signed = new Set(
		props.data.signatures.map( ( s ) => s.type_document )
	);

	return (
		<section className="jde-profil__section">
			<h2>À signer</h2>
			<ul className="jde-profil__signlist">
				{ items.map( ( it ) => {
					const already = signed.has( it.type );
					return (
						<li key={ it.type }>
							{ already ? (
								<span className="jde-tag jde-tag--success">
									{ it.label } ✓ signé
								</span>
							) : (
								<SignButton
									type={ it.type }
									label={ it.label }
									onSigned={ props.onSigned }
								/>
							) }
						</li>
					);
				} ) }
			</ul>
		</section>
	);
}

function SignButton( props: {
	type: 'entente' | 'lettre';
	label: string;
	onSigned: () => void;
} ): JSX.Element {
	const [ confirming, setConfirming ] = useState< boolean >( false );
	const [ saving, setSaving ] = useState< boolean >( false );

	const sign = async () => {
		setSaving( true );
		try {
			await api.sign( props.type );
			props.onSigned();
		} catch ( e ) {
			// eslint-disable-next-line no-alert
			window.alert( e instanceof Error ? e.message : 'Erreur' );
		} finally {
			setSaving( false );
		}
	};

	if ( ! confirming ) {
		return (
			<button
				className="jde-btn jde-btn--secondary"
				onClick={ () => setConfirming( true ) }
			>
				Signer { props.label }
			</button>
		);
	}
	return (
		<div className="jde-profil__sign-confirm">
			<p>
				En cliquant ci-dessous, je confirme avoir lu { props.label } et
				m&apos;engage à en respecter les conditions.
			</p>
			<button
				className="jde-btn jde-btn--primary"
				onClick={ sign }
				disabled={ saving }
			>
				{ saving ? 'Enregistrement…' : 'Je signe' }
			</button>
			<button
				className="jde-btn jde-btn--ghost"
				onClick={ () => setConfirming( false ) }
			>
				Annuler
			</button>
		</div>
	);
}

function MesAssignations( props: {
	data: ProfileResponse;
	onChanged: () => void;
} ): JSX.Element {
	if ( props.data.assignations.length === 0 ) {
		return <p>Aucune assignation pour le moment.</p>;
	}
	return (
		<ul className="jde-profil__assignations">
			{ props.data.assignations.map( ( a ) => (
				<li
					key={ a.id }
					className={ `jde-assignation jde-assignation--${ a.statut }` }
				>
					<div className="jde-assignation__head">
						<strong>{ a.poste?.nom || 'Poste inconnu' }</strong>
						{ a.poste?.lieu && <span> — { a.poste.lieu }</span> }
					</div>
					{ a.quart && (
						<div className="jde-assignation__when">
							{ formatRange(
								a.quart.date_debut,
								a.quart.date_fin
							) }
						</div>
					) }
					<div className="jde-assignation__statut">
						Statut : { a.statut }
					</div>
					{ a.motif && (
						<div className="jde-assignation__motif">
							Motif : { a.motif }
						</div>
					) }
					{ a.statut === 'proposee' && (
						<DecisionButtons
							id={ a.id }
							onChanged={ props.onChanged }
						/>
					) }
				</li>
			) ) }
		</ul>
	);
}

function DecisionButtons( props: {
	id: number;
	onChanged: () => void;
} ): JSX.Element {
	const [ refusing, setRefusing ] = useState< boolean >( false );
	const [ motif, setMotif ] = useState< string >( '' );
	const [ saving, setSaving ] = useState< boolean >( false );

	const accept = async () => {
		setSaving( true );
		try {
			await api.decideAssignation( props.id, 'acceptee' );
			props.onChanged();
		} catch ( e ) {
			// eslint-disable-next-line no-alert
			window.alert( e instanceof Error ? e.message : 'Erreur' );
		} finally {
			setSaving( false );
		}
	};

	const reject = async () => {
		setSaving( true );
		try {
			await api.decideAssignation( props.id, 'refusee', motif );
			props.onChanged();
		} catch ( e ) {
			// eslint-disable-next-line no-alert
			window.alert( e instanceof Error ? e.message : 'Erreur' );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="jde-assignation__actions">
			<button
				className="jde-btn jde-btn--primary"
				onClick={ accept }
				disabled={ saving }
			>
				J&apos;accepte
			</button>
			{ ! refusing ? (
				<button
					className="jde-btn jde-btn--ghost"
					onClick={ () => setRefusing( true ) }
				>
					Refuser
				</button>
			) : (
				<div className="jde-assignation__refuse">
					<textarea
						placeholder="Motif (optionnel)"
						value={ motif }
						onChange={ ( e ) => setMotif( e.target.value ) }
						rows={ 2 }
					/>
					<button
						className="jde-btn jde-btn--danger"
						onClick={ reject }
						disabled={ saving }
					>
						Confirmer le refus
					</button>
				</div>
			) }
		</div>
	);
}

function formatRange( debut: string, fin: string ): string {
	try {
		const d = new Date( debut );
		const f = new Date( fin );
		const fmt = new Intl.DateTimeFormat( 'fr-CA', {
			weekday: 'short',
			day: '2-digit',
			month: 'short',
			hour: '2-digit',
			minute: '2-digit',
		} );
		return `${ fmt.format( d ) } → ${ fmt.format( f ) }`;
	} catch {
		return `${ debut } → ${ fin }`;
	}
}
