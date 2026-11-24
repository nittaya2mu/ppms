<?php
/** @var array $years @var array $semesters @var array $courses @var array $offerings @var array $staff @var string $tab */
$this->layout('layouts/app', ['title' => __('menu.settings')]);
echo $this->partial('admin/settings/_tabs', ['tab' => $tab]);
?>
<div class="card mb-4"><div class="card-body">
    <h2 class="h6"><?= e(__('settings.offerings')) ?></h2>
    <p class="small text-secondary"><?= e(__('settings.offerings_help')) ?></p>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>รายวิชา</th><th>ภาค/ปี</th><th>ผู้ประสานงาน</th><th>กลุ่ม</th><th><?= e(__('common.status')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($offerings as $o): ?>
            <tr>
                <td><?= e($o['course_code'] . ' ' . $o['course_name_th']) ?></td>
                <td><?= (int) $o['term_no'] ?>/<?= (int) $o['year_be'] ?></td>
                <td><?= e(full_name($o)) ?></td>
                <td><?= (int) $o['group_count'] ?></td>
                <td><span class="badge text-bg-<?= $o['status'] === 'open' ? 'success' : ($o['status'] === 'closed' ? 'secondary' : 'warning') ?>"><?= e(__('offering.' . $o['status'])) ?></span></td>
                <td class="text-end">
                    <form method="post" action="<?= e(url('/admin/settings/offerings/' . (int) $o['id'] . '/status')) ?>" class="d-inline-flex gap-1">
                        <?= csrf_field() ?>
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach (['draft', 'open', 'closed'] as $st): ?><option value="<?= $st ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= e(__('offering.' . $st)) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary"><?= e(__('common.save')) ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <form method="post" action="<?= e(url('/admin/settings/offerings')) ?>" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-4"><label class="form-label small">รายวิชา</label>
            <select name="course_id" class="form-select form-select-sm" required><?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['course_code'] . ' ' . $c['name_th']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small">ภาคการศึกษา</label>
            <select name="semester_id" class="form-select form-select-sm" required><?php foreach ($semesters as $s): ?><option value="<?= (int) $s['id'] ?>"><?= (int) $s['term_no'] ?>/<?= (int) $s['year_be'] ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small">ผู้ประสานงานรายวิชา</label>
            <select name="coordinator_id" class="form-select form-select-sm" required><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e(full_name($u)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-ppms w-100"><i class="bi bi-plus"></i> <?= e(__('settings.add_offering')) ?></button></div>
    </form>
</div></div>

<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h6"><?= e(__('settings.courses')) ?></h2>
        <?php foreach ($courses as $c): ?>
            <form method="post" action="<?= e(url('/admin/settings/courses')) ?>" class="row g-1 mb-2">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <div class="col-3"><input class="form-control form-control-sm" name="course_code" value="<?= e($c['course_code']) ?>" aria-label="รหัสวิชา"></div>
                <div class="col-4"><input class="form-control form-control-sm" name="name_th" value="<?= e($c['name_th']) ?>" aria-label="ชื่อวิชา (ไทย)"></div>
                <div class="col-3"><input class="form-control form-control-sm" name="name_en" value="<?= e($c['name_en']) ?>" aria-label="Course name"></div>
                <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100"><?= e(__('common.save')) ?></button></div>
                <input type="hidden" name="credits" value="<?= e($c['credits']) ?>">
            </form>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('/admin/settings/courses')) ?>" class="row g-1">
            <?= csrf_field() ?>
            <div class="col-3"><input class="form-control form-control-sm" name="course_code" placeholder="รหัสวิชา"></div>
            <div class="col-4"><input class="form-control form-control-sm" name="name_th" placeholder="ชื่อวิชา (ไทย)"></div>
            <div class="col-3"><input class="form-control form-control-sm" name="name_en" placeholder="Course name"></div>
            <div class="col-2"><button class="btn btn-sm btn-ppms w-100"><i class="bi bi-plus"></i></button></div>
        </form>
    </div></div></div>

    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h6"><?= e(__('settings.years')) ?></h2>
        <table class="table table-sm"><tbody>
            <?php foreach ($years as $y): ?>
                <tr><td><?= (int) $y['year_be'] ?> <?= (int) $y['is_current'] === 1 ? '<span class="badge text-bg-success">ปัจจุบัน</span>' : '' ?></td>
                    <td class="small"><?= e(thai_date($y['start_date'])) ?> – <?= e(thai_date($y['end_date'])) ?></td></tr>
                <?php foreach ($semesters as $s): if ((int) $s['academic_year_id'] !== (int) $y['id']) { continue; } ?>
                    <tr class="small text-secondary"><td class="ps-4">ภาค <?= (int) $s['term_no'] ?></td><td><?= e(thai_date($s['start_date'])) ?> – <?= e(thai_date($s['end_date'])) ?></td></tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody></table>
        <form method="post" action="<?= e(url('/admin/settings/years')) ?>" class="row g-1 mb-2">
            <?= csrf_field() ?>
            <div class="col-3"><input type="number" class="form-control form-control-sm" name="year_be" placeholder="ปี พ.ศ." required></div>
            <div class="col-3"><input type="date" class="form-control form-control-sm" name="start_date" required aria-label="เริ่ม"></div>
            <div class="col-3"><input type="date" class="form-control form-control-sm" name="end_date" required aria-label="สิ้นสุด"></div>
            <div class="col-1 form-check pt-1"><input class="form-check-input" type="checkbox" name="is_current" value="1" title="ปีปัจจุบัน"></div>
            <div class="col-2"><button class="btn btn-sm btn-ppms w-100"><?= e(__('common.save')) ?></button></div>
        </form>
        <form method="post" action="<?= e(url('/admin/settings/semesters')) ?>" class="row g-1">
            <?= csrf_field() ?>
            <div class="col-3"><select name="academic_year_id" class="form-select form-select-sm"><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"><?= (int) $y['year_be'] ?></option><?php endforeach; ?></select></div>
            <div class="col-2"><select name="term_no" class="form-select form-select-sm"><option>1</option><option>2</option><option>3</option></select></div>
            <div class="col-3"><input type="date" class="form-control form-control-sm" name="start_date" required aria-label="เริ่มภาค"></div>
            <div class="col-2"><input type="date" class="form-control form-control-sm" name="end_date" required aria-label="สิ้นสุดภาค"></div>
            <div class="col-2"><button class="btn btn-sm btn-ppms w-100"><?= e(__('common.save')) ?></button></div>
        </form>
    </div></div></div>
</div>
