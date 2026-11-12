<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Member;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\Mailer;
use App\Services\RateLimiter;
use App\Services\Uploader;
use App\Services\Validator;

final class AdminController
{
    // Dummy bcrypt hash used to keep login verification timing uniform when user does not exist (AUTH-03, SEC-19)
    private const DUMMY_HASH = '$2y$12$jLnYmlV.2q8Di8BaU0UFEurBIPRwst2XXw9EsJrriHs2WsnMVuBBm';

    public static function handleLogin(): array
    {
        if (Auth::current() !== null) {
            header('Location: /admin/dashboard.php');
            exit;
        }

        $error = '';
        $identifier = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validateRequest('admin_login')) {
                http_response_code(403);
                $error = 'Security token mismatch. Please refresh the page and try again.';
                return ['error' => $error, 'identifier' => $identifier];
            }

            $identifier = trim((string) ($_POST['identifier'] ?? ''));
            $password   = (string) ($_POST['password'] ?? '');

            if ($identifier === '' || $password === '') {
                http_response_code(422);
                $error = 'Enter both username/email and password.';
                return ['error' => $error, 'identifier' => $identifier];
            }

            $ip = clientIp();

            // Check lockout (AUTH-04, AUTH-05)
            $lockedUntil = Admin::getLockoutStatus($ip, $identifier);
            if ($lockedUntil !== null) {
                http_response_code(429);
                $error = 'Too many failed login attempts. Account/IP locked until ' . $lockedUntil . '.';
                return ['error' => $error, 'identifier' => $identifier];
            }

            // IP-level rate limiter (SEC-12)
            $rateLimit = (int) appConfig('rate_login', 5) * 3;
            if (!RateLimiter::check('admin_login_ip', $ip, $rateLimit, 900)) {
                http_response_code(429);
                $error = 'Too many login requests from your network. Please wait 15 minutes.';
                return ['error' => $error, 'identifier' => $identifier];
            }

            $admin = Admin::findByIdentifier($identifier);
            $hashToCheck = $admin !== null ? (string) $admin['password_hash'] : self::DUMMY_HASH;
            $passwordValid = password_verify($password, $hashToCheck);

            if ($admin === null || (int) $admin['is_active'] !== 1 || !$passwordValid) {
                $attempts = Admin::recordFailedLogin($ip, $identifier);
                AuditLog::record(
                    $admin !== null ? (int) $admin['id'] : null,
                    'admin.login_failed',
                    'admin',
                    $admin !== null ? (int) $admin['id'] : null,
                    null,
                    ['attempt' => $attempts],
                    $ip
                );
                $maxAtt = (int) appConfig('rate_login', 5);
                if ($attempts >= $maxAtt) {
                    http_response_code(429);
                    $error = 'Too many failed login attempts. Your access is temporarily locked.';
                } else {
                    http_response_code(401);
                    $error = 'Invalid credentials.';
                }
                return ['error' => $error, 'identifier' => $identifier];
            }

            Admin::clearFailedLogins($ip, $identifier);
            Admin::recordLastLogin((int) $admin['id']);
            Auth::login($admin);
            AuditLog::record((int) $admin['id'], 'admin.login', 'admin', (int) $admin['id'], null, null, $ip);

            if ((int) $admin['must_change_password'] === 1) {
                header('Location: /admin/change-password.php');
                exit;
            }

