<?php
/** แบบฟอร์ม 8: บันทึกการเข้าพบอาจารย์ที่ปรึกษา. Vars from FormService::context + reports, rubric, ... */
$this->layout('layouts/app', ['title' => __('progress.title') . ' ' . $project['project_code']]);
$base = '/projects/' . (int) $project['id'];
$reviewed = count(array_filter($reports, fn ($r) => $r['status'] === 'reviewed'));
$scores = array_map(fn ($r) => (float) $r['score'], array_filter($reports, fn ($r) => $r['status'] === 'reviewed'));
?>
<?= $this->partial('projects/_head', ['project' => $project]) ?>
<?= $this->partial('projects/_tabs', ['project' => $project, 'active' => 'progress']) ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h2 class="h5 mb-1"><?= e(__('progress.title')) ?></h2>
        <div class="small text-secondary"><?= e(__('progress.help', ['min' => $minReports, 'max' => $maxReports])) ?></div>
    </div>
    <div class="text-end">
        <div class="small"><?= e(__('progress.reviewed_count')) ?>: <strong class="<?= $reviewed >= $minReports ? 'text-success' : 'text-danger' ?>"><?= $reviewed ?> / <?= $minReports ?></strong>
            <?php if ($scores): ?> · <?= e(__('progress.average')) ?> <strong><?= e(number_format(array_sum($scores) / count($scores), 2)) ?></strong> / <?= e((string) (int) $maxScore) ?><?php endif; ?></div>
        <a class="btn btn-sm btn-outline-secondary mt-1" href="<?= e(url("$base/progress/print")) ?>"><i class="bi bi-printer"></i> <?= e(__('progress.print')) ?></a>
    </div>
</div>

<?php if (!$open): ?>
    <div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('progress.not_open')) ?></div>
<?php endif; ?>

<?php foreach ($reports as $rep): $rid = (int) $rep['id']; $editable = $isStudent && $rep['status'] === 'submitted'; ?>
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 mb-0"><?= e(__('progress.meeting_no', ['n' => (int) $rep['report_no']])) ?> · <?= e(thai_date($rep['meeting_date'])) ?></h3>
            <span><?= $rep['status'] === 'reviewed' ? '<span class="badge text-bg-success">' . e(__('progress.status_reviewed')) . ' ' . e(number_format((float) $rep['score'], 0)) . ' ' . e(__('progress.points')) . '</span>' : '<span class="badge text-bg-warning">' . e(__('progress.status_submitted')) . '</span>' ?></span>
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="small fw-semibold"><?= e(__('progress.part1')) ?></div>
                <?php if ($editable): ?>
                    <form method="post" action="<?= e(url("$base/progress/$rid")) ?>">
                        <?= csrf_field() ?>
                        <input type="date" class="form-control form-control-sm mb-2" name="meeting_date" value="<?= e($rep['meeting_date']) ?>" required>
                        <textarea class="form-control" rows="5" name="work_summary" required><?= e($rep['work_summary']) ?></textarea>
                        <button class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-save"></i> <?= e(__('common.save')) ?></button>
                    </form>
                <?php else: ?>
                    <div class="small border rounded p-2 bg-light"><?= nl2br(e($rep['work_summary'])) ?></div>
                <?php endif; ?>
                <div class="small text-secondary mt-1"><?= e(__('progress.by')) ?> <?= e(trim($rep['s_title'] . $rep['s_first'] . ' ' . $rep['s_last'])) ?> · <?= e(thai_date($rep['submitted_at'], true)) ?></div>
            </div>
            <div class="col-lg-6">
                <div class="small fw-semibold"><?= e(__('progress.part2')) ?></div>
                <?php if ($isAdvisor): ?>
                    <form method="post" action="<?= e(url("$base/progress/$rid/review")) ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small mb-1"><?= e(__('progress.suggestions')) ?></label>
                        <textarea class="form-control mb-2" rows="3" name="suggestions"><?= e($rep['suggestions'] ?? '') ?></textarea>
                        <div class="small fw-semibold mb-1"><?= e(__('progress.score')) ?> <span class="text-danger">*</span></div>
                        <?php foreach ($rubric as $lvl): $sc = (string) $lvl['score']; $id = "s{$rid}_" . str_replace('.', '_', $sc); ?>
                            <div class="form-check small"><input class="form-check-input" type="radio" name="score" id="<?= e($id) ?>" value="<?= e($sc) ?>" <?= $rep['score'] !== null && (float) $rep['score'] === $lvl['score'] ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="<?= e($id) ?>"><strong><?= e(number_format($lvl['score'], 0)) ?> <?= e(__('progress.points')) ?></strong> <?= e($lvl['label']) ?></label></div>
                        <?php endforeach; ?>
                        <div class="form-text"><?= e(__('progress.score_note')) ?></div>
                        <button class="btn btn-sm btn-ppms mt-2"><i class="bi bi-check2-square"></i> <?= e($rep['status'] === 'reviewed' ? __('progress.update_review') : __('progress.save_review')) ?></button>
                    </form>
                <?php elseif ($rep['status'] === 'reviewed'): ?>
                    <div class="small border rounded p-2"><?= $rep['suggestions'] ? nl2br(e($rep['suggestions'])) : '<span class="text-secondary">-</span>' ?></div>
                    <div class="small mt-1"><?= e(__('progress.score')) ?>: <strong><?= e(number_format((float) $rep['score'], 0)) ?></strong> / <?= e((string) (int) $maxScore) ?> · <?= e(full_name($rep)) ?> · <?= e(thai_date($rep['reviewed_at'], true)) ?></div>
                <?php else: ?>
                    <div class="small text-secondary"><i class="bi bi-hourglass-split"></i> <?= e(__('progress.waiting_advisor')) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div></div>
<?php endforeach; ?>

<?php if ($isStudent && $open && count($reports) < $maxReports): ?>
    <div class="card mb-3 border-primary"><div class="card-body">
        <h3 class="h6"><?= e(__('progress.new', ['n' => count($reports) + 1])) ?></h3>
        <form method="post" action="<?= e(url("$base/progress")) ?>">
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small fw-semibold"><?= e(__('progress.meeting_date')) ?> <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="meeting_date" value="<?= e(old('meeting_date', date('Y-m-d'))) ?>" max="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-12"><label class="form-label small fw-semibold"><?= e(__('progress.work_summary')) ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control" rows="5" name="work_summary" required placeholder="<?= e(__('progress.work_summary_hint')) ?>"><?= e(old('work_summary')) ?></textarea></div>
            </div>
            <button class="btn btn-ppms mt-2"><i class="bi bi-send"></i> <?= e(__('progress.submit')) ?></button>
        </form>
    </div></div>
<?php elseif (!$reports): ?>
    <p class="text-secondary"><?= e(__('progress.none')) ?></p>
<?php endif; ?>
