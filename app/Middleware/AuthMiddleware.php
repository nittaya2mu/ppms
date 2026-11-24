<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SettingsService;

/** Requires a logged-in user and enforces the idle timeout (FR-AUTH-02). */
final class AuthMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!Auth::check()) {
            Session::set('intended', $request->path);
            return Response::redirect('/login');
        }

        $timeout = SettingsService::int('session.timeout_min', 60) * 60;
        $last = (int) Session::get('last_activity', time());
        if ($timeout > 0 && time() - $last > $timeout) {
            Session::destroy();
            \App\Core\Session::start(config('session'));
            Session::flash('warning', __('auth.session_expired'));
            return Response::redirect('/login');
        }
        Session::set('last_activity', time());

        return $next($request);
    }
}
