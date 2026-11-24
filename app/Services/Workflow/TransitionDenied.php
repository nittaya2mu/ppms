<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use RuntimeException;

final class TransitionDenied extends RuntimeException
{
    public function __construct(string $message, public readonly string $reasonCode = 'denied')
    {
        parent::__construct($message);
    }
}
