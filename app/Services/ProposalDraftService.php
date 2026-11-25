<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Rules\RuleViolation;

/**
 * Step 3: preparing the proposal (chapters 1-3).
 *  - The group keeps a working draft: chapter 1 sections 1.1-1.6, chapters 2 and 3 as numbered
 *    sections (heading + content), and one document file per chapter.
 *  - The leader sends each chapter to the advisor; the advisor approves or asks for changes.
 *  - When every chapter is approved (setting proposal.chapter_review_required), the leader submits
 *    the proposal: the new version is built from the approved chapter snapshots.
 */
final class ProposalDraftService
{
    public const CHAPTERS = [1, 2, 3];

    /** Default section headings offered for chapters 2 and 3 (students can rename or add). */
    public const DEFAULTS = [
        2 => ['title' => 'ทฤษฎีและงานวิจัยที่เกี่ยวข้อง', 'sections' => ['ทฤษฎีที่เกี่ยวข้อง', 'งานวิจัยที่เกี่ยวข้อง', 'เปรียบเทียบงานวิจัยที่เกี่ยวข้อง']],
        3 => ['title' => 'วิธีการดำเนินงาน', 'sections' => ['ภาพรวมของระบบ', 'การออกแบบระบบ', 'เครื่องมือและอุปกรณ์ที่ใช้', 'แผนการทดสอบและประเมินผล']],
    ];

    public const MAX_SECTIONS = 15;

    /** Statuses in which the group may prepare the next version. */
    public static function editable(?array $proposal): bool
    {
        return $proposal !== null && in_array($proposal['status'], ['draft', 'revision_required', 'approved_minor'], true);
    }

    /** The draft, created on first use from the latest version (or the defaults). */
    public static function get(int $projectId): array
    {
        $db = Database::connection();
        $row = $db->fetch('SELECT * FROM proposal_drafts WHERE project_id = :p', ['p' => $projectId]);
        if ($row === null) {
            $proposal = ProposalService::forProject($projectId);
            $latest = $proposal ? ProposalService::latestVersion((int) $proposal['id']) : null;
            return ['id' => null, 'project_id' => $projectId, 'content' => self::fromVersion($latest),
                'files' => [1 => $latest['file_id'] ?? null, 2 => $latest['ch2_file_id'] ?? null, 3 => $latest['ch3_file_id'] ?? null],
                'updated_at' => null];
        }
        $content = json_decode((string) $row['content_json'], true);
        return ['id' => (int) $row['id'], 'project_id' => $projectId, 'content' => self::normalize(is_array($content) ? $content : []),
            'files' => [1 => $row['ch1_file_id'] ? (int) $row['ch1_file_id'] : null, 2 => $row['ch2_file_id'] ? (int) $row['ch2_file_id'] : null, 3 => $row['ch3_file_id'] ? (int) $row['ch3_file_id'] : null],
            'updated_at' => $row['updated_at'] ?? $row['created_at']];
    }

    /** Content of a submitted version in the draft structure. */
    public static function fromVersion(?array $v): array
    {
        $c = ['ch1' => [], 'ch2' => null, 'ch3' => null];
        foreach (ProposalService::CHAPTER1 as $k) {
            $c['ch1'][$k] = (string) ($v[$k] ?? '');
        }
        foreach ([2, 3] as $ch) {
            $json = $v ? json_decode((string) ($v["ch{$ch}_json"] ?? ''), true) : null;
            $c["ch$ch"] = is_array($json) ? $json : null;
        }
        return self::normalize($c);
    }

    /** Fills missing parts with defaults and drops empty sections. */
    public static function normalize(array $c): array
    {
        $out = ['ch1' => []];
        foreach (ProposalService::CHAPTER1 as $k) {
            $out['ch1'][$k] = (string) ($c['ch1'][$k] ?? '');
        }
        foreach ([2, 3] as $ch) {
            $src = $c["ch$ch"] ?? null;
            if (!is_array($src)) {
                $src = ['title' => self::DEFAULTS[$ch]['title'], 'sections' => array_map(fn ($h) => ['heading' => $h, 'content' => ''], self::DEFAULTS[$ch]['sections'])];
            }
            $sections = [];
            foreach ((array) ($src['sections'] ?? []) as $s) {
                $h = trim((string) ($s['heading'] ?? ''));
                $t = trim((string) ($s['content'] ?? ''));
                if ($h !== '' || $t !== '') {
                    $sections[] = ['heading' => $h, 'content' => $t];
                }
            }
            $out["ch$ch"] = ['title' => trim((string) ($src['title'] ?? '')) ?: self::DEFAULTS[$ch]['title'], 'sections' => array_slice($sections, 0, self::MAX_SECTIONS)];
        }
        return $out;
    }

