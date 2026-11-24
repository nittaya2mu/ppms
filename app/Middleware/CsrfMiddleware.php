<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;

/** Rejects state-changing requests without a valid CSRF token (NFR-SEC-03). */
final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if ($request->isPost() && !Csrf::verify($request->input('_token'))) {
            throw new HttpException(419, 'CSRF token mismatch');
        }
        return $next($request);
    }
}
