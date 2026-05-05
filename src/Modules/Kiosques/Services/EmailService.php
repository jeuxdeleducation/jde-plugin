<?php
/**
 * Service d'envoi de courriels HTML du module Kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Services;

use JDE\Modules\Kiosques\Models\Exposant;

defined( 'ABSPATH' ) || exit;

/**
 * Envoie les courriels transactionnels du module via wp_mail().
 *
 * Toutes les valeurs par défaut sont surchargeable depuis la page
 * Paramètres (option `jde_plugin_settings`). Si un champ est vide
 * dans les paramètres, le template par défaut (`templates/emails/`)
 * est utilisé.
 */
final class EmailService {

	/** @param array<string, string> $settings Tableau de l'option `jde_plugin_settings`. */
	public function __construct( private readonly array $settings ) {}

	/**
	 * Envoyer le courriel « Code d'accès » à un exposant.
	 *
	 * @param string $urlReservation      URL de la page publique de réservation.
	 * @param string $messagePersonnalise Message personnalisé de l'admin (vide = section masquée).
	 */
	public function sendAccessCode( Exposant $exposant, string $evenementTitre, string $urlReservation = '', string $messagePersonnalise = '' ): bool {
		if ( null === $exposant->courriel ) {
			return false;
		}

		$subject = $this->settings['email_code_sujet'] ?? '';
		if ( '' === $subject ) {
			$subject = sprintf(
				/* translators: %s: titre de l'événement */
				__( 'Votre code d\'accès — %s', 'jde-plugin' ),
				$evenementTitre
			);
		}

		$contact = $this->contactEmail();

		if ( ! empty( $this->settings['email_code_corps'] ) ) {
			$body = $this->injectLayout( $this->settings['email_code_corps'], $subject );
		} else {
			$body = $this->renderTemplate(
				JDE_PLUGIN_DIR . 'templates/emails/access-code.php',
				array(
					'nom_entreprise'       => $exposant->nomEntreprise,
					'code_acces'           => $exposant->codeAcces,
					'evenement_titre'      => $evenementTitre,
					'url_reservation'      => $urlReservation,
					'contact_email'        => $contact,
					'message_personnalise' => $messagePersonnalise,
				)
			);
		}

		return $this->send( $exposant->courriel, $subject, $body );
	}

	/**
	 * Envoyer le courriel de confirmation de réservation à un exposant.
	 *
	 * @param string[] $kiosqueNumeros Numéros des kiosques réservés.
	 */
	public function sendReservationConfirmation( Exposant $exposant, string $evenementTitre, array $kiosqueNumeros ): bool {
		if ( null === $exposant->courriel ) {
			return false;
		}

		$subject = $this->settings['email_confirmation_sujet'] ?? '';
		if ( '' === $subject ) {
			$subject = sprintf(
				/* translators: %s: titre de l'événement */
				__( 'Confirmation de votre réservation — %s', 'jde-plugin' ),
				$evenementTitre
			);
		}

		$contact = $this->contactEmail();

		if ( ! empty( $this->settings['email_confirmation_corps'] ) ) {
			$body = $this->injectLayout( $this->settings['email_confirmation_corps'], $subject );
		} else {
			$body = $this->renderTemplate(
				JDE_PLUGIN_DIR . 'templates/emails/reservation-confirmation.php',
				array(
					'nom_entreprise'  => $exposant->nomEntreprise,
					'evenement_titre' => $evenementTitre,
					'kiosque_numeros' => $kiosqueNumeros,
					'contact_email'   => $contact,
				)
			);
		}

		return $this->send( $exposant->courriel, $subject, $body );
	}

	/**
	 * Envoyer un courriel HTML.
	 */
	private function send( string $to, string $subject, string $htmlBody ): bool {
		$fromName  = $this->settings['email_expediteur_nom'] ?? '';
		$fromEmail = $this->settings['email_expediteur_adresse'] ?? '';

		if ( '' === $fromEmail ) {
			$fromEmail = get_option( 'admin_email', '' );
		}
		if ( '' === $fromName ) {
			$fromName = get_option( 'blogname', 'Jeux de l\'Éducation' );
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $fromName, $fromEmail ),
		);

		return wp_mail( $to, $subject, $htmlBody, $headers );
	}

	/**
	 * Rendre un template PHP en capturant la sortie.
	 *
	 * @param array<string, mixed> $vars Variables extraites dans le template.
	 */
	private function renderTemplate( string $templatePath, array $vars ): string {
		if ( ! file_exists( $templatePath ) ) {
			return '';
		}

		$title   = $vars['subject'] ?? ( $vars['evenement_titre'] ?? '' );
		$content = $this->captureTemplate( $templatePath, $vars );

		return $this->captureTemplate(
			JDE_PLUGIN_DIR . 'templates/emails/layout.php',
			array(
				'title'   => $title,
				'content' => $content,
				'footer'  => '',
			)
		);
	}

	private function captureTemplate( string $path, array $vars ): string {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );
		ob_start();
		require $path;
		return (string) ob_get_clean();
	}

	/**
	 * Encapsuler un corps HTML personnalisé dans le layout JDE.
	 */
	private function injectLayout( string $htmlContent, string $title ): string {
		return $this->captureTemplate(
			JDE_PLUGIN_DIR . 'templates/emails/layout.php',
			array(
				'title'   => $title,
				'content' => $htmlContent,
				'footer'  => '',
			)
		);
	}

	private function contactEmail(): string {
		$email = $this->settings['email_contact'] ?? '';
		return '' !== $email ? $email : 'info@jeuxdeleducation.com';
	}
}
