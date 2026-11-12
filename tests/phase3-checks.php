<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Admin;
use App\Services\Database;

$baseUrl  = 'http://127.0.0.1:8012';
$failures = 0;

function p3(string $id, bool $ok, string $info = ''): void {
    global $failures;
    echo sprintf("[%s] %s %s\n", $ok ? 'PASS' : 'FAIL', $id, $info);
    if (!$ok) {
        $failures++;
    }
}

function req(string $url, string $cookieJar, ?array $post = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
    ];
    if ($post !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $raw     = (string) curl_exec($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [
        'code'    => $code,
        'headers' => substr($raw, 0, $hdrSize),
        'body'    => substr($raw, $hdrSize),
    ];
}

function getCsrf(string $html): string {
    preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

$db = Database::connection();
$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limits');
$db->exec('DELETE FROM audit_log');
$db->exec('DELETE FROM members');

$tmpDir = sys_get_temp_dir() . '/cp_p3_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0777, true);
$jarAdmin    = $tmpDir . '/admin.txt';
$jarReviewer = $tmpDir . '/rev.txt';
$jarAnon     = $tmpDir . '/anon.txt';

// ── AUTH-07: Direct URL access logged out -> redirect to login ──
$auth07 = true;
foreach (['/admin/dashboard.php', '/admin/members.php', '/admin/member.php?id=1', '/admin/export.php', '/admin/file.php?id=1'] as $u) {
    $r = req($baseUrl . $u, $jarAnon);
    if ($r['code'] !== 302 || !str_contains($r['headers'], '/admin/login.php')) {
        $auth07 = false;
    }
}
p3('AUTH-07', $auth07);
p3('MEM-04', req($baseUrl . '/admin/file.php?id=1', $jarAnon)['code'] === 302);
p3('MEM-17', req($baseUrl . '/admin/export.php', $jarAnon)['code'] === 302);

// ── AUTH-12: Login CSRF missing -> 403 ──
$rNoCsrf = req($baseUrl . '/admin/login.php', $jarAnon, ['identifier' => 'admin', 'password' => 'Admin@1234']);
p3('AUTH-12', $rNoCsrf['code'] === 403);

// ── AUTH-06: Empty fields ──
$loginPage = req($baseUrl . '/admin/login.php', $jarAdmin);
$csrfLogin = getCsrf($loginPage['body']);
$rEmpty = req($baseUrl . '/admin/login.php', $jarAdmin, ['_csrf_token' => $csrfLogin, 'identifier' => '', 'password' => '']);
p3('AUTH-06', $rEmpty['code'] === 422 && str_contains($rEmpty['body'], 'Enter both'));

// ── AUTH-02 & AUTH-03 & AUTH-13: Wrong password, unknown email, SQLi -> generic Invalid credentials ──
$rWrong = req($baseUrl . '/admin/login.php', $jarAdmin, ['_csrf_token' => $csrfLogin, 'identifier' => 'admin', 'password' => 'WrongPass']);
$rUnknown = req($baseUrl . '/admin/login.php', $jarAdmin, ['_csrf_token' => $csrfLogin, 'identifier' => 'nobody@example.com', 'password' => 'WrongPass']);
$rSqli = req($baseUrl . '/admin/login.php', $jarAdmin, ['_csrf_token' => $csrfLogin, 'identifier' => "admin' OR '1'='1", 'password' => "x' OR '1'='1"]);
p3('AUTH-02', $rWrong['code'] === 401 && str_contains($rWrong['body'], 'Invalid credentials.'));
p3('AUTH-03', $rUnknown['code'] === 401 && str_contains($rUnknown['body'], 'Invalid credentials.'));
p3('AUTH-13', $rSqli['code'] === 401);

// ── AUTH-04: 5 failed attempts lockout ──
for ($i = 0; $i < 4; $i++) {
    $rLock = req($baseUrl . '/admin/login.php', $jarAdmin, ['_csrf_token' => $csrfLogin, 'identifier' => 'admin', 'password' => 'WrongPass']);
}
p3('AUTH-04', $rLock['code'] === 429 && str_contains(strtolower($rLock['body']), 'locked'));

// ── AUTH-05: Clear/expire lockout -> login works ──
$db->exec("UPDATE login_attempts SET locked_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
Admin::clearFailedLogins('127.0.0.1', 'admin');

// Capture session ID before login (AUTH-08)
$cookieContentBefore = file_exists($jarAdmin) ? file_get_contents($jarAdmin) : '';
preg_match('/PHPSESSID\s+(\S+)/', $cookieContentBefore, $smBefore);
$sidBefore = $smBefore[1] ?? '';

// ── AUTH-01 & AUTH-15: Login with seed password redirects to change-password ──
$rValid = req($baseUrl . '/admin/login.php', $jarAdmin, ['_csrf_token' => $csrfLogin, 'identifier' => 'admin', 'password' => 'Admin@1234']);
$cookieContentAfter = file_get_contents($jarAdmin);
preg_match('/PHPSESSID\s+(\S+)/', $cookieContentAfter, $smAfter);
$sidAfter = $smAfter[1] ?? '';

p3('AUTH-08', $sidBefore !== '' && $sidAfter !== '' && $sidBefore !== $sidAfter);
p3('AUTH-09', str_contains(strtolower($rValid['headers']), 'httponly') && str_contains(strtolower($rValid['headers']), 'samesite=lax'));
p3('AUTH-15', $rValid['code'] === 302 && str_contains($rValid['headers'], '/admin/change-password.php'));

// Change temporary password
$cpPage = req($baseUrl . '/admin/change-password.php', $jarAdmin);
$csrfAdmin = getCsrf($cpPage['body']);
$rCp = req($baseUrl . '/admin/change-password.php', $jarAdmin, [
    '_csrf_token'      => $csrfAdmin,
    'current_password' => 'Admin@1234',
    'new_password'     => 'NewAdmin@2026',
    'confirm_password' => 'NewAdmin@2026',
]);
p3('AUTH-01', $rCp['code'] === 200 && str_contains($rCp['body'], 'Password updated successfully'));

// ── AUTH-14: Verify password hashed in DB ──
$hashInDb = (string) $db->query("SELECT password_hash FROM admins WHERE username = 'admin'")->fetchColumn();
p3('AUTH-14', str_starts_with($hashInDb, '$2y$'));

// ── AUTH-16: Reviewer role blocked from super_admin pages (403) ──
Admin::create('Reviewer One', 'reviewer1', 'rev1@councilportal.local', 'Reviewer@123', 'reviewer', 0);
$revLoginPage = req($baseUrl . '/admin/login.php', $jarReviewer);
$revCsrf = getCsrf($revLoginPage['body']);
req($baseUrl . '/admin/login.php', $jarReviewer, ['_csrf_token' => $revCsrf, 'identifier' => 'reviewer1', 'password' => 'Reviewer@123']);
$revDash = req($baseUrl . '/admin/dashboard.php', $jarReviewer);
$revAudit = req($baseUrl . '/admin/audit.php', $jarReviewer);
$revAdmins = req($baseUrl . '/admin/admins.php', $jarReviewer);
p3('AUTH-16', $revDash['code'] === 200 && $revAudit['code'] === 403 && $revAdmins['code'] === 403);

// ── Seed a member with XSS + Hindi + formula name + document for MEM & ADM tests ──
$jpgPath = UPLOAD_PATH . '/2026';
if (!is_dir($jpgPath)) mkdir($jpgPath, 0750, true);
$sampleFile = $jpgPath . '/sample_test_photo.jpg';
file_put_contents($sampleFile, hex2bin('ffd8ffe000104a46494600010101004800480000ffd9'));

$db->exec("INSERT INTO members (reg_no, full_name, father_spouse_name, dob, gender, email, mobile, address, city, state, pincode, category_id, id_proof_type, id_proof_no, consent, status, ip_address)
    VALUES ('REG-2026-000101', 'राहुल <script>alert(1)</script>', 'Mohan', '1995-04-12', 'male', 'rahul101@example.com', '9876500101', '12 MG Road', 'Agra', 'UP', '282001', 1, 'PAN Card', 'ABCDE1234F', 1, 'pending', '127.0.0.1')");
$m1Id = (int) $db->lastInsertId();
$db->exec("INSERT INTO documents (member_id, doc_type, original_name, stored_path, mime_type, file_size)
    VALUES ({$m1Id}, 'photo', 'photo.jpg', '2026/sample_test_photo.jpg', 'image/jpeg', 22)");
$doc1Id = (int) $db->lastInsertId();

$db->exec("INSERT INTO members (reg_no, full_name, father_spouse_name, dob, gender, email, mobile, address, city, state, pincode, category_id, id_proof_type, id_proof_no, consent, status, ip_address)
    VALUES ('REG-2026-000102', '=HYPERLINK(\"http://evil\",\"Click\")', 'Sohan', '1990-01-01', 'female', 'csvtest@example.com', '9876500102', '99 Civil Lines', 'Jaipur', 'RJ', '302001', 2, 'Voter ID', 'VOT123456', 1, 'approved', '127.0.0.1')");

// ── ADM-01, ADM-02 ──
$dash = req($baseUrl . '/admin/dashboard.php', $jarAdmin);
p3('ADM-01', str_contains($dash['body'], 'id="statTotal">2</div>') && str_contains($dash['body'], 'id="statPending">1</div>') && str_contains($dash['body'], 'id="statApproved">1</div>'));
p3('ADM-02', str_contains($dash['body'], 'REG-2026-000102'));

// ── ADM-05, ADM-06, ADM-07, ADM-08, ADM-10, ADM-12 ──
$listAll = req($baseUrl . '/admin/members.php', $jarAdmin);
p3('ADM-05', str_contains(req($baseUrl . '/admin/members.php?q=REG-2026-000101', $jarAdmin)['body'], 'rahul101@example.com'));
$rPct = req($baseUrl . '/admin/members.php?q=' . urlencode('%'), $jarAdmin);
p3('ADM-06', $rPct['code'] === 200 && str_contains($rPct['body'], 'No members found'));
$rStatus = req($baseUrl . '/admin/members.php?status=pending', $jarAdmin);
p3('ADM-07', str_contains($rStatus['body'], 'REG-2026-000101') && !str_contains($rStatus['body'], 'REG-2026-000102'));
$rCat = req($baseUrl . '/admin/members.php?category_id=2', $jarAdmin);
p3('ADM-08', str_contains($rCat['body'], 'REG-2026-000102') && !str_contains($rCat['body'], 'REG-2026-000101'));
$rComb = req($baseUrl . '/admin/members.php?q=csvtest&status=approved&category_id=2', $jarAdmin);
p3('ADM-10', str_contains($rComb['body'], 'REG-2026-000102'));
p3('ADM-12', str_contains(req($baseUrl . '/admin/members.php?q=zzzznotfound', $jarAdmin)['body'], 'No members found'));
p3('ADM-11', req($baseUrl . '/admin/members.php?sort=INVALID_COL;DROP+TABLE&dir=bad', $jarAdmin)['code'] === 200);

// ── MEM-01..MEM-15 ──
$detail = req($baseUrl . '/admin/member.php?id=' . $m1Id, $jarAdmin);
p3('MEM-01', $detail['code'] === 200 && str_contains($detail['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($detail['body'], '<script>alert(1)</script>'));
p3('MEM-02', req($baseUrl . '/admin/member.php?id=999999', $jarAdmin)['code'] === 404);
$fileOk = req($baseUrl . '/admin/file.php?id=' . $doc1Id . '&mode=inline', $jarAdmin);
p3('MEM-03', $fileOk['code'] === 200 && str_contains($fileOk['headers'], 'image/jpeg'));
p3('MEM-05', req($baseUrl . '/admin/file.php?id=999999', $jarAdmin)['code'] === 404);

$csrfDetail = getCsrf($detail['body']);
// MEM-10 CSRF missing
p3('MEM-10', req($baseUrl . '/admin/member.php?id=' . $m1Id, $jarAdmin, ['action' => 'approve'])['code'] === 403);
// MEM-07 Reject without reason blocked
$rRejNoReason = req($baseUrl . '/admin/member.php?id=' . $m1Id, $jarAdmin, ['_csrf_token' => $csrfDetail, 'action' => 'reject', 'decision_reason' => '']);
p3('MEM-07', $rRejNoReason['code'] === 422 && str_contains($rRejNoReason['body'], 'reason is required'));
// MEM-08 Reject with reason
$rRejOk = req($baseUrl . '/admin/member.php?id=' . $m1Id, $jarAdmin, ['_csrf_token' => $csrfDetail, 'action' => 'reject', 'decision_reason' => 'Incomplete ID scan']);
p3('MEM-08', $rRejOk['code'] === 200 && str_contains($rRejOk['body'], 'Incomplete ID scan'));
// MEM-06 Approve
$rAppOk = req($baseUrl . '/admin/member.php?id=' . $m1Id, $jarAdmin, ['_csrf_token' => $csrfDetail, 'action' => 'approve', 'decision_reason' => 'Verified']);
p3('MEM-06', $rAppOk['code'] === 200 && str_contains($rAppOk['body'], 'Approved'));

// MEM-14, MEM-15, MEM-16 CSV export
$csv = req($baseUrl . '/admin/export.php', $jarAdmin);
p3('MEM-14', $csv['code'] === 200 && str_contains($csv['body'], 'REG-2026-000101'));
p3('MEM-15', str_contains($csv['body'], "\"'=HYPERLINK"));
p3('MEM-16', str_starts_with($csv['body'], "\xEF\xBB\xBF") && str_contains($csv['body'], 'राहुल'));

// MEM-18 Audit log view
$auditPage = req($baseUrl . '/admin/audit.php', $jarAdmin);
p3('MEM-18', $auditPage['code'] === 200 && str_contains($auditPage['body'], 'member.approved') && str_contains($auditPage['body'], 'member.rejected'));

// MEM-20 Cannot deactivate last super admin
$adminsPage = req($baseUrl . '/admin/admins.php', $jarAdmin);
$rLastSa = req($baseUrl . '/admin/admins.php', $jarAdmin, [
    '_csrf_token' => getCsrf($adminsPage['body']),
    'action'      => 'update',
    'id'          => '1',
    'role'        => 'reviewer',
    'is_active'   => '0',
]);
p3('MEM-20', $rLastSa['code'] === 422 && str_contains($rLastSa['body'], 'last active Super Admin'));

// AUTH-10 Logout destroys session
$logoutRes = req($baseUrl . '/admin/logout.php', $jarAdmin, ['_csrf_token' => $csrfDetail]);
$afterLogout = req($baseUrl . '/admin/dashboard.php', $jarAdmin);
p3('AUTH-10', $logoutRes['code'] === 302 && $afterLogout['code'] === 302 && str_contains($afterLogout['headers'], '/admin/login.php'));

// Reset seed admin password back to Admin@1234 with must_change_password=1 for clean seeded state
$seedHash = '$2y$12$jLnYmlV.2q8Di8BaU0UFEurBIPRwst2XXw9EsJrriHs2WsnMVuBBm';
$db->prepare("UPDATE admins SET password_hash = :h, must_change_password = 1 WHERE username = 'admin'")->execute([':h' => $seedHash]);
$db->exec("DELETE FROM admins WHERE username = 'reviewer1'");
$db->exec('DELETE FROM members');
$db->exec('DELETE FROM audit_log');
$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limits');
@unlink($sampleFile);
foreach (glob($tmpDir . '/*') ?: [] as $f) @unlink($f);
@rmdir($tmpDir);

exit($failures > 0 ? 1 : 0);

