<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Csrf;
use App\Services\Database;
use App\Services\RateLimiter;
use App\Services\Validator;

$failures = 0;
function check(string $id, bool $ok, string $detail = ''): void {
    global $failures;
    echo sprintf("[%s] %s %s\n", $ok ? 'PASS' : 'FAIL', $id, $detail);
    if (!$ok) {
        $failures++;
    }
}

// SVC-01: Validator valid sample data
$validSample = [
    'full_name'          => 'Rahul Sharma',
    'father_spouse_name' => 'Mohan Sharma',
    'dob'                => '1995-04-12',
    'gender'             => 'male',
    'email'              => 'rahul@example.com',
    'mobile'             => '9876543210',
    'address'            => '12 MG Road, Civil Lines',
    'city'               => 'Agra',
    'state'              => 'Uttar Pradesh',
    'pincode'            => '282001',
    'category_id'        => '1',
    'id_proof_type'      => 'PAN Card',
    'id_proof_no'        => 'ABCDE1234F',
    'consent'            => '1',
];
$res = Validator::registration($validSample);
check('SVC-01', $res['errors'] === [], json_encode($res['errors']));

// SVC-02: Omit each required field
$reqFields = [
    'full_name', 'father_spouse_name', 'dob', 'gender', 'email',
    'mobile', 'address', 'city', 'state', 'pincode', 'category_id',
    'id_proof_no', 'consent',
];
$allMissingCaught = true;
foreach ($reqFields as $f) {
    $copy = $validSample;
    unset($copy[$f]);
    $r = Validator::registration($copy);
    if (!isset($r['errors'][$f])) {
        $allMissingCaught = false;
        echo "  Missing field not caught: {$f}\n";
    }
}
check('SVC-02', $allMissingCaught);

// SVC-03: CSRF stable in session
$_SESSION = [];
$t1 = Csrf::token('reg');
$t2 = Csrf::token('reg');
check('SVC-03', $t1 !== '' && $t1 === $t2);

// SVC-04: CSRF verify rejects wrong/missing
check('SVC-04', !Csrf::validate('reg', null) && !Csrf::validate('reg', 'wrong') && Csrf::validate('reg', $t1));

// SVC-05: Output escaping
$escaped = e('<script>alert(1)</script>');
check('SVC-05', $escaped === '&lt;script&gt;alert(1)&lt;/script&gt;', $escaped);

// SVC-06: RateLimiter
$testKey = '127.0.0.99-' . bin2hex(random_bytes(4));
$ok1 = RateLimiter::check('test_svc', $testKey, 2, 60);
$ok2 = RateLimiter::check('test_svc', $testKey, 2, 60);
$ok3 = RateLimiter::check('test_svc', $testKey, 2, 60);
RateLimiter::reset('test_svc', $testKey);
check('SVC-06', $ok1 === true && $ok2 === true && $ok3 === false);