    /**
     * Saves typed content and any uploaded chapter files.
     * @param array $input request fields: ch1_* , ch2_title, ch2_heading[], ch2_content[], ch3_...
     * @param array<int,?array> $uploads chapter => uploaded file
     */
    public static function save(int $projectId, int $userId, array $input, array $uploads = []): array
    {
        $project = self::projectForMember($projectId, $userId);
        if (!self::editable(ProposalService::forProject($projectId))) {
            throw new RuleViolation('proposal_locked', 'The proposal cannot be edited now');
        }
        $draft = self::get($projectId);
        $content = ['ch1' => []];
        foreach (ProposalService::CHAPTER1 as $k) {
            $content['ch1'][$k] = array_key_exists($k, $input) ? trim((string) $input[$k]) : $draft['content']['ch1'][$k];
        }
        foreach ([2, 3] as $ch) {
            if (!array_key_exists("ch{$ch}_heading", $input)) {
                $content["ch$ch"] = $draft['content']["ch$ch"];
                continue;
            }
            $sections = [];
            foreach ((array) $input["ch{$ch}_heading"] as $i => $h) {
                $sections[] = ['heading' => mb_substr(trim((string) $h), 0, 300), 'content' => trim((string) ($input["ch{$ch}_content"][$i] ?? ''))];
            }
            $content["ch$ch"] = ['title' => mb_substr(trim((string) ($input["ch{$ch}_title"] ?? '')), 0, 300), 'sections' => $sections];
        }
        $content = self::normalize($content);

        $allowed = SettingsService::get('upload.chapter_allowed_ext', ['pdf', 'docx', 'doc']);
        $allowed = is_array($allowed) ? $allowed : ['pdf'];
        $files = $draft['files'];
        foreach (self::CHAPTERS as $ch) {
            $u = $uploads[$ch] ?? null;
            if ($u !== null && (int) ($u['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $files[$ch] = FileService::store($u, 'proposal_draft', $projectId, $allowed, 'upload.chapter_max_mb', $userId);
            }
        }
        $data = ['content_json' => json_encode($content, JSON_UNESCAPED_UNICODE), 'ch1_file_id' => $files[1], 'ch2_file_id' => $files[2], 'ch3_file_id' => $files[3], 'updated_by' => $userId];
        $db = Database::connection();
        if ($draft['id'] === null) {
            $db->insert('proposal_drafts', $data + ['project_id' => $projectId]);
        } else {
            $db->update('proposal_drafts', $data, 'id = :id', ['id' => $draft['id']]);
        }
        return self::get($projectId);
    }

    /** Missing parts of one chapter in the draft (empty list = complete). */
    public static function missing(array $draft, int $chapter): array
    {
        $out = [];
        if ($chapter === 1) {
            foreach (ProposalService::CHAPTER1 as $k) {
                if (trim($draft['content']['ch1'][$k]) === '') {
                    $out[] = $k;
                }
            }
        } elseif (!array_filter($draft['content']["ch$chapter"]['sections'], fn ($s) => $s['heading'] !== '' && $s['content'] !== '')) {
            $out[] = 'sections';
        }
        if (empty($draft['files'][$chapter])) {
            $out[] = 'file';
        }
        return $out;
    }

    // ----- Chapter review by the advisor ---------------------------------------

    public static function reviewRequired(): bool
    {
        return SettingsService::bool('proposal.chapter_review_required', true);
    }

    /** Version number the draft will become. */
    public static function targetVersion(int $projectId): int
    {
        return 1 + (int) Database::connection()->value(
            'SELECT COALESCE(MAX(pv.version_no), 0) FROM proposal_versions pv JOIN proposal_submissions ps ON ps.id = pv.proposal_id WHERE ps.project_id = :p',
            ['p' => $projectId]
        );
    }

    /** @return array<int,?array> chapter => latest review for the next version */
    public static function reviews(int $projectId): array
    {
        $out = [1 => null, 2 => null, 3 => null];
        foreach (Database::connection()->fetchAll(
            'SELECT r.*, u.title_th, u.first_name_th, u.last_name_th, f.original_name FROM proposal_chapter_reviews r
               LEFT JOIN users u ON u.id = r.reviewed_by JOIN document_files f ON f.id = r.file_id
              WHERE r.project_id = :p AND r.target_version = :v ORDER BY r.id',
            ['p' => $projectId, 'v' => self::targetVersion($projectId)]
        ) as $r) {
            $r['content'] = json_decode((string) $r['content_json'], true) ?: [];
            $out[(int) $r['chapter']] = $r;
        }
        return $out;
    }

    public static function history(int $projectId): array
    {
        return Database::connection()->fetchAll(
            'SELECT r.id, r.chapter, r.target_version, r.status, r.submitted_at, r.reviewed_at, r.comments, u.title_th, u.first_name_th, u.last_name_th
               FROM proposal_chapter_reviews r LEFT JOIN users u ON u.id = r.reviewed_by WHERE r.project_id = :p ORDER BY r.id DESC',
            ['p' => $projectId]
        );
    }

    /** The leader sends one chapter (its current draft content + file) to the advisor. */
    public static function sendChapter(int $projectId, int $chapter, int $userId): int
    {
        $project = self::projectForMember($projectId, $userId);
        if (!AccessPolicy::isLeader((int) $project['group_id'], $userId)) {
            throw new RuleViolation('not_leader', 'Only the group leader sends chapters');
        }
        if (!in_array($chapter, self::CHAPTERS, true)) {
            throw new RuleViolation('invalid', 'Invalid chapter');
        }
        $proposal = ProposalService::forProject($projectId);
        if (!self::editable($proposal)) {
            throw new RuleViolation('proposal_locked', 'The proposal cannot be edited now');
        }
        $draft = self::get($projectId);
        if (self::missing($draft, $chapter)) {
            throw new RuleViolation('chapter_incomplete', 'The chapter is incomplete');
        }
        $latest = self::reviews($projectId)[$chapter];
        if ($latest !== null && $latest['status'] === 'submitted') {
            throw new RuleViolation('chapter_pending', 'This chapter is already waiting for the advisor');
        }
        $snapshot = $chapter === 1 ? $draft['content']['ch1'] : $draft['content']["ch$chapter"];
        $db = Database::connection();
        return $db->transaction(function (Database $db) use ($projectId, $chapter, $userId, $snapshot, $draft, $project) {
            $id = $db->insert('proposal_chapter_reviews', [
                'project_id' => $projectId, 'chapter' => $chapter, 'target_version' => self::targetVersion($projectId),
                'content_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE), 'file_id' => (int) $draft['files'][$chapter], 'submitted_by' => $userId,
            ]);
            AuditLogger::log('proposal.chapter_sent', 'proposal_chapter', $id, null, ['chapter' => $chapter], userId: $userId);
            $advisor = GroupService::advisorAssigned((int) $project['group_id']);
            if ($advisor) {
                NotificationService::notify([$advisor], 'proposal.chapter_sent', "บทที่ $chapter รอการตรวจ",
                    "โครงงาน {$project['project_code']} ส่งบทที่ $chapter ให้ตรวจ", '/projects/' . $projectId . '#chapters', 'proposal_chapter', $id);
            }
            return $id;
        });
    }

    /** $decision: approve | revise */
    public static function reviewChapter(int $reviewId, int $advisorId, string $decision, ?string $comments): void
    {
        $db = Database::connection();
        $r = $db->fetch('SELECT * FROM proposal_chapter_reviews WHERE id = :id', ['id' => $reviewId]) ?? throw new RuleViolation('chapter_missing', 'Not found');
        $project = $db->fetch('SELECT * FROM projects WHERE id = :p', ['p' => (int) $r['project_id']]);
        if (GroupService::advisorAssigned((int) $project['group_id']) !== $advisorId) {
            throw new RuleViolation('not_advisor', 'Only the project advisor reviews chapters');
        }
        if ($r['status'] !== 'submitted') {
            throw new RuleViolation('chapter_not_pending', 'This chapter is not waiting for review');
        }
        if (!in_array($decision, ['approve', 'revise'], true)) {
            throw new RuleViolation('invalid', 'Invalid decision');
        }
        if ($decision === 'revise' && trim((string) $comments) === '') {
            throw new RuleViolation('reason_required', 'Comments are required');
        }
        $status = $decision === 'approve' ? 'approved' : 'revision_required';
        $db->transaction(function (Database $db) use ($r, $reviewId, $advisorId, $status, $comments, $project) {
            $db->update('proposal_chapter_reviews', ['status' => $status, 'reviewed_by' => $advisorId, 'reviewed_at' => date('Y-m-d H:i:s'), 'comments' => trim((string) $comments) ?: null], 'id = :id', ['id' => $reviewId]);
            AuditLogger::log('proposal.chapter_' . $status, 'proposal_chapter', $reviewId, null, ['chapter' => (int) $r['chapter']], $comments, $advisorId);
            NotificationService::notify(GroupService::memberUserIds((int) $project['group_id']), 'proposal.chapter_reviewed',
                "อาจารย์ที่ปรึกษาตรวจบทที่ {$r['chapter']} แล้ว", __('chapter_status.' . $status) . ($comments ? ': ' . $comments : ''),
                '/projects/' . $project['id'] . '#chapters', 'proposal_chapter', $reviewId);
        });
    }

    /** Why the leader cannot submit the proposal yet (null = can). */
    public static function submitBlocker(int $projectId): ?string
    {
        $draft = self::get($projectId);
        foreach (self::CHAPTERS as $ch) {
            if (self::missing($draft, $ch)) {
                return 'chapter_incomplete';
            }
        }
        if (self::reviewRequired()) {
            foreach (self::reviews($projectId) as $r) {
                if ($r === null || $r['status'] !== 'approved') {
                    return 'chapters_not_approved';
                }
            }
        }
        return null;
    }

    /**
     * Builds the next proposal version. With chapter review on, the approved snapshots are used
     * (later draft edits need a new chapter review); otherwise the draft itself.
     */
    public static function submit(int $projectId, int $userId, ?array $response, ?string $notes): int
    {
        if ($b = self::submitBlocker($projectId)) {
            throw new RuleViolation($b, 'The proposal cannot be submitted yet');
        }
        $draft = self::get($projectId);
        $ch1 = $draft['content']['ch1'];
        $ch2 = $draft['content']['ch2'];
        $ch3 = $draft['content']['ch3'];
        $files = $draft['files'];
        if (self::reviewRequired()) {
            $rev = self::reviews($projectId);
            $ch1 = $rev[1]['content'];
            $ch2 = $rev[2]['content'];
            $ch3 = $rev[3]['content'];
            $files = [1 => (int) $rev[1]['file_id'], 2 => (int) $rev[2]['file_id'], 3 => (int) $rev[3]['file_id']];
        }
        return ProposalService::submit($projectId, $ch1, array_map('intval', $files), $response, $notes, $userId, [
            'ch2_json' => json_encode($ch2, JSON_UNESCAPED_UNICODE), 'ch3_json' => json_encode($ch3, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** Chapters waiting for an advisor (for the Advisor Review menu). */
    public static function pendingForAdvisor(int $offeringId, int $advisorId): array
    {
        return Database::connection()->fetchAll(
            "SELECT p.id AS project_id, p.project_code, p.title_th, g.group_code, g.group_name, r.target_version AS version_no,
                    r.submitted_at, 'submitted' AS status, CONCAT('บทที่ ', r.chapter) AS chapter_label
               FROM proposal_chapter_reviews r JOIN projects p ON p.id = r.project_id JOIN project_groups g ON g.id = p.group_id
               JOIN project_advisors pa ON pa.group_id = g.id AND pa.status = 'assigned' AND pa.advisor_id = :u
              WHERE p.offering_id = :o AND r.status = 'submitted' ORDER BY r.submitted_at",
            ['o' => $offeringId, 'u' => $advisorId]
        );
    }

    private static function projectForMember(int $projectId, int $userId): array
    {
        $project = Database::connection()->fetch('SELECT * FROM projects WHERE id = :p AND deleted_at IS NULL', ['p' => $projectId])
            ?? throw new RuleViolation('project_missing', 'Project not found');
        if (!in_array($userId, GroupService::memberUserIds((int) $project['group_id']), true)) {
            throw new RuleViolation('not_member', 'Only group members can edit the proposal');
        }
        return $project;
    }
}
