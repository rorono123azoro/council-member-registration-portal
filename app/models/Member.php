<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Member
{
    /**
     * Create a member and 2 document rows atomically in a DB transaction.
     * Generates a strictly sequential unique registration number REG-YYYY-NNNNNN.
     *
     * @param  array<string, mixed>      $data
     * @param  array<int, array<string, mixed>> $documents
     * @return string Generated registration number
     */
    public static function create(array $data, array $documents): string
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $tmpReg = 'TMP-' . bin2hex(random_bytes(8));

            $stmt = $db->prepare(
                'INSERT INTO members (
                    reg_no, full_name, father_spouse_name, dob, gender,
                    email, mobile, address, city, state, pincode,
                    category_id, id_proof_type, id_proof_no, consent,
                    status, ip_address
                ) VALUES (
                    :reg_no, :full_name, :father_spouse_name, :dob, :gender,
                    :email, :mobile, :address, :city, :state, :pincode,
                    :category_id, :id_proof_type, :id_proof_no, :consent,
                    \'pending\', :ip_address
                )'
            );

            $stmt->execute([
                ':reg_no'             => $tmpReg,
                ':full_name'          => $data['full_name'],
                ':father_spouse_name' => $data['father_spouse_name'],
                ':dob'                => $data['dob'],
                ':gender'             => $data['gender'],
                ':email'              => mb_strtolower($data['email'], 'UTF-8'),
                ':mobile'             => $data['mobile'],
                ':address'            => $data['address'],
                ':city'               => $data['city'],
                ':state'              => $data['state'],
                ':pincode'            => $data['pincode'],
                ':category_id'        => (int) $data['category_id'],
                ':id_proof_type'      => $data['id_proof_type'],
                ':id_proof_no'        => $data['id_proof_no'],
                ':consent'            => 1,
                ':ip_address'         => $data['ip_address'],
            ]);

            $memberId = (int) $db->lastInsertId();
            $regNo    = sprintf('REG-%s-%06d', date('Y'), $memberId);

            $upd = $db->prepare('UPDATE members SET reg_no = :reg_no WHERE id = :id');
            $upd->execute([':reg_no' => $regNo, ':id' => $memberId]);

            $docStmt = $db->prepare(
                'INSERT INTO documents (member_id, doc_type, original_name, stored_path, mime_type, file_size)
                 VALUES (:member_id, :doc_type, :original_name, :stored_path, :mime_type, :file_size)'
            );

            foreach ($documents as $doc) {
                $docStmt->execute([
                    ':member_id'     => $memberId,
                    ':doc_type'      => $doc['doc_type'],
                    ':original_name' => $doc['original_name'],
                    ':stored_path'   => $doc['stored_path'],
                    ':mime_type'     => $doc['mime_type'],
                    ':file_size'     => (int) $doc['file_size'],
                ]);
            }

            $db->commit();
            return $regNo;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $db   = Database::connection();
        $norm = mb_strtolower(trim($email), 'UTF-8');
        if ($excludeId !== null) {
            $stmt = $db->prepare('SELECT 1 FROM members WHERE email = :email AND id != :id LIMIT 1');
            $stmt->execute([':email' => $norm, ':id' => $excludeId]);
        } else {
            $stmt = $db->prepare('SELECT 1 FROM members WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $norm]);
        }
        return (bool) $stmt->fetchColumn();
    }

    public static function existsByMobile(string $mobile, ?int $excludeId = null): bool
    {
        $db = Database::connection();
        if ($excludeId !== null) {
            $stmt = $db->prepare('SELECT 1 FROM members WHERE mobile = :mobile AND id != :id LIMIT 1');
            $stmt->execute([':mobile' => trim($mobile), ':id' => $excludeId]);
        } else {
            $stmt = $db->prepare('SELECT 1 FROM members WHERE mobile = :mobile LIMIT 1');
            $stmt->execute([':mobile' => trim($mobile)]);
        }
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Lookup minimal non-sensitive acknowledgement info by registration number (SUC-01..03).
     */
    public static function findPublicAckByRegNo(string $regNo): ?array
    {
        $db   = Database::connection();
        $stmt = $db->prepare(
            'SELECT m.reg_no, m.status, m.created_at, c.name AS category_name
             FROM members m
             JOIN categories c ON c.id = m.category_id
             WHERE m.reg_no = :reg_no
             LIMIT 1'
        );
        $stmt->execute([':reg_no' => trim($regNo)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $db   = Database::connection();
        $stmt = $db->prepare(
            'SELECT m.*, c.name AS category_name, c.code AS category_code,
                    a.name AS reviewer_name, a.username AS reviewer_username
             FROM members m
             JOIN categories c ON c.id = m.category_id
             LEFT JOIN admins a ON a.id = m.reviewed_by
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $member = $stmt->fetch();
        if (!$member) {
            return null;
        }

        $docStmt = $db->prepare('SELECT * FROM documents WHERE member_id = :id ORDER BY doc_type ASC');
        $docStmt->execute([':id' => $id]);
        $member['documents'] = $docStmt->fetchAll();

        return $member;
    }

    public static function findDocumentById(int $docId): ?array
    {
        $db   = Database::connection();
        $stmt = $db->prepare('SELECT * FROM documents WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $docId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function countsByStatus(): array
    {
        $db   = Database::connection();
        $rows = $db->query('SELECT status, COUNT(*) AS cnt FROM members GROUP BY status')->fetchAll();

        $counts = [
            'total'    => 0,
            'pending'  => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        foreach ($rows as $r) {
            $s = (string) $r['status'];
            $c = (int) $r['cnt'];
            if (isset($counts[$s])) {
                $counts[$s] = $c;
            }
            $counts['total'] += $c;
        }

        return $counts;
    }

    public static function recent(int $limit = 10): array
    {
        $db   = Database::connection();
        $stmt = $db->prepare(
            'SELECT m.id, m.reg_no, m.full_name, m.email, m.mobile, m.status, m.created_at,
                    c.name AS category_name
             FROM members m
             JOIN categories c ON c.id = m.category_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', max(1, min(50, $limit)), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Build shared WHERE clause and bound parameters for list & export queries.
     *
     * @return array{whereSql: string, params: array<string, mixed>}
     */
    private static function buildFilterSql(array $filters): array
    {
        $clauses = [];
        $params  = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $like    = '%' . $escaped . '%';
            $clauses[] = '(m.full_name LIKE :q1 ESCAPE \'\\\\\' OR m.email LIKE :q2 ESCAPE \'\\\\\' OR m.mobile LIKE :q3 ESCAPE \'\\\\\' OR m.reg_no LIKE :q4 ESCAPE \'\\\\\')';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $clauses[] = 'm.status = :status';
            $params[':status'] = $status;
        }

        $categoryId = (string) ($filters['category_id'] ?? '');
        if ($categoryId !== '' && ctype_digit($categoryId) && (int) $categoryId > 0) {
            $clauses[] = 'm.category_id = :cat_id';
            $params[':cat_id'] = (int) $categoryId;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $clauses[] = 'm.created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $clauses[] = 'm.created_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        $whereSql = $clauses !== [] ? ('WHERE ' . implode(' AND ', $clauses)) : '';
        return ['whereSql' => $whereSql, 'params' => $params];
    }

    /**
     * Paginated, filtered, and sorted member list (ADM-03..ADM-13).
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $db = Database::connection();
        $filterData = self::buildFilterSql($filters);
        $whereSql   = $filterData['whereSql'];
        $params     = $filterData['params'];

        // Whitelisted sort columns (ADM-11)
        $sortMap = [
            'created_at' => 'm.created_at',
            'reg_no'     => 'm.reg_no',
            'full_name'  => 'm.full_name',
            'status'     => 'm.status',
            'category'   => 'c.name',
        ];
        $sortKey = (string) ($filters['sort'] ?? 'created_at');
        $sortCol = $sortMap[$sortKey] ?? 'm.created_at';
        $dir     = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM members m {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage    = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = max(1, min($totalPages, $page));
        $offset     = ($page - 1) * $perPage;

        $sql = "SELECT m.id, m.reg_no, m.full_name, m.email, m.mobile, m.city, m.state,
                       m.status, m.created_at, c.name AS category_name
                FROM members m
                JOIN categories c ON c.id = m.category_id
                {$whereSql}
                ORDER BY {$sortCol} {$dir}, m.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items'       => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'sort'        => isset($sortMap[$sortKey]) ? $sortKey : 'created_at',
            'dir'         => strtolower($dir),
        ];
    }

    /**
     * Fetch all rows matching current filters for CSV export (MEM-14).
     */
    public static function allFilteredForExport(array $filters): array
    {
        $db         = Database::connection();
        $filterData = self::buildFilterSql($filters);
        $whereSql   = $filterData['whereSql'];
        $params     = $filterData['params'];

        $sql = "SELECT m.reg_no, m.full_name, m.father_spouse_name, m.dob, m.gender,
                       m.email, m.mobile, m.address, m.city, m.state, m.pincode,
                       c.name AS category_name, m.id_proof_type, m.id_proof_no,
                       m.status, m.decision_reason, m.created_at, m.reviewed_at
                FROM members m
                JOIN categories c ON c.id = m.category_id
                {$whereSql}
                ORDER BY m.created_at DESC, m.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Approve or reject a member registration atomically with audit log (MEM-06..MEM-11).
     *
     * @return array{ok: bool, changed?: bool, member?: array, error?: string}
     */
    public static function updateDecision(
        int     $memberId,
        string  $newStatus,
        ?string $reason,
        int     $adminId,
        string  $ipAddress
    ): array {
        if (!in_array($newStatus, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Invalid decision status.'];
        }

        $cleanReason = $reason !== null ? trim($reason) : null;
        if ($newStatus === 'rejected' && ($cleanReason === null || $cleanReason === '')) {
            return ['ok' => false, 'error' => 'A rejection reason is required.'];
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM members WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $memberId]);
            $member = $stmt->fetch();

            if (!$member) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Member not found.'];
            }

            // MEM-09: avoid duplicate audit/email noise if decision unchanged
            if ($member['status'] === $newStatus && (string) ($member['decision_reason'] ?? '') === (string) ($cleanReason ?? '')) {
                $db->commit();
                return ['ok' => true, 'changed' => false, 'member' => $member];
            }

            $oldState = [
                'status'          => $member['status'],
                'decision_reason' => $member['decision_reason'],
            ];
            $newState = [
                'status'          => $newStatus,
                'decision_reason' => $cleanReason,
            ];

            $upd = $db->prepare(
                'UPDATE members
                 SET status = :status, decision_reason = :reason, reviewed_by = :admin_id, reviewed_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                ':status'   => $newStatus,
                ':reason'   => $cleanReason,
                ':admin_id' => $adminId,
                ':id'       => $memberId,
            ]);

            AuditLog::record(
                $adminId,
                $newStatus === 'approved' ? 'member.approved' : 'member.rejected',
                'member',
                $memberId,
                $oldState,
                $newState,
                $ipAddress
            );

            $db->commit();
            $member['status']          = $newStatus;
            $member['decision_reason'] = $cleanReason;
            return ['ok' => true, 'changed' => true, 'member' => $member];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Edit member details with full audit diff (MEM-12).
     */
    public static function updateDetails(int $memberId, array $clean, int $adminId, string $ipAddress): bool
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM members WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $memberId]);
            $existing = $stmt->fetch();
            if (!$existing) {
                $db->rollBack();
                return false;
            }

            $fields = [
                'full_name', 'father_spouse_name', 'dob', 'gender',
                'email', 'mobile', 'address', 'city', 'state', 'pincode',
                'category_id', 'id_proof_type', 'id_proof_no',
            ];

            $oldDiff = [];
            $newDiff = [];
            foreach ($fields as $f) {
                $oldVal = (string) ($existing[$f] ?? '');
                $newVal = $f === 'email'
                    ? mb_strtolower((string) $clean[$f], 'UTF-8')
                    : (string) $clean[$f];
                if ($oldVal !== $newVal) {
                    $oldDiff[$f] = $oldVal;
                    $newDiff[$f] = $newVal;
                }
            }

            if ($newDiff === []) {
                $db->commit();
                return true;
            }

            $upd = $db->prepare(
                'UPDATE members SET
                    full_name = :full_name,
                    father_spouse_name = :father_spouse_name,
                    dob = :dob,
                    gender = :gender,
                    email = :email,
                    mobile = :mobile,
                    address = :address,
                    city = :city,
                    state = :state,
                    pincode = :pincode,
                    category_id = :category_id,
                    id_proof_type = :id_proof_type,
                    id_proof_no = :id_proof_no
                 WHERE id = :id'
            );
            $upd->execute([
                ':id'                 => $memberId,
                ':full_name'          => $clean['full_name'],
                ':father_spouse_name' => $clean['father_spouse_name'],
                ':dob'                => $clean['dob'],
                ':gender'             => $clean['gender'],
                ':email'              => mb_strtolower((string) $clean['email'], 'UTF-8'),
                ':mobile'             => $clean['mobile'],
                ':address'            => $clean['address'],
                ':city'               => $clean['city'],
                ':state'              => $clean['state'],
                ':pincode'            => $clean['pincode'],
                ':category_id'        => (int) $clean['category_id'],
                ':id_proof_type'      => $clean['id_proof_type'],
                ':id_proof_no'        => $clean['id_proof_no'],
            ]);

            AuditLog::record($adminId, 'member.edited', 'member', $memberId, $oldDiff, $newDiff, $ipAddress);

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

