<?php
/**
 * Endpoints REST admin pour la gestion des réservations.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\REST;

use JDE\Modules\Kiosques\Exceptions\EvenementInactifException;
use JDE\Modules\Kiosques\Exceptions\KiosqueAlreadyReservedException;
use JDE\Modules\Kiosques\Exceptions\KiosqueIndisponibleException;
use JDE\Modules\Kiosques\Exceptions\KiosqueIntrouvableException;
use JDE\Modules\Kiosques\Exceptions\QuotaExceededException;
use JDE\Modules\Kiosques\Models\ReservationDetail;
use JDE\Modules\Kiosques\PostTypes\EvenementPostType;
use JDE\Modules\Kiosques\Repositories\ReservationRepository;
use JDE\Modules\Kiosques\Services\CsvExporter;
use JDE\Modules\Kiosques\Services\ReservationService;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Routes :
 *   GET    /jde/v1/admin/evenements/{id}/reservations  → liste enrichie
 *   POST   /jde/v1/admin/reservations                  → créer manuellement
 *   PUT    /jde/v1/admin/reservations/{id}             → modifier (kiosque/notes)
 *   DELETE /jde/v1/admin/reservations/{id}             → supprimer avec motif
 *
 * Toutes les routes nécessitent la capacité `jde_manage_kiosques`.
 */
final class AdminReservationsController extends AbstractController {

	public function __construct(
		private readonly ReservationService $service,
		private readonly ReservationRepository $repo,
		private readonly CsvExporter $csvExporter,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/evenements/(?P<id>\d+)/reservations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list' ),
				'permission_callback' => array( $this, 'adminPermissionCheck' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/evenements/(?P<id>\d+)/reservations.csv',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'exportCsv' ),
				'permission_callback' => array( $this, 'adminPermissionCheck' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/reservations',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'adminPermissionCheck' ),
				'args'                => array(
					'kiosque_id'   => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'exposant_id'  => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'notes_admin'  => array(
						'type'     => array( 'string', 'null' ),
						'required' => false,
					),
					'bypass_quota' => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/reservations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'adminPermissionCheck' ),
					'args'                => array(
						'id'          => array(
							'type'     => 'integer',
							'required' => true,
						),
						'kiosque_id'  => array(
							'type'     => array( 'integer', 'null' ),
							'required' => false,
						),
						'notes_admin' => array(
							'type'     => array( 'string', 'null' ),
							'required' => false,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'adminPermissionCheck' ),
					'args'                => array(
						'id'     => array(
							'type'     => 'integer',
							'required' => true,
						),
						'reason' => array(
							'type'      => 'string',
							'required'  => true,
							'minLength' => 1,
							'maxLength' => 500,
						),
					),
				),
			)
		);
	}

	/**
	 * GET — liste enrichie des réservations d'un événement.
	 */
	public function list( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$evenementId = (int) $request['id'];

		$post = get_post( $evenementId );
		if ( ! $post || EvenementPostType::SLUG !== $post->post_type ) {
			return $this->errorResponse(
				'evenement_introuvable',
				__( 'Événement introuvable.', 'jde-plugin' ),
				404
			);
		}

		$reservations = $this->repo->findDetailedByEvenement( $evenementId );

		return new WP_REST_Response(
			array(
				'reservations' => array_map(
					static fn ( ReservationDetail $r ): array => $r->toArray(),
					$reservations
				),
			)
		);
	}

