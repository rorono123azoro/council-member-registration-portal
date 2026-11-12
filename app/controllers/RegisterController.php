<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Member;
use App\Services\Captcha;
use App\Services\Csrf;
use App\Services\Mailer;
use App\Services\RateLimiter;
use App\Services\Uploader;
use App\Services\Validator;

final class RegisterController
{
    public static function showForm(): void
    {
        $categories      = Category::allActive();
        $captchaQuestion = Captcha::question(true);
        $csrfToken       = Csrf::token('register');
        $idProofTypes    = Validator::ALLOWED_ID_TYPES;
        $pageTitle       = 'Council Member Registration';

        require BASE_PATH . '/app/views/register_form.php';
    }

    public static function checkUnique(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            jsonResponse(false, [], ['_general' => 'Method not allowed.'], 405);
        }

        $limit = (int) appConfig('rate_check_unique', 30);
        if (!RateLimiter::check('check_unique', clientIp(), $limit, 3600)) {
            jsonResponse(false, [], ['_general' => 'Too many lookup requests. Please wait a moment.'], 429);
        }

        $email  = trim((string) ($_GET['email'] ?? ''));
        $mobile = trim((string) ($_GET['mobile'] ?? ''));

        if ($email !== '') {
            $normEmail = mb_strtolower(str_replace("\0", '', $email), 'UTF-8');
            if (!filter_var($normEmail, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(true, ['available' => true]);
            }
            $taken = Member::existsByEmail($normEmail);
            jsonResponse(true, ['available' => !$taken]);
        }

        if ($mobile !== '') {
            $digits = preg_replace('/[\s\-()]+/', '', $mobile) ?? $mobile;
            if (preg_match('/^\+?91([6-9]\d{9})$/', $digits, $m)) {
                $digits = $m[1];
            } elseif (preg_match('/^0([6-9]\d{9})$/', $digits, $m)) {
                $digits = $m[1];
            }
            if (!preg_match('/^[6-9]\d{9}$/', $digits)) {
                jsonResponse(true, ['available' => true]);
            }
            $taken = Member::existsByMobile($digits);
            jsonResponse(true, ['available' => !$taken]);
        }

        jsonResponse(false, [], ['_general' => 'Provide email or mobile to check.'], 400);
    }

    public static function submit(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            jsonResponse(false, [], ['_general' => 'Method not allowed.'], 405);
        }

