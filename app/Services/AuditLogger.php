<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Writes append-only audit records (FR-AUD-01). Request context (IP, user agent)
 * is captured once per request by the front controller.
 */
final class AuditLogger
{
    private static ?string $ip = null;
    private static ?string $userAgent = null;

    public static function setRequestContext(?string $ip, ?string $userAgent): void
    {
        self::$ip = $ip;
        self::$userAgent = $userAgent;
    }

    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
        ?int $userId = null,
    ): void {
        Database::connection()->insert('audit_logs', [
            'user_id'     => $userId ?? Auth::id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $old === null ? null : json_encode(self::scrub($old), JSON_UNESCAPED_UNICODE),
            'new_values'  => $new === null ? null : json_encode(self::scrub($new), JSON_UNESCAPED_UNICODE),
            'reason'      => $reason,
            'ip_address'  => self::$ip,
            'user_agent'  => self::$userAgent,
        ]);
    }

    /** Never store secrets in the audit trail. */
    private static function scrub(array $values): array
    {
        foreach (['password', 'password_hash', 'current_password', 'password_confirmation', '_token'] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }
        return $values;
    }
}
