<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Database;
use App\Services\Mailer;
use App\Services\Validator;

$baseUrl = 'http://127.0.0.1:8011';
$failures = 0;

function t(string $id, bool $ok, string $info = ''): void {
    global $failures;
    echo sprintf("[%s] %s %s\n", $ok ? 'PASS' : 'FAIL', $id, $info);
    if (!$ok) {
        $failures++;
    }
}

// Create temp valid & malicious test files
$tmpDir = sys_get_temp_dir() . '/cp_test_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0777, true);

// Minimal valid 1x1 JPEG bytes
$jpgPath = $tmpDir . '/photo.jpg';
file_put_contents($jpgPath, hex2bin(
    'ffd8ffe000104a46494600010101004800480000ffdb004300030202030202030303030403030405' .
    '0805050404050a070706080c0a0c0c0b0a0b0b0d0e12100d0e110e0b0b1016101113141515150c0f' .
    '171816141812141514ffc0000b080001000101011100ffc400140001000000000000000000000000' .
    '00000009ffc40014100100000000000000000000000000000000ffda0008010100003f00d2cf20ffd9'
));

// Minimal valid PNG
$pngPath = $tmpDir . '/photo.png';
file_put_contents($pngPath, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
));

// Minimal valid PDF
$pdfPath = $tmpDir . '/idproof.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

// Oversized 2.1MB JPG header + padding
$bigJpgPath = $tmpDir . '/big.jpg';
file_put_contents($bigJpgPath, file_get_contents($jpgPath) . str_repeat('A', 2202010));

// Exact 2.0MB JPG header + padding (2 * 1024 * 1024 = 2097152 bytes)
$exact2MbPath = $tmpDir . '/exact2mb.jpg';
$hdr = file_get_contents($jpgPath);
file_put_contents($exact2MbPath, $hdr . str_repeat('A', 2097152 - strlen($hdr)));

// Disguised PHP as JPG
$fakePhpJpg = $tmpDir . '/shell.jpg';
file_put_contents($fakePhpJpg, "<?php system(\$_GET['c']); ?>");

// Double extension
$doubleExt = $tmpDir . '/shell.php.jpg';
file_put_contents($doubleExt, file_get_contents($jpgPath));

// Zero byte
$zeroPath = $tmpDir . '/empty.jpg';
file_put_contents($zeroPath, '');

// Exe file
$exePath = $tmpDir . '/bad.exe';
file_put_contents($exePath, "MZ\x90\x00");

function fetchFormAndSession(string $baseUrl, string $cookieJar): array {
    $ch = curl_init($baseUrl . '/index.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
    ]);
    $html = (string) curl_exec($ch);
    curl_close($ch);

    preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $cm);
    preg_match('/What is (\d+) \+ (\d+)\?/', $html, $qm);
    $csrf = $cm[1] ?? '';
    $ans  = isset($qm[1], $qm[2]) ? (string) ((int) $qm[1] + (int) $qm[2]) : '';
    return ['csrf' => $csrf, 'captcha' => $ans, 'html' => $html];
}