            header('Location: /admin/dashboard.php');
            exit;
        }

        return ['error' => $error, 'identifier' => $identifier];
    }

    public static function handleLogout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Location: /admin/dashboard.php');
            exit;
        }

        if (!Csrf::validateRequest('admin')) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            exit;
        }

        $current = Auth::current();
        if ($current !== null) {
            AuditLog::record((int) $current['id'], 'admin.logout', 'admin', (int) $current['id'], null, null, clientIp());
        }

        Auth::logout();
        header('Location: /admin/login.php');
        exit;
    }

    public static function handleChangePassword(array $currentAdmin): array
    {
        $error   = '';
        $success = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validateRequest('admin')) {
                http_response_code(403);
                return ['error' => 'Security token mismatch. Please refresh and try again.', 'success' => ''];
            }

            $currentPw = (string) ($_POST['current_password'] ?? '');
            $newPw     = (string) ($_POST['new_password'] ?? '');
            $confirmPw = (string) ($_POST['confirm_password'] ?? '');

            $adminRow = Admin::findById((int) $currentAdmin['id']);
            if ($adminRow === null || !password_verify($currentPw, (string) $adminRow['password_hash'])) {
                http_response_code(422);
                return ['error' => 'Current password is incorrect.', 'success' => ''];
            }

            if (strlen($newPw) < 8 || !preg_match('/[A-Z]/', $newPw) || !preg_match('/[a-z]/', $newPw) || !preg_match('/\d/', $newPw)) {
                http_response_code(422);
                return ['error' => 'New password must be at least 8 characters and include uppercase, lowercase, and a digit.', 'success' => ''];
            }

            if ($newPw !== $confirmPw) {
                http_response_code(422);
                return ['error' => 'New password and confirmation do not match.', 'success' => ''];
            }

            if (password_verify($newPw, (string) $adminRow['password_hash'])) {
                http_response_code(422);
                return ['error' => 'Choose a new password different from your current password.', 'success' => ''];
            }

            Admin::updatePassword((int) $currentAdmin['id'], $newPw, true);
            $_SESSION['admin_user']['must_change_password'] = 0;
            AuditLog::record((int) $currentAdmin['id'], 'admin.password_changed', 'admin', (int) $currentAdmin['id'], null, null, clientIp());

            $success = 'Password updated successfully.';
        }

        return ['error' => $error, 'success' => $success];
    }

    public static function extractMemberFilters(): array
    {
        return [
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'status'      => (string) ($_GET['status'] ?? ''),
            'category_id' => (string) ($_GET['category_id'] ?? ''),
            'date_from'   => trim((string) ($_GET['date_from'] ?? '')),
            'date_to'     => trim((string) ($_GET['date_to'] ?? '')),
            'sort'        => (string) ($_GET['sort'] ?? 'created_at'),
            'dir'         => (string) ($_GET['dir'] ?? 'desc'),
        ];
    }

    public static function handleMemberActions(int $memberId, array $currentAdmin): array
    {
        $error   = '';
        $success = '';
        $editErrors = [];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return ['error' => '', 'success' => '', 'editErrors' => []];
        }

        if (!Csrf::validateRequest('admin')) {
            http_response_code(403);
            return ['error' => 'Security token mismatch. Please reload the page.', 'success' => '', 'editErrors' => []];
        }

        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'approve' || $action === 'reject') {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $reason    = trim((string) ($_POST['decision_reason'] ?? ''));

            $res = Member::updateDecision(
                $memberId,
                $newStatus,
                $reason !== '' ? $reason : null,
                (int) $currentAdmin['id'],
                clientIp()
            );

            if (!$res['ok']) {
                http_response_code(422);
                return ['error' => $res['error'] ?? 'Unable to update decision.', 'success' => '', 'editErrors' => []];
            }

            if (!empty($res['changed']) && !empty($res['member'])) {
                $m = $res['member'];
                Mailer::sendDecisionNotification(
                    (string) $m['email'],
                    (string) $m['full_name'],
                    (string) $m['reg_no'],
                    $newStatus,
                    $reason !== '' ? $reason : null
                );
                $success = 'Member application marked as ' . ucfirst($newStatus) . '.';
            } else {
                $success = 'Application already has this status.';
            }

            return ['error' => '', 'success' => $success, 'editErrors' => []];
        }

        if ($action === 'edit') {
            $validated = Validator::memberFields($_POST, false);
            $editErrors = $validated['errors'];
            $clean      = $validated['clean'];

            if ($editErrors === []) {
                if (Member::existsByEmail((string) $clean['email'], $memberId)) {
                    $editErrors['email'] = 'Another member already uses this email address.';
                }
                if (Member::existsByMobile((string) $clean['mobile'], $memberId)) {
                    $editErrors['mobile'] = 'Another member already uses this mobile number.';
                }
            }

            if ($editErrors !== []) {
                http_response_code(422);
                return ['error' => 'Please fix the highlighted member details.', 'success' => '', 'editErrors' => $editErrors];
            }

            Member::updateDetails($memberId, $clean, (int) $currentAdmin['id'], clientIp());
            $success = 'Member details updated and recorded in the audit log.';
            return ['error' => '', 'success' => $success, 'editErrors' => []];
        }

        http_response_code(400);
        return ['error' => 'Unknown action.', 'success' => '', 'editErrors' => []];
    }

    public static function streamCsvExport(array $filters, array $currentAdmin): never
    {
        $rows = Member::allFilteredForExport($filters);
        AuditLog::record(
            (int) $currentAdmin['id'],
            'members.exported',
            'member',
            null,
            null,
            ['count' => count($rows), 'filters' => $filters],
            clientIp()
        );

        $filename = 'council-members-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        // UTF-8 BOM for Excel compatibility with Hindi / Unicode names (MEM-16)
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Registration No', 'Full Name', 'Father/Spouse Name', 'DOB', 'Gender',
            'Email', 'Mobile', 'Address', 'City', 'State', 'PIN Code',
            'Category', 'ID Proof Type', 'ID Proof No', 'Status',
            'Decision Reason', 'Submitted At', 'Reviewed At',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                self::safeCsvCell($r['reg_no']),
                self::safeCsvCell($r['full_name']),
                self::safeCsvCell($r['father_spouse_name']),
                self::safeCsvCell($r['dob']),
                self::safeCsvCell($r['gender']),
                self::safeCsvCell($r['email']),
                self::safeCsvCell($r['mobile']),
                self::safeCsvCell($r['address']),
                self::safeCsvCell($r['city']),
                self::safeCsvCell($r['state']),
                self::safeCsvCell($r['pincode']),
                self::safeCsvCell($r['category_name']),
                self::safeCsvCell($r['id_proof_type']),
                self::safeCsvCell($r['id_proof_no']),
                self::safeCsvCell($r['status']),
                self::safeCsvCell($r['decision_reason'] ?? ''),
                self::safeCsvCell($r['created_at']),
                self::safeCsvCell($r['reviewed_at'] ?? ''),
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * Neutralize spreadsheet formula injection (MEM-15).
     */
    public static function safeCsvCell(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'" . $str;
        }
        return $str;
    }

    public static function serveDocument(int $docId): never
    {
        if ($docId <= 0) {
            http_response_code(404);
            exit('Document not found.');
        }

        $doc = Member::findDocumentById($docId);
        if ($doc === null) {
            http_response_code(404);
            exit('Document not found.');
        }

        $path = Uploader::resolveSafePath((string) $doc['stored_path']);
        if ($path === null) {
            http_response_code(404);
            exit('Document file missing.');
        }

        $mime = (string) $doc['mime_type'];
        $mode = ($_GET['mode'] ?? 'attachment') === 'inline' ? 'inline' : 'attachment';
        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $doc['original_name']) ?: 'document';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: ' . $mode . '; filename="' . $safeFilename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');

        readfile($path);
        exit;
    }
}