        // Detect body exceeding PHP post_max_size (API-14)
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            jsonResponse(
                false,
                [],
                ['_general' => 'Submitted upload is too large. Keep each file under 2 MB.'],
                413
            );
        }

        // CSRF check (API-04, SEC-04)
        if (!Csrf::validateRequest('register')) {
            jsonResponse(
                false,
                [],
                ['_general' => 'Your session security token expired. Please reload the page and try again.'],
                403
            );
        }

        // Honeypot check — silently reject without saving (SEC-14)
        if (Captcha::isHoneypotTriggered($_POST)) {
            appLog('WARN', 'Honeypot triggered from IP ' . clientIp());
            jsonResponse(true, ['reg_no' => 'REG-' . date('Y') . '-000000']);
        }

        // Rate limit check (SVC-06, SEC-12)
        $limit = (int) appConfig('rate_register', 5);
        if (!RateLimiter::check('register', clientIp(), $limit, 3600)) {
            jsonResponse(
                false,
                [],
                ['_general' => 'Too many registration attempts from your network. Please try again in an hour.'],
                429
            );
        }

        // Strip extra/mass-assignment fields (API-13, SEC-18)
        $allowedKeys = [
            'full_name', 'father_spouse_name', 'dob', 'gender',
            'email', 'mobile', 'address', 'city', 'state', 'pincode',
            'category_id', 'id_proof_type', 'id_proof_no', 'consent',
            'captcha_answer',
        ];
        $input = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $_POST)) {
                $input[$key] = $_POST[$key];
            }
        }

        // Validate fields (VAL-01..VAL-30)
        $validated = Validator::registration($input);
        $errors    = $validated['errors'];
        $clean     = $validated['clean'];

        // Math Captcha check (SEC-13)
        if (!Captcha::validateAnswer(is_string($input['captcha_answer'] ?? null) ? $input['captcha_answer'] : null)) {
            $errors['captcha_answer'] = 'Enter the correct answer to the verification question.';
        }

        // Also validate presence/preliminary errors of uploads without moving yet
        if (!isset($_FILES['photo']) || (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['photo'] = 'Upload Passport Photo (JPG or PNG, max 2 MB).';
        }
        if (!isset($_FILES['id_proof']) || (int) ($_FILES['id_proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['id_proof'] = 'Upload ID Proof (PDF, JPG, or PNG, max 2 MB).';
        }

        if ($errors !== []) {
            jsonResponse(false, [], $errors, 422);
        }

        // Process file uploads (UP-01..UP-16)
        $photoRes = Uploader::process($_FILES['photo'] ?? null, 'photo');
        if (!$photoRes['ok']) {
            jsonResponse(false, [], ['photo' => $photoRes['error'] ?? 'Invalid photo upload.'], 422);
        }

        $idProofRes = Uploader::process($_FILES['id_proof'] ?? null, 'id_proof');
        if (!$idProofRes['ok']) {
            Uploader::delete($photoRes['meta']['stored_path']);
            jsonResponse(false, [], ['id_proof' => $idProofRes['error'] ?? 'Invalid ID proof upload.'], 422);
        }

        $photoPath   = $photoRes['meta']['stored_path'];
        $idProofPath = $idProofRes['meta']['stored_path'];

        // Pre-insert duplicate check (API-06, API-07)
        $dupErrors = [];
        if (Member::existsByEmail((string) $clean['email'])) {
            $dupErrors['email'] = 'An application with this email address already exists.';
        }
        if (Member::existsByMobile((string) $clean['mobile'])) {
            $dupErrors['mobile'] = 'An application with this mobile number already exists.';
        }
        if ($dupErrors !== []) {
            Uploader::delete($photoPath);
            Uploader::delete($idProofPath);
            jsonResponse(false, [], $dupErrors, 409);
        }

        $clean['ip_address'] = clientIp();

        try {
            $regNo = Member::create($clean, [$photoRes['meta'], $idProofRes['meta']]);
        } catch (\PDOException $e) {
            Uploader::delete($photoPath);
            Uploader::delete($idProofPath);

            // Handle concurrent duplicate constraint violation (API-08)
            if ((string) $e->getCode() === '23000') {
                $msg = $e->getMessage();
                $raceErrors = [];
                if (str_contains($msg, 'uq_members_email')) {
                    $raceErrors['email'] = 'An application with this email address already exists.';
                }
                if (str_contains($msg, 'uq_members_mobile')) {
                    $raceErrors['mobile'] = 'An application with this mobile number already exists.';
                }
                if ($raceErrors === []) {
                    $raceErrors['_general'] = 'Duplicate registration detected.';
                }
                jsonResponse(false, [], $raceErrors, 409);
            }

            appLog('ERROR', 'Registration DB failure: ' . $e->getMessage());
            jsonResponse(false, [], ['_general' => 'Unable to save registration right now. Please try again.'], 500);
        } catch (\Throwable $e) {
            Uploader::delete($photoPath);
            Uploader::delete($idProofPath);
            appLog('ERROR', 'Registration unexpected failure: ' . $e->getMessage());
            jsonResponse(false, [], ['_general' => 'Unable to process registration right now. Please try again.'], 500);
        }

        // Send confirmation email (non-blocking, API-15, API-16)
        Mailer::sendRegistrationConfirmation(
            (string) $clean['email'],
            (string) $clean['full_name'],
            $regNo
        );

        // Refresh captcha and store reference in session
        Captcha::refresh();
        $_SESSION['last_reg_no'] = $regNo;

        jsonResponse(true, ['reg_no' => $regNo], [], 200);
    }
}

