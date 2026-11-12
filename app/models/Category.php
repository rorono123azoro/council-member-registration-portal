<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Category
{
    public static function allActive(): array
    {
        $db = Database::connection();
        $stmt = $db->query(
            'SELECT id, name, code, description FROM categories WHERE is_active = 1 ORDER BY name ASC'
        );
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        $db = Database::connection();
        $stmt = $db->query(
            'SELECT c.*, (SELECT COUNT(*) FROM members m WHERE m.category_id = c.id) AS member_count
             FROM categories c
             ORDER BY c.is_active DESC, c.name ASC'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function codeExists(string $code, ?int $excludeId = null): bool
    {
        $db = Database::connection();
        if ($excludeId !== null) {
            $stmt = $db->prepare('SELECT 1 FROM categories WHERE code = :code AND id != :id LIMIT 1');
            $stmt->execute([':code' => strtoupper($code), ':id' => $excludeId]);
        } else {
            $stmt = $db->prepare('SELECT 1 FROM categories WHERE code = :code LIMIT 1');
            $stmt->execute([':code' => strtoupper($code)]);
        }
        return (bool) $stmt->fetchColumn();
    }

    public static function create(string $name, string $code, ?string $description, int $isActive = 1): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO categories (name, code, description, is_active) VALUES (:name, :code, :desc, :active)'
        );
        $stmt->execute([
            ':name'   => trim($name),
            ':code'   => strtoupper(trim($code)),
            ':desc'   => $description !== null && trim($description) !== '' ? trim($description) : null,
            ':active' => $isActive ? 1 : 0,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, string $code, ?string $description, int $isActive): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE categories SET name = :name, code = :code, description = :desc, is_active = :active WHERE id = :id'
        );
        return $stmt->execute([
            ':id'     => $id,
            ':name'   => trim($name),
            ':code'   => strtoupper(trim($code)),
            ':desc'   => $description !== null && trim($description) !== '' ? trim($description) : null,
            ':active' => $isActive ? 1 : 0,
        ]);
    }
}

