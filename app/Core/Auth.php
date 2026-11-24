<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Current-user accessor. The user row and role codes are cached in the session
 * and re-validated against the database on every request (deactivated users are logged out).
 */
final class Auth
{
    private static ?array $user = null;
    /** @var string[] */
    private static array $roles = [];

    public static function load(): void
    {
        self::$user = null;
        self::$roles = [];
        $id = Session::get('user_id');
        if ($id === null) {
            return;
        }
        $db = Database::connection();
        $user = $db->fetch(
            'SELECT * FROM users WHERE id = :id AND is_active = 1 AND deleted_at IS NULL',
            ['id' => (int) $id]
        );
        if ($user === null) {
            Session::destroy();
            return;
        }
        self::$user = $user;
        self::$roles = array_column($db->fetchAll(
            'SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id
              WHERE ur.user_id = :id AND ur.is_active = 1 ORDER BY r.id',
            ['id' => (int) $id]
        ), 'code');

        $active = Session::get('active_role');
        if ($active === null || !in_array($active, self::$roles, true)) {
            Session::set('active_role', self::$roles[0] ?? null);
        }
    }

    /** For tests and CLI scripts. */
    public static function setUser(?array $user, array $roles = [], ?string $activeRole = null): void
    {
        self::$user = $user;
        self::$roles = $roles;
        $_SESSION['active_role'] = $activeRole ?? ($roles[0] ?? null);
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user === null ? null : (int) self::$user['id'];
    }

    /** @return string[] */
    public static function roles(): array
    {
        return self::$roles;
    }

    public static function hasRole(string $role): bool
    {
        return in_array($role, self::$roles, true);
    }

    public static function activeRole(): ?string
    {
        return Session::get('active_role');
    }

    public static function switchRole(string $role): bool
    {
        if (!self::hasRole($role)) {
            return false;
        }
        Session::set('active_role', $role);
        return true;
    }
}
