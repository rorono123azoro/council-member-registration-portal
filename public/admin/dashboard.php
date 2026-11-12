<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\Member;
use App\Services\Auth;

$currentAdmin = Auth::requireAdmin();
$counts = Member::countsByStatus();
$recent = Member::recent(10);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require BASE_PATH . '/app/views/partials/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="mb-0">Dashboard</h1>
    <a href="/admin/members.php" class="btn btn-primary btn-sm px-3">View All Members</a>
</div>

<!-- Stat Cards (A2) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="small text-secondary mb-1">Total Registrations</div>
            <div class="fs-2 fw-bold" style="color:#1f4e79;" id="statTotal"><?= (int) $counts['total'] ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="small text-secondary mb-1">Pending Review</div>
            <div class="fs-2 fw-bold" style="color:#d98e04;" id="statPending"><?= (int) $counts['pending'] ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="small text-secondary mb-1">Approved</div>
            <div class="fs-2 fw-bold" style="color:#2e7d4f;" id="statApproved"><?= (int) $counts['approved'] ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="admin-stat-card">
            <div class="small text-secondary mb-1">Rejected</div>
            <div class="fs-2 fw-bold" style="color:#c0392b;" id="statRejected"><?= (int) $counts['rejected'] ?></div>
        </div>
    </div>
</div>

<!-- Recent Registrations -->
<div class="portal-card">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Recent Registrations</h2>
        <a href="/admin/members.php?status=pending" class="small text-decoration-none">Review Pending &rarr;</a>
    </div>

    <?php if ($recent === []): ?>
        <div class="p-4 text-center text-secondary">No members found yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reg. No</th>
                        <th>Applicant</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td class="font-monospace small fw-semibold"><?= e($row['reg_no']) ?></td>
                            <td>
                                <div class="fw-medium"><?= e($row['full_name']) ?></div>
                                <div class="small text-secondary"><?= e($row['email']) ?></div>
                            </td>
                            <td class="small"><?= e($row['category_name']) ?></td>
                            <td>
                                <span class="badge badge-status-<?= e($row['status']) ?> text-capitalize">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td class="small text-secondary"><?= e($row['created_at']) ?></td>
                            <td class="text-end">
                                <a href="/admin/member.php?id=<?= (int) $row['id'] ?>" class="btn btn-outline-primary btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/views/partials/admin_footer.php'; ?>

