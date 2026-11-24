<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/** Forces a password change before using the system (FR-AUTH-03). */
final class PasswordChangedMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $user = Auth::user();
        if ($user !== null && (int) $user['must_change_password'] === 1) {
            return Response::redirect('/password');
        }
        return $next($request);
    }
}
