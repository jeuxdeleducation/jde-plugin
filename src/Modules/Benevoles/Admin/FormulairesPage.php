<?php
/**
 * Page admin : édition des schémas de formulaire d'inscription.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

use JDE\Modules\Benevoles\Capabilities;
use JDE\Modules\Benevoles\Models\Personne;
use JDE\Modules\Benevoles\Services\FormSchemaService;

defined( 'ABSPATH' ) || exit;

/**
 * Édite les définitions de champs des trois formulaires (un par rôle).
 *
 * Implémentation simple : un textarea JSON par rôle. Le format attendu
 * est documenté en haut de la page. Suffisant pour un module interne ;
 * un éditeur visuel pourrait être ajouté plus tard.
 */
final class FormulairesPage {

	public const PAGE_SLUG = 'jde-benevoles-formulaires';

	private static ?self $instance = null;

	public function __construct( private readonly FormSchemaService $service ) {
		self::$instance = $this;
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'handleSubmission' ) );
	}

	public function handleSubmission(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['jde_formulaires_submit'] ) ) {
			return;
		}

		check_admin_referer( 'jde_formulaires' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}

		foreach ( array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE ) as $role ) {
			$json    = (string) ( $_POST[ 'schema_' . $role ] ?? '' );
			$json    = wp_unslash( $json );
			$decoded = json_decode( $json, true );
			if ( is_array( $decoded ) ) {
				$this->service->saveSchemaForRole( $role, $decoded );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'jde-plugin' ) );
		}
		if ( null === self::$instance ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Formulaires', 'jde-plugin' ) . '</h1></div>';
			return;
		}
		self::$instance->renderPage();
	}

	private function renderPage(): void {
		$schemas = $this->service->getAllSchemas();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Formulaires d\'inscription', 'jde-plugin' ); ?></h1>
			<p><?php esc_html_e( 'Définissez les questions personnalisées pour chaque type de rôle. Les champs prénom, nom, courriel, téléphone et disponibilités sont toujours rendus en plus.', 'jde-plugin' ); ?></p>

			<details><summary><strong><?php esc_html_e( 'Format JSON attendu', 'jde-plugin' ); ?></strong></summary>
			<pre style="background:#f1f1f1;padding:1em;overflow:auto">[
	{"key": "experience", "label": "Avez-vous déjà bénévolé ?", "type": "select", "required": true,
	"options": ["Oui", "Non"]},
	{"key": "commentaires", "label": "Commentaires", "type": "textarea", "required": false}
]</pre>
			<p>
			<?php
				echo esc_html(
					sprintf(
						/* translators: %s: list of supported field types */
						__( 'Types supportés : %s.', 'jde-plugin' ),
						implode( ', ', FormSchemaService::TYPES_AUTORISES )
					)
				);
			?>
			</p>
			</details>

			<form method="post">
				<?php wp_nonce_field( 'jde_formulaires' ); ?>
				<?php foreach ( array( Personne::TYPE_BENEVOLE, Personne::TYPE_JURY, Personne::TYPE_ARBITRE ) as $role ) : ?>
					<h2><?php echo esc_html( ucfirst( $role ) ); ?></h2>
					<textarea name="schema_<?php echo esc_attr( $role ); ?>" rows="12" class="large-text code"><?php echo esc_textarea( wp_json_encode( $schemas[ $role ] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
				<?php endforeach; ?>
				<?php submit_button( __( 'Enregistrer', 'jde-plugin' ), 'primary', 'jde_formulaires_submit' ); ?>
			</form>
		</div>
		<?php
	}
}
