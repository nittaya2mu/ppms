<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use App\Validators\Validator;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        return Response::view('auth/login');
    }

    public function login(Request $request): Response
    {
        $v = Validator::make($request->all(), ['login' => 'required|max:150', 'password' => 'required']);
        if ($v->fails()) {
            return Response::back($request, '/login', $v->errors());
        }

        $result = (new AuthService())->attempt((string) $request->input('login'), (string) ($request->all()['password'] ?? ''));
        if ($result !== AuthService::OK) {
            Session::flash('danger', __('auth.' . $result));
            return Response::back($request, '/login');
        }

        $intended = Session::get('intended', '/dashboard');
        Session::forget('intended');
        return Response::redirect(is_string($intended) && str_starts_with($intended, '/') ? $intended : '/dashboard');
    }

    public function logout(Request $request): Response
    {
        (new AuthService())->logout();
        Session::start(config('session'));
        Session::flash('success', __('auth.logged_out'));
        return Response::redirect('/login');
    }

    public function showChangePassword(Request $request): Response
    {
        return Response::view('auth/change_password', ['forced' => (int) Auth::user()['must_change_password'] === 1]);
    }

    public function changePassword(Request $request): Response
    {
        $data = $request->all();
        $min = (int) config('security.min_password_len', 8);
        $v = Validator::make($data, [
            'current_password' => 'required',
            'password'         => "required|min:$min|max:72|confirmed|regex:/^(?=.*[A-Za-z])(?=.*\\d).+$/",
        ]);
        if ($v->fails()) {
            return Response::back($request, '/password', $v->errors());
        }
        if (($data['password'] ?? '') === ($data['current_password'] ?? '')) {
            return Response::back($request, '/password', ['password' => [__('auth.password_same')]]);
        }
        if (!(new AuthService())->changePassword((int) Auth::id(), (string) $data['current_password'], (string) $data['password'])) {
            return Response::back($request, '/password', ['current_password' => [__('auth.current_password_wrong')]]);
        }
        Session::flash('success', __('auth.password_changed'));
        return Response::redirect('/dashboard');
    }

    public function switchRole(Request $request): Response
    {
        $role = (string) $request->input('role');
        if (!Auth::switchRole($role)) {
            Session::flash('danger', __('auth.role_not_allowed'));
        }
        return Response::redirect('/dashboard');
    }
}
