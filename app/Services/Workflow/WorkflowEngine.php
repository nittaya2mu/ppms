<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Core\Database;
use App\Services\AuditLogger;

/**
 * Applies a state-machine transition to a row, server-side (Section 23 rule 7):
 * lock row -> check transition -> update status -> status_histories -> audit_logs.
 * Callers run this inside their own transaction together with decision records
 * and notifications so that everything commits or rolls back as one unit.
 */
final class WorkflowEngine
{
    private const TABLES = [
        'group'    => 'project_groups',
        'topic'    => 'project_topics',
        'proposal' => 'proposal_submissions',
    ];

    public function __construct(private readonly StateMachine $machine)
    {
    }

    /**
     * @param array<string,mixed> $extra additional columns to update with the status
     * @return string the new status
     */
    public function apply(int $id, string $action, string $role, int $userId, ?string $reason = null, array $extra = []): string
    {
        $table = self::TABLES[$this->machine->entity] ?? throw new \LogicException('No table for ' . $this->machine->entity);
        $db = Database::connection();

        return $db->transaction(function (Database $db) use ($table, $id, $action, $role, $userId, $reason, $extra) {
            $row = $db->fetch("SELECT id, status FROM $table WHERE id = :id FOR UPDATE", ['id' => $id]);
            if ($row === null) {
                throw new TransitionDenied("{$this->machine->entity} #$id not found", 'not_found');
            }
            $from = $row['status'];
            $to = $this->machine->check($from, $action, $role, $reason);

            $data = array_merge($extra, ['status' => $to]);
            $hasLock = in_array($table, ['project_groups', 'project_topics', 'proposal_submissions'], true);
            $sets = [];
            $params = ['id' => $id];
            foreach ($data as $col => $val) {
                if (!preg_match('/^[a-z_]+$/', $col)) {
                    throw new \InvalidArgumentException("Bad column $col");
                }
                $sets[] = "$col = :c_$col";
                $params["c_$col"] = $val;
            }
            if ($hasLock) {
                $sets[] = 'lock_version = lock_version + 1';
            }
            $db->query("UPDATE $table SET " . implode(', ', $sets) . ' WHERE id = :id', $params);

            $db->insert('status_histories', [
                'entity_type' => $this->machine->entity,
                'entity_id'   => $id,
                'from_status' => $from,
                'to_status'   => $to,
                'reason'      => $reason,
                'changed_by'  => $userId,
            ]);
            AuditLogger::log(
                $this->machine->entity . '.' . $action,
                $this->machine->entity,
                $id,
                ['status' => $from],
                $data,
                $reason,
                $userId
            );
            return $to;
        });
    }
}
