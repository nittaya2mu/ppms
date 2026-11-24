<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

/**
 * Login with lockout (FR-AUTH-01, FR-AUTH-06) and password changes (FR-AUTH-03, FR-AUTH-04).
 */
final class AuthService
{
    public const OK = 'ok';
    public const INVALID = 'invalid';
    public const LOCKED = 'locked';
    public const INACTIVE = 'inactive';

    public function attempt(string $login, string $password): string
    {
        $db = Database::connection();
        $user = $db->fetch(
            'SELECT * FROM users WHERE (username = :l OR email = :l2) AND deleted_at IS NULL LIMIT 1',
            ['l' => $login, 'l2' => $login]
        );

        if ($user === null) {
            // Spend comparable time to avoid user enumeration by timing.
            password_verify($password, '$2y$10$usesomesillystringforsaltDZbvn7Nf0p8pN7G4rF4m9U1r8dOa');
            AuditLogger::log('login.failed', 'user', null, null, ['login' => $login]);
            return self::INVALID;
        }
        if ((int) $user['is_active'] !== 1) {
            AuditLogger::log('login.inactive', 'user', (int) $user['id'], userId: (int) $user['id']);
            return self::INACTIVE;
        }
        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            AuditLogger::log('login.locked', 'user', (int) $user['id'], userId: (int) $user['id']);
            return self::LOCKED;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $max = (int) config('security.max_failed_logins', 5);
            $failed = (int) $user['failed_login_count'] + 1;
            $lockUntil = $failed >= $max
                ? date('Y-m-d H:i:s', time() + 60 * (int) config('security.lockout_minutes', 15))
                : null;
            $db->update('users', [
                'failed_login_count' => $lockUntil ? 0 : $failed,
                'locked_until'       => $lockUntil,
            ], 'id = :id', ['id' => (int) $user['id']]);
            AuditLogger::log('login.failed', 'user', (int) $user['id'], null, ['failed_count' => $failed, 'locked' => (bool) $lockUntil], userId: (int) $user['id']);
            return $lockUntil ? self::LOCKED : self::INVALID;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => (int) $user['id']]);
        }
        $db->update('users', [
            'failed_login_count' => 0,
            'locked_until'       => null,
            'last_login_at'      => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $user['id']]);

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('last_activity', time());
        Session::forget('active_role');
        AuditLogger::log('login.success', 'user', (int) $user['id'], userId: (int) $user['id']);
        return self::OK;
    }

    public function changePassword(int $userId, string $current, string $new): bool
    {
        $db = Database::connection();
        $hash = $db->value('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]);
        if (!is_string($hash) || !password_verify($current, $hash)) {
            return false;
        }
        $db->update('users', [
            'password_hash'        => password_hash($new, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ], 'id = :id', ['id' => $userId]);
        Session::regenerate();
        AuditLogger::log('password.changed', 'user', $userId);
        return true;
    }

    /** Admin reset: returns the temporary password to show once. */
    public function resetPassword(int $userId, int $adminId): string
    {
        $temp = self::temporaryPassword();
        Database::connection()->update('users', [
            'password_hash'        => password_hash($temp, PASSWORD_DEFAULT),
            'must_change_password' => 1,
            'failed_login_count'   => 0,
            'locked_until'         => null,
        ], 'id = :id', ['id' => $userId]);
        AuditLogger::log('password.reset', 'user', $userId, userId: $adminId);
        return $temp;
    }

    public static function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out . random_int(10, 99);
    }

    public function logout(): void
    {
        $id = Session::get('user_id');
        if ($id !== null) {
            AuditLogger::log('logout', 'user', (int) $id, userId: (int) $id);
        }
        Session::destroy();
    }
}
