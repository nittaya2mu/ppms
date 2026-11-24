<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;

final class NotificationController
{
    public function index(Request $request): Response
    {
        $items = NotificationService::latest((int) Auth::id(), 100);
        return Response::view('notifications/index', ['items' => $items]);
    }

    public function markRead(Request $request): Response
    {
        $id = $request->input('id');
        NotificationService::markRead((int) Auth::id(), $id === null || $id === '' ? null : (int) $id);
        return Response::redirect('/notifications');
    }
}
