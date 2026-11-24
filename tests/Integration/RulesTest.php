<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\IntegrityGuard;
use App\Core\IntegrityViolation;
use App\Services\Rules\CommitteeRules;
use App\Services\Rules\EvaluationRules;
use App\Services\Rules\RuleViolation;
use App\Services\Rules\VersionRules;
use Tests\DatabaseTestCase;

require_once __DIR__ . '/Fixtures.php';

/**
 * PHP-level copies of the trigger rules. These must pass under BOTH schemas, because on
 * InfinityFree (no triggers) they are the only enforcement.
 */
final class RulesTest extends DatabaseTestCase
{
    private Fixtures $fx;

    public function setUp(): void
    {
        parent::setUp();
        $this->fx = new Fixtures($this->db);
    }

    public function testCommitteeRules(): void
    {
        CommitteeRules::assertAssignable(101, 103, 104);
        $this->assertThrows(RuleViolation::class, fn () => CommitteeRules::assertAssignable(101, 103, 103), 'different');
        $this->assertThrows(RuleViolation::class, fn () => CommitteeRules::assertAssignable(101, 101, 103), 'advisor');
        $this->assertThrows(RuleViolation::class, fn () => CommitteeRules::assertAssignable(101, 103, 201), 'committee role'); // student
        $this->db->query('UPDATE user_roles SET is_active = 0 WHERE user_id = 104 AND role_id = 4');
        $this->assertThrows(RuleViolation::class, fn () => CommitteeRules::assertAssignable(101, 103, 104), 'committee role');
        $this->db->query('UPDATE user_roles SET is_active = 1 WHERE user_id = 104 AND role_id = 4');
        $this->db->query('UPDATE users SET is_active = 0 WHERE id = 104');
        $this->assertThrows(RuleViolation::class, fn () => CommitteeRules::assertAssignable(101, 103, 104), 'committee role');
    }

    public function testAssignedVersionIsFixed(): void
    {
        $ids = $this->fx->evaluationReady();
        CommitteeRules::assertVersionUnchanged($ids['assignment'], $ids['version']);
        $this->assertThrows(RuleViolation::class, fn () => CommitteeRules::assertVersionUnchanged($ids['assignment'], $ids['version'] + 1));
    }

    public function testEvaluationLockAndReopen(): void
    {
        $ids = $this->fx->evaluationReady();
        EvaluationRules::assertEditable($ids['eval1']);
        $this->db->query("UPDATE committee_evaluations SET status = 'submitted', total_score = 79, recommendation = 'approved', submitted_at = NOW() WHERE id = :id", ['id' => $ids['eval1']]);
        $this->assertThrows(RuleViolation::class, fn () => EvaluationRules::assertEditable($ids['eval1']), 'locked');
        $this->assertThrows(RuleViolation::class, fn () => EvaluationRules::assertReopenable($ids['eval1'], 1, ' '), 'reason');
        $this->assertThrows(RuleViolation::class, fn () => EvaluationRules::assertReopenable($ids['eval2'], 1, 'x'), 'submitted');
        EvaluationRules::assertReopenable($ids['eval1'], 1, 'คะแนนผิดพลาด');
        EvaluationRules::assertEditable($ids['eval2']); // the other member is unaffected
    }

    public function testVersionRules(): void
    {
        $t = $this->fx->topicWithSubmission($this->fx->project($this->fx->group('G1', 1)));
        $this->assertThrows(RuleViolation::class, fn () => VersionRules::assertTopicSubmissionEditable($t['submission']), 'immutable');
        VersionRules::assertProposalVersionUpdate(['version_status' => 'superseded']);
        $this->assertThrows(RuleViolation::class, fn () => VersionRules::assertProposalVersionUpdate(['file_id' => 9]));
    }

    public function testIntegrityGuardBlocksAppendOnlyWritesThroughDatabaseLayer(): void
    {
        $this->db->insert('audit_logs', ['action' => 'x']);   // INSERT is allowed
        foreach (IntegrityGuard::APPEND_ONLY as $table) {
            $this->assertThrows(IntegrityViolation::class, fn () => $this->db->query("UPDATE $table SET id = id"));
            $this->assertThrows(IntegrityViolation::class, fn () => $this->db->query("DELETE FROM $table"));
            $this->assertThrows(IntegrityViolation::class, fn () => $this->db->query("TRUNCATE TABLE `$table`"));
        }
        $this->assertThrows(IntegrityViolation::class, fn () => $this->db->query("  -- comment\n delete from proposal_versions where id = 1"));
        $this->assertThrows(IntegrityViolation::class, fn () => $this->db->update('approval_decisions', ['comments' => 'x'], 'id = 1'));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM audit_logs'));
    }
}
