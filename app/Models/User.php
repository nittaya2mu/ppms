<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Data access for users, user_roles and students (T02-T04). */
final class User
{
    public static function find(int $id): ?array
    {
        return Database::connection()->fetch('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
    }

    /** @return string[] */
    public static function roleCodes(int $userId, bool $activeOnly = true): array
    {
        $sql = 'SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :u';
        if ($activeOnly) {
            $sql .= ' AND ur.is_active = 1';
        }
        return array_column(Database::connection()->fetchAll($sql . ' ORDER BY r.id', ['u' => $userId]), 'code');
    }

    public static function student(int $userId): ?array
    {
        return Database::connection()->fetch('SELECT * FROM students WHERE user_id = :u AND deleted_at IS NULL', ['u' => $userId]);
    }

    /**
     * Paginated, searchable listing (NFR-PERF-03).
     * @return array{rows:array,total:int,page:int,per_page:int}
     */
    public static function paginate(string $search = '', string $role = '', int $page = 1, int $perPage = 25): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.username LIKE :s1 OR u.email LIKE :s2 OR u.first_name_th LIKE :s3 OR u.last_name_th LIKE :s4 OR s.student_code LIKE :s5)';
            foreach (['s1', 's2', 's3', 's4', 's5'] as $k) {
                $params[$k] = '%' . $search . '%';
            }
        }
        if ($role !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code = :role AND ur.is_active = 1)';
            $params['role'] = $role;
        }
        $whereSql = implode(' AND ', $where);
        $db = Database::connection();
        $total = (int) $db->value("SELECT COUNT(*) FROM users u LEFT JOIN students s ON s.user_id = u.id WHERE $whereSql", $params);
        $page = max(1, $page);
        $rows = $db->fetchAll(
            "SELECT u.*, s.student_code,
                    (SELECT GROUP_CONCAT(r.code ORDER BY r.id) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                      WHERE ur.user_id = u.id AND ur.is_active = 1) AS role_codes
               FROM users u LEFT JOIN students s ON s.user_id = u.id
              WHERE $whereSql
              ORDER BY u.id
              LIMIT :lim OFFSET :off",
            array_merge($params, ['lim' => $perPage, 'off' => ($page - 1) * $perPage])
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * Creates a user with roles (and a student profile when the student role is given).
     * @param string[] $roles
     */
    public static function create(array $data, array $roles, int $grantedBy, ?array $student = null): int
    {
        $db = Database::connection();
        return $db->transaction(function (Database $db) use ($data, $roles, $grantedBy, $student) {
            $id = $db->insert('users', $data);
            self::syncRoles($id, $roles, $grantedBy);
            if ($student !== null) {
                $db->insert('students', array_merge($student, ['user_id' => $id]));
            }
            return $id;
        });
    }

    /** @param string[] $roles role codes to be active; others are deactivated (history kept). */
    public static function syncRoles(int $userId, array $roles, int $grantedBy): void
    {
        $db = Database::connection();
        foreach ($db->fetchAll('SELECT id, code FROM roles') as $role) {
            $params = ['u' => $userId, 'r' => (int) $role['id']];
            if (in_array($role['code'], $roles, true)) {
                $db->query(
                    'INSERT INTO user_roles (user_id, role_id, is_active, granted_by) VALUES (:u, :r, 1, :g)
                     ON DUPLICATE KEY UPDATE granted_at = IF(is_active = 0, NOW(), granted_at),
                                             granted_by = IF(is_active = 0, VALUES(granted_by), granted_by),
                                             is_active = 1',
                    $params + ['g' => $grantedBy]
                );
            } else {
                // Deactivate rather than delete so role history is kept.
                $db->query('UPDATE user_roles SET is_active = 0 WHERE user_id = :u AND role_id = :r', $params);
            }
        }
    }
}
