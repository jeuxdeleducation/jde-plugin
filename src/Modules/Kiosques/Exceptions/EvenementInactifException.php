<?php
/**
 * Exception levée quand l'événement n'est pas actif.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class EvenementInactifException extends RuntimeException {

	public function __construct() {
		parent::__construct( 'L\'événement n\'est plus actif.' );
	}
}