// SVC-07: strict_types in every PHP file
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__)));
$missingStrict = [];
foreach ($it as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $p = $file->getPathname();
        if (str_contains($p, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $src = file_get_contents($p);
        if (!str_contains($src, 'declare(strict_types=1);')) {
            $missingStrict[] = $p;
        }
    }
}
check('SVC-07', $missingStrict === [], implode(', ', $missingStrict));

// DB-03..DB-11 checks via PDO
$db = Database::connection();
$createSql = $db->query('SHOW CREATE TABLE members')->fetchColumn(1);
check('DB-03', str_contains($createSql, 'utf8mb4') && str_contains($createSql, 'InnoDB'));

// Insert test member + Hindi unicode (DB-09, DB-11) + Duplicate checks (DB-04..06) + FK (DB-07) + Cascade (DB-08)
$db->beginTransaction();
try {
    $db->prepare(
        "INSERT INTO members (reg_no, full_name, father_spouse_name, dob, gender, email, mobile, address, city, state, pincode, category_id, id_proof_type, id_proof_no, consent, ip_address)
         VALUES ('REG-2026-999991', 'राहुल शर्मा', 'मोहन शर्मा', '1995-04-12', 'male', 'dbtest1@example.com', '9999911111', '12 MG Road', 'Agra', 'UP', '282001', 1, 'PAN Card', 'ABCDE1234F', 1, '127.0.0.1')"
    )->execute();
    $mId = (int) $db->lastInsertId();
    $row = $db->query("SELECT full_name, status FROM members WHERE id = {$mId}")->fetch();
    check('DB-09', $row['status'] === 'pending');
    check('DB-11', $row['full_name'] === 'राहुल शर्मा');

    // DB-04 duplicate email
    $dupEmail = false;
    try {
        $db->prepare(
            "INSERT INTO members (reg_no, full_name, father_spouse_name, dob, gender, email, mobile, address, city, state, pincode, category_id, id_proof_type, id_proof_no, consent, ip_address)
             VALUES ('REG-2026-999992', 'Test', 'Test', '1995-04-12', 'male', 'dbtest1@example.com', '9999922222', '12 MG Road', 'Agra', 'UP', '282001', 1, 'PAN Card', 'ABCDE1234F', 1, '127.0.0.1')"
        )->execute();
    } catch (\PDOException) {
        $dupEmail = true;
    }
    check('DB-04', $dupEmail);

    // DB-05 duplicate mobile
    $dupMobile = false;
    try {
        $db->prepare(
            "INSERT INTO members (reg_no, full_name, father_spouse_name, dob, gender, email, mobile, address, city, state, pincode, category_id, id_proof_type, id_proof_no, consent, ip_address)
             VALUES ('REG-2026-999993', 'Test', 'Test', '1995-04-12', 'male', 'dbtest2@example.com', '9999911111', '12 MG Road', 'Agra', 'UP', '282001', 1, 'PAN Card', 'ABCDE1234F', 1, '127.0.0.1')"
        )->execute();
    } catch (\PDOException) {
        $dupMobile = true;
    }
    check('DB-05', $dupMobile);

    // DB-06 duplicate reg_no
    $dupReg = false;
    try {
        $db->prepare(
            "INSERT INTO members (reg_no, full_name, father_spouse_name, dob, gender, email, mobile, address, city, state, pincode, category_id, id_proof_type, id_proof_no, consent, ip_address)
             VALUES ('REG-2026-999991', 'Test', 'Test', '1995-04-12', 'male', 'dbtest3@example.com', '9999933333', '12 MG Road', 'Agra', 'UP', '282001', 1, 'PAN Card', 'ABCDE1234F', 1, '127.0.0.1')"
        )->execute();
    } catch (\PDOException) {
        $dupReg = true;
    }
    check('DB-06', $dupReg);

    // DB-07 invalid FK
    $badFk = false;
    try {
        $db->prepare(
            "INSERT INTO members (reg_no, full_name, father_spouse_name, dob, gender, email, mobile, address, city, state, pincode, category_id, id_proof_type, id_proof_no, consent, ip_address)
             VALUES ('REG-2026-999994', 'Test', 'Test', '1995-04-12', 'male', 'dbtest4@example.com', '9999944444', '12 MG Road', 'Agra', 'UP', '282001', 9999, 'PAN Card', 'ABCDE1234F', 1, '127.0.0.1')"
        )->execute();
    } catch (\PDOException) {
        $badFk = true;
    }
    check('DB-07', $badFk);

    // DB-08 cascade delete on documents
    $db->prepare(
        "INSERT INTO documents (member_id, doc_type, original_name, stored_path, mime_type, file_size)
         VALUES (:mid, 'photo', 'p.jpg', '2026/test.jpg', 'image/jpeg', 1024)"
    )->execute([':mid' => $mId]);
    $db->prepare('DELETE FROM members WHERE id = :mid')->execute([':mid' => $mId]);
    $docCount = (int) $db->query("SELECT COUNT(*) FROM documents WHERE member_id = {$mId}")->fetchColumn();
    check('DB-08', $docCount === 0);

    $db->rollBack();
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo 'DB Test Exception: ' . $e->getMessage() . "\n";
    $failures++;
}

// DB-10 indexes
$indexes = $db->query('SHOW INDEX FROM members')->fetchAll();
$colNames = array_column($indexes, 'Column_name');
check('DB-10', in_array('status', $colNames, true) && in_array('created_at', $colNames, true));

exit($failures > 0 ? 1 : 0);

