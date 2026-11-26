<?php

declare(strict_types=1);

/**
 * HTTP end-to-end walk-through: drives the whole Section 22 scenario through the real
 * routes, controllers and views (login, CSRF, forms, uploads), then opens every page for
 * every role and fails on PHP errors, error flashes or non-200 responses.
 *
 * Needs a server on a FRESH database loaded with schema + seed + seed_demo:
 *   PPMS_DB_DATABASE=ppms_e2e php -S 127.0.0.1:8000 -t public public/index.php
 *   PPMS_DB_DATABASE=ppms_e2e php tests/http_e2e.php [http://127.0.0.1:8000]
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$cfg = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$q = fn (string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();

$fail = 0;
$pass = 0;
function check(bool $ok, string $msg): void
{
    global $fail, $pass;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  ok   " : "  FAIL ") . $msg . "\n";
}

final class Client
{
    private string $jar;
    public string $body = '';
    public int $code = 0;
    public string $url = '';

    public function __construct(private string $base, public string $who)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function get(string $path): string
    {
        return $this->req($path, null);
    }

    public function post(string $path, array $data = [], bool $multipart = false): string
    {
        if (preg_match('/name="_token" value="([^"]+)"/', $this->body, $m) !== 1) {
            $this->get('/dashboard');
            preg_match('/name="_token" value="([^"]+)"/', $this->body, $m);
        }
        $data['_token'] = $m[1] ?? '';
        return $this->req($path, $data, $multipart);
    }

    private function req(string $path, ?array $data, bool $multipart = false): string
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar]);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));
        }
        $this->body = (string) curl_exec($ch);
        $this->code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->url = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $this->body;
    }

    public function errors(): array
    {
        $e = [];
        if (preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught|Stack trace|View not found)/', $this->body, $m)) {
            $e[] = 'PHP: ' . $m[1];
        }
        if (preg_match('/alert-danger[^>]*>(.*?)<\/div>/s', $this->body, $m)) {
            $e[] = 'flash: ' . trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
        }
        if (str_contains($this->body, 'is-invalid')) {
            preg_match_all('/invalid-feedback[^>]*>(.*?)</s', $this->body, $mm);
            $e[] = 'field: ' . implode(' | ', array_map('trim', $mm[1]));
        }
        if ($this->code !== 200) {
            $e[] = 'HTTP ' . $this->code;
        }
        return $e;
    }

    public function ok(string $step): void
    {
        $e = $this->errors();
        check($e === [], "[{$this->who}] $step" . ($e ? ' -> ' . implode('; ', $e) . ' @ ' . $this->url : ''));
    }

    public function flashOk(): bool
    {
        return str_contains($this->body, 'alert-success');
    }
}

function login(string $base, string $user, ?string $role = null): Client
{
    $c = new Client($base, $user . ($role ? "/$role" : ''));
    $c->get('/login');
    $c->post('/login', ['login' => $user, 'password' => $user === 'admin' ? 'Admin@1234' : 'Demo@1234']);
    $c->ok('login');
    if ($role !== null) {
        $c->post('/role', ['role' => $role]);
        $c->ok("switch role $role");
    }
    return $c;
}

function pdfFile(string $label): CURLFile
{
    $tmp = sys_get_temp_dir() . "/$label.pdf";
    file_put_contents($tmp, "%PDF-1.4\n% $label\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF\n");
    return new CURLFile($tmp, 'application/pdf', "$label.pdf");
}

echo "== Workflow\n";
$admin = login($base, 'admin');
$s1 = login($base, 'student1');
$s2 = login($base, 'student2');
$adv = login($base, 'advisor1', 'advisor');
$c1 = login($base, 'committee1');
$c2 = login($base, 'committee2');

$s1->get('/groups');
$s1->ok('open group start page');
$s1->post('/groups', ['group_name' => 'ทีมทดสอบ HTTP']);
$s1->ok('create group');
$gid = (int) $q('SELECT id FROM project_groups ORDER BY id DESC LIMIT 1');
check($gid > 0, "group created id=$gid");

$s1->post("/groups/$gid/invite", ['student_code' => '6510000002']);
$s1->ok('invite member');
$mid = (int) $q('SELECT id FROM project_members WHERE group_id = ? AND student_id = 2', [$gid]);
$s2->get('/groups');
$s2->ok('invitee sees invitation');
check(str_contains($s2->body, 'ทีมทดสอบ HTTP'), 'invitation lists group name');
$s2->post("/groups/invitations/$mid", ['answer' => 'accept']);
$s2->ok('accept invitation');

$s1->post("/groups/$gid/submit");
$s1->ok('submit group registration');
$s1->post("/groups/$gid/advisor-request", ['advisor_id' => 101]);
$s1->ok('request advisor');

$admin->get("/groups/$gid");
$admin->ok('admin opens group');
$admin->post("/groups/$gid/verify");
$admin->ok('admin verifies group');
$admin->post("/groups/$gid/advisor", ['advisor_id' => 101, 'reason' => '']);
check(str_contains($admin->body, 'alert-danger'), 'admin cannot approve before the advisor accepts');
$adv->get('/groups');
$adv->ok('advisor group list');
check(str_contains($adv->body, 'คำขอให้ท่านเป็นอาจารย์ที่ปรึกษา'), 'advisor sees request');
$adv->get("/groups/$gid");
$adv->ok('requested advisor opens group');
$adv->post("/groups/$gid/advisor-response", ['answer' => 'accept', 'note' => 'ยินดีรับ']);
$adv->ok('advisor accepts request');
$admin->get('/groups');
check(str_contains($admin->body, 'รออนุมัติลำดับสุดท้าย'), 'admin sees accepted request');
$admin->get("/groups/$gid");
$admin->post("/groups/$gid/advisor", ['advisor_id' => 101]);
$admin->ok('admin approves advisor (final)');
check($q('SELECT status FROM project_groups WHERE id = ?', [$gid]) === 'active', 'group is active');

$pid = (int) $q('SELECT id FROM projects WHERE group_id = ? ORDER BY id DESC LIMIT 1', [$gid]);
check($pid > 0, "project id=$pid");

$fields = ['title_th' => 'ระบบทดสอบผ่าน HTTP', 'title_en' => 'HTTP Test System', 'project_type' => 'web', 'project_type_other' => '', 'co_advisor_name' => 'อ.ที่ปรึกษาร่วม ทดสอบ'];
$s1->get("/projects/$pid");
$s1->ok('student opens project');
$s1->post("/projects/$pid/topic", $fields + ['do' => 'save']);
$s1->ok('save topic draft');
$s1->post("/projects/$pid/topic", $fields + ['do' => 'submit']);
$s1->ok('submit topic');

$adv->get('/topic-approval');
$adv->ok('advisor topic queue');
check(str_contains($adv->body, 'ระบบทดสอบผ่าน HTTP'), 'advisor queue lists topic');
$adv->post("/projects/$pid/topic/advisor", ['decision' => 'revise', 'comments' => 'ขอให้ปรับขอบเขต']);
$adv->ok('advisor asks topic revision');
$s1->post("/projects/$pid/topic", ['project_type' => 'other', 'project_type_other' => 'Web + IoT', 'revision_note' => 'ปรับประเภทโครงงานแล้ว', 'do' => 'submit'] + $fields);
$s1->ok('resubmit topic v2');
check((int) $q('SELECT MAX(version_no) FROM topic_submissions ts JOIN project_topics t ON t.id = ts.topic_id WHERE t.project_id = ?', [$pid]) === 2, 'topic v2 stored');
$adv->post("/projects/$pid/topic/advisor", ['decision' => 'approve', 'comments' => 'ผ่าน']);
$adv->ok('advisor approves topic');
$admin->get('/topic-approval');
$admin->ok('admin topic queue');
$admin->post("/projects/$pid/topic/admin", ['decision' => 'approve', 'comments' => 'อนุมัติ']);
$admin->ok('admin final-approves topic');

$s1->get("/projects/$pid");
$s1->ok('topic print link visible');
check(str_contains($s1->body, '/topic/print'), 'form 1 print link');
$s1->get("/projects/$pid/topic/print");
$s1->ok('print form 1');
check(str_contains($s1->body, 'แบบขอเสนออนุมัติโครงงาน') && str_contains($s1->body, '☑ อื่นๆ Web + IoT'), 'form 1 shows project type');
$s1->get("/projects/$pid");
$ch1 = [];
foreach (['ch1_background', 'ch1_objectives', 'ch1_scope', 'ch1_benefits', 'ch1_tools', 'ch1_plan'] as $k) {
    $ch1[$k] = "บทที่ 1 $k";
}
$s2->get("/projects/$pid");
check(str_contains($s2->body, 'id="chapters"') && str_contains($s2->body, 'name="ch2_heading[]"'), 'member sees chapter 1-3 draft form');
$s2->post("/projects/$pid/proposal", ['do' => 'save', 'ch1_background' => 'ร่างโดยสมาชิก'], true);
$s2->ok('member saves draft');
check(str_contains($s2->body, 'ร่างโดยสมาชิก'), 'draft text kept');
$s1->get("/projects/$pid");
$s1->post("/projects/$pid/proposal", $ch1 + ['do' => 'send_1', 'chapter1' => pdfFile('ch1')], true);
$s1->ok('leader saves chapter 1 and sends it');
$s1->post("/projects/$pid/proposal", ['do' => 'send_2', 'ch2_title' => 'ทฤษฎีและงานวิจัยที่เกี่ยวข้อง', 'ch2_heading[0]' => 'ทฤษฎีที่เกี่ยวข้อง', 'ch2_content[0]' => 'Deep learning', 'ch2_heading[1]' => 'งานวิจัยที่เกี่ยวข้อง', 'ch2_content[1]' => 'งานวิจัย A'], true);
check(str_contains($s1->body, 'alert-danger'), 'chapter 2 without file refused');
$s1->post("/projects/$pid/proposal", ['do' => 'send_2', 'chapter2' => pdfFile('ch2'), 'ch2_title' => 'ทฤษฎีและงานวิจัยที่เกี่ยวข้อง', 'ch2_heading[0]' => 'ทฤษฎีที่เกี่ยวข้อง', 'ch2_content[0]' => 'Deep learning', 'ch2_heading[1]' => 'งานวิจัยที่เกี่ยวข้อง', 'ch2_content[1]' => 'งานวิจัย A'], true);
$s1->ok('send chapter 2');
$s1->post("/projects/$pid/proposal", ['do' => 'send_3', 'chapter3' => pdfFile('ch3'), 'ch3_title' => 'วิธีการดำเนินงาน', 'ch3_heading[0]' => 'ภาพรวมของระบบ', 'ch3_content[0]' => 'สถาปัตยกรรม'], true);
$s1->ok('send chapter 3');
$s1->post("/projects/$pid/proposal", ['do' => 'submit'], true);
check(str_contains($s1->body, 'alert-danger'), 'submit refused before chapters approved');
$adv->get('/advisor-review');
$adv->ok('advisor review queue');
check(substr_count($adv->body, 'บทที่ ') >= 3, 'queue lists pending chapters');
$adv->get("/projects/$pid");
$adv->ok('advisor opens chapters');
$revs = $pdo->query("SELECT id, chapter FROM proposal_chapter_reviews WHERE project_id = $pid ORDER BY chapter")->fetchAll(PDO::FETCH_KEY_PAIR);
check(count($revs) === 3, '3 chapters waiting');
$adv->post("/projects/$pid/chapters/" . array_search(2, $revs) . "/review", ['decision' => 'revise', 'comments' => 'เพิ่มงานวิจัย']);
$adv->ok('advisor returns chapter 2');
foreach ([1, 3] as $chn) {
    $adv->post("/projects/$pid/chapters/" . array_search($chn, $revs) . "/review", ['decision' => 'approve', 'comments' => '']);
    $adv->ok("advisor approves chapter $chn");
}
$s1->get("/projects/$pid");
check(str_contains($s1->body, 'เพิ่มงานวิจัย'), 'student sees chapter 2 comments');
$s1->post("/projects/$pid/proposal", ['do' => 'send_2', 'ch2_title' => 'ทฤษฎีและงานวิจัยที่เกี่ยวข้อง', 'ch2_heading[0]' => 'ทฤษฎีที่เกี่ยวข้อง', 'ch2_content[0]' => 'Deep learning', 'ch2_heading[1]' => 'งานวิจัยที่เกี่ยวข้อง', 'ch2_content[1]' => 'งานวิจัย A และ B'], true);
$s1->ok('resend chapter 2');
$r2b = $q("SELECT id FROM proposal_chapter_reviews WHERE project_id = ? AND chapter = 2 ORDER BY id DESC LIMIT 1", [$pid]);
$adv->post("/projects/$pid/chapters/$r2b/review", ['decision' => 'approve', 'comments' => '']);
$adv->ok('advisor approves chapter 2');
$s1->get("/projects/$pid/proposal/print");
$s1->ok('print draft chapters');
check(str_contains($s1->body, 'บทที่ 2') && str_contains($s1->body, '2.2 งานวิจัยที่เกี่ยวข้อง'), 'print shows numbered chapter 2');
$s1->get("/projects/$pid");
$s1->post("/projects/$pid/proposal", ['do' => 'submit', 'revision_notes' => ''], true);
$s1->ok('submit proposal chapter 1 + files');
check($q('SELECT ch3_file_id FROM proposal_versions ORDER BY id DESC LIMIT 1') !== null, 'chapter 3 stored');

$adv->get('/advisor-review');
$adv->ok('advisor proposal queue');
$adv->post("/projects/$pid/proposal/advisor", ['decision' => 'ready', 'comments' => 'พร้อมประเมิน']);
$adv->ok('advisor marks ready for committee');

$admin->get('/committee-assignment');
$admin->ok('committee assignment queue');
$admin->get("/projects/$pid");
check(!preg_match('/name="member1_id".*?value="101"/s', $admin->body), 'advisor not offered as committee');
$admin->post("/projects/$pid/committee", ['member1_id' => 103, 'member2_id' => 104, 'deadline' => date('Y-m-d\TH:i', strtotime('+7 days'))]);
$admin->ok('assign committee');

$evalPost = function (Client $c, int $userId, string $letter, string $result, bool $submit) use ($q, $pid) {
    $eid = (int) $q('SELECT e.id FROM presentation_evaluations e JOIN committee_assignments ca ON ca.id = e.assignment_id JOIN proposal_submissions p ON p.id = ca.proposal_id WHERE p.project_id = ? AND e.evaluator_id = ? ORDER BY e.id DESC LIMIT 1', [$pid, $userId]);
    $c->get('/evaluations');
    $c->ok('evaluation queue');
    check(str_contains($c->body, '/evaluations/' . $eid), 'queue lists own form');
    $c->get("/evaluations/$eid");
    $c->ok("open form 2 #$eid");
    check(str_contains($c->body, 'บทที่ 3') && str_contains($c->body, 'ความเป็นมาและความสำคัญ'), 'form shows chapter files and chapter 1');
    $data = ['result' => $result, 'suggestions' => $result === 'pass' ? '' : 'ควรปรับปรุง', 'do' => $submit ? 'submit' : 'draft'];
    foreach (['pres_completeness', 'pres_delivery', 'pres_punctuality', 'pres_dress', 'pres_qa', 'theory_completeness', 'theory_understanding', 'doc_format', 'doc_content'] as $k) {
        $data["grade[$k]"] = $letter;
    }
    foreach (['objectives_ok', 'scope_ok', 'methodology_ok', 'literature_ok'] as $k) {
        $data["check[$k]"] = '1';
    }
    $c->post("/evaluations/$eid", $data);
    $c->ok(($submit ? 'submit' : 'save draft') . " form 2 #$eid");
    return $eid;
};
$e1 = $evalPost($c1, 103, 'A', 'pass', false);
$c1->get("/projects/$pid");
check($c1->code === 403 || !str_contains($c1->body, 'ควรปรับปรุง'), 'committee cannot see other evaluations');
$evalPost($c1, 103, 'A', 'pass', true);
$evalPost($c2, 104, 'B', 'revise', true);
$advId = $q("SELECT e.id FROM presentation_evaluations e WHERE e.evaluator_role = 'advisor' ORDER BY id DESC LIMIT 1");
$c1->get("/evaluations/$advId");
check($c1->code === 403, 'committee cannot open the advisor form');
$evalPost($adv, 101, 'B+', 'pass', true);
check($q('SELECT status FROM proposal_submissions WHERE project_id = ?', [$pid]) === 'evaluation_completed', 'all three submitted -> evaluation completed');

$admin->get('/final-approval');
$admin->ok('final approval queue');
check(str_contains($admin->body, 'ระบบทดสอบผ่าน HTTP'), 'final queue lists proposal');
$admin->get("/projects/$pid");
$admin->ok('admin sees both evaluations');
$admin->post("/projects/$pid/decision", ['decision' => 'not_approved', 'comments' => 'x', 'reasons' => 'y', 'justification' => '']);
check(!$admin->flashOk() && $q('SELECT status FROM proposal_submissions WHERE project_id = ?', [$pid]) !== 'rejected', 'override without justification is refused');
$admin->post("/projects/$pid/decision", ['decision' => 'approved', 'comments' => 'อนุมัติข้อเสนอโครงงาน', 'reasons' => 'คะแนนเฉลี่ยดี', 'justification' => 'กรรมการคนที่ 2 ขอแก้ไขเล็กน้อย แต่ประเด็นแก้ไขแล้วในที่ประชุม']);
check(str_contains($admin->body, 'อนุมัติข้อเสนอโครงงาน'), 'decision recorded');
$admin->ok('final decision approved');
check($q('SELECT status FROM proposal_submissions WHERE project_id = ?', [$pid]) === 'approved', 'proposal approved');

$s1->get("/projects/$pid");
$s1->ok('student sees approved project');
check(str_contains($s1->body, 'อนุมัติข้อเสนอโครงงาน'), 'student sees decision comments');
$s1->get("/projects/$pid/summary");
$s1->ok('approval summary');
check(!str_contains($s1->body, 'วิชัย'), 'committee names hidden from student (show_committee_names=false)');

echo "== Progress reports and scope\n";
$s1->get("/projects/$pid/scope");
$s1->ok('scope page before progress');
check(!str_contains($s1->body, 'name="detail[]"'), 'scope form hidden until 3 reviewed reports');
for ($i = 1; $i <= 3; $i++) {
    $who = $i === 2 ? $s2 : $s1;
    $who->get("/projects/$pid/progress");
    $who->post("/projects/$pid/progress", ['meeting_date' => date('Y-m-d', strtotime('-' . (10 - $i) . ' days')), 'work_summary' => "สรุปงานครั้งที่ $i"]);
    $who->ok("submit progress report $i");
}
$rids = $pdo->query("SELECT id FROM progress_reports WHERE project_id = $pid ORDER BY report_no")->fetchAll(PDO::FETCH_COLUMN);
check(count($rids) === 3, '3 progress reports stored');
$s1->post("/projects/$pid/progress/{$rids[0]}", ['meeting_date' => date('Y-m-d', strtotime('-9 days')), 'work_summary' => 'สรุปงานครั้งที่ 1 (แก้ไข)']);
$s1->ok('student edits unreviewed report');
$adv->get('/progress');
$adv->ok('advisor progress queue');
$adv->get("/projects/$pid/progress");
$adv->ok('advisor opens progress page');
foreach ($rids as $i => $rid) {
    $adv->post("/projects/$pid/progress/$rid/review", ['score' => (string) [25, 20, 30][$i], 'suggestions' => 'ข้อเสนอแนะครั้งที่ ' . ($i + 1)]);
    $adv->ok('advisor scores report ' . ($i + 1));
}
$s1->post("/projects/$pid/progress/{$rids[0]}", ['meeting_date' => date('Y-m-d'), 'work_summary' => 'x']);
check(str_contains($s1->body, 'alert-danger'), 'reviewed report locked for students');
$s1->get("/projects/$pid/progress/print");
$s1->ok('print form 8');
check(str_contains($s1->body, 'สมุดบันทึกการเข้าพบ') && substr_count($s1->body, '☑') === 3, 'form 8 shows 3 scored meetings');
$s1->get("/projects/$pid/scope");
check(str_contains($s1->body, 'name="detail[]"'), 'scope form available');
$s1->post("/projects/$pid/scope", ['detail' => ['ระบบสมาชิก', 'ระบบหลัก'], 'weight' => ['40', '50']]);
check(str_contains($s1->body, 'alert-danger'), 'weights not 100 refused');
$s1->post("/projects/$pid/scope", ['detail' => ['ระบบสมาชิก', 'ระบบหลัก', 'รายงาน'], 'weight' => ['30', '50', '20']]);
$s1->ok('submit scope form');
foreach ([[$adv, 'advisor'], [$c1, 'committee1'], [$c2, 'committee2']] as [$c, $label]) {
    $c->get('/scope-approval');
    $c->ok("$label scope queue");
    check(str_contains($c->body, 'รอท่านพิจารณา'), "$label sees pending scope");
}
$c2->post("/projects/$pid/scope/decide", ['decision' => 'revision_required', 'comments' => 'แยกหัวข้อรายงาน']);
$c2->ok('committee2 asks for revision');
$s1->get("/projects/$pid/scope");
check(str_contains($s1->body, 'value="รายงาน"'), 'revision form prefilled');
$s1->post("/projects/$pid/scope", ['detail' => ['ระบบสมาชิก', 'ระบบหลัก', 'รายงานรายวัน', 'รายงานรายเดือน'], 'weight' => ['30', '50', '10', '10']]);
$s1->ok('resubmit scope v2');
$adv->post("/projects/$pid/scope/decide", ['decision' => 'approved', 'comments' => '']);
$adv->ok('advisor approves scope');
$c1->post("/projects/$pid/scope/decide", ['decision' => 'approved', 'comments' => '']);
$c1->ok('committee1 approves scope');
$c2->post("/projects/$pid/scope/decide", ['decision' => 'approved', 'comments' => 'เห็นชอบ']);
$c2->ok('committee2 approves scope');
check($q('SELECT status FROM scope_submissions WHERE project_id = ? ORDER BY version_no DESC LIMIT 1', [$pid]) === 'approved', 'scope approved by all three');
$s1->get("/projects/$pid/scope/print");
$s1->ok('print form 7');
check(str_contains($s1->body, 'แบบอนุมัติขอบเขตโครงงาน') && str_contains($s1->body, 'รายงานรายเดือน'), 'form 7 shows scope items');
$s1->get("/projects/$pid");
check(substr_count($s1->body, 'class="done"') === 6, 'stepper complete');

$fileId = (int) $q('SELECT ch3_file_id FROM proposal_versions ORDER BY id DESC LIMIT 1');
$s1->get("/files/$fileId");
check($s1->code === 200 && str_starts_with($s1->body, '%PDF'), 'student downloads own proposal');
$other = login($base, 'student5');
$other->get("/files/$fileId");
check($other->code === 403 || $other->code === 404, 'other student cannot download proposal');
$other->get("/projects/$pid");
check($other->code === 403 || $other->code === 404, 'other student cannot open project');

echo "== Page sweep\n";
$pages = [
    'admin' => [$admin, ['/dashboard', '/groups', "/groups/$gid", "/projects/$pid", "/projects/$pid/summary", "/projects/$pid/proposal/print?version=1", "/projects/$pid/progress", "/projects/$pid/scope", "/projects/$pid/topic/print", "/projects/$pid/progress/print", "/projects/$pid/scope/print", '/topics', '/topic-approval', '/proposals', '/committee-assignment', '/final-approval', '/evaluations', '/progress', '/scope-approval', '/reports', '/admin/settings', '/admin/settings/academic', '/admin/settings/deadlines', '/admin/settings/rubric', '/admin/users', '/notifications']],
    'advisor' => [$adv, ['/dashboard', '/groups', "/groups/$gid", "/projects/$pid", "/projects/$pid/progress", "/projects/$pid/scope", '/topics', '/topic-approval', '/advisor-review', '/proposals', '/evaluations', '/progress', '/scope-approval', '/reports', '/notifications']],
    'committee' => [$c1, ['/dashboard', '/evaluations', "/evaluations/$e1", '/scope-approval', "/projects/$pid/scope", '/notifications']],
    'student' => [$s1, ['/dashboard', '/groups', "/groups/$gid", '/topics', '/proposals', '/progress', '/scope-approval', "/projects/$pid/progress", "/projects/$pid/scope", '/notifications']],
    'new student' => [$other, ['/dashboard', '/groups', '/topics', '/proposals']],
];
foreach ($pages as [$c, $list]) {
    foreach ($list as $p) {
        $c->get($p);
        $c->ok("GET $p");
    }
}
foreach (['groups', 'memberships', 'topic_submissions', 'topic_approvals', 'proposal_submissions', 'committee_assignments', 'individual_evaluations', 'average_scores', 'recommendations', 'revision_history', 'final_approvals', 'progress_reports', 'scope_approvals', 'course_progress'] as $r) {
    $admin->get("/reports/$r");
    $admin->ok("report $r");
    $admin->get("/reports/$r?format=xlsx");
    check($admin->code === 200 && str_starts_with($admin->body, 'PK'), "report $r xlsx");
    $admin->get("/reports/$r?format=csv");
    check($admin->code === 200 && $admin->body !== '', "report $r csv");
    $admin->get("/reports/$r?format=print");
    $admin->ok("report $r print");
}
$s1->get('/reports');
check($s1->code === 403, 'student blocked from reports');
$s1->get('/admin/settings');
check($s1->code === 403, 'student blocked from settings');
$other->get("/projects/$pid/progress");
check($other->code === 403, 'other student blocked from progress page');
$admin->post('/admin/settings/rubric', ['min_reports' => '3', 'max_reports' => '6', 'level_score' => ['30', '20', '10', '0'], 'level_label' => ['ดีมาก', 'ดี', 'พอใช้', 'ไม่มีความก้าวหน้า']]);
$admin->ok('admin saves progress rubric');
check($q("SELECT setting_value FROM system_settings WHERE setting_key = 'progress.max_reports'") === '6', 'progress settings saved');

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
