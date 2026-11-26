<?php
/** @var array $group @var array $members @var ?array $advisor @var array $history @var bool $isLeader @var array $advisors @var ?array $topic @var int $minMembers @var int $maxMembers @var ?array $myRequest */
use App\Core\Auth;

$this->layout('layouts/app', ['title' => $group['group_code']]);
$role = Auth::activeRole();
$isAdmin = $role === 'admin';
$draft = $group['status'] === 'draft';
$activeCount = count(array_filter($members, fn ($m) => $m['status'] === 'active'));
$base = '/groups/' . (int) $group['id'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><?= e($group['group_code']) ?> · <?= e($group['group_name']) ?></h1>
        <div class="small text-secondary"><?= e(__('groups.registered_at')) ?>: <?= e(thai_date($group['registered_at'], true)) ?></div>
    </div>
    <div><?= status_badge($group['status']) ?></div>
</div>

<?php if ($draft && $group['reject_reason']): ?>
    <div class="alert alert-warning"><strong><?= e(__('groups.reject_reason')) ?>:</strong> <?= e($group['reject_reason']) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6"><?= e(__('dash.members')) ?> <span class="text-secondary small">(<?= $activeCount ?>/<?= $maxMembers ?>, ขั้นต่ำ <?= $minMembers ?>)</span></h2>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>รหัสนิสิต</th><th>ชื่อ-สกุล</th><th>อีเมล</th><th>สาขา</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= e($m['student_code']) ?></td>
                        <td><?= e(full_name($m)) ?>
                            <?php if ($m['member_role'] === 'leader'): ?><span class="badge bg-ppms-navy">Group Leader</span><?php endif; ?>
                            <?php if ($m['status'] === 'invited'): ?><span class="badge text-bg-warning"><?= e(__('groups.pending')) ?></span><?php endif; ?></td>
                        <td class="small"><?= e($m['email']) ?></td>
                        <td class="small"><?= e($m['program']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($isLeader && $draft && $m['member_role'] !== 'leader'): ?>
                                <form method="post" action="<?= e(url("$base/members/" . (int) $m['member_id'] . '/remove')) ?>" class="d-inline" data-confirm="นำ <?= e($m['student_code']) ?> ออกจากกลุ่ม?">
                                    <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($isAdmin && !$draft && $m['status'] === 'active' && $m['member_role'] !== 'leader'): ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#mem<?= (int) $m['member_id'] ?>"><i class="bi bi-three-dots"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($isAdmin && !$draft && $m['status'] === 'active' && $m['member_role'] !== 'leader'): ?>
                        <tr class="collapse" id="mem<?= (int) $m['member_id'] ?>"><td colspan="5">
                            <form method="post" class="row g-2" action="<?= e(url("$base/members/" . (int) $m['member_id'] . '/admin')) ?>">
                                <?= csrf_field() ?>
                                <div class="col-md-6"><input class="form-control form-control-sm" name="reason" required placeholder="<?= e(__('groups.reason')) ?>"></div>
                                <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100" name="action" value="leader"><?= e(__('groups.make_leader')) ?></button></div>
                                <div class="col-md-3"><button class="btn btn-sm btn-outline-danger w-100" name="action" value="remove"><?= e(__('groups.remove_member')) ?></button></div>
                            </form>
                        </td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table></div>

            <?php if ($isLeader && $draft): ?>
                <hr>
                <form method="post" action="<?= e(url("$base/invite")) ?>" class="row g-2 mb-2">
                    <?= csrf_field() ?>
                    <div class="col-md-8"><input class="form-control" name="student_code" required placeholder="<?= e(__('groups.invite_placeholder')) ?>"></div>
                    <div class="col-md-4"><button class="btn btn-outline-primary w-100"><i class="bi bi-person-plus"></i> <?= e(__('groups.invite')) ?></button></div>
                </form>
                <form method="post" action="<?= e(url("$base/rename")) ?>" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <div class="col-md-8"><input class="form-control" name="group_name" value="<?= e($group['group_name']) ?>" required></div>
                    <div class="col-md-4"><button class="btn btn-outline-secondary w-100"><?= e(__('groups.rename')) ?></button></div>
                </form>
                <form method="post" action="<?= e(url("$base/submit")) ?>" data-confirm="<?= e(__('groups.submit_confirm')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-ppms"><i class="bi bi-send"></i> <?= e(__('groups.submit')) ?></button>
                    <span class="small text-secondary ms-2"><?= e(__('groups.submit_help')) ?></span>
                </form>
            <?php endif; ?>

            <?php if ($isAdmin && !$draft): ?>
                <hr>
                <form method="post" action="<?= e(url("$base/members/add")) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-4"><input class="form-control form-control-sm" name="student_code" required placeholder="รหัสนิสิต"></div>
                    <div class="col-md-5"><input class="form-control form-control-sm" name="reason" required placeholder="<?= e(__('groups.reason')) ?>"></div>
                    <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100"><?= e(__('groups.add_member')) ?></button></div>
                </form>
            <?php endif; ?>
        </div></div>

        <?php if ($isAdmin && $history): ?>
            <div class="card"><div class="card-body">
                <h2 class="h6"><?= e(__('groups.history')) ?></h2>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($history as $h): ?>
                        <li class="border-bottom py-1"><?= e(thai_date($h['created_at'], true)) ?> · <?= e($h['student_code']) ?> <?= e($h['first_name_th']) ?> · <strong><?= e(__('history.' . $h['action'])) ?></strong>
                            <?php if ($h['reason']): ?> · <?= e($h['reason']) ?><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div></div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6"><?= e(__('dash.advisor')) ?></h2>
            <?php if ($advisor && $advisor['status'] === 'assigned'): ?>
                <p class="mb-2"><i class="bi bi-person-check text-success"></i> <?= e(full_name($advisor)) ?></p>
                <?php if ($isAdmin): ?>
                    <details class="small mb-2"><summary class="text-danger"><?= e(__('groups.revoke_advisor')) ?></summary>
                        <form method="post" action="<?= e(url("$base/advisor/revoke")) ?>" class="mt-1" data-confirm="<?= e(__('groups.revoke_confirm')) ?>">
                            <?= csrf_field() ?>
                            <textarea class="form-control form-control-sm mb-1" name="reason" rows="2" required placeholder="<?= e(__('groups.revoke_reason')) ?>"></textarea>
                            <div class="form-text mb-1"><?= e(__('groups.revoke_help')) ?></div>
                            <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-x-circle"></i> <?= e(__('groups.revoke_advisor')) ?></button>
                        </form></details>
                <?php endif; ?>
            <?php elseif ($advisor): ?>
                <p class="mb-2"><span class="badge <?= $advisor['status'] === 'accepted' ? 'text-bg-info' : 'text-bg-warning' ?>"><?= e(__('advisor_status.' . $advisor['status'])) ?></span> <?= e(full_name($advisor)) ?></p>
                <?php if ($advisor['status'] === 'accepted' && $advisor['note']): ?><p class="small text-secondary mb-2"><?= e(__('groups.advisor_note')) ?>: <?= e($advisor['note']) ?></p><?php endif; ?>
                <ol class="small text-secondary ps-3 mb-2">
                    <li class="<?= $advisor['status'] === 'requested' ? 'fw-semibold text-body' : '' ?>"><?= e(__('groups.advisor_step1')) ?></li>
                    <li class="<?= $advisor['status'] === 'accepted' ? 'fw-semibold text-body' : '' ?>"><?= e(__('groups.advisor_step2')) ?></li>
                </ol>
            <?php else: ?>
                <p class="text-secondary mb-2"><?= e(__('dash.no_advisor')) ?></p>
            <?php endif; ?>

            <?php if ($isLeader && !($advisor && $advisor['status'] === 'assigned')): ?>
                <form method="post" action="<?= e(url("$base/advisor-request")) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-8"><select name="advisor_id" class="form-select form-select-sm" required>
                        <?php foreach ($advisors as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e(full_name($a)) ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100"><?= e(__('groups.request_advisor')) ?></button></div>
                </form>
            <?php endif; ?>

            <?php if (!empty($myRequest)): ?>
                <div class="border border-primary rounded p-2 mt-2">
                    <div class="small fw-semibold mb-1"><?= e(__('groups.advisor_request_for_you')) ?></div>
                    <form method="post" action="<?= e(url("$base/advisor-response")) ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <div class="col-12"><textarea class="form-control form-control-sm" name="note" rows="2" placeholder="<?= e(__('groups.advisor_response_note')) ?>"></textarea></div>
                        <div class="col-6"><button class="btn btn-sm btn-ppms w-100" name="answer" value="accept"><i class="bi bi-check2"></i> <?= e(__('groups.advisor_accept')) ?></button></div>
                        <div class="col-6"><button class="btn btn-sm btn-outline-danger w-100" name="answer" value="decline" data-confirm-button="<?= e(__('groups.advisor_decline_confirm')) ?>"><i class="bi bi-x"></i> <?= e(__('groups.advisor_decline')) ?></button></div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($isAdmin && $advisor && $advisor['status'] === 'accepted'): ?>
                <form method="post" action="<?= e(url("$base/advisor")) ?>" class="mt-2" data-confirm="<?= e(__('groups.advisor_approve_confirm')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="advisor_id" value="<?= (int) $advisor['advisor_id'] ?>">
                    <button class="btn btn-sm btn-ppms w-100"><i class="bi bi-check2-all"></i> <?= e(__('groups.advisor_final_approve', ['name' => full_name($advisor)])) ?></button>
                </form>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <details class="mt-2 small" <?= $advisor ? '' : 'open' ?>><summary><?= e($advisor ? __('groups.assign_other') : __('groups.assign_advisor')) ?></summary>
                <form method="post" action="<?= e(url("$base/advisor")) ?>" class="row g-2 mt-1">
                    <?= csrf_field() ?>
                    <div class="col-12"><select name="advisor_id" class="form-select form-select-sm" required>
                        <?php foreach ($advisors as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $advisor && (int) $advisor['advisor_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e(full_name($a)) ?> (<?= (int) $a['load_count'] ?> กลุ่ม)</option><?php endforeach; ?>
                    </select></div>
                    <?php if ($advisor): ?>
                        <div class="col-12"><input class="form-control form-control-sm" name="reason" placeholder="<?= e(__('groups.change_reason')) ?>"></div>
                    <?php endif; ?>
                    <div class="col-12"><button class="btn btn-sm btn-outline-primary w-100"><?= e(__('groups.assign_advisor')) ?></button></div>
                </form></details>
            <?php endif; ?>
        </div></div>

        <?php if ($isAdmin && $group['status'] === 'submitted'): ?>
            <div class="card mb-3 border-primary"><div class="card-body">
                <h2 class="h6"><?= e(__('groups.verify_title')) ?></h2>
                <form method="post" action="<?= e(url("$base/verify")) ?>" class="mb-2" data-confirm="<?= e(__('groups.verify_confirm')) ?>">
                    <?= csrf_field() ?><button class="btn btn-ppms w-100"><i class="bi bi-check2"></i> <?= e(__('groups.verify')) ?></button>
                </form>
                <form method="post" action="<?= e(url("$base/reject")) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-8"><input class="form-control form-control-sm" name="reason" required placeholder="<?= e(__('groups.reject_reason')) ?>"></div>
                    <div class="col-4"><button class="btn btn-sm btn-outline-danger w-100"><?= e(__('groups.reject')) ?></button></div>
                </form>
            </div></div>
        <?php endif; ?>

        <?php if ($group['project_id']): ?>
            <div class="card"><div class="card-body">
                <h2 class="h6"><?= e(__('menu.topics')) ?></h2>
                <p class="mb-2"><?= $topic ? status_badge($topic['status']) : '<span class="text-secondary">' . e(__('topics.none')) . '</span>' ?></p>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/projects/' . (int) $group['project_id'])) ?>"><?= e(__('topics.open')) ?></a>
            </div></div>
        <?php endif; ?>
    </div>
</div>
