<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DB-backed sliding-window rate limiter.
 */
final class RateLimiter
{
    /**
     * Check and increment attempt counter for an action+key pair.
     * Returns true if allowed, false if limit exceeded.
     */
    public static function check(
        string $action,
        string $key,
        int    $limit,
        int    $windowSeconds = 3600
    ): bool {
        try {
            $db          = Database::connection();
            $keyHash     = hash('sha256', $action . '|' . $key);
            $windowStart = (new \DateTimeImmutable())->modify("-{$windowSeconds} seconds")->format('Y-m-d H:i:s');

            $db->prepare(
                'DELETE FROM rate_limits WHERE window_start < :ws AND action = :action'
            )->execute([':ws' => $windowStart, ':action' => $action]);

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

            $row = $db->prepare(
                'SELECT attempts FROM rate_limits WHERE key_hash = :kh AND action = :action'
            );
            $row->execute([':kh' => $keyHash, ':action' => $action]);
            $attempts = (int) ($row->fetchColumn() ?: 0);

            return $attempts <= $limit;
        } catch (\Throwable $e) {
            appLog('ERROR', 'RateLimiter error: ' . $e->getMessage());
            return true;
        }
    }

    public static function reset(string $action, string $key): void
    {
        try {
            $db      = Database::connection();
            $keyHash = hash('sha256', $action . '|' . $key);
            $db->prepare(
                'DELETE FROM rate_limits WHERE key_hash = :kh AND action = :action'
            )->execute([':kh' => $keyHash, ':action' => $action]);
        } catch (\Throwable $e) {
            appLog('ERROR', 'RateLimiter reset error: ' . $e->getMessage());
        }
    }
}
