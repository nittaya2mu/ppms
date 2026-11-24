<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Validators\Validator;

/** User Management (FR-USR-01, FR-USR-03, FR-AUTH-04). */
final class UserController
{
    private const ROLES = ['admin', 'student', 'advisor', 'committee'];

    public function index(Request $request): Response
    {
        $search = (string) $request->query('q', '');
        $role = (string) $request->query('role', '');
        $role = in_array($role, self::ROLES, true) ? $role : '';
        $page = max(1, (int) $request->query('page', 1));
        $result = User::paginate($search, $role, $page);
        return Response::view('admin/users/index', $result + ['search' => $search, 'role' => $role]);
    }

    public function create(Request $request): Response
    {
        return Response::view('admin/users/form', ['user' => null, 'roles' => [], 'student' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        $roles = $this->roles($data);
        $rules = $this->rules(null) + ['roles' => 'required'];
        if (in_array('student', $roles, true)) {
            $rules += ['student_code' => 'required|max:20|unique:students,student_code', 'program' => 'required|max:150', 'department' => 'required|max:150'];
        }
        $v = Validator::make($data + ['roles' => $roles], $rules);
        if ($v->fails()) {
            return Response::back($request, '/admin/users/create', $v->errors());
        }

        $temp = AuthService::temporaryPassword();
        $student = in_array('student', $roles, true) ? [
            'student_code' => $data['student_code'],
            'program'      => $data['program'],
            'department'   => $data['department'],
        ] : null;
        $id = User::create($this->userColumns($data) + [
            'password_hash'        => password_hash($temp, PASSWORD_DEFAULT),
            'must_change_password' => 1,
        ], $roles, (int) Auth::id(), $student);

        AuditLogger::log('user.created', 'user', $id, null, $this->userColumns($data) + ['roles' => $roles]);
        Session::flash('success', __('users.created_with_password', ['password' => $temp]));
        return Response::redirect('/admin/users');
    }

    public function edit(Request $request): Response
    {
        $user = User::find((int) $request->param('id')) ?? throw HttpException::notFound();
        return Response::view('admin/users/form', [
            'user'    => $user,
            'roles'   => User::roleCodes((int) $user['id']),
            'student' => User::student((int) $user['id']),
        ]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        $user = User::find($id) ?? throw HttpException::notFound();
        $data = $request->all();
        $roles = $this->roles($data);
        $v = Validator::make($data + ['roles' => $roles], $this->rules($id) + ['roles' => 'required']);
        if ($v->fails()) {
            return Response::back($request, "/admin/users/$id/edit", $v->errors());
        }
        if ($id === Auth::id() && !in_array('admin', $roles, true)) {
            return Response::back($request, "/admin/users/$id/edit", ['roles' => [__('users.cannot_remove_own_admin')]]);
        }
        if (in_array('student', $roles, true) && User::student($id) === null) {
            return Response::back($request, "/admin/users/$id/edit", ['roles' => [__('users.student_profile_required')]]);
        }

        $new = $this->userColumns($data) + ['is_active' => isset($data['is_active']) ? 1 : 0];
        if ($id === Auth::id()) {
            $new['is_active'] = 1;
        }
        Database::connection()->transaction(function (Database $db) use ($id, $new, $roles, $user) {
            $db->update('users', $new, 'id = :id', ['id' => $id]);
            $oldRoles = User::roleCodes($id);
            User::syncRoles($id, $roles, (int) Auth::id());
            AuditLogger::log(
                'user.updated',
                'user',
                $id,
                array_intersect_key($user, $new) + ['roles' => $oldRoles],
                $new + ['roles' => $roles]
            );
        });
        Session::flash('success', __('users.updated'));
        return Response::redirect('/admin/users');
    }

    public function resetPassword(Request $request): Response
    {
        $id = (int) $request->param('id');
        User::find($id) ?? throw HttpException::notFound();
        $temp = (new AuthService())->resetPassword($id, (int) Auth::id());
        Session::flash('success', __('users.password_reset', ['password' => $temp]));
        return Response::redirect('/admin/users');
    }

    /** Soft delete (FR-USR-01, DD-12). */
    public function deactivate(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($id === Auth::id()) {
            throw HttpException::forbidden();
        }
        User::find($id) ?? throw HttpException::notFound();
        Database::connection()->update('users', ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        AuditLogger::log('user.deleted', 'user', $id, reason: (string) $request->input('reason', ''));
        Session::flash('success', __('users.deleted'));
        return Response::redirect('/admin/users');
    }

    private function rules(?int $ignoreId): array
    {
        $ignore = $ignoreId === null ? '' : ",$ignoreId";
        return [
            'username'      => "required|max:50|regex:/^[A-Za-z0-9._-]+$/|unique:users,username$ignore",
            'email'         => "required|email|max:150|unique:users,email$ignore",
            'title_th'      => 'max:50',
            'first_name_th' => 'required|max:100',
            'last_name_th'  => 'required|max:100',
            'first_name_en' => 'max:100',
            'last_name_en'  => 'max:100',
        ];
    }

    private function userColumns(array $data): array
    {
        $out = [];
        foreach (['username', 'email', 'title_th', 'first_name_th', 'last_name_th', 'first_name_en', 'last_name_en'] as $k) {
            $value = isset($data[$k]) ? trim((string) $data[$k]) : null;
            $out[$k] = $value === '' ? null : $value;
        }
        return $out;
    }

    /** @return string[] */
    private function roles(array $data): array
    {
        $roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];
        return array_values(array_intersect(self::ROLES, array_map('strval', $roles)));
    }
}
