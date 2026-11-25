<?php
/**
 * @var array $project @var array $group @var array $members @var ?array $advisor @var ?array $topicView
 * @var array $rejectedTopics @var ?array $proposalView @var bool $isLeader @var bool $isAdvisor
 * @var array $committeeChoices @var int $proposalMaxMb @var int $topicMaxMb @var string $justifyWhen
 */
use App\Core\Auth;
use App\Services\ProposalService;
use App\Services\TopicService;

$this->layout('layouts/app', ['title' => $project['project_code']]);
$role = Auth::activeRole();
$isAdmin = $role === 'admin';
$base = '/projects/' . (int) $project['id'];
$topic = $topicView['topic'] ?? null;
$sub = $topicView['submission'] ?? null;
$topicEditable = $isLeader && ($topic === null || in_array($topic['status'], ['draft', 'revision_required'], true));
$p = $proposalView['proposal'] ?? null;
$file = fn (?int $id, string $label) => $id ? '<a href="' . e(url('/files/' . $id)) . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-arrow-down"></i> ' . e($label) . '</a>' : '';

$steps = ['group' => 'group', 'topic' => 'topic', 'proposal' => 'proposal', 'evaluation' => 'evaluation', 'final' => 'final', 'scope' => 'scope'];
$current = 1;
if ($topic && $topic['status'] === 'topic_approved') {
    $current = 2;
}
if ($p && !in_array($p['status'], ['draft'], true)) {
    $current = 3;
}
if ($p && in_array($p['status'], ['evaluation_completed', 'final_review', 'revision_required', 'approved_minor', 're_evaluation'], true)) {
    $current = 4;
}
if ($p && in_array($p['status'], ['approved', 'rejected'], true)) {
    $current = 5;
}
if ($scopeStatus === 'approved') {
    $current = 6;
}

