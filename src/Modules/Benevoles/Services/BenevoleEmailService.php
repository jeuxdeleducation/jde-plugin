<?php
/**
 * Service d'envoi des courriels du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Services;

use DateTimeImmutable;
use DateTimeZone;
use JDE\Modules\Benevoles\Admin\SettingsPage;
use JDE\Modules\Benevoles\Models\EmailLog;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Repositories\EmailLogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Envoi des courriels transactionnels et ciblés.
 *
 * Six gabarits transactionnels prédéfinis : confirmation, acceptation,
 * refus, assignation, rappel, remerciement. Plus un mode `broadcast`
 * pour les envois ad-hoc depuis le composer admin.
 *
 * Chaque gabarit est stocké en option (`OPTION_KEY`, indexé par clef de
 * gabarit) sous la forme `{subject, html_body}`. Si une entrée est
 * absente ou vide, le service retombe sur le fichier de fallback dans
 * `templates/emails/benevole-<key>.php` (sera ajouté à la phase 6).
 *
 * Tous les envois sont consignés dans `wp_jde_rh_email_log`.
 */
final class BenevoleEmailService {

	public const OPTION_KEY = 'jde_plugin_benevoles_emails';

	public const TPL_CONFIRMATION = 'confirmation';
	public const TPL_ACCEPTATION  = 'acceptation';
	public const TPL_REFUS        = 'refus';
	public const TPL_ASSIGNATION  = 'assignation';
	public const TPL_RAPPEL       = 'rappel';
	public const TPL_REMERCIEMENT = 'remerciement';
	public const TPL_BROADCAST    = 'broadcast';

	public function __construct(
		private readonly EmailRenderer $renderer,
		private readonly EmailLogRepository $logRepository,
	) {}

	/**
	 * Envoyer un courriel à une personne avec un gabarit donné.
	 *
	 * @param array<string, mixed> $vars Variables additionnelles fusionnées
	 *                                   avec les variables communes.
	 * @return bool true si wp_mail() a accepté l'envoi.
	 */
	public function sendToPersonne( string $template, Personne $personne, array $vars = array() ): bool {
		$baseVars = array(
			'prenom'          => $personne->prenom,
			'nom'             => $personne->nom,
			'courriel'        => $personne->courriel,
			'contact_email'   => $this->contactEmail(),
			'evenement_titre' => $this->evenementTitre( $personne->evenementRhId ),
		);

		$merged = array_merge( $baseVars, $vars );

		[ $subject, $bodyHtml ] = $this->resolveTemplate( $template );

		$subjectRendered = $this->renderer->render( $subject, $merged );
		$bodyRendered    = $this->renderer->render( $bodyHtml, $merged );

		$wrapped = $this->wrapInLayout( $subjectRendered, $bodyRendered );

		$ok = (bool) wp_mail(
			$personne->courriel,
			$subjectRendered,
			$wrapped,
			$this->headers()
		);

		$this->logRepository->insert(
			new EmailLog(
				id: null,
				evenementRhId: $personne->evenementRhId,
				template: $template,
				subject: $subjectRendered,
				recipientCount: 1,
				sentAt: new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ),
				sentBy: $this->currentUserIdOrNull(),
				filters: array(),
			)
		);

