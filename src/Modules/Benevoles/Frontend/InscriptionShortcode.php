<?php
/**
 * Shortcode `[jde_inscription_benevole]`.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Frontend;

use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\PostTypes\EvenementRhPostType;
use JDE\Modules\Benevoles\Repositories\PlageDisponibiliteRepository;
use JDE\Modules\Benevoles\Services\EvenementRhService;
use JDE\Modules\Benevoles\Services\FormSchemaService;
use JDE\Support\Assets;
use JDE\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Rend le formulaire d'inscription public pour un rôle donné.
 *
 * Usage : `[jde_inscription_benevole role="benevole|jury|arbitre"]`.
 * Le formulaire est rendu côté serveur depuis le schéma défini dans
 * `FormulairesPage`, et un petit bundle vanilla JS gère la soumission
 * AJAX vers `/jde/v1/benevoles/inscription`.
 */
final class InscriptionShortcode {

	public const TAG = 'jde_inscription_benevole';

	public function __construct(
		private readonly Assets $assets,
		private readonly Template $template,
		private readonly EvenementRhService $evenementService,
		private readonly FormSchemaService $formSchemas,
		private readonly PlageDisponibiliteRepository $plages,
	) {}

	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function render( array|string $atts = array(), ?string $content = null ): string {
		unset( $content );
		$atts = shortcode_atts(
			array( 'role' => Personne::TYPE_BENEVOLE ),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$role = sanitize_key( (string) $atts['role'] );
		if ( ! in_array( $role, array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE ), true ) ) {
			$role = Personne::TYPE_BENEVOLE;
		}

		$evenementId = $this->evenementService->getActiveId();
		$schema      = $this->formSchemas->getSchemaForRole( $role );
		$plages      = null !== $evenementId ? $this->plages->findByEvenement( $evenementId ) : array();
		$titreEv     = null !== $evenementId ? (string) get_the_title( $evenementId ) : '';

		$this->assets->enqueueScript( 'jde-public-inscription', 'public-inscription' );
		$this->assets->enqueueStyle( 'jde-public-inscription', 'public-inscription' );

		$config = array(
			'restUrl'        => esc_url_raw( rest_url( 'jde/v1/benevoles/inscription' ) ),
			'restNonce'      => wp_create_nonce( 'wp_rest' ),
			'role'           => $role,
			'isOpen'         => null !== $evenementId,
			'evenementTitre' => $titreEv,
		);
		wp_add_inline_script(
			'jde-public-inscription',
			'window.jdeBenevolesInscription = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		ob_start();
		?>
		<div class="jde-inscription">
			<?php if ( null === $evenementId ) : ?>
				<div class="jde-inscription__closed">
					<p><?php esc_html_e( 'Les inscriptions sont fermées présentement.', 'jde-plugin' ); ?></p>
				</div>
			<?php else : ?>
				<h2 class="jde-inscription__title">
					<?php
					printf(
						/* translators: %s: event title */
						esc_html__( 'Inscription — %s', 'jde-plugin' ),
						esc_html( $titreEv )
					);
					?>
				</h2>

				<form id="jde-inscription-form" novalidate>
					<input type="hidden" name="type_role" value="<?php echo esc_attr( $role ); ?>" />

					<!-- Honeypot -->
					<div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
						<label>Site web<input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
					</div>

					<div class="jde-field">
						<label for="jde-prenom"><?php esc_html_e( 'Prénom', 'jde-plugin' ); ?> *</label>
						<input type="text" id="jde-prenom" name="prenom" required />
					</div>
					<div class="jde-field">
						<label for="jde-nom"><?php esc_html_e( 'Nom', 'jde-plugin' ); ?> *</label>
						<input type="text" id="jde-nom" name="nom" required />
					</div>
					<div class="jde-field">
						<label for="jde-courriel"><?php esc_html_e( 'Courriel', 'jde-plugin' ); ?> *</label>
						<input type="email" id="jde-courriel" name="courriel" required />
					</div>
					<div class="jde-field">
						<label for="jde-telephone"><?php esc_html_e( 'Téléphone', 'jde-plugin' ); ?></label>
						<input type="tel" id="jde-telephone" name="telephone" />
					</div>

					<?php $this->renderCustomFields( $schema ); ?>

					<?php if ( array() !== $plages ) : ?>
						<fieldset class="jde-field">
							<legend><?php esc_html_e( 'Mes disponibilités', 'jde-plugin' ); ?></legend>
							<?php foreach ( $plages as $plage ) : ?>
								<label class="jde-checkbox">
									<input type="checkbox" name="plages[]" value="<?php echo (int) ( $plage->id ?? 0 ); ?>" />
									<span>
										<strong><?php echo esc_html( $plage->libelle ); ?></strong><br />
										<?php echo esc_html( $plage->dateDebut->format( 'Y-m-d H:i' ) . ' → ' . $plage->dateFin->format( 'H:i' ) ); ?>
									</span>
								</label>
							<?php endforeach; ?>
						</fieldset>
					<?php endif; ?>

					<button type="submit" class="jde-btn jde-btn--primary">
						<?php esc_html_e( 'Envoyer ma candidature', 'jde-plugin' ); ?>
					</button>

					<div id="jde-inscription-feedback" class="jde-inscription__feedback" role="status" aria-live="polite"></div>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<int, array<string, mixed>> $schema
	 */
	private function renderCustomFields( array $schema ): void {
		foreach ( $schema as $field ) {
			$key      = (string) ( $field['key'] ?? '' );
			$label    = (string) ( $field['label'] ?? '' );
			$type     = (string) ( $field['type'] ?? 'text' );
			$required = ! empty( $field['required'] );
			$options  = (array) ( $field['options'] ?? array() );

			$id = 'jde-field-' . sanitize_html_class( $key );

			echo '<div class="jde-field">';
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label );
			if ( $required ) {
				echo ' *';
			}
			echo '</label>';

			$nameAttr = 'reponses[' . esc_attr( $key ) . ']';
			$reqAttr  = $required ? ' required' : '';
			$dataKey  = ' data-key="' . esc_attr( $key ) . '" data-label="' . esc_attr( $label ) . '"';

			switch ( $type ) {
				case 'textarea':
					echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $nameAttr ) . '" rows="3"' . $reqAttr . $dataKey . '></textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					break;
				case 'select':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $nameAttr ) . '"' . $reqAttr . $dataKey . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<option value="">' . esc_html__( '— Sélectionner —', 'jde-plugin' ) . '</option>';
					foreach ( $options as $opt ) {
						echo '<option value="' . esc_attr( (string) $opt ) . '">' . esc_html( (string) $opt ) . '</option>';
					}
					echo '</select>';
					break;
				case 'radio':
					foreach ( $options as $opt ) {
						echo '<label class="jde-radio"><input type="radio" name="' . esc_attr( $nameAttr ) . '" value="' . esc_attr( (string) $opt ) . '"' . $reqAttr . $dataKey . ' /><span>' . esc_html( (string) $opt ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					break;
				case 'checkbox':
					echo '<label class="jde-checkbox"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $nameAttr ) . '" value="1"' . $dataKey . ' /><span>' . esc_html( $label ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					break;
				default:
					$inputType = in_array( $type, array( 'email', 'tel', 'date' ), true ) ? $type : 'text';
					echo '<input type="' . esc_attr( $inputType ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $nameAttr ) . '"' . $reqAttr . $dataKey . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			echo '</div>';
		}
	}
}
