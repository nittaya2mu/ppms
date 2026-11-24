<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

/**
 * Route-level role check (layer 1 of the RBAC model in Phase 1 section 3).
 * The user's ACTIVE role must be one of the allowed roles.
 * Record-level checks (layer 2) and workflow-state checks (layer 3) live in Policies/Services.
 */
final class RoleMiddleware
{
    /** @param string[] $roles */
    public function __construct(private readonly array $roles)
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        if (!in_array(Auth::activeRole(), $this->roles, true)) {
            throw HttpException::forbidden();
        }
        return $next($request);
    }
}
