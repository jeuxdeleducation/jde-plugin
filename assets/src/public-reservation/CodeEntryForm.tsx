/**
 * Formulaire d'entrée du code d'accès.
 */

import { useState, type FormEvent } from 'react';
import { api, ApiClientError } from '../shared/api';
import type { PublicState } from '../shared/types';
import { T } from '../shared/i18n';

interface CodeEntryFormProps {
	onAuthenticated: ( state: PublicState ) => void;
}

export function CodeEntryForm( { onAuthenticated }: CodeEntryFormProps ): JSX.Element {
	const [ code, setCode ] = useState< string >( '' );
	const [ submitting, setSubmitting ] = useState< boolean >( false );
	const [ error, setError ] = useState< string | null >( null );

	const handleSubmit = async ( event: FormEvent ): Promise< void > => {
		event.preventDefault();
		setError( null );
		setSubmitting( true );

		try {
			const state = await api.post< PublicState >( 'auth/code', {
				code: code.toUpperCase().trim(),
			} );
			onAuthenticated( state );
		} catch ( e ) {
			if ( e instanceof ApiClientError ) {
				if ( e.status === 429 ) {
					setError( T.public.codeForm.errorRateLimit );
				} else if ( e.code === 'invalid_code' || e.status === 401 ) {
					setError( T.public.codeForm.errorInvalid );
				} else {
					setError( e.message || T.public.codeForm.errorInvalid );
				}
			} else {
				setError( T.public.codeForm.errorNetwork );
			}
		} finally {
			setSubmitting( false );
		}
	};

	const formatCode = ( raw: string ): string => {
		// Auto-format en XXXX-XXXX (insère un tiret après 4 caractères).
		const cleaned = raw.replace( /[^A-Za-z0-9]/g, '' ).toUpperCase().slice( 0, 8 );
		if ( cleaned.length <= 4 ) {
			return cleaned;
		}
		return cleaned.slice( 0, 4 ) + '-' + cleaned.slice( 4 );
	};

	return (
		<div className="jde-public__card">
			<h2 className="jde-public__heading">{ T.public.codeForm.heading }</h2>
			<p className="jde-public__subheading">{ T.public.codeForm.subheading }</p>

			<form onSubmit={ ( e ) => void handleSubmit( e ) } noValidate>
				<label className="jde-field">
					<span className="jde-field__label">
						{ T.public.codeForm.label }
					</span>
					<input
						type="text"
						value={ code }
						onChange={ ( e ) => setCode( formatCode( e.target.value ) ) }
						placeholder={ T.public.codeForm.placeholder }
						maxLength={ 9 }
						autoComplete="off"
						autoCapitalize="characters"
						autoFocus
						required
					/>
				</label>

				{ error && (
					<div className="jde-public__error" role="alert">
						{ error }
					</div>
				) }

				<button
					type="submit"
					className="jde-button jde-button--primary jde-button--full"
					disabled={ submitting || code.length < 9 }
				>
					{ submitting
						? T.public.codeForm.submitting
						: T.public.codeForm.submit }
				</button>
			</form>
		</div>
	);
}
