<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function conflict(string $message = ''): self
    {
        return new self(409, $message);
    }
}
