<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Secure file uploader.
 * Validates MIME, extension, size; stores with random filename outside web root.
 */
final class Uploader
{
    private const MAX_SIZE = 2 * 1024 * 1024; // 2 MB

    private const ALLOWED = [
        'photo'    => [
            'extensions' => ['jpg', 'jpeg', 'png'],
            'mimes'      => ['image/jpeg', 'image/png'],
        ],
        'id_proof' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'mimes'      => ['image/jpeg', 'image/png', 'application/pdf'],
        ],
    ];

    /**
     * Process an uploaded file.
     *
     * @param  array  $file   $_FILES['field']
     * @param  string $type   'photo' | 'id_proof'
     * @return array{ok: bool, path?: string, error?: string}
     */
    public static function process(array $file, string $type): array
    {
        // Basic upload error check
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $msg = match ($file['error'] ?? -1) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum allowed size.',
                UPLOAD_ERR_PARTIAL  => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE  => 'No file was uploaded.',
                default             => 'File upload error.',
            };
            return ['ok' => false, 'error' => $msg];
        }

        // Size check
        if ($file['size'] > self::MAX_SIZE) {
            return ['ok' => false, 'error' => 'File must not exceed 2 MB.'];
        }

        // Extension check
        $origName  = $file['name'] ?? '';
        $ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedDef = self::ALLOWED[$type] ?? null;

        if ($allowedDef === null) {
            return ['ok' => false, 'error' => 'Unknown upload type.'];
        }

        if (!in_array($ext, $allowedDef['extensions'], true)) {
            return [
                'ok'    => false,
                'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedDef['extensions']),
            ];
        }

        // Real MIME check using finfo
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']);

        if (!in_array($realMime, $allowedDef['mimes'], true)) {
            return ['ok' => false, 'error' => 'File content does not match the expected type.'];
        }

        // Build target path  storage/uploads/{year}/{random}.{ext}
        $year    = date('Y');
        $dir     = UPLOAD_PATH . '/' . $year;

        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            return ['ok' => false, 'error' => 'Upload directory could not be created.'];
        }

        $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $dir . '/' . $randomName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['ok' => false, 'error' => 'Failed to save uploaded file.'];
        }

        // Relative path stored in DB
        $relativePath = $year . '/' . $randomName;

        return ['ok' => true, 'path' => $relativePath];
    }
}

