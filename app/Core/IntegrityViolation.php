<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** A business-integrity rule was violated (application-level equivalent of a trigger error). */
final class IntegrityViolation extends RuntimeException
{
}
