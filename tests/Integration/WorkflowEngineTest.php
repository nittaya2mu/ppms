<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Workflow\TransitionDenied;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\Workflows;
use Tests\DatabaseTestCase;

require_once __DIR__ . '/Fixtures.php';

/** Server-side transitions write status history and audit in one transaction (DD-10, FR-AUD-02). */
final class WorkflowEngineTest extends DatabaseTestCase
{
    private Fixtures $fx;

    public function setUp(): void
    {
        parent::setUp();
        $this->fx = new Fixtures($this->db);
    }

    public function testTransitionUpdatesStatusAndRecordsHistory(): void
    {
        $t = $this->fx->topicWithSubmission($this->fx->project($this->fx->group('G1', 1)));
        $engine = new WorkflowEngine(Workflows::topic());
        $this->assertSame('advisor_review', $engine->apply($t['topic'], 'start_review', 'advisor', 101));
        $this->assertSame('revision_required', $engine->apply($t['topic'], 'advisor_revise', 'advisor', 101, 'ขอบเขตกว้างเกินไป', ['returned_from' => 'advisor']));

        $row = $this->db->fetch('SELECT status, returned_from, lock_version FROM project_topics WHERE id = :id', ['id' => $t['topic']]);
        $this->assertSame('revision_required', $row['status']);
        $this->assertSame('advisor', $row['returned_from']);
        $this->assertSame(2, (int) $row['lock_version']);

        $history = $this->db->fetchAll("SELECT from_status, to_status, reason, changed_by FROM status_histories WHERE entity_type = 'topic' AND entity_id = :id ORDER BY id", ['id' => $t['topic']]);
        $this->assertSame(2, count($history));
        $this->assertSame('advisor_review', $history[1]['from_status']);
        $this->assertSame('ขอบเขตกว้างเกินไป', $history[1]['reason']);
        $this->assertSame(101, (int) $history[1]['changed_by']);
        $this->assertSame(2, (int) $this->db->value("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'topic' AND entity_id = :id", ['id' => $t['topic']]));
    }

    public function testDeniedTransitionChangesNothing(): void
    {
        $t = $this->fx->topicWithSubmission($this->fx->project($this->fx->group('G1', 1)));
        $engine = new WorkflowEngine(Workflows::topic());
        $this->assertThrows(TransitionDenied::class, fn () => $engine->apply($t['topic'], 'admin_approve', 'admin', 1));
        $this->assertSame('submitted', $this->db->value('SELECT status FROM project_topics WHERE id = :id', ['id' => $t['topic']]));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM status_histories'));
    }

    public function testOuterFailureRollsBackTransition(): void   // NFR-INT-01
    {
        $t = $this->fx->topicWithSubmission($this->fx->project($this->fx->group('G1', 1)));
        $engine = new WorkflowEngine(Workflows::topic());
        try {
            $this->db->transaction(function () use ($engine, $t) {
                $engine->apply($t['topic'], 'start_review', 'advisor', 101);
                throw new \RuntimeException('notification failed');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('submitted', $this->db->value('SELECT status FROM project_topics WHERE id = :id', ['id' => $t['topic']]));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM status_histories'));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM audit_logs'));
    }

    public function testGroupVerificationFlow(): void
    {
        $g = $this->fx->group('G1', 1, [2], 'draft');
        $engine = new WorkflowEngine(Workflows::group());
        $engine->apply($g, 'submit', 'leader', 201);
        $this->assertThrows(TransitionDenied::class, fn () => $engine->apply($g, 'reject', 'admin', 1, ''), 'requires a reason');
        $engine->apply($g, 'verify', 'admin', 1);
        $this->assertSame('active', $engine->apply($g, 'activate', 'admin', 1));
    }
}
