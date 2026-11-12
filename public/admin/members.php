<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Models\Category;
use App\Models\Member;
use App\Services\Auth;

$currentAdmin = Auth::requireAdmin();
$filters      = AdminController::extractMemberFilters();
$page         = max(1, (int) ($_GET['page'] ?? 1));
$result       = Member::paginate($filters, $page, 20);
$categories   = Category::all();

// Build query string helper preserving filters (ADM-14)
$buildQuery = static function (array $overrides = []) use ($filters): string {
    $merged = array_merge($filters, $overrides);
    $clean  = array_filter($merged, static fn(mixed $v): bool => $v !== '' && $v !== null);
    return http_build_query($clean);
};

$currentFilterQs = $buildQuery(['page' => $result['page']]);

$sortLink = static function (string $col, string $label) use ($result, $buildQuery): string {
    $isCurrent = $result['sort'] === $col;
    $nextDir   = ($isCurrent && $result['dir'] === 'asc') ? 'desc' : 'asc';
    $arrow     = $isCurrent ? ($result['dir'] === 'asc' ? ' &uarr;' : ' &darr;') : '';
    $qs        = $buildQuery(['sort' => $col, 'dir' => $nextDir, 'page' => 1]);
    return '<a class="text-decoration-none text-dark fw-semibold" href="?' . e($qs) . '">' . e($label) . $arrow . '</a>';
};

$pageTitle = 'Members';
$activeNav = 'members';
require BASE_PATH . '/app/views/partials/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="mb-0">Members</h1>
        <div class="small text-secondary"><?= (int) $result['total'] ?> matching application(s)</div>
    </div>
    <a href="/admin/export.php?<?= e($buildQuery()) ?>" class="btn btn-outline-primary btn-sm px-3">Export CSV</a>
</div>

<!-- Filters -->
<form method="get" action="/admin/members.php" class="portal-card p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label for="q" class="form-label">Search</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, email, mobile, or reg no">
        </div>
        <div class="col-6 col-md-2">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'approved', 'rejected'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="category_id" class="form-label">Category</label>
            <select class="form-select" id="category_id" name="category_id">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= (string) $cat['id'] === $filters['category_id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="date_from" class="form-label">From Date</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="date_to" class="form-label">To Date</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
            <a href="/admin/members.php" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
            <button type="submit" class="btn btn-primary btn-sm px-4">Apply Filters</button>
        </div>
    </div>
</form>

<!-- Table -->
<div class="portal-card">
    <?php if ($result['items'] === []): ?>
        <div class="p-5 text-center text-secondary">
            <div class="fw-medium mb-1">No members found</div>
            <div class="small">Try adjusting your search or filter criteria.</div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= $sortLink('reg_no', 'Reg. No') ?></th>
                        <th><?= $sortLink('full_name', 'Applicant') ?></th>
                        <th>Mobile</th>
                        <th><?= $sortLink('category', 'Category') ?></th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th><?= $sortLink('created_at', 'Submitted') ?></th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['items'] as $row): ?>
                        <tr>
                            <td class="font-monospace small fw-semibold"><?= e($row['reg_no']) ?></td>
                            <td>
                                <div class="fw-medium"><?= e($row['full_name']) ?></div>
                                <div class="small text-secondary"><?= e($row['email']) ?></div>
                            </td>
                            <td class="small"><?= e($row['mobile']) ?></td>
                            <td class="small"><?= e($row['category_name']) ?></td>
                            <td>
                                <span class="badge badge-status-<?= e($row['status']) ?> text-capitalize">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td class="small text-secondary"><?= e($row['created_at']) ?></td>
                            <td class="text-end">
                                <a href="/admin/member.php?id=<?= (int) $row['id'] ?>&amp;return=<?= e(urlencode($currentFilterQs)) ?>" class="btn btn-outline-primary btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['total_pages'] > 1): ?>
            <nav class="p-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2" aria-label="Members pagination">
                <div class="small text-secondary">
                    Page <?= (int) $result['page'] ?> of <?= (int) $result['total_pages'] ?>
                </div>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $result['page'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= e($buildQuery(['page' => max(1, $result['page'] - 1)])) ?>">Previous</a>
                    </li>
                    <?php
                    $startP = max(1, $result['page'] - 2);
                    $endP   = min($result['total_pages'], $result['page'] + 2);
                    for ($p = $startP; $p <= $endP; $p++):
                    ?>
                        <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= e($buildQuery(['page' => $p])) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $result['page'] >= $result['total_pages'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= e($buildQuery(['page' => min($result['total_pages'], $result['page'] + 1)])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/views/partials/admin_footer.php'; ?>

