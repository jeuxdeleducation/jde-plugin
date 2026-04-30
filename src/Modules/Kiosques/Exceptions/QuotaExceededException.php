<?php
/**
 * Exception levée quand l'exposant a atteint sa limite de kiosques.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Kiosques\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class QuotaExceededException extends RuntimeException {

	public function __construct( public readonly int $quota ) {
		parent::__construct(
			sprintf( 'Quota dépassé : %d kiosques maximum.', $quota )
		);
	}
}
