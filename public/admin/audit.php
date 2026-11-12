<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\AuditLog;
use App\Services\Auth;

$currentAdmin = Auth::requireAdmin('super_admin');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$result = AuditLog::paginate($page, 25);

$pageTitle = 'Audit Log';
$activeNav = 'audit';
require BASE_PATH . '/app/views/partials/admin_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Audit Log</h1>
        <div class="small text-secondary">Immutable record of administrative actions (<?= (int) $result['total'] ?> total)</div>
    </div>
</div>

<div class="portal-card">
    <?php if ($result['items'] === []): ?>
        <div class="p-5 text-center text-secondary">No audit entries recorded yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['items'] as $row): ?>
                        <tr>
                            <td class="text-nowrap"><?= e($row['created_at']) ?></td>
                            <td><?= e($row['admin_username'] ?? 'system') ?></td>
                            <td><span class="badge bg-secondary-subtle text-dark border"><?= e($row['action']) ?></span></td>
                            <td><?= e($row['target_type']) ?><?= $row['target_id'] ? ' #' . (int) $row['target_id'] : '' ?></td>
                            <td class="text-break" style="max-width:220px;"><?= e($row['old_value'] ?? '—') ?></td>
                            <td class="text-break" style="max-width:220px;"><?= e($row['new_value'] ?? '—') ?></td>
                            <td class="font-monospace"><?= e($row['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['total_pages'] > 1): ?>
            <nav class="p-3 border-top d-flex justify-content-between align-items-center" aria-label="Audit pagination">
                <span class="small text-secondary">Page <?= (int) $result['page'] ?> of <?= (int) $result['total_pages'] ?></span>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $result['page'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= max(1, $result['page'] - 1) ?>">Previous</a>
                    </li>
                    <li class="page-item <?= $result['page'] >= $result['total_pages'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= min($result['total_pages'], $result['page'] + 1) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/views/partials/admin_footer.php'; ?>