function postRegister(string $baseUrl, string $cookieJar, array $fields, array $files = []): array {
    $ch = curl_init($baseUrl . '/api/register.php');
    $post = $fields;
    foreach ($files as $k => $fInfo) {
        if (is_array($fInfo)) {
            $post[$k] = new CURLFile($fInfo['path'], $fInfo['mime'], $fInfo['name']);
        } else {
            $post[$k] = new CURLFile($fInfo);
        }
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_HEADER         => true,
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr($raw, 0, $hdrSize);
    $body    = substr($raw, $hdrSize);
    return ['code' => $code, 'headers' => $headers, 'body' => $body, 'json' => json_decode($body, true)];
}

// Clear rate limits & test rows before test
$db = Database::connection();
$db->exec('DELETE FROM rate_limits');

// ── Unit validation tests (VAL-01..VAL-30) ──
$baseData = [
    'full_name' => 'Rahul Sharma', 'father_spouse_name' => 'Mohan Sharma',
    'dob' => '1995-04-12', 'gender' => 'male', 'email' => 'Rahul@Example.COM',
    'mobile' => '9876543210', 'address' => '12 MG Road', 'city' => 'Agra',
    'state' => 'Uttar Pradesh', 'pincode' => '282001', 'category_id' => '1',
    'id_proof_type' => 'PAN Card', 'id_proof_no' => 'ABCDE1234F', 'consent' => '1',
];

$r = Validator::registration(array_merge($baseData, ['full_name' => '']));
t('VAL-01', isset($r['errors']['full_name']));
t('VAL-02', isset(Validator::registration(array_merge($baseData, ['full_name' => 'A']))['errors']['full_name']));
t('VAL-03', isset(Validator::registration(array_merge($baseData, ['full_name' => str_repeat('a', 121)]))['errors']['full_name']));
t('VAL-04', isset(Validator::registration(array_merge($baseData, ['full_name' => 'Rahul123']))['errors']['full_name'])
         && isset(Validator::registration(array_merge($baseData, ['full_name' => 'Rahul@#']))['errors']['full_name']));
t('VAL-05', !isset(Validator::registration(array_merge($baseData, ['full_name' => 'Anne-Marie O.']))['errors']['full_name'])
         && !isset(Validator::registration(array_merge($baseData, ['full_name' => 'राहुल शर्मा']))['errors']['full_name']));
$r06 = Validator::registration(array_merge($baseData, ['full_name' => '  Rahul Sharma  ']));
t('VAL-06', $r06['errors'] === [] && $r06['clean']['full_name'] === 'Rahul Sharma');
t('VAL-07', isset(Validator::registration(array_merge($baseData, ['email' => 'abc']))['errors']['email'])
         && isset(Validator::registration(array_merge($baseData, ['email' => 'a@b']))['errors']['email'])
         && isset(Validator::registration(array_merge($baseData, ['email' => 'a b@c.com']))['errors']['email']));
t('VAL-08', isset(Validator::registration(array_merge($baseData, ['email' => str_repeat('a', 142) . '@test.com']))['errors']['email']));
$r09 = Validator::registration($baseData);
t('VAL-09', $r09['clean']['email'] === 'rahul@example.com');
t('VAL-10', isset(Validator::registration(array_merge($baseData, ['mobile' => '987654321']))['errors']['mobile'])
         && isset(Validator::registration(array_merge($baseData, ['mobile' => '98765432101']))['errors']['mobile'])
         && isset(Validator::registration(array_merge($baseData, ['mobile' => '98765abcde']))['errors']['mobile']));
t('VAL-11', isset(Validator::registration(array_merge($baseData, ['mobile' => '5123456789']))['errors']['mobile']));
$r12 = Validator::registration(array_merge($baseData, ['mobile' => '+91 98765 43210']));
t('VAL-12', $r12['errors'] === [] && $r12['clean']['mobile'] === '9876543210');
t('VAL-13', isset(Validator::registration(array_merge($baseData, ['dob' => '2000-02-31']))['errors']['dob'])
         && isset(Validator::registration(array_merge($baseData, ['dob' => '2099-01-01']))['errors']['dob']));
$today18 = (new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');
$under18 = (new DateTimeImmutable('today'))->modify('-18 years +1 day')->format('Y-m-d');
$over100 = (new DateTimeImmutable('today'))->modify('-101 years')->format('Y-m-d');
t('VAL-14', !isset(Validator::registration(array_merge($baseData, ['dob' => $today18]))['errors']['dob']));
t('VAL-15', isset(Validator::registration(array_merge($baseData, ['dob' => $under18]))['errors']['dob']));
t('VAL-16', isset(Validator::registration(array_merge($baseData, ['dob' => $over100]))['errors']['dob']));
t('VAL-17', isset(Validator::registration(array_merge($baseData, ['gender' => 'invalid']))['errors']['gender']));
t('VAL-18', isset(Validator::registration(array_merge($baseData, ['pincode' => '12345']))['errors']['pincode'])
         && isset(Validator::registration(array_merge($baseData, ['pincode' => '1234567']))['errors']['pincode'])
         && isset(Validator::registration(array_merge($baseData, ['pincode' => '28200a']))['errors']['pincode']));
t('VAL-19', !isset(Validator::registration(array_merge($baseData, ['pincode' => '282001']))['errors']['pincode']));
t('VAL-20', isset(Validator::registration(array_merge($baseData, ['address' => '   ']))['errors']['address']));
t('VAL-21', isset(Validator::registration(array_merge($baseData, ['address' => str_repeat('X', 1001)]))['errors']['address']));
t('VAL-22', isset(Validator::registration(array_merge($baseData, ['category_id' => '99999']))['errors']['category_id']));
t('VAL-23', isset(Validator::registration(array_merge($baseData, ['category_id' => '9']))['errors']['category_id'])); // seeded inactive
$noConsent = $baseData;
unset($noConsent['consent']);
t('VAL-24', isset(Validator::registration($noConsent)['errors']['consent']));
t('VAL-25', isset(Validator::registration(array_merge($baseData, ['city' => '', 'state' => '']))['errors']['city']));
t('VAL-26', isset(Validator::registration(array_merge($baseData, ['id_proof_no' => 'AB']))['errors']['id_proof_no']));
t('VAL-29', Validator::registration(array_merge($baseData, ['address' => "12 MG\0 Road 🚀"]))['errors'] === []);
t('VAL-30', count(Validator::registration([])['errors']) >= 10);

// ── HTTP API Tests ──
$cookieJar = $tmpDir . '/cookies.txt';
$sess = fetchFormAndSession($baseUrl, $cookieJar);

// API-03 wrong method
$chGet = curl_init($baseUrl . '/api/register.php');
curl_setopt($chGet, CURLOPT_RETURNTRANSFER, true);
curl_exec($chGet);
$getCode = (int) curl_getinfo($chGet, CURLINFO_HTTP_CODE);
curl_close($chGet);
t('API-03', $getCode === 405, "got {$getCode}");

// API-04 missing CSRF
$rNoCsrf = postRegister($baseUrl, $cookieJar, $baseData, []);
t('API-04', $rNoCsrf['code'] === 403, "got {$rNoCsrf['code']}");

// SEC-13 invalid captcha
$rBadCap = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'],
    'captcha_answer' => '999',
]), ['photo' => $jpgPath, 'id_proof' => $pdfPath]);
t('SEC-13', $rBadCap['code'] === 422 && isset($rBadCap['json']['errors']['captcha_answer']));