	/**
	 * POST — créer manuellement une réservation depuis l'admin.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$kiosqueId   = (int) $request->get_param( 'kiosque_id' );
		$exposantId  = (int) $request->get_param( 'exposant_id' );
		$notesAdmin  = $request->get_param( 'notes_admin' );
		$bypassQuota = (bool) $request->get_param( 'bypass_quota' );

		$notesAdmin = is_string( $notesAdmin ) && '' !== trim( $notesAdmin )
			? sanitize_textarea_field( $notesAdmin )
			: null;

		try {
			$reservation = $this->service->create(
				$exposantId,
				$kiosqueId,
				get_current_user_id(),
				$bypassQuota,
				$notesAdmin
			);
		} catch ( KiosqueAlreadyReservedException $e ) {
			return $this->errorResponse(
				'kiosque_pris',
				__( 'Ce kiosque est déjà réservé.', 'jde-plugin' ),
				409,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( KiosqueIntrouvableException $e ) {
			return $this->errorResponse(
				'kiosque_introuvable',
				__( 'Kiosque introuvable.', 'jde-plugin' ),
				404,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( KiosqueIndisponibleException $e ) {
			return $this->errorResponse(
				'kiosque_indisponible',
				__( 'Ce kiosque est marqué indisponible.', 'jde-plugin' ),
				409,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( QuotaExceededException $e ) {
			return $this->errorResponse(
				'quota_exceeded',
				__( 'L\'exposant a atteint son quota. Active bypass_quota pour forcer.', 'jde-plugin' ),
				422,
				array( 'quota' => $e->quota )
			);
		} catch ( EvenementInactifException $e ) {
			return $this->errorResponse( 'event_inactive', $e->getMessage(), 403 );
		} catch ( Throwable $e ) {
			return $this->errorResponse(
				'reservation_error',
				__( 'Erreur lors de la création.', 'jde-plugin' ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'reservation_id' => $reservation->id ),
			201
		);
	}

	/**
	 * PUT — modifier une réservation (kiosque ou notes).
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$reservationId = (int) $request['id'];
		$kiosqueId     = $request->get_param( 'kiosque_id' );
		$notesAdmin    = $request->get_param( 'notes_admin' );

		$kiosqueId  = ( null !== $kiosqueId && '' !== $kiosqueId ) ? (int) $kiosqueId : null;
		$notesAdmin = is_string( $notesAdmin ) ? sanitize_textarea_field( $notesAdmin ) : null;
		if ( '' === $notesAdmin ) {
			$notesAdmin = null;
		}

		try {
			$updated = $this->service->update(
				$reservationId,
				$kiosqueId,
				$notesAdmin,
				get_current_user_id()
			);
		} catch ( KiosqueAlreadyReservedException $e ) {
			return $this->errorResponse(
				'kiosque_pris',
				__( 'Ce kiosque est déjà réservé. Le déplacement est annulé.', 'jde-plugin' ),
				409,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( KiosqueIntrouvableException $e ) {
			return $this->errorResponse(
				'kiosque_introuvable',
				__( 'Kiosque introuvable.', 'jde-plugin' ),
				404,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( KiosqueIndisponibleException $e ) {
			return $this->errorResponse(
				'kiosque_indisponible',
				__( 'Ce kiosque est marqué indisponible.', 'jde-plugin' ),
				409,
				array( 'kiosque_id' => $e->kiosqueId )
			);
		} catch ( Throwable $e ) {
			return $this->errorResponse(
				'reservation_error',
				__( 'Erreur lors de la modification.', 'jde-plugin' ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'reservation_id' => $updated->id ),
			200
		);
	}

	/**
	 * DELETE — supprimer une réservation avec motif obligatoire.
	 */
	public function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$reservationId = (int) $request['id'];
		$reason        = sanitize_text_field( (string) $request->get_param( 'reason' ) );

		if ( '' === $reason ) {
			return $this->errorResponse(
				'reason_required',
				__( 'Un motif de suppression est requis.', 'jde-plugin' ),
				400
			);
		}

		try {
			$this->service->delete( $reservationId, get_current_user_id(), $reason );
		} catch ( Throwable $e ) {
			return $this->errorResponse(
				'reservation_error',
				__( 'Erreur lors de la suppression.', 'jde-plugin' ),
				500
			);
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * GET — exporter les réservations d'un événement en CSV.
	 *
	 * Court-circuit la pipeline REST normale pour streamer le CSV
	 * directement avec les bons headers HTTP. La méthode termine par
	 * `exit` pour empêcher WP_REST_Server d'ajouter du contenu.
	 *
	 * @return never
	 */
	public function exportCsv( WP_REST_Request $request ): never {
		$evenementId = (int) $request['id'];

		$post = get_post( $evenementId );
		if ( ! $post || EvenementPostType::SLUG !== $post->post_type ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html__( 'Événement introuvable.', 'jde-plugin' );
			exit;
		}

		$this->csvExporter->streamReservations( $evenementId, $post->post_title );
		exit;
	}
}
