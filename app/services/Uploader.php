<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Secure file uploader.
 * Checks upload error code, size (<= 2 MB), extension, dangerous double extensions,
 * and real MIME type via finfo; stores outside web root with random filename.
 */
final class Uploader
{
    public const MAX_SIZE = 2 * 1024 * 1024; // 2 MB exact (2,097,152 bytes)

    private const ALLOWED = [
        'photo' => [
            'extensions' => ['jpg', 'jpeg', 'png'],
            'mimes'      => ['image/jpeg' => 'jpg', 'image/png' => 'png'],
            'label'      => 'Passport Photo (JPG or PNG, max 2 MB)',
        ],
        'id_proof' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'mimes'      => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'],
            'label'      => 'ID Proof (PDF, JPG, or PNG, max 2 MB)',
        ],
    ];

    /**
     * Process an uploaded file from $_FILES.
     *
     * @param  array|null $file $_FILES['field']
     * @param  string     $type 'photo' | 'id_proof'
     * @return array{ok: bool, meta?: array{doc_type: string, original_name: string, stored_path: string, mime_type: string, file_size: int}, error?: string}
     */
    public static function process(?array $file, string $type): array
    {
        $def = self::ALLOWED[$type] ?? null;
        if ($def === null) {
            return ['ok' => false, 'error' => 'Unsupported upload type.'];
        }

        if ($file === null || !isset($file['error'])) {
            return ['ok' => false, 'error' => "Upload {$def['label']}."];
        }

        $errCode = (int) $file['error'];
        if ($errCode !== UPLOAD_ERR_OK) {
            $msg = match ($errCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the 2 MB maximum size.',
                UPLOAD_ERR_PARTIAL  => 'File upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE  => "Upload {$def['label']}.",
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Server temporary upload error. Please try again.',
                default             => 'File upload failed. Please try again.',
            };
            return ['ok' => false, 'error' => $msg];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['ok' => false, 'error' => 'Invalid uploaded file.'];
        }

        $fileSize = (int) ($file['size'] ?? 0);
        $actualSize = filesize($tmpName);
        if ($fileSize <= 0 || $actualSize === false || $actualSize <= 0) {
            return ['ok' => false, 'error' => 'Uploaded file is empty.'];
        }

        if ($fileSize > self::MAX_SIZE || $actualSize > self::MAX_SIZE) {
            return ['ok' => false, 'error' => 'File must not exceed 2 MB.'];
        }

        $rawName = (string) ($file['name'] ?? 'upload');
        $rawName = str_replace("\0", '', $rawName);
        $origName = basename($rawName);

        // Reject double extensions or embedded script extensions (UP-07)
        if (preg_match('/\.(php\d*|phtml|phar|exe|bat|cmd|sh|js|html?)\b/i', $origName)) {
            return ['ok' => false, 'error' => 'File name contains a disallowed extension.'];
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $def['extensions'], true)) {
            return [
                'ok'    => false,
                'error' => 'Invalid file format. Allowed: ' . strtoupper(implode(', ', $def['extensions'])) . '.',
            ];
        }

        // Real MIME inspection from file bytes (UP-06, UP-08)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = (string) $finfo->file($tmpName);

        if (!isset($def['mimes'][$realMime])) {
            return ['ok' => false, 'error' => 'File content does not match an allowed image or PDF format.'];
        }

        $safeExt = $def['mimes'][$realMime];
        $year    = date('Y');
        $dir     = UPLOAD_PATH . '/' . $year;

        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not prepare upload storage directory.'];
        }

        $randomName   = bin2hex(random_bytes(16)) . '.' . $safeExt;
        $relativePath = $year . '/' . $randomName;
        $targetPath   = $dir . '/' . $randomName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            return ['ok' => false, 'error' => 'Failed to save uploaded file.'];
        }

        @chmod($targetPath, 0640);

        $safeOriginal = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $origName) ?: ('file.' . $safeExt), 0, 255, 'UTF-8');

        return [
            'ok'   => true,
            'meta' => [
                'doc_type'      => $type,
                'original_name' => $safeOriginal,
                'stored_path'   => $relativePath,
                'mime_type'     => $realMime,
                'file_size'     => (int) $actualSize,
            ],
        ];
    }

    /**
     * Resolve an absolute path inside UPLOAD_PATH safely (prevents path traversal).
     */
    public static function resolveSafePath(string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, '..') || str_contains($relativePath, "\0")) {
            return null;
        }
        $base = realpath(UPLOAD_PATH);
        if ($base === false) {
            return null;
        }
        $target = realpath($base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
        if ($target === false || !str_starts_with($target, $base . DIRECTORY_SEPARATOR) || !is_file($target)) {
            return null;
        }
        return $target;
    }

    /**
     * Remove an uploaded file safely (used for transaction rollback).
     */
    public static function delete(string $relativePath): void
    {
        $path = self::resolveSafePath($relativePath);
        if ($path !== null) {
            @unlink($path);
        }
    }
}
