<?php
/**
 * Exception levée quand un kiosque référencé n'existe pas ou n'appartient pas à l'événement.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class KiosqueIntrouvableException extends RuntimeException {

	public function __construct( public readonly int $kiosqueId ) {
		parent::__construct( sprintf( 'Le kiosque %d est introuvable.', $kiosqueId ) );
	}
}
