<?php
/**
 * Exception levée quand un kiosque est déjà réservé (collision UNIQUE).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Indique qu'une tentative d'insert dans `wp_jde_reservations` a échoué
 * parce que le kiosque est déjà réservé. Capturée par le contrôleur REST
 * pour retourner HTTP 409 avec le payload approprié.
 */
final class KiosqueAlreadyReservedException extends RuntimeException {

	public function __construct( public readonly int $kiosqueId ) {
		parent::__construct( sprintf( 'Le kiosque %d est déjà réservé.', $kiosqueId ) );
	}
}
