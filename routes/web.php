<?php

declare(strict_types=1);

use App\Controllers\Admin\UserController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\NotificationController;
use App\Core\Auth;
use App\Core\Response;
use App\Core\Router;

/** @var Router $router */

$router->get('/', fn () => Response::redirect(Auth::check() ? '/dashboard' : '/login'));

// Guest
$router->group('', ['guest'], function (Router $r) {
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login'], ['csrf']);
});

// Authenticated, password change allowed even when forced
$router->group('', ['auth'], function (Router $r) {
    $r->post('/logout', [AuthController::class, 'logout'], ['csrf']);
    $r->get('/password', [AuthController::class, 'showChangePassword']);
    $r->post('/password', [AuthController::class, 'changePassword'], ['csrf']);
});

// Authenticated and password already changed
$router->group('', ['auth', 'password'], function (Router $r) {
    $r->get('/dashboard', [DashboardController::class, 'index']);
    $r->post('/role', [AuthController::class, 'switchRole'], ['csrf']);
    $r->get('/notifications', [NotificationController::class, 'index']);
    $r->post('/notifications/read', [NotificationController::class, 'markRead'], ['csrf']);

    // Administrator
    $r->group('/admin', ['role:admin'], function (Router $r) {
        $r->get('/users', [UserController::class, 'index']);
        $r->get('/users/create', [UserController::class, 'create']);
        $r->post('/users', [UserController::class, 'store'], ['csrf']);
        $r->get('/users/{id}/edit', [UserController::class, 'edit']);
        $r->post('/users/{id}', [UserController::class, 'update'], ['csrf']);
        $r->post('/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['csrf']);
        $r->post('/users/{id}/delete', [UserController::class, 'deactivate'], ['csrf']);
    });
});
