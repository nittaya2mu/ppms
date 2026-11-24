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
$s1 = login($base, '6510000001');
$s2 = login($base, '6510000002');
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
$admin->ok('admin assigns advisor');
check($q('SELECT status FROM project_groups WHERE id = ?', [$gid]) === 'active', 'group is active');

$pid = (int) $q('SELECT id FROM projects WHERE group_id = ? ORDER BY id DESC LIMIT 1', [$gid]);
check($pid > 0, "project id=$pid");

$fields = ['title_th' => 'ระบบทดสอบผ่าน HTTP', 'title_en' => 'HTTP Test System'];
foreach (['background', 'problem_statement', 'objectives', 'scope', 'expected_outcomes', 'methodology', 'expected_benefits', 'related_work', 'references_text'] as $f) {
    $fields[$f] = "ข้อมูล $f";
}
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
$s1->post("/projects/$pid/topic", $fields + ['scope' => 'ขอบเขตใหม่', 'revision_note' => 'ปรับขอบเขตแล้ว', 'do' => 'submit']);
$s1->ok('resubmit topic v2');
check((int) $q('SELECT MAX(version_no) FROM topic_submissions ts JOIN project_topics t ON t.id = ts.topic_id WHERE t.project_id = ?', [$pid]) === 2, 'topic v2 stored');
$adv->post("/projects/$pid/topic/advisor", ['decision' => 'approve', 'comments' => 'ผ่าน']);
$adv->ok('advisor approves topic');
$admin->get('/topic-approval');
$admin->ok('admin topic queue');
$admin->post("/projects/$pid/topic/admin", ['decision' => 'approve', 'comments' => 'อนุมัติ']);
$admin->ok('admin final-approves topic');

$s1->get("/projects/$pid");
$s1->post("/projects/$pid/proposal", [
    'title' => 'ระบบทดสอบผ่าน HTTP', 'proposal_pdf' => pdfFile('proposal_v1'), 'revision_notes' => '',
] + array_combine(array_map(fn ($i) => "sections[$i]", range(0, 12)), ['title', 'introduction', 'background', 'problem', 'objectives', 'scope', 'literature', 'related_research', 'methodology', 'architecture', 'expected_results', 'timeline', 'references']), true);
$s1->ok('submit proposal PDF');

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

$criteria = $pdo->query('SELECT id FROM evaluation_criteria WHERE offering_id = 1 AND is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
$evalPost = function (Client $c, int $userId, int $rating, string $rec, bool $submit) use ($q, $pid, $criteria) {
    $eid = (int) $q('SELECT e.id FROM committee_evaluations e JOIN committee_assignments ca ON ca.id = e.assignment_id JOIN proposal_submissions p ON p.id = ca.proposal_id WHERE p.project_id = ? AND e.evaluator_id = ? ORDER BY e.id DESC LIMIT 1', [$pid, $userId]);
    $c->get('/evaluations');
    $c->ok('evaluation queue');
    $c->get("/evaluations/$eid");
    $c->ok("open evaluation form #$eid");
    $data = ['recommendation' => $rec, 'do' => $submit ? 'submit' : 'draft'];
    foreach ($criteria as $cid) {
        $data["rating[$cid]"] = $rating;
    }
    foreach (['strengths', 'weaknesses', 'technical', 'improvements', 'overall'] as $t) {
        $data["comment[$t]"] = "ความเห็น $t";
    }
    $c->post("/evaluations/$eid", $data);
    $c->ok(($submit ? 'submit' : 'save draft') . " evaluation #$eid");
    return $eid;
};
$e1 = $evalPost($c1, 103, 4, 'approved', false);
$c1->get("/projects/$pid");
check($c1->code === 403 || !str_contains($c1->body, 'ความเห็น strengths'), 'committee cannot see other evaluations via project page');
$evalPost($c1, 103, 4, 'approved', true);
$c2->get('/evaluations');
$e2 = $evalPost($c2, 104, 3, 'approved_minor', true);

$admin->get('/final-approval');
$admin->ok('final approval queue');
check(str_contains($admin->body, 'ระบบทดสอบผ่าน HTTP'), 'final queue lists proposal');
$admin->get("/projects/$pid");
$admin->ok('admin sees both evaluations');
$admin->post("/projects/$pid/decision", ['decision' => 'not_approved', 'comments' => 'x', 'reasons' => 'y', 'justification' => '']);
check(!$admin->flashOk() && $q('SELECT status FROM proposal_submissions WHERE project_id = ?', [$pid]) !== 'rejected', 'override without justification is refused');
$admin->post("/projects/$pid/decision", ['decision' => 'approved', 'comments' => 'อนุมัติข้อเสนอโครงงาน', 'reasons' => 'คะแนนเฉลี่ยดี', 'justification' => 'กรรมการคนที่ 2 ขอแก้ไขเล็กน้อย แต่ประเด็นแก้ไขแล้วในที่ประชุม']);
$admin->ok('final decision approved');
check($q('SELECT status FROM proposal_submissions WHERE project_id = ?', [$pid]) === 'approved', 'proposal approved');

$s1->get("/projects/$pid");
$s1->ok('student sees approved project');
check(str_contains($s1->body, 'อนุมัติข้อเสนอโครงงาน'), 'student sees decision comments');
$s1->get("/projects/$pid/summary");
$s1->ok('approval summary');
check(!str_contains($s1->body, 'วิชัย'), 'committee names hidden from student (show_committee_names=false)');

$fileId = (int) $q('SELECT file_id FROM proposal_versions ORDER BY id DESC LIMIT 1');
$s1->get("/files/$fileId");
check($s1->code === 200 && str_starts_with($s1->body, '%PDF'), 'student downloads own proposal');
$other = login($base, '6510000005');
$other->get("/files/$fileId");
check($other->code === 403 || $other->code === 404, 'other student cannot download proposal');
$other->get("/projects/$pid");
check($other->code === 403 || $other->code === 404, 'other student cannot open project');

echo "== Page sweep\n";
$pages = [
    'admin' => [$admin, ['/dashboard', '/groups', "/groups/$gid", "/projects/$pid", "/projects/$pid/summary", '/topics', '/topic-approval', '/proposals', '/committee-assignment', '/final-approval', '/evaluations', '/reports', '/admin/settings', '/admin/settings/academic', '/admin/settings/deadlines', '/admin/settings/rubric', '/admin/users', '/notifications']],
    'advisor' => [$adv, ['/dashboard', '/groups', "/groups/$gid", "/projects/$pid", '/topics', '/topic-approval', '/advisor-review', '/proposals', '/reports', '/notifications']],
    'committee' => [$c1, ['/dashboard', '/evaluations', "/evaluations/$e1", '/notifications']],
    'student' => [$s1, ['/dashboard', '/groups', "/groups/$gid", '/topics', '/proposals', '/notifications']],
    'new student' => [$other, ['/dashboard', '/groups', '/topics', '/proposals']],
];
foreach ($pages as [$c, $list]) {
    foreach ($list as $p) {
        $c->get($p);
        $c->ok("GET $p");
    }
}
foreach (['groups', 'memberships', 'topic_submissions', 'topic_approvals', 'proposal_submissions', 'committee_assignments', 'individual_evaluations', 'average_scores', 'recommendations', 'revision_history', 'final_approvals', 'course_progress'] as $r) {
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

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