// SEC-14 honeypot filled -> 200 silent without saving
$cntBefore = (int) $db->query('SELECT COUNT(*) FROM members')->fetchColumn();
$rHoney = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'],
    'captcha_answer' => $sess['captcha'],
    'website_url' => 'http://spam.example',
]), ['photo' => $jpgPath, 'id_proof' => $pdfPath]);
$cntAfter = (int) $db->query('SELECT COUNT(*) FROM members')->fetchColumn();
t('SEC-14', $rHoney['code'] === 200 && $cntBefore === $cntAfter);

// UP-03..UP-10 upload rejection tests (reset rate limit so we don't hit 429)
$db->exec('DELETE FROM rate_limits');
$upFilesBefore = count(glob(UPLOAD_PATH . '/*/*') ?: []);
$rBig = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'], 'captcha_answer' => $sess['captcha'],
]), ['photo' => $bigJpgPath, 'id_proof' => $pdfPath]);
t('UP-03', $rBig['code'] === 422 && isset($rBig['json']['errors']['photo']));

$rExe = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'], 'captcha_answer' => $sess['captcha'],
]), ['photo' => $exePath, 'id_proof' => $pdfPath]);
t('UP-05', $rExe['code'] === 422 && isset($rExe['json']['errors']['photo']));

$rFakePhp = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'], 'captcha_answer' => $sess['captcha'],
]), ['photo' => $fakePhpJpg, 'id_proof' => $pdfPath]);
t('UP-06', $rFakePhp['code'] === 422 && isset($rFakePhp['json']['errors']['photo']));

