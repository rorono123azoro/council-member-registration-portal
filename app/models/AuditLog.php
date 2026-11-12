<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

/**
 * Append-only audit log model (no update or delete methods).
 */
final class AuditLog
{
    public static function record(
        ?int    $adminId,
        string  $action,
        string  $targetType,
        ?int    $targetId,
        mixed   $oldValue,
        mixed   $newValue,
        string  $ipAddress
    ): void {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO audit_log (admin_id, action, target_type, target_id, old_value, new_value, ip_address)
             VALUES (:admin_id, :action, :target_type, :target_id, :old_value, :new_value, :ip)'
        );
        $stmt->execute([
            ':admin_id'    => $adminId,
            ':action'      => $action,
            ':target_type' => $targetType,
            ':target_id'   => $targetId,
            ':old_value'   => self::encodeValue($oldValue),
            ':new_value'   => self::encodeValue($newValue),
            ':ip'          => $ipAddress,
        ]);
    }

    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $db = Database::connection();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = (int) $db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $stmt = $db->prepare(
            'SELECT l.*, a.username AS admin_username, a.name AS admin_name
             FROM audit_log l
             LEFT JOIN admins a ON a.id = l.admin_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items'       => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    private static function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: null;
    }
}