$decisionForm = function (string $action, array $options, bool $commentRequiredHint = true) {
    ob_start(); ?>
    <form method="post" action="<?= e(url($action)) ?>" class="row g-2" data-confirm="<?= e(__('common.decision_confirm')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-4"><select name="decision" class="form-select" required>
            <?php foreach ($options as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-8"><textarea name="comments" class="form-control" rows="2" placeholder="<?= e(__('common.comments_placeholder')) ?>"></textarea></div>
        <div class="col-12"><button class="btn btn-ppms"><i class="bi bi-check2-square"></i> <?= e(__('common.record_decision')) ?></button>
            <?php if ($commentRequiredHint): ?><span class="small text-secondary ms-2"><?= e(__('common.comment_required_hint')) ?></span><?php endif; ?></div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="small text-secondary"><?= e($project['project_code']) ?> · <a href="<?= e(url('/groups/' . (int) $group['id'])) ?>"><?= e($group['group_code']) ?> <?= e($group['group_name']) ?></a></div>
        <h1 class="h4 mb-0"><?= e($project['title_th'] ?: ($sub['title_th'] ?? __('topics.untitled'))) ?></h1>
        <?php if ($project['title_en'] ?: ($sub['title_en'] ?? '')): ?><div class="text-secondary"><?= e($project['title_en'] ?: $sub['title_en']) ?></div><?php endif; ?>
    </div>
    <?php if ($project['stage'] === 'approved'): ?>
        <a class="btn btn-outline-success" href="<?= e(url("$base/summary")) ?>"><i class="bi bi-award"></i> <?= e(__('final.summary')) ?></a>
    <?php endif; ?>
</div>

<?= $this->partial('projects/_tabs', ['project' => $project, 'active' => 'main']) ?>
<div class="card mb-3"><div class="card-body">
    <ol class="ppms-stepper mb-3">
        <?php $i = 0; foreach ($steps as $s): ?>
            <li class="<?= $i < $current ? 'done' : ($i === $current ? 'current' : '') ?>"><span class="dot"><?= $i < $current ? '<i class="bi bi-check"></i>' : $i + 1 ?></span><span class="label"><?= e(__('step.' . $s)) ?></span></li>
        <?php $i++; endforeach; ?>
    </ol>
    <div class="row small">
        <div class="col-md-6"><strong><?= e(__('dash.members')) ?>:</strong>
            <?php foreach ($members as $m): ?><span class="me-2"><?= e($m['student_code']) ?> <?= e($m['first_name_th']) ?><?= $m['member_role'] === 'leader' ? ' (Leader)' : '' ?></span><?php endforeach; ?></div>
        <div class="col-md-6"><strong><?= e(__('dash.advisor')) ?>:</strong> <?= $advisor && $advisor['status'] === 'assigned' ? e(full_name($advisor)) : '-' ?></div>
    </div>
</div></div>

<!-- ========================== TOPIC ========================== -->
<div class="card mb-3" id="topic"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0"><i class="bi bi-journal-text"></i> <?= e(__('menu.topics')) ?></h2>
        <div><?= $topic ? status_badge($topic['status']) . ' <span class="small text-secondary">v' . (int) ($sub['version_no'] ?? 1) . '</span>' : '' ?></div>
    </div>

    <?php if ($rejectedTopics): ?>
        <div class="alert alert-secondary small py-2"><?= e(__('topics.previous_rejected', ['n' => count($rejectedTopics)])) ?></div>
    <?php endif; ?>

    <?php if ($topicEditable): ?>
        <?php if ($topic && $topic['status'] === 'revision_required' && $topicView['decisions']): ?>
            <div class="alert alert-warning"><strong><?= e(__('topics.revision_comments')) ?>:</strong> <?= nl2br(e($topicView['decisions'][0]['comments'])) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url("$base/topic")) ?>">
            <?= csrf_field() ?>
            <p class="small text-secondary mb-2"><i class="bi bi-file-earmark-text"></i> <?= e(__('topics.form1_help')) ?></p>
            <div class="row g-2">
                <div class="col-12"><label class="form-label small fw-semibold" for="f_title_th"><?= e(__('topic_field.title_th')) ?> <span class="text-danger">*</span></label>
                    <input class="form-control" id="f_title_th" name="title_th" maxlength="500" value="<?= e(old('title_th', $sub['title_th'] ?? '')) ?>"></div>
                <div class="col-12"><label class="form-label small fw-semibold" for="f_title_en"><?= e(__('topic_field.title_en')) ?> <span class="text-danger">*</span></label>
                    <input class="form-control" id="f_title_en" name="title_en" maxlength="500" value="<?= e(old('title_en', $sub['title_en'] ?? '')) ?>"></div>
                <div class="col-12"><span class="form-label small fw-semibold d-block"><?= e(__('topic_field.students')) ?></span>
                    <ul class="small mb-1"><?php foreach ($members as $m): ?><li><?= e(full_name($m)) ?> · <?= e(__('topics.student_code')) ?> <?= e($m['student_code']) ?></li><?php endforeach; ?></ul></div>
                <div class="col-12"><span class="form-label small fw-semibold d-block"><?= e(__('topic_field.project_type')) ?> <span class="text-danger">*</span></span>
                    <?php $ptype = old('project_type', $sub['project_type'] ?? ''); ?>
                    <div class="row row-cols-1 row-cols-md-2 g-1 small">
                        <?php foreach (TopicService::PROJECT_TYPES as $t): ?>
                            <div class="col"><div class="form-check"><input class="form-check-input" type="radio" name="project_type" id="pt_<?= e($t) ?>" value="<?= e($t) ?>" <?= $ptype === $t ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pt_<?= e($t) ?>"><?= e(__('project_type.' . $t)) ?></label></div></div>
                        <?php endforeach; ?>
                    </div>
                    <input class="form-control form-control-sm mt-1" name="project_type_other" maxlength="200" placeholder="<?= e(__('topic_field.project_type_other')) ?>" value="<?= e(old('project_type_other', $sub['project_type_other'] ?? '')) ?>"></div>
                <div class="col-md-6"><span class="form-label small fw-semibold d-block"><?= e(__('topic_field.advisor')) ?></span>
                    <div class="small"><?= $advisor && $advisor['status'] === 'assigned' ? e(full_name($advisor)) : '-' ?></div></div>
                <div class="col-md-6"><label class="form-label small fw-semibold" for="f_co"><?= e(__('topic_field.co_advisor_name')) ?></label>
                    <input class="form-control" id="f_co" name="co_advisor_name" maxlength="200" value="<?= e(old('co_advisor_name', $sub['co_advisor_name'] ?? '')) ?>"></div>
                <?php if ($topic && $topic['status'] === 'revision_required'): ?>
                    <div class="col-12"><label class="form-label small fw-semibold"><?= e(__('topics.revision_note')) ?></label>
                        <textarea class="form-control" rows="2" name="revision_note"><?= e(old('revision_note', $sub['revision_note'] ?? '')) ?></textarea></div>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-outline-primary" name="do" value="save"><i class="bi bi-save"></i> <?= e(__('topics.save_draft')) ?></button>
                <button class="btn btn-ppms" name="do" value="submit" data-confirm-button="<?= e(__('topics.submit_confirm')) ?>"><i class="bi bi-send"></i> <?= e(__('topics.submit')) ?></button>
            </div>
        </form>
        <?php if ($sub): ?>
            <hr>
            <form method="post" enctype="multipart/form-data" action="<?= e(url("$base/topic/documents")) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-8"><label class="form-label small"><?= e(__('topics.supporting_documents')) ?> (≤ <?= (int) $topicMaxMb ?> MB)</label>
                    <input type="file" class="form-control" name="document" required></div>
                <div class="col-md-4"><button class="btn btn-outline-secondary w-100"><i class="bi bi-upload"></i> <?= e(__('common.upload')) ?></button></div>
            </form>
        <?php endif; ?>
    <?php elseif ($sub): ?>
        <?= $this->partial('projects/_topic_form1', ['sub' => $sub, 'members' => $members, 'advisor' => $advisor]) ?>
        <?php if ($sub['revision_note']): ?><div class="small"><strong><?= e(__('topics.revision_note')) ?>:</strong> <?= nl2br(e($sub['revision_note'])) ?></div><?php endif; ?>
        <?php if ((int) $sub['is_draft'] === 0): ?><a class="btn btn-sm btn-outline-secondary mt-2" href="<?= e(url("$base/topic/print")) ?>"><i class="bi bi-printer"></i> <?= e(__('topics.print_form1')) ?></a><?php endif; ?>
    <?php else: ?>
        <p class="text-secondary mb-0"><?= e(__('topics.none')) ?></p>
    <?php endif; ?>

    <?php if (!empty($topicView['documents'])): ?>
        <div class="mt-2 small"><strong><?= e(__('topics.supporting_documents')) ?>:</strong>
            <?php foreach ($topicView['documents'] as $d): ?><span class="me-3"><?= $file((int) $d['id'], $d['original_name']) ?></span><?php endforeach; ?></div>
    <?php endif; ?>

    <?php if ($isAdvisor && $topic && in_array($topic['status'], ['submitted', 'advisor_review'], true)): ?>
        <div class="border rounded p-3 mt-3 bg-light">
            <h3 class="h6"><?= e(__('topics.advisor_review')) ?></h3>
            <?= $decisionForm("$base/topic/advisor", ['approve' => __('decision.approved'), 'revise' => __('decision.revision_required'), 'reject' => __('decision.advisor_rejected')]) ?>
        </div>
    <?php endif; ?>
    <?php if ($isAdmin && $topic && $topic['status'] === 'admin_review'): ?>
        <div class="border border-primary rounded p-3 mt-3">
            <h3 class="h6"><?= e(__('topics.admin_review')) ?></h3>
            <?= $decisionForm("$base/topic/admin", ['approve' => __('decision.final_topic_approved'), 'revise' => __('decision.revision_required'), 'reject' => __('decision.final_topic_rejected')]) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($topicView['decisions'])): ?>
        <h3 class="h6 mt-3"><?= e(__('topics.approval_history')) ?></h3>
        <div class="table-responsive"><table class="table table-sm small mb-0">
            <thead><tr><th>วันที่</th><th>ระดับ</th><th>Version</th><th>ผลการพิจารณา</th><th>ผู้พิจารณา</th><th>ความเห็น</th></tr></thead>
            <tbody><?php foreach ($topicView['decisions'] as $d): ?>
                <tr><td><?= e(thai_date($d['decided_at'], true)) ?></td><td><?= e(__('level.' . $d['level'])) ?></td><td>v<?= (int) $d['version_no'] ?></td>
                    <td><?= e(__('decision.' . ($d['level'] === 'admin' && $d['decision'] !== 'revision_required' ? 'final_topic_' . $d['decision'] : ($d['level'] === 'advisor' && $d['decision'] === 'rejected' ? 'advisor_rejected' : $d['decision'])))) ?></td>
                    <td><?= e(full_name($d)) ?></td><td><?= nl2br(e($d['comments'])) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <?php if (!empty($topicView['versions']) && count($topicView['versions']) > 1): ?>
        <details class="mt-2 small"><summary><?= e(__('topics.versions')) ?> (<?= count($topicView['versions']) ?>)</summary>
            <ul class="mb-0"><?php foreach ($topicView['versions'] as $v): ?>
                <li>v<?= (int) $v['version_no'] ?> · <?= e($v['title_th']) ?> · <?= (int) $v['is_draft'] === 1 ? 'Draft' : e(thai_date($v['submitted_at'], true)) ?><?= (int) $v['is_late'] === 1 ? ' <span class="badge text-bg-warning">late</span>' : '' ?></li>
            <?php endforeach; ?></ul></details>
    <?php endif; ?>