$rDbl = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'], 'captcha_answer' => $sess['captcha'],
]), ['photo' => $doubleExt, 'id_proof' => $pdfPath]);
t('UP-07', $rDbl['code'] === 422 && isset($rDbl['json']['errors']['photo']));

$rFakeMime = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'], 'captcha_answer' => $sess['captcha'],
]), ['photo' => ['path' => $fakePhpJpg, 'mime' => 'image/jpeg', 'name' => 'pic.jpg'], 'id_proof' => $pdfPath]);
t('UP-08', $rFakeMime['code'] === 422 && isset($rFakeMime['json']['errors']['photo']));

$rZero = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'], 'captcha_answer' => $sess['captcha'],
]), ['photo' => $zeroPath, 'id_proof' => $pdfPath]);
t('SEC-12', $rZero['code'] === 429, "6th attempt rate limited: {$rZero['code']}");

$db->exec('DELETE FROM rate_limits');
$rZero2 = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'], 'captcha_answer' => $sess['captcha'],
]), ['photo' => $zeroPath, 'id_proof' => $pdfPath]);
t('UP-09', $rZero2['code'] === 422 && isset($rZero2['json']['errors']['photo']), $rZero2['body']);

$rNoFile = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'], 'captcha_answer' => $sess['captcha'],
]), []);
t('UP-10', $rNoFile['code'] === 422 && isset($rNoFile['json']['errors']['photo']) && isset($rNoFile['json']['errors']['id_proof']), $rNoFile['body']);

$upFilesAfterFails = count(glob(UPLOAD_PATH . '/*/*') ?: []);
t('API-05', $upFilesBefore === $upFilesAfterFails, "before={$upFilesBefore} after={$upFilesAfterFails}");

// API-01 Happy Path + API-12 JSON header + API-13 mass assignment + UP-01/UP-02/UP-12/UP-15
$db->exec('DELETE FROM rate_limits');
$rOk1 = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    '_csrf_token' => $sess['csrf'],
    'captcha_answer' => $sess['captcha'],
    'status' => 'approved', // mass assignment attempt
]), [
    'photo'    => ['path' => $jpgPath, 'mime' => 'image/jpeg', 'name' => '../../photo.jpg'],
    'id_proof' => ['path' => $pdfPath, 'mime' => 'application/pdf', 'name' => 'idproof.pdf'],
]);
$reg1 = $rOk1['json']['data']['reg_no'] ?? '';
t('API-01', $rOk1['code'] === 200 && ($rOk1['json']['ok'] ?? false) === true && preg_match('/^REG-\d{4}-\d{6}$/', $reg1) === 1, $reg1);
t('API-12', str_contains(strtolower($rOk1['headers']), 'application/json') && !str_contains($rOk1['body'], 'Warning'));
$row1 = $db->prepare('SELECT id, status FROM members WHERE reg_no = :r');
$row1->execute([':r' => $reg1]);
$m1 = $row1->fetch();
t('API-13', $m1 && $m1['status'] === 'pending');

$docRows = $db->query('SELECT stored_path FROM documents WHERE member_id = ' . (int) $m1['id'])->fetchAll();
$filesOnDisk = count($docRows) === 2 && file_exists(UPLOAD_PATH . '/' . $docRows[0]['stored_path']) && file_exists(UPLOAD_PATH . '/' . $docRows[1]['stored_path']);
t('UP-12', $filesOnDisk);
t('UP-11', !str_contains($docRows[0]['stored_path'], '..'));

