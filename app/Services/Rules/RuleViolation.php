<?php

declare(strict_types=1);

namespace App\Services\Rules;

use RuntimeException;

/** A business rule refused the operation. $rule is a stable machine-readable reason code. */
final class RuleViolation extends RuntimeException
{
    public function __construct(public readonly string $rule, string $message)
    {
        parent::__construct($message);
    }
}
