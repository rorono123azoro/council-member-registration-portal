<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Admin
{
    public static function findByIdentifier(string $identifier): ?array
    {
        $db = Database::connection();
        $norm = mb_strtolower(trim($identifier), 'UTF-8');
        $stmt = $db->prepare(
            'SELECT * FROM admins WHERE LOWER(username) = :u OR LOWER(email) = :e LIMIT 1'
        );
        $stmt->execute([':u' => $norm, ':e' => $norm]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        $db = Database::connection();
        return $db->query(
            'SELECT id, name, username, email, role, is_active, must_change_password, last_login, created_at
             FROM admins
             ORDER BY role DESC, id ASC'
        )->fetchAll();
    }

    public static function countActiveSuperAdmins(): int
    {
        $db = Database::connection();
        return (int) $db->query(
            "SELECT COUNT(*) FROM admins WHERE role = 'super_admin' AND is_active = 1"
        )->fetchColumn();
    }

    public static function existsByUsernameOrEmail(string $username, string $email, ?int $excludeId = null): bool
    {
        $db = Database::connection();
        $u = mb_strtolower(trim($username), 'UTF-8');
        $e = mb_strtolower(trim($email), 'UTF-8');
        if ($excludeId !== null) {
            $stmt = $db->prepare(
                'SELECT 1 FROM admins WHERE (LOWER(username) = :u OR LOWER(email) = :e) AND id != :id LIMIT 1'
            );
            $stmt->execute([':u' => $u, ':e' => $e, ':id' => $excludeId]);
        } else {
            $stmt = $db->prepare(
                'SELECT 1 FROM admins WHERE LOWER(username) = :u OR LOWER(email) = :e LIMIT 1'
            );
            $stmt->execute([':u' => $u, ':e' => $e]);
        }
        return (bool) $stmt->fetchColumn();
    }

    public static function create(
        string $name,
        string $username,
        string $email,
        string $plainPassword,
        string $role = 'reviewer',
        int    $mustChange = 1
    ): int {
        $db = Database::connection();
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare(
            'INSERT INTO admins (name, username, email, password_hash, role, is_active, must_change_password)
             VALUES (:name, :username, :email, :hash, :role, 1, :must_change)'
        );
        $stmt->execute([
            ':name'        => trim($name),
            ':username'    => mb_strtolower(trim($username), 'UTF-8'),
            ':email'       => mb_strtolower(trim($email), 'UTF-8'),
            ':hash'        => $hash,
            ':role'        => in_array($role, ['reviewer', 'super_admin'], true) ? $role : 'reviewer',
            ':must_change' => $mustChange ? 1 : 0,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function updateRoleAndStatus(int $id, string $role, int $isActive): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE admins SET role = :role, is_active = :active WHERE id = :id');
        return $stmt->execute([
            ':id'     => $id,
            ':role'   => in_array($role, ['reviewer', 'super_admin'], true) ? $role : 'reviewer',
            ':active' => $isActive ? 1 : 0,
        ]);
    }

    public static function updatePassword(int $id, string $newPlainPassword, bool $clearMustChange = true): bool
    {
        $db = Database::connection();
        $hash = password_hash($newPlainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare(
            'UPDATE admins SET password_hash = :hash, must_change_password = :mcp WHERE id = :id'
        );
        return $stmt->execute([
            ':id'   => $id,
            ':hash' => $hash,
            ':mcp'  => $clearMustChange ? 0 : 1,
        ]);
    }

    public static function recordLastLogin(int $id): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE admins SET last_login = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    // ── Login Attempt & Lockout Tracking (AUTH-04, AUTH-05) ───────────────────

    public static function getLockoutStatus(string $ip, string $identifier): ?string
    {
        $db = Database::connection();
        $norm = mb_strtolower(trim($identifier), 'UTF-8');
        $stmt = $db->prepare(
            'SELECT locked_until FROM login_attempts
             WHERE (ip_address = :ip AND identifier = :id)
                OR (ip_address = :ip2 AND attempts >= :max_att AND locked_until > NOW())
             ORDER BY locked_until DESC LIMIT 1'
        );
        $maxAttempts = (int) appConfig('rate_login', 5);
        $stmt->execute([
            ':ip'      => $ip,
            ':id'      => $norm,
            ':ip2'     => $ip,
            ':max_att' => $maxAttempts,
        ]);
        $lockedUntil = $stmt->fetchColumn();
        if (is_string($lockedUntil) && strtotime($lockedUntil) > time()) {
            return $lockedUntil;
        }
        return null;
    }

    public static function recordFailedLogin(string $ip, string $identifier): int
    {
        $db = Database::connection();
        $norm        = mb_strtolower(trim($identifier), 'UTF-8');
        $maxAttempts = (int) appConfig('rate_login', 5);
        $lockMinutes = (int) appConfig('lockout_minutes', 15);

        // Reset expired lockout automatically (AUTH-05)
        $db->prepare(
            'UPDATE login_attempts SET attempts = 0, locked_until = NULL
             WHERE ip_address = :ip AND identifier = :id AND locked_until IS NOT NULL AND locked_until <= NOW()'
        )->execute([':ip' => $ip, ':id' => $norm]);

        $stmt = $db->prepare(
            'INSERT INTO login_attempts (ip_address, identifier, attempts, locked_until, last_attempt_at)
             VALUES (:ip, :id, 1, NULL, NOW())
             ON DUPLICATE KEY UPDATE
                 attempts = attempts + 1,
                 last_attempt_at = NOW(),
                 locked_until = IF(attempts + 1 >= :max_att, DATE_ADD(NOW(), INTERVAL :mins MINUTE), NULL)'
        );
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':id', $norm);
        $stmt->bindValue(':max_att', $maxAttempts, \PDO::PARAM_INT);
        $stmt->bindValue(':mins', $lockMinutes, \PDO::PARAM_INT);
        $stmt->execute();

        $q = $db->prepare('SELECT attempts FROM login_attempts WHERE ip_address = :ip AND identifier = :id');
        $q->execute([':ip' => $ip, ':id' => $norm]);
        return (int) ($q->fetchColumn() ?: 1);
    }

    public static function clearFailedLogins(string $ip, string $identifier): void
    {
        $db = Database::connection();
        $norm = mb_strtolower(trim($identifier), 'UTF-8');
        $stmt = $db->prepare('DELETE FROM login_attempts WHERE ip_address = :ip AND identifier = :id');
        $stmt->execute([':ip' => $ip, ':id' => $norm]);
    }
}