</div></div>

<!-- ========================== PROPOSAL ========================== -->
<?php if ($proposalView): $pv = $proposalView; $status = $p['status']; ?>
<div class="card mb-3" id="proposal"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0"><i class="bi bi-file-earmark-pdf"></i> Project Proposal</h2>
        <div><?= status_badge($status) ?> <?= $p['version_no'] ? '<span class="small text-secondary">v' . (int) $p['version_no'] . '</span>' : '' ?>
            <?php if ((int) $p['needs_admin_attention'] === 1 && $isAdmin): ?><span class="badge text-bg-danger"><?= e(__('final.disagreement')) ?></span><?php endif; ?></div>
    </div>

    <?php if ($pv['revision'] && in_array($status, ['revision_required', 'approved_minor'], true)): ?>
        <div class="alert alert-warning"><strong><?= e(__('proposals.revision_requested')) ?> (<?= e(__('level.' . $pv['revision']['requested_by_level'])) ?>):</strong>
            <?= nl2br(e($pv['revision']['comments'])) ?>
            <?php if ($pv['revision']['due_at']): ?><div class="small"><?= e(__('proposals.revision_due')) ?>: <?= e(thai_date($pv['revision']['due_at'], true)) ?></div><?php endif; ?></div>
    <?php endif; ?>

    <?php if ($isLeader && in_array($status, ['draft', 'revision_required', 'approved_minor'], true)): $resub = $status !== 'draft'; $prev = $pv['latest']; ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(url("$base/proposal")) ?>" class="border rounded p-3 mb-3" data-confirm="<?= e(__('proposals.submit_confirm')) ?>">
            <?= csrf_field() ?>
            <h3 class="h6"><?= e($resub ? __('proposals.resubmit') : __('proposals.submit')) ?></h3>
            <p class="small text-secondary"><?= e(__('proposals.chapter1_help')) ?></p>
            <h4 class="h6 mt-2"><?= e(__('proposals.chapter1')) ?></h4>
            <div class="row g-2">
                <?php foreach (ProposalService::CHAPTER1 as $i => $k): ?>
                    <div class="col-12"><label class="form-label small fw-semibold" for="<?= e($k) ?>">1.<?= $i + 1 ?> <?= e(__('chapter1.' . $k)) ?> <span class="text-danger">*</span></label>
                        <textarea class="form-control" rows="<?= $k === 'ch1_background' ? 6 : 4 ?>" id="<?= e($k) ?>" name="<?= e($k) ?>"><?= e(old($k, $prev[$k] ?? '')) ?></textarea></div>
                <?php endforeach; ?>
            </div>
            <h4 class="h6 mt-3"><?= e(__('proposals.chapter_files')) ?> <span class="small text-secondary fw-normal">(PDF / DOCX ≤ <?= (int) $chapterMaxMb ?> MB)</span></h4>
            <div class="row g-2">
                <?php foreach (ProposalService::CHAPTER_FILES as $ch => $col): $hasPrev = !empty($prev[$col]); ?>
                    <div class="col-md-4"><label class="form-label small fw-semibold"><?= e(__('proposals.chapter_n', ['n' => $ch])) ?><?= $hasPrev ? '' : ' <span class="text-danger">*</span>' ?></label>
                        <input type="file" class="form-control" name="chapter<?= $ch ?>" accept=".pdf,.docx,.doc" <?= $hasPrev ? '' : 'required' ?>>
                        <?php if ($hasPrev): ?><div class="form-text"><?= e(__('proposals.keep_previous')) ?></div><?php endif; ?></div>
                <?php endforeach; ?>
                <?php if ($resub): ?>
                    <div class="col-md-6"><label class="form-label small fw-semibold"><?= e(__('proposals.response_doc')) ?> (PDF/DOCX) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="response_doc" accept=".pdf,.docx" required></div>
                <?php endif; ?>
                <div class="col-12"><label class="form-label small fw-semibold"><?= e(__('proposals.revision_notes')) ?></label>
                    <textarea class="form-control" rows="2" name="revision_notes"><?= e(old('revision_notes')) ?></textarea></div>
            </div>
            <button class="btn btn-ppms mt-3"><i class="bi bi-upload"></i> <?= e(__('proposals.upload_submit')) ?></button>
        </form>
    <?php endif; ?>

    <?php if ($pv['versions']): ?>
        <div class="table-responsive"><table class="table table-sm small align-middle">
            <thead><tr><th>Version</th><th><?= e(__('proposals.chapter_files')) ?></th><th>ส่งเมื่อ</th><th>สถานะ</th><th>หมายเหตุ / คำชี้แจง</th></tr></thead>
            <tbody><?php foreach ($pv['versions'] as $v): ?>
                <tr><td>v<?= (int) $v['version_no'] ?></td>
                    <td><?= e(__('proposals.chapter_n', ['n' => 1])) ?>: <?= $file((int) $v['file_id'], $v['original_name']) ?>
                        <?php if ($v['ch2_file_id']): ?><br><?= e(__('proposals.chapter_n', ['n' => 2])) ?>: <?= $file((int) $v['ch2_file_id'], $v['ch2_name']) ?><?php endif; ?>
                        <?php if ($v['ch3_file_id']): ?><br><?= e(__('proposals.chapter_n', ['n' => 3])) ?>: <?= $file((int) $v['ch3_file_id'], $v['ch3_name']) ?><?php endif; ?>
                        <?php if ($v['response_file_id']): ?><br><?= $file((int) $v['response_file_id'], __('proposals.response_doc')) ?><?php endif; ?></td>
                    <td><?= e(thai_date($v['submitted_at'], true)) ?> <?= (int) $v['is_late'] === 1 ? '<span class="badge text-bg-warning">late</span>' : '' ?></td>
                    <td><?= e(__('version_status.' . $v['version_status'])) ?></td>
                    <td><?= nl2br(e($v['revision_notes'])) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php $cur = $pv['versions'][0]; if (!empty($cur['ch1_background'])): ?>
            <details class="mb-3"><summary class="small fw-semibold"><?= e(__('proposals.chapter1')) ?> (v<?= (int) $cur['version_no'] ?>)</summary>
                <?= $this->partial('projects/_chapter1', ['v' => $cur]) ?></details>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($isAdvisor && in_array($status, ['submitted', 'advisor_review'], true)): ?>
        <div class="border rounded p-3 mb-3 bg-light">
            <h3 class="h6"><?= e(__('menu.advisor_review')) ?></h3>
            <?= $decisionForm("$base/proposal/advisor", ['ready' => __('decision.ready_for_committee'), 'revise' => __('decision.revision_required'), 'not_approved' => __('decision.not_approved')]) ?>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin && $status === 'committee_assignment'): ?>
        <form method="post" action="<?= e(url("$base/committee")) ?>" class="border border-primary rounded p-3 mb-3" data-confirm="<?= e(__('proposals.assign_confirm')) ?>">
            <?= csrf_field() ?>
            <h3 class="h6"><?= e(__('menu.committee_assignment')) ?> <span class="small text-secondary">(Proposal v<?= (int) $p['version_no'] ?>)</span></h3>
            <?= $this->partial('projects/_committee_fields', ['choices' => $committeeChoices, 'advisorId' => $advisor['advisor_id'] ?? null, 'defaults' => []]) ?>
            <button class="btn btn-ppms mt-2"><i class="bi bi-person-check"></i> <?= e(__('proposals.assign')) ?></button>
        </form>
    <?php endif; ?>

    <?php if ($isAdmin && $status === 'advisor_approved' && (int) $p['current_round_no'] > 0): $last = $pv['assignments'][0] ?? null; ?>
        <div class="border border-primary rounded p-3 mb-3">
            <h3 class="h6"><?= e(__('proposals.select_mode')) ?></h3>
            <p class="small text-secondary"><?= e(__('proposals.select_mode_help')) ?></p>
            <div class="row g-3">
                <div class="col-md-5"><form method="post" action="<?= e(url("$base/mode")) ?>" data-confirm="<?= e(__('proposals.mode_a_confirm')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="mode" value="A">
                    <button class="btn btn-outline-primary w-100"><strong>Mode A</strong><br><small><?= e(__('proposals.mode_a')) ?></small></button>
                </form></div>
                <div class="col-md-7"><form method="post" action="<?= e(url("$base/mode")) ?>" data-confirm="<?= e(__('proposals.mode_b_confirm')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="mode" value="B">
                    <?= $this->partial('projects/_committee_fields', ['choices' => $committeeChoices, 'advisorId' => $advisor['advisor_id'] ?? null, 'defaults' => $last ? [(int) $last['member1_id'], (int) $last['member2_id']] : []]) ?>
                    <button class="btn btn-outline-primary w-100 mt-2"><strong>Mode B</strong> · <small><?= e(__('proposals.mode_b')) ?></small></button>
                </form></div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($pv['assignments'] as $a): $aid = (int) $a['id']; ?>
        <div class="border rounded p-3 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <h3 class="h6 mb-1"><?= e(__('evaluations.round', ['n' => (int) $a['round_no']])) ?> · Proposal v<?= (int) $a['version_no'] ?></h3>
                <span class="small"><?= e(__('evaluations.deadline')) ?>: <?= e(thai_date($a['evaluation_deadline'], true)) ?> · <?= e(__('assignment.' . $a['status'])) ?></span>
            </div>
            <?php if (isset($pv['progress'][$aid])): ?>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($pv['progress'][$aid] as $pr): ?>
                        <li><?= e(__('evaluator_role.' . $pr['evaluator_role'])) ?>: <?= e(full_name($pr)) ?> · <?= status_badge($pr['status']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="small text-secondary mb-0 mt-1"><i class="bi bi-shield-lock"></i> <?= e(__('evaluations.hidden_until_both')) ?></p>
                <?php if ($isAdmin && $a['status'] === 'active'): ?>
                    <details class="mt-2 small"><summary><?= e(__('proposals.replace_member')) ?></summary>
                        <form method="post" action="<?= e(url("$base/committee/replace")) ?>" class="row g-2 mt-1">
                            <?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= $aid ?>">
                            <div class="col-md-2"><select name="slot" class="form-select form-select-sm"><option value="1">คนที่ 1</option><option value="2">คนที่ 2</option></select></div>
                            <div class="col-md-4"><select name="member_id" class="form-select form-select-sm"><?php foreach ($committeeChoices as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e(full_name($c)) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4"><input class="form-control form-control-sm" name="reason" required placeholder="<?= e(__('groups.reason')) ?>"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-outline-danger w-100"><?= e(__('common.save')) ?></button></div>
                        </form></details>
                <?php endif; ?>
            <?php elseif (isset($pv['summaries'][$aid]) && $pv['summaries'][$aid]): ?>
                <?= $this->partial('projects/_evaluation_summary', ['summary' => $pv['summaries'][$aid], 'showNames' => $role !== 'student' || \App\Services\SettingsService::bool('evaluation.show_committee_names'), 'canReopen' => $isAdmin, 'base' => $base]) ?>
            <?php else: ?>
                <p class="small text-secondary mb-0"><i class="bi bi-hourglass-split"></i> <?= e(__('evaluations.not_released')) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($isAdmin && in_array($status, ['evaluation_completed', 'final_review'], true)): $cv = $pv['committeeView']; ?>
        <form method="post" action="<?= e(url("$base/decision")) ?>" class="border border-success rounded p-3 mb-3" data-confirm="<?= e(__('final.confirm')) ?>">
            <?= csrf_field() ?>
            <h3 class="h6"><i class="bi bi-award"></i> <?= e(__('menu.final_approval')) ?></h3>
            <?php if ($cv['assignment'] && (int) $cv['assignment']['proposal_version_id'] === (int) $p['current_version_id']): ?>
                <p class="small mb-2"><?= e(__('final.average')) ?>: <strong><?= e(number_format((float) $cv['average'], 2)) ?></strong> ·
                    <?= e(__('final.recommendations')) ?>: <?php foreach ($cv['recommendations'] as $erole => $rec): ?><span class="badge text-bg-light border"><?= e(__('evaluator_role.' . $erole)) ?>: <?= e(__('presentation_result.' . $rec)) ?></span> <?php endforeach; ?>
                    <?php if ($cv['differs']): ?><span class="badge text-bg-danger"><?= e(__('final.disagreement')) ?></span><?php endif; ?></p>
                <p class="small text-secondary"><?= e(__('final.no_auto', ['mode' => __('final.justify_' . $justifyWhen)])) ?></p>
            <?php else: ?>
                <p class="small text-secondary"><?= e(__('final.no_committee_for_version')) ?></p>
            <?php endif; ?>
            <div class="row g-2">
                <div class="col-md-4"><select name="decision" class="form-select" required>
                    <?php foreach (\App\Services\DecisionService::DECISIONS as $d): ?><option value="<?= e($d) ?>"><?= e(__('decision.' . $d)) ?></option><?php endforeach; ?>
                </select></div>
                <div class="col-md-4"><input type="datetime-local" class="form-control" name="revision_due" title="<?= e(__('proposals.revision_due')) ?>"></div>
                <div class="col-12"><textarea class="form-control" name="comments" rows="2" placeholder="<?= e(__('final.comments')) ?>"></textarea></div>
                <div class="col-12"><textarea class="form-control" name="reasons" rows="2" placeholder="<?= e(__('final.reasons')) ?>"></textarea></div>
                <div class="col-12"><textarea class="form-control" name="justification" rows="2" placeholder="<?= e(__('final.justification')) ?>"></textarea></div>
            </div>
            <button class="btn btn-ppms mt-2"><i class="bi bi-check2-circle"></i> <?= e(__('final.record')) ?></button>
        </form>
    <?php endif; ?>

    <?php if ($pv['decisions']): ?>
        <h3 class="h6"><?= e(__('proposals.decision_history')) ?></h3>
        <div class="table-responsive"><table class="table table-sm small mb-0">
            <thead><tr><th>วันที่</th><th>ระดับ</th><th>Version</th><th>ผล</th><th>ผู้พิจารณา</th><th>ความเห็น / เหตุผล</th></tr></thead>
            <tbody><?php foreach ($pv['decisions'] as $d): if ($role === 'student' && $d['decision_level'] === 'final_decision' && $p['released_at'] === null) { continue; } ?>
                <tr><td><?= e(thai_date($d['decided_at'], true)) ?></td><td><?= e(__('level.' . $d['decision_level'])) ?></td><td>v<?= (int) $d['version_no'] ?></td>
                    <td><?= e(__('decision.' . $d['decision'])) ?><?php if ((int) $d['differs_from_committee'] === 1): ?> <span class="badge text-bg-warning"><?= e(__('final.override')) ?></span><?php endif; ?>
                        <?php if ($d['average_score_snapshot'] !== null): ?><div class="text-secondary">S<sub>avg</sub> <?= e($d['average_score_snapshot']) ?></div><?php endif; ?></td>
                    <td><?= e(full_name($d)) ?></td>
                    <td><?= nl2br(e($d['comments'])) ?><?php if ($d['supporting_reasons']): ?><div class="text-secondary"><?= nl2br(e($d['supporting_reasons'])) ?></div><?php endif; ?>
                        <?php if ($d['justification']): ?><div><em><?= e(__('final.justification_label')) ?>:</em> <?= nl2br(e($d['justification'])) ?></div><?php endif; ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</div></div>
<?php elseif ($topic && $topic['status'] !== 'topic_approved'): ?>
    <div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('proposals.after_topic')) ?></div>
<?php endif; ?>
