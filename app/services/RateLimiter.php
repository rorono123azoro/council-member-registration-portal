<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Database;
use PDO;

/**
 * Rate limiter using DB-backed sliding window counters.
 * Falls back gracefully if DB is unavailable.
 */
final class RateLimiter
{
    /**
     * @param string $action   e.g. 'register', 'login'
     * @param string $key      IP address or username
     * @param int    $limit    max attempts allowed
     * @param int    $window   window in seconds (default 3600 = 1 hour)
     */
    public static function check(
        string $action,
        string $key,
        int    $limit,
        int    $window = 3600
    ): bool {
        try {
            $db      = Database::connection();
            $keyHash = hash('sha256', $key);
            $now     = new \DateTimeImmutable();
            $windowStart = $now->modify("-{$window} seconds")->format('Y-m-d H:i:s');

            // Clean stale windows
            $db->prepare(
                'DELETE FROM rate_limits WHERE window_start < :ws AND action = :action'
            )->execute([':ws' => $windowStart, ':action' => $action]);

            // Upsert
            $stmt = $db->prepare(
                'INSERT INTO rate_limits (key_hash, action, attempts, window_start)
                 VALUES (:kh, :action, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                     attempts     = IF(window_start < :ws2, 1, attempts + 1),
                     window_start = IF(window_start < :ws3, NOW(), window_start)'
            );
            $stmt->execute([
                ':kh'     => $keyHash,
                ':action' => $action,
                ':ws2'    => $windowStart,
                ':ws3'    => $windowStart,
            ]);

            // Read current count
            $row = $db->prepare(
                'SELECT attempts FROM rate_limits WHERE key_hash = :kh AND action = :action'
            );
            $row->execute([':kh' => $keyHash, ':action' => $action]);
            $attempts = (int) ($row->fetchColumn() ?: 0);

            return $attempts <= $limit;
        } catch (\Throwable $e) {
            error_log('RateLimiter error: ' . $e->getMessage());
            return true; // fail open — don't block legitimate users on DB error
        }
    }

    /**
     * Reset rate limit for a key+action (e.g., after successful login).
     */
    public static function reset(string $action, string $key): void
    {
        try {
            $db      = Database::connection();
            $keyHash = hash('sha256', $key);
            $db->prepare(
                'DELETE FROM rate_limits WHERE key_hash = :kh AND action = :action'
            )->execute([':kh' => $keyHash, ':action' => $action]);
        } catch (\Throwable $e) {
            error_log('RateLimiter reset error: ' . $e->getMessage());
        }
    }
}

