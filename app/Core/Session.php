<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Secure session handling (NFR-SEC-05) and flash messages.
 */
final class Session
{
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($config['name'] ?? 'PPMS_SESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (bool) ($config['secure_cookie'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function pullFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    public static function setOld(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_token']);
        $_SESSION['_old'] = $input;
    }

    public static function setErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    /** Moves old input / errors from the session into request scope (read once). */
    public static function ageFlashData(): void
    {
        $GLOBALS['__old'] = $_SESSION['_old'] ?? [];
        $GLOBALS['__errors'] = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_old'], $_SESSION['_errors']);
    }
}