		return $ok;
	}

	/**
	 * Diffuser un courriel à un ensemble de destinataires sélectionnés
	 * via le composer admin.
	 *
	 * @param Personne[]           $destinataires
	 * @param array<string, mixed> $filters Filtres ayant servi à la sélection (audit).
	 */
	public function broadcast(
		int $evenementRhId,
		string $subject,
		string $bodyHtml,
		array $destinataires,
		array $filters = array()
	): int {
		if ( array() === $destinataires ) {
			return 0;
		}

		$sent = 0;
		foreach ( $destinataires as $personne ) {
			$vars = array(
				'prenom'          => $personne->prenom,
				'nom'             => $personne->nom,
				'courriel'        => $personne->courriel,
				'contact_email'   => $this->contactEmail(),
				'evenement_titre' => $this->evenementTitre( $personne->evenementRhId ),
				'message'         => $bodyHtml,
			);

			$subjectRendered = $this->renderer->render( $subject, $vars );
			$bodyRendered    = $this->renderer->render( $bodyHtml, $vars );

			$wrapped = $this->wrapInLayout( $subjectRendered, $bodyRendered );

			$ok = (bool) wp_mail(
				$personne->courriel,
				$subjectRendered,
				$wrapped,
				$this->headers()
			);
			if ( $ok ) {
				++$sent;
			}
		}

		$this->logRepository->insert(
			new EmailLog(
				id: null,
				evenementRhId: $evenementRhId,
				template: self::TPL_BROADCAST,
				subject: $subject,
				recipientCount: $sent,
				sentAt: new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ),
				sentBy: $this->currentUserIdOrNull(),
				filters: $filters,
			)
		);

		return $sent;
	}

	/**
	 * Aperçu sans envoi : retourne `{subject, body}` après rendu.
	 *
	 * @param array<string, mixed> $vars
	 * @return array{subject: string, body: string}
	 */
	public function preview( string $template, array $vars ): array {
		[ $subject, $bodyHtml ] = $this->resolveTemplate( $template );

		$subjectRendered = $this->renderer->render( $subject, $vars );
		$bodyRendered    = $this->renderer->render( $bodyHtml, $vars );

		return array(
			'subject' => $subjectRendered,
			'body'    => $this->wrapInLayout( $subjectRendered, $bodyRendered ),
		);
	}

	/**
	 * @return array{0: string, 1: string} [sujet, corps_html]
	 */
	private function resolveTemplate( string $template ): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( is_array( $stored ) && isset( $stored[ $template ] ) && is_array( $stored[ $template ] ) ) {
			$subject  = (string) ( $stored[ $template ]['subject'] ?? '' );
			$bodyHtml = (string) ( $stored[ $template ]['html_body'] ?? '' );
			if ( '' !== $subject && '' !== $bodyHtml ) {
				return array( $subject, $bodyHtml );
			}
		}

		// Fallback fichier (sera fourni à la phase 6 ; en attendant, on
		// compose un message minimaliste pour ne pas casser les flux).
		$fallbackPath = JDE_PLUGIN_DIR . 'templates/emails/benevole-' . $template . '.php';
		if ( file_exists( $fallbackPath ) ) {
			$loaded = include $fallbackPath;
			if ( is_array( $loaded ) && isset( $loaded['subject'], $loaded['html_body'] ) ) {
				return array( (string) $loaded['subject'], (string) $loaded['html_body'] );
			}
		}

		return array(
			__( 'Notification — Jeux de l\'Éducation', 'jde-plugin' ),
			'<p>{{prenom}}, ce courriel sera bientôt enrichi par votre gestionnaire.</p>',
		);
	}

	private function wrapInLayout( string $subject, string $bodyHtml ): string {
		$layoutPath = JDE_PLUGIN_DIR . 'templates/emails/benevoles-layout.php';
		if ( file_exists( $layoutPath ) ) {
			ob_start();
			include $layoutPath;
			$rendered = ob_get_clean();
			if ( is_string( $rendered ) && '' !== $rendered ) {
				return $rendered;
			}
		}

		return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
			. esc_html( $subject )
			. '</title></head><body>'
			. $bodyHtml
			. '</body></html>';
	}

	/**
	 * @return string[]
	 */
	private function headers(): array {
		$from = $this->fromAddress();
		$out  = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( '' !== $from ) {
			$out[] = 'From: ' . $from;
		}
		return $out;
	}

	private function fromAddress(): string {
		$settings = get_option( SettingsPage::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			return '';
		}

		$name  = isset( $settings['expediteur_nom'] ) ? trim( (string) $settings['expediteur_nom'] ) : '';
		$email = isset( $settings['expediteur_email'] ) ? trim( (string) $settings['expediteur_email'] ) : '';

		if ( '' === $email ) {
			return '';
		}

		return '' !== $name ? sprintf( '%s <%s>', $name, $email ) : $email;
	}

	private function contactEmail(): string {
		$settings = get_option( SettingsPage::OPTION_NAME, array() );
		if ( is_array( $settings ) && isset( $settings['contact_email'] ) ) {
			return (string) $settings['contact_email'];
		}
		return get_option( 'admin_email', '' );
	}

	private function evenementTitre( int $evenementRhId ): string {
		$post = get_post( $evenementRhId );
		return $post ? (string) $post->post_title : '';
	}

	private function currentUserIdOrNull(): ?int {
		$id = (int) get_current_user_id();
		return $id > 0 ? $id : null;
	}
}
