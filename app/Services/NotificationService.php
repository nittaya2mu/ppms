<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** In-app notifications (FR-NOT-01). Email delivery is an optional extension (email_status). */
final class NotificationService
{
    /** @param int[] $userIds */
    public static function notify(
        array $userIds,
        string $eventType,
        string $title,
        string $message,
        ?string $link = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): void {
        $db = Database::connection();
        $emailStatus = SettingsService::bool('email.enabled') ? 'queued' : 'none';
        foreach (array_unique(array_filter($userIds)) as $userId) {
            $db->insert('notifications', [
                'user_id'      => (int) $userId,
                'event_type'   => $eventType,
                'title'        => $title,
                'message'      => $message,
                'link_url'     => $link,
                'related_type' => $relatedType,
                'related_id'   => $relatedId,
                'email_status' => $emailStatus,
            ]);
        }
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::connection()->value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0',
            ['u' => $userId]
        );
    }

    public static function latest(int $userId, int $limit = 20): array
    {
        return Database::connection()->fetchAll(
            'SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC, id DESC LIMIT :lim',
            ['u' => $userId, 'lim' => $limit]
        );
    }

    public static function markRead(int $userId, ?int $notificationId = null): void
    {
        $sql = 'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :u AND is_read = 0';
        $params = ['u' => $userId];
        if ($notificationId !== null) {
            $sql .= ' AND id = :id';
            $params['id'] = $notificationId;
        }
        Database::connection()->query($sql, $params);
    }

    /**
     * @return int[] user ids of active administrators.
     * Until per-offering admins are approved (OQ-24), every active admin is notified.
     */
    public static function admins(): array
    {
        $rows = Database::connection()->fetchAll(
            "SELECT DISTINCT u.id FROM users u
               JOIN user_roles ur ON ur.user_id = u.id AND ur.is_active = 1
               JOIN roles r ON r.id = ur.role_id AND r.code = 'admin'
              WHERE u.is_active = 1 AND u.deleted_at IS NULL"
        );
        return array_map('intval', array_column($rows, 'id'));
    }
}
