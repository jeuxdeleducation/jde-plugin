<?php
/**
 * Page « Historique » : consultation du journal d'audit.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use DateTimeImmutable;
use JDE\Modules\Kiosques\Capabilities;
use JDE\Modules\Kiosques\Models\AuditEntry;
use JDE\Modules\Kiosques\Repositories\AuditRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Page d'admin sous le menu Kiosques (slug `jde-audit`).
 *
 * Affiche le journal d'audit en lecture seule avec :
 *  - Filtres : type d'entité, identifiant entité, action prefix, utilisateur.
 *  - Pagination 50 par page.
 *  - Détail repliable du payload JSON brut pour chaque entrée.
 */
final class AuditPage {

	public const PAGE_SLUG = 'jde-audit';
	private const PER_PAGE = 50;

	public function __construct( private readonly AuditRepository $audit ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ), 20 );
	}

	public function registerPage(): void {
		add_submenu_page(
			AdminMenu::SLUG,
			__( 'Historique des actions', 'jde-plugin' ),
			__( 'Historique', 'jde-plugin' ),
			Capabilities::MANAGE,
			self::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	/**
	 * URL d'admin pour cette page (utile pour les liens depuis ailleurs).
	 */
	public static function url( array $extraArgs = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_SLUG ), $extraArgs ),
			admin_url( 'admin.php' )
		);
	}

	public function renderPage(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lecture seule (filtres GET)
		$entityType = isset( $_GET['entity_type'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['entity_type'] ) ) : '';
		$entityId   = isset( $_GET['entity_id'] ) ? (int) $_GET['entity_id'] : 0;
		$actionPref = isset( $_GET['action_prefix'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['action_prefix'] ) ) : '';
		$userId     = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$page       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:enable

		$filters = array_filter(
			array(
				'entity_type'   => $entityType,
				'entity_id'     => $entityId > 0 ? $entityId : null,
				'action_prefix' => $actionPref,
				'user_id'       => $userId > 0 ? $userId : null,
			),
			static fn ( $value ): bool => null !== $value && '' !== $value
		);

		$total      = $this->audit->countMatching( $filters );
		$totalPages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page       = min( $page, $totalPages );
		$entries    = $this->audit->query( $filters, self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );

		$wpTimezone = wp_timezone();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Historique des actions', 'jde-plugin' ); ?></h1>

			<form method="get" style="margin:18px 0;background:#fff;border:1px solid #c3c4c7;padding:12px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">

				<label>
					<span style="display:block;font-size:11px;color:#666;"><?php esc_html_e( 'Type d\'entité', 'jde-plugin' ); ?></span>
					<select name="entity_type">
						<option value=""><?php esc_html_e( '— tous —', 'jde-plugin' ); ?></option>
						<?php foreach ( array( 'reservation', 'exposant', 'evenement' ) as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"<?php selected( $entityType, $type ); ?>>
								<?php echo esc_html( $type ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					<span style="display:block;font-size:11px;color:#666;"><?php esc_html_e( 'ID entité', 'jde-plugin' ); ?></span>
					<input type="number" name="entity_id" min="1" value="<?php echo $entityId > 0 ? (int) $entityId : ''; ?>" class="small-text">
				</label>

				<label>
					<span style="display:block;font-size:11px;color:#666;"><?php esc_html_e( 'Préfixe d\'action', 'jde-plugin' ); ?></span>
					<input type="text" name="action_prefix" value="<?php echo esc_attr( $actionPref ); ?>" placeholder="reservation." class="regular-text">
				</label>

				<label>
					<span style="display:block;font-size:11px;color:#666;"><?php esc_html_e( 'ID utilisateur', 'jde-plugin' ); ?></span>
					<input type="number" name="user_id" min="1" value="<?php echo $userId > 0 ? (int) $userId : ''; ?>" class="small-text">
				</label>

				<button type="submit" class="button"><?php esc_html_e( 'Filtrer', 'jde-plugin' ); ?></button>
				<?php if ( ! empty( $filters ) ) : ?>
					<a href="<?php echo esc_url( self::url() ); ?>" class="button button-link">
						<?php esc_html_e( 'Réinitialiser', 'jde-plugin' ); ?>
					</a>
				<?php endif; ?>
			</form>

			<p>
				<?php
				printf(
					/* translators: %1$s: nombre d'entrées affichées, %2$s: total */
					esc_html__( '%1$s entrée(s) sur %2$s au total.', 'jde-plugin' ),
					esc_html( (string) count( $entries ) ),
					esc_html( (string) $total )
				);
				?>
			</p>

			<?php if ( empty( $entries ) ) : ?>
				<p><?php esc_html_e( 'Aucune entrée correspondante.', 'jde-plugin' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:160px;"><?php esc_html_e( 'Date', 'jde-plugin' ); ?></th>
							<th style="width:140px;"><?php esc_html_e( 'Utilisateur', 'jde-plugin' ); ?></th>
							<th style="width:200px;"><?php esc_html_e( 'Action', 'jde-plugin' ); ?></th>
							<th style="width:160px;"><?php esc_html_e( 'Entité', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Détails', 'jde-plugin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<?php $this->renderRow( $entry, $wpTimezone ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php $this->renderPagination( $page, $totalPages, $filters ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function renderRow( AuditEntry $entry, \DateTimeZone $tz ): void {
		$local = $entry->createdAt->setTimezone( $tz );
		$user  = null !== $entry->userLogin
			? $entry->userLogin . ' (#' . $entry->userId . ')'
			: '#' . $entry->userId;

		$payload = '';
		if ( null !== $entry->payload ) {
			$payload = (string) wp_json_encode( $entry->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}
		?>
		<tr>
			<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $local->getTimestamp() ) ); ?></td>
			<td><?php echo esc_html( $user ); ?></td>
			<td><code><?php echo esc_html( $entry->action ); ?></code></td>
			<td>
				<code><?php echo esc_html( $entry->entityType ); ?></code>
				#<?php echo (int) $entry->entityId; ?>
			</td>
			<td>
				<?php if ( '' !== $payload ) : ?>
					<details>
						<summary><?php esc_html_e( 'Voir le détail', 'jde-plugin' ); ?></summary>
						<pre style="font-size:11px;background:#f6f7f7;padding:8px;margin-top:4px;overflow:auto;max-height:300px;"><?php echo esc_html( $payload ); ?></pre>
					</details>
				<?php else : ?>
					—
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function renderPagination( int $currentPage, int $totalPages, array $filters ): void {
		if ( $totalPages <= 1 ) {
			return;
		}
		?>
		<div class="tablenav bottom"><div class="tablenav-pages">
			<?php
			$baseArgs = $filters;
			for ( $p = 1; $p <= $totalPages; $p++ ) {
				$args        = array_merge( $baseArgs, array( 'paged' => $p ) );
				$url         = self::url( $args );
				$activeClass = $p === $currentPage ? 'current' : '';
				printf(
					'<a href="%1$s" class="page-numbers %2$s">%3$s</a> ',
					esc_url( $url ),
					esc_attr( $activeClass ),
					esc_html( (string) $p )
				);
			}
			?>
		</div></div>
		<?php
	}
}
