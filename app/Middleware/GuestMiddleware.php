<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class GuestMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        return Auth::check() ? Response::redirect('/dashboard') : $next($request);
    }
}
