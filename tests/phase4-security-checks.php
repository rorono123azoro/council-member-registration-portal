<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Database;

$baseUrl  = 'http://127.0.0.1:8013';
$failures = 0;

function p4(string $id, bool $ok, string $info = ''): void {
    global $failures;
    echo sprintf("[%s] %s %s\n", $ok ? 'PASS' : 'FAIL', $id, $info);
    if (!$ok) {
        $failures++;
    }
}

function httpGet(string $url, string $cookieJar = ''): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($cookieJar !== '') {
        $opts[CURLOPT_COOKIEJAR]  = $cookieJar;
        $opts[CURLOPT_COOKIEFILE] = $cookieJar;
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

// SEC-01: Static check that no PHP file concatenates SQL variables directly into query()/exec()
$appFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/app'));
$unsafeSql = [];
foreach ($appFiles as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $src = (string) file_get_contents($f->getPathname());
        if (preg_match('/->(query|exec)\s*\(\s*["\'].*\$/', $src)) {
            $unsafeSql[] = $f->getFilename();
        }
    }
}
p4('SEC-01', $unsafeSql === [], implode(', ', $unsafeSql));

// SEC-03: Reflected XSS in query params
$rReflected = httpGet($baseUrl . '/success.php?ref=' . urlencode('<script>alert(1)</script>'));
p4('SEC-03', $rReflected['code'] === 302 && !str_contains($rReflected['body'], '<script>alert(1)</script>'));

// SEC-05 & SEC-06: Security headers & Clickjacking protection
$rHome = httpGet($baseUrl . '/index.php');
$hdrLower = strtolower($rHome['headers']);
p4('SEC-05',
    str_contains($hdrLower, 'content-security-policy:')
    && str_contains($hdrLower, 'x-content-type-options: nosniff')
    && str_contains($hdrLower, 'x-frame-options: deny')
    && str_contains($hdrLower, 'referrer-policy:')
);
p4('SEC-06', str_contains($hdrLower, "frame-ancestors 'none'"));

// SEC-07: Directory listing blocked
$dirBlocked = true;
foreach (['/assets/', '/api/', '/admin/'] as $d) {
    $rd = httpGet($baseUrl . $d);
    if ($rd['code'] !== 403 && $rd['code'] !== 404) {
        $dirBlocked = false;
    }
}
p4('SEC-07', $dirBlocked);

// SEC-08: Sensitive files blocked
$sensBlocked = true;
foreach (['/.env', '/.env.example', '/composer.json', '/database/schema.sql', '/storage/logs/app.log'] as $s) {
    $rs = httpGet($baseUrl . $s);
    if ($rs['code'] !== 403 && $rs['code'] !== 404) {
        $sensBlocked = false;
    }
}
p4('SEC-08', $sensBlocked);

// SEC-10: Open redirect prevention check on member.php return param
$rOpenRedir = httpGet($baseUrl . '/admin/member.php?id=1&return=https://evil.example');
p4('SEC-10', $rOpenRedir['code'] === 302 && !str_contains($rOpenRedir['headers'], 'evil.example'));

// SEC-16: Secrets scan in tracked files (.env.example has no real passwords/keys)
$envEx = (string) file_get_contents(BASE_PATH . '/.env.example');
p4('SEC-16', !str_contains($envEx, 'Admin@1234') && !str_contains($envEx, 'sk_live'));

// SEC-20: Personal data & passwords not in logs
$logFiles = glob(LOG_PATH . '/*.log') ?: [];
$leakFound = false;
foreach ($logFiles as $lf) {
    $content = (string) file_get_contents($lf);
    if (str_contains($content, 'Admin@1234') || str_contains($content, 'NewAdmin@2026')) {
        $leakFound = true;
    }
}
p4('SEC-20', !$leakFound);

// DEP-02: display_errors is 0 in production/non-debug mode
p4('DEP-02', ini_get('display_errors') === '0');

// PERF-01: Page weight of index.php HTML + custom.css + js < 500 KB
$totalWeight = strlen($rHome['body'])
    + filesize(BASE_PATH . '/public/assets/css/custom.css')
    + filesize(BASE_PATH . '/public/assets/js/register.js')
    + filesize(BASE_PATH . '/public/assets/js/validators.js');
p4('PERF-01', $totalWeight < 500 * 1024, sprintf('%.1f KB', $totalWeight / 1024));

exit($failures > 0 ? 1 : 0);

