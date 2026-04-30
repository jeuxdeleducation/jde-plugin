<?php
/**
 * Exception levée quand un kiosque est marqué `indisponible` (statut admin).
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class KiosqueIndisponibleException extends RuntimeException {

	public function __construct( public readonly int $kiosqueId ) {
		parent::__construct( sprintf( 'Le kiosque %d est marqué indisponible.', $kiosqueId ) );
	}
}