// API-02 + UP-04 exact 2.0MB + PNG
$sess2 = fetchFormAndSession($baseUrl, $cookieJar);
$rOk2 = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    'email' => 'anita@example.com', 'mobile' => '9876543211',
    '_csrf_token' => $sess2['csrf'], 'captcha_answer' => $sess2['captcha'],
]), [
    'photo'    => ['path' => $exact2MbPath, 'mime' => 'image/jpeg', 'name' => 'photo.jpg'],
    'id_proof' => ['path' => $pngPath, 'mime' => 'image/png', 'name' => 'id.png'],
]);
$reg2 = $rOk2['json']['data']['reg_no'] ?? '';
t('UP-04', $rOk2['code'] === 200 && $reg2 !== '', $reg2);
t('API-02', $reg1 !== '' && $reg2 !== '' && $reg2 > $reg1, "{$reg1} < {$reg2}");

// API-06 Duplicate email (case-insensitive)
$sess3 = fetchFormAndSession($baseUrl, $cookieJar);
$rDupEmail = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    'email' => 'RAHUL@EXAMPLE.COM', 'mobile' => '9876543219',
    '_csrf_token' => $sess3['csrf'], 'captcha_answer' => $sess3['captcha'],
]), ['photo' => $jpgPath, 'id_proof' => $pdfPath]);
t('API-06', $rDupEmail['code'] === 409 && isset($rDupEmail['json']['errors']['email']));

// API-07 Duplicate mobile
$rDupMob = postRegister($baseUrl, $cookieJar, array_merge($baseData, [
    'email' => 'unique@example.com', 'mobile' => '9876543210',
    '_csrf_token' => $sess3['csrf'], 'captcha_answer' => $sess3['captcha'],
]), ['photo' => $jpgPath, 'id_proof' => $pdfPath]);
t('API-07', $rDupMob['code'] === 409 && isset($rDupMob['json']['errors']['mobile']));

// API-10 check-unique.php
$cu1 = json_decode((string) file_get_contents($baseUrl . '/api/check-unique.php?email=rahul@example.com'), true);
$cu2 = json_decode((string) file_get_contents($baseUrl . '/api/check-unique.php?email=brandnew@example.com'), true);
t('API-10', ($cu1['data']['available'] ?? true) === false && ($cu2['data']['available'] ?? false) === true && !isset($cu1['data']['full_name']));

// SUC-01, SUC-02, SUC-03
$suc1 = (string) file_get_contents($baseUrl . '/success.php?ref=' . urlencode($reg1));
t('SUC-01', str_contains($suc1, $reg1));
t('SUC-03', str_contains($suc1, $reg1) && !str_contains($suc1, 'rahul@example.com') && !str_contains($suc1, '9876543210'));

$chSuc2 = curl_init($baseUrl . '/success.php?ref=INVALID');
curl_setopt_array($chSuc2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false]);
curl_exec($chSuc2);
$suc2Code = (int) curl_getinfo($chSuc2, CURLINFO_HTTP_CODE);
curl_close($chSuc2);
t('SUC-02', $suc2Code === 302);

// SUC-05 Email header injection sanitization
$sanitized = Mailer::sanitizeHeader("Rahul\r\nBcc: evil@attacker.com");
t('SUC-05', !str_contains($sanitized, "\r") && !str_contains($sanitized, "\n"));

// API-15 Mail log check
$mailLog = file_exists(LOG_PATH . '/mail.log') ? (string) file_get_contents(LOG_PATH . '/mail.log') : '';
t('API-15', str_contains($mailLog, $reg1));

// Cleanup test rows & files
$db->exec('DELETE FROM members');
$db->exec('DELETE FROM rate_limits');
foreach (glob(UPLOAD_PATH . '/*/*') ?: [] as $f) {
    @unlink($f);
}
foreach (glob($tmpDir . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmpDir);

exit($failures > 0 ? 1 : 0);
