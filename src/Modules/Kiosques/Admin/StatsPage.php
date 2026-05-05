<?php
/**
 * Tableau de bord de statistiques du module Kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Admin;

use JDE\Modules\Kiosques\Capabilities;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Repositories\ExposantRepository;
use JDE\Modules\Kiosques\Repositories\KiosqueRepository;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;
use JDE\Modules\Kiosques\Services\EvenementService;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Page d'administration accessible via Kiosques → Statistiques.
 *
 * Affiche un tableau récapitulatif de tous les événements publiés avec
 * leurs métriques : taux de remplissage, répartition des exposants,
 * réservations par source.
 */
final class StatsPage {

	public const PAGE_SLUG = 'jde-stats';

	public function __construct(
		private readonly EvenementService $evenementService,
		private readonly KiosqueRepository $kiosques,
		private readonly ExposantRepository $exposants,
		private readonly ReservationRepository $reservations,
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
	}

	public function registerPage(): void {
		add_submenu_page(
			AdminMenu::SLUG,
			__( 'Statistiques Kiosques', 'jde-plugin' ),
			__( 'Statistiques', 'jde-plugin' ),
			Capabilities::MANAGE,
			self::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	public function renderPage(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'jde-plugin' ), 403 );
		}

		$query = new WP_Query(
			array(
				'post_type'      => EvenementPostType::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$evenements = array_filter(
			$query->posts,
			static fn ( mixed $p ): bool => $p instanceof WP_Post
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Statistiques Kiosques', 'jde-plugin' ); ?></h1>

			<?php if ( empty( $evenements ) ) : ?>
				<p><?php esc_html_e( 'Aucun événement publié pour le moment.', 'jde-plugin' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Événement', 'jde-plugin' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Statut', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Kiosques', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Exposants', 'jde-plugin' ); ?></th>
							<th><?php esc_html_e( 'Sources', 'jde-plugin' ); ?></th>
							<th style="width:120px;"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $evenements as $post ) : ?>
							<?php $this->renderRow( $post ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private function renderRow( WP_Post $post ): void {
		$id       = $post->ID;
		$actif    = $this->evenementService->isActive( $id );
		$total    = $this->kiosques->countByEvenement( $id );
		$reserved = $this->reservations->countByEvenement( $id );
		$pct      = $total > 0 ? min( 100, (int) round( $reserved / $total * 100 ) ) : 0;

		$breakdown = $this->exposants->getBreakdownForEvenement( $id );
		$sources   = $this->reservations->countBySourceForEvenement( $id );

		$reservationsUrl = ReservationsPage::url( $id );
		$editUrl         = (string) ( get_edit_post_link( $id ) ?? '' );
		?>
		<tr>
			<td>
				<a href="<?php echo esc_url( $editUrl ); ?>" style="font-weight:600;">
					<?php echo esc_html( $post->post_title ); ?>
				</a>
			</td>
			<td>
				<?php if ( $actif ) : ?>
					<span style="background:#00a32a;color:#fff;padding:2px 7px;border-radius:3px;font-size:11px;">
						<?php esc_html_e( 'Actif', 'jde-plugin' ); ?>
					</span>
				<?php else : ?>
					<span style="background:#ddd;color:#555;padding:2px 7px;border-radius:3px;font-size:11px;">
						<?php esc_html_e( 'Inactif', 'jde-plugin' ); ?>
					</span>
				<?php endif; ?>
			</td>
			<td>
				<div style="font-size:13px;margin-bottom:4px;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: réservés, 2: total, 3: pourcentage */
							__( '%1$d / %2$d (%3$d%%)', 'jde-plugin' ),
							$reserved,
							$total,
							$pct
						)
					);
					?>
				</div>
				<div style="background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden;width:120px;">
					<div style="background:#00b0a8;height:100%;width:<?php echo (int) $pct; ?>%;"></div>
				</div>
			</td>
			<td>
				<div style="font-size:12px;line-height:1.8;">
					<span style="color:#00a32a;">●</span>
					<?php
					printf(
						/* translators: %d: nombre d'exposants ayant complété */
						esc_html__( '%d complété(s)', 'jde-plugin' ),
						(int) $breakdown['completed']
					);
					?>
					<br>
					<span style="color:#dba617;">●</span>
					<?php
					printf(
						/* translators: %d: nombre d'exposants en cours */
						esc_html__( '%d en cours', 'jde-plugin' ),
						(int) $breakdown['partial']
					);
					?>
					<br>
					<span style="color:#aaa;">●</span>
					<?php
					printf(
						/* translators: %d: nombre d'exposants sans réservation */
						esc_html__( '%d sans réservation', 'jde-plugin' ),
						(int) $breakdown['none']
					);
					?>
				</div>
			</td>
			<td>
				<div style="font-size:12px;line-height:1.8;">
					<?php
					printf(
						/* translators: %d: réservations admin */
						esc_html__( '%d par admin', 'jde-plugin' ),
						(int) $sources['admin']
					);
					echo '<br>';
					printf(
						/* translators: %d: réservations exposant */
						esc_html__( '%d par exposant', 'jde-plugin' ),
						(int) $sources['exposant']
					);
					?>
				</div>
			</td>
			<td>
				<a href="<?php echo esc_url( $reservationsUrl ); ?>" class="button button-small">
					<?php esc_html_e( 'Réservations', 'jde-plugin' ); ?>
				</a>
			</td>
		</tr>
		<?php
	}
}
