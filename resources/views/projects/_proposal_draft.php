<?php
/**
 * Step 3: preparing proposal chapters 1-3 (draft, chapter-by-chapter advisor review, submission).
 * @var array $pv @var string $status @var bool $isMember @var bool $isLeader @var bool $isAdvisor
 * @var string $base @var int $chapterMaxMb @var callable $fileLink
 */
use App\Services\ProposalDraftService;
use App\Services\ProposalService;

if (!ProposalDraftService::editable($pv['proposal'])) {
    return;
}
$draft = $pv['draft'];
$reviews = $pv['chapterReviews'];
$content = $draft['content'];
$resub = $status !== 'draft';
$required = $pv['reviewRequired'];
$badge = function (?array $r) use ($required): string {
    if ($r === null) {
        return '<span class="badge text-bg-light border">' . e(__($required ? 'chapter_status.not_sent' : 'chapter_status.optional')) . '</span>';
    }
    $cls = ['submitted' => 'text-bg-warning', 'approved' => 'text-bg-success', 'revision_required' => 'text-bg-danger'][$r['status']];
    return '<span class="badge ' . $cls . '">' . e(__('chapter_status.' . $r['status'])) . '</span>';
};
$names = [1 => __('proposals.chapter1'), 2 => 'บทที่ 2 ' . $content['ch2']['title'], 3 => 'บทที่ 3 ' . $content['ch3']['title']];
$sendButton = function (int $ch) use ($isLeader, $reviews): string {
    if (!$isLeader) {
        return '';
    }
    $pending = ($reviews[$ch]['status'] ?? null) === 'submitted';
    return '<button class="btn btn-sm btn-outline-primary" name="do" value="send_' . $ch . '" ' . ($pending ? 'disabled' : '')
        . ' data-confirm-button="' . e(__('chapters.send_confirm', ['n' => $ch])) . '"><i class="bi bi-send"></i> ' . e(__('chapters.send', ['n' => $ch])) . '</button>';
};
?>
<div class="border rounded p-3 mb-3" id="chapters">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div>
            <h3 class="h6 mb-1"><?= e(__('chapters.title', ['v' => (int) $pv['targetVersion']])) ?></h3>
            <div class="small text-secondary"><?= e(__($required ? 'chapters.help_review' : 'chapters.help_no_review')) ?></div>
        </div>
        <div class="text-end small">
            <?php if ($draft['updated_at']): ?><div class="text-secondary"><?= e(__('chapters.saved_at')) ?> <?= e(thai_date($draft['updated_at'], true)) ?></div><?php endif; ?>
            <a href="<?= e(url("$base/proposal/print")) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> <?= e(__('chapters.print_draft')) ?></a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <?php foreach (ProposalDraftService::CHAPTERS as $ch): $r = $reviews[$ch]; ?>
            <div class="col-md-4"><div class="border rounded p-2 h-100 small">
                <div class="d-flex justify-content-between"><strong><?= e(__('proposals.chapter_n', ['n' => $ch])) ?></strong> <?= $badge($r) ?></div>
                <?php if ($r): ?><div class="text-secondary"><?= e(__('chapters.sent_at')) ?> <?= e(thai_date($r['submitted_at'], true)) ?></div><?php endif; ?>
                <?php if ($r && $r['comments']): ?><div class="<?= $r['status'] === 'revision_required' ? 'text-danger' : '' ?>"><?= nl2br(e($r['comments'])) ?></div><?php endif; ?>
            </div></div>
        <?php endforeach; ?>
    </div>

    <?php if ($isAdvisor): ?>
        <?php foreach ($reviews as $ch => $r): if (!$r || $r['status'] !== 'submitted') { continue; } ?>
            <div class="border border-primary rounded p-3 mb-3">
                <h4 class="h6"><?= e(__('chapters.review_title', ['n' => $ch])) ?> <span class="small text-secondary">(<?= e(thai_date($r['submitted_at'], true)) ?>)</span></h4>
                <div class="small mb-2"><?= $fileLink((int) $r['file_id'], $r['original_name']) ?></div>
                <?= $this->partial('projects/_chapters', ['content' => $ch === 1 ? ['ch1' => $r['content']] : ["ch$ch" => $r['content']], 'only' => $ch]) ?>
                <form method="post" action="<?= e(url("$base/chapters/" . (int) $r['id'] . '/review')) ?>" class="row g-2 mt-2" data-confirm="<?= e(__('common.decision_confirm')) ?>">
                    <?= csrf_field() ?>
                    <div class="col-md-3"><select name="decision" class="form-select form-select-sm"><option value="approve"><?= e(__('chapters.approve')) ?></option><option value="revise"><?= e(__('chapters.revise')) ?></option></select></div>
                    <div class="col-md-7"><textarea name="comments" class="form-control form-control-sm" rows="2" placeholder="<?= e(__('common.comments_placeholder')) ?>"></textarea></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-ppms w-100"><?= e(__('common.save')) ?></button></div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($isMember): ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url("$base/proposal")) ?>">
        <?= csrf_field() ?>
        <div class="accordion mb-3" id="chapterAccordion">
            <?php foreach (ProposalDraftService::CHAPTERS as $ch): $fid = $draft['files'][$ch]; ?>
                <div class="accordion-item">
                    <h4 class="accordion-header"><button class="accordion-button <?= $ch === 1 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ch<?= $ch ?>Body">
                        <?= e($names[$ch]) ?>&nbsp;<?= $badge($reviews[$ch]) ?></button></h4>
                    <div id="ch<?= $ch ?>Body" class="accordion-collapse collapse <?= $ch === 1 ? 'show' : '' ?>"><div class="accordion-body">
                        <?php if ($ch === 1): ?>
                            <?php foreach (ProposalService::CHAPTER1 as $i => $k): ?>
                                <label class="form-label small fw-semibold mt-1" for="<?= e($k) ?>">1.<?= $i + 1 ?> <?= e(__('chapter1.' . $k)) ?> <span class="text-danger">*</span></label>
                                <textarea class="form-control mb-2" rows="<?= $k === 'ch1_background' ? 6 : 4 ?>" id="<?= e($k) ?>" name="<?= e($k) ?>"><?= e(old($k, $content['ch1'][$k])) ?></textarea>
                            <?php endforeach; ?>
                        <?php else: $c = $content["ch$ch"]; ?>
                            <label class="form-label small fw-semibold"><?= e(__('chapters.chapter_title')) ?></label>
                            <input class="form-control mb-2" name="ch<?= $ch ?>_title" maxlength="300" value="<?= e($c['title']) ?>">
                            <div class="small text-secondary mb-2"><?= e(__('chapters.sections_help', ['n' => $ch])) ?></div>
                            <div id="ch<?= $ch ?>Sections">
                                <?php $rows = $c['sections'] ?: [['heading' => '', 'content' => '']]; foreach ($rows as $i => $sec): ?>
                                    <div class="border rounded p-2 mb-2 js-section">
                                        <div class="input-group input-group-sm mb-1"><span class="input-group-text"><?= $ch ?>.<span class="js-section-no"><?= $i + 1 ?></span></span>
                                            <input class="form-control" name="ch<?= $ch ?>_heading[]" maxlength="300" placeholder="<?= e(__('chapters.section_heading')) ?>" value="<?= e($sec['heading']) ?>"></div>
                                        <textarea class="form-control form-control-sm" rows="4" name="ch<?= $ch ?>_content[]" placeholder="<?= e(__('chapters.section_content')) ?>"><?= e($sec['content']) ?></textarea>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <template id="ch<?= $ch ?>Template">
                                <div class="border rounded p-2 mb-2 js-section">
                                    <div class="input-group input-group-sm mb-1"><span class="input-group-text"><?= $ch ?>.<span class="js-section-no"></span></span>
                                        <input class="form-control" name="ch<?= $ch ?>_heading[]" maxlength="300" placeholder="<?= e(__('chapters.section_heading')) ?>"></div>
                                    <textarea class="form-control form-control-sm" rows="4" name="ch<?= $ch ?>_content[]" placeholder="<?= e(__('chapters.section_content')) ?>"></textarea>
                                </div>
                            </template>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-add-section" data-target="ch<?= $ch ?>Sections" data-template="ch<?= $ch ?>Template" data-max="<?= ProposalDraftService::MAX_SECTIONS ?>"><i class="bi bi-plus"></i> <?= e(__('chapters.add_section')) ?></button>
                            <div class="form-text"><?= e(__('chapters.remove_help')) ?></div>
                        <?php endif; ?>
                        <div class="row g-2 align-items-end mt-2">
                            <div class="col-md-7"><label class="form-label small fw-semibold"><?= e(__('chapters.file', ['n' => $ch])) ?> (PDF / DOCX ≤ <?= (int) $chapterMaxMb ?> MB) <span class="text-danger">*</span></label>
                                <input type="file" class="form-control form-control-sm" name="chapter<?= $ch ?>" accept=".pdf,.docx,.doc">
                                <?php if ($fid): ?><div class="form-text"><?= e(__('chapters.current_file')) ?>: <?= $fileLink((int) $fid, __('proposals.chapter_n', ['n' => $ch])) ?></div><?php endif; ?></div>
                            <div class="col-md-5 text-md-end"><?= $sendButton($ch) ?></div>
                        </div>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <button class="btn btn-outline-primary" name="do" value="save"><i class="bi bi-save"></i> <?= e(__('chapters.save_draft')) ?></button>

        <?php if ($isLeader): ?>
            <div class="border-top mt-3 pt-3">
                <h4 class="h6"><?= e($resub ? __('proposals.resubmit') : __('proposals.submit')) ?></h4>
                <?php if ($resub): ?>
                    <label class="form-label small fw-semibold"><?= e(__('proposals.response_doc')) ?> (PDF/DOCX) <span class="text-danger">*</span></label>
                    <input type="file" class="form-control mb-2" name="response_doc" accept=".pdf,.docx">
                <?php endif; ?>
                <label class="form-label small fw-semibold"><?= e(__('proposals.revision_notes')) ?></label>
                <textarea class="form-control mb-2" rows="2" name="revision_notes"><?= e(old('revision_notes')) ?></textarea>
                <?php if ($pv['submitBlocker']): ?><div class="small text-secondary mb-2"><i class="bi bi-info-circle"></i> <?= e(__('rule.' . $pv['submitBlocker'])) ?></div><?php endif; ?>
                <button class="btn btn-ppms" name="do" value="submit" <?= $pv['submitBlocker'] ? 'disabled' : '' ?> data-confirm-button="<?= e(__('proposals.submit_confirm')) ?>"><i class="bi bi-upload"></i> <?= e(__('proposals.upload_submit')) ?></button>
                <?php if ($required): ?><div class="form-text"><?= e(__('chapters.submit_uses_approved')) ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <?php if ($pv['chapterHistory']): ?>
        <details class="small mt-3"><summary><?= e(__('chapters.history')) ?> (<?= count($pv['chapterHistory']) ?>)</summary>
            <table class="table table-sm small mb-0 mt-1"><thead><tr><th>Version</th><th><?= e(__('chapters.chapter')) ?></th><th><?= e(__('chapters.sent_at')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('chapters.reviewer')) ?></th><th><?= e(__('evaluations.comments')) ?></th></tr></thead>
                <tbody><?php foreach ($pv['chapterHistory'] as $h): ?>
                    <tr><td>v<?= (int) $h['target_version'] ?></td><td><?= (int) $h['chapter'] ?></td><td><?= e(thai_date($h['submitted_at'], true)) ?></td>
                        <td><?= e(__('chapter_status.' . $h['status'])) ?></td><td><?= $h['first_name_th'] ? e(full_name($h)) : '-' ?></td><td><?= nl2br(e($h['comments'])) ?></td></tr>
                <?php endforeach; ?></tbody></table></details>
    <?php endif; ?>
</div>
