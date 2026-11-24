<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Typed access to system_settings (T32) with a per-request cache. */
final class SettingsService
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$cache ??= self::loadAll();
        if (!array_key_exists($key, self::$cache)) {
            return $default;
        }
        [$value, $type] = self::$cache[$key];
        return match ($type) {
            'int'  => (int) $value,
            'bool' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode((string) $value, true),
            default => (string) $value,
        };
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default);
    }

    public static function set(string $key, string $value, int $userId): void
    {
        Database::connection()->query(
            'UPDATE system_settings SET setting_value = :v, updated_by = :u WHERE setting_key = :k',
            ['v' => $value, 'u' => $userId, 'k' => $key]
        );
        self::$cache = null;
    }

    public static function all(): array
    {
        return Database::connection()->fetchAll('SELECT * FROM system_settings ORDER BY setting_key');
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function loadAll(): array
    {
        try {
            $rows = Database::connection()->fetchAll('SELECT setting_key, setting_value, value_type FROM system_settings');
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[$row['setting_key']] = [$row['setting_value'], $row['value_type']];
        }
        return $out;
    }
}
