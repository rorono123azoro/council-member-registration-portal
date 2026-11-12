<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\AuditLog;
use App\Models\Category;
use App\Services\Auth;
use App\Services\Csrf;

$currentAdmin = Auth::requireAdmin('super_admin');
$error   = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::validateRequest('admin')) {
        http_response_code(403);
        $error = 'Security token mismatch.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $name   = trim((string) ($_POST['name'] ?? ''));
        $code   = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $desc   = trim((string) ($_POST['description'] ?? ''));
        $active = !empty($_POST['is_active']) ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 120 || !preg_match('/^[A-Za-z0-9\s\-\/&()]+$/', $code) || strlen($code) < 2 || strlen($code) > 20) {
            http_response_code(422);
            $error = 'Provide a valid category name (max 120 chars) and alphanumeric code (2–20 chars).';
        } elseif ($action === 'create') {
            if (Category::codeExists($code)) {
                http_response_code(409);
                $error = 'Category code already exists.';
            } else {
                $newId = Category::create($name, $code, $desc, 1);
                AuditLog::record((int) $currentAdmin['id'], 'category.created', 'category', $newId, null, ['name' => $name, 'code' => $code], clientIp());
                $success = 'Category added.';
            }
        } elseif ($action === 'update') {
            $id  = (int) ($_POST['id'] ?? 0);
            $old = $id > 0 ? Category::find($id) : null;
            if ($old === null) {
                http_response_code(404);
                $error = 'Category not found.';
            } elseif (Category::codeExists($code, $id)) {
                http_response_code(409);
                $error = 'Category code already exists.';
            } else {
                Category::update($id, $name, $code, $desc, $active);
                AuditLog::record((int) $currentAdmin['id'], 'category.updated', 'category', $id, $old, ['name' => $name, 'code' => $code, 'is_active' => $active], clientIp());
                $success = 'Category updated.';
            }
        }
    }
}

$categories = Category::all();

$pageTitle = 'Categories';
$activeNav = 'categories';
require BASE_PATH . '/app/views/partials/admin_header.php';
?>
<h1 class="mb-4">Council Categories</h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="portal-card p-4">
            <h2 class="h5 mb-3">Add Category</h2>
            <form method="post" action="/admin/categories.php">
                <?= Csrf::field('admin') ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label for="cat_name" class="form-label">Category Name <span class="required-mark">*</span></label>
                    <input type="text" class="form-control" id="cat_name" name="name" maxlength="120" required>
                </div>
                <div class="mb-3">
                    <label for="cat_code" class="form-label">Short Code <span class="required-mark">*</span></label>
                    <input type="text" class="form-control" id="cat_code" name="code" maxlength="20" required>
                </div>
                <div class="mb-3">
                    <label for="cat_desc" class="form-label">Description</label>
                    <input type="text" class="form-control" id="cat_desc" name="description" maxlength="255">
                </div>
                <button type="submit" class="btn btn-primary w-100">Add Category</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="portal-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Members</th>
                            <th>Status</th>
                            <th class="text-end">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <form method="post" action="/admin/categories.php">
                                    <?= Csrf::field('admin') ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                    <input type="hidden" name="description" value="<?= e($cat['description'] ?? '') ?>">
                                    <td><input type="text" class="form-control form-control-sm font-monospace" name="code" value="<?= e($cat['code']) ?>" aria-label="Category code" style="width:90px;"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="name" value="<?= e($cat['name']) ?>" aria-label="Category name"></td>
                                    <td><?= (int) $cat['member_count'] ?></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active_<?= (int) $cat['id'] ?>" <?= (int) $cat['is_active'] === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="active_<?= (int) $cat['id'] ?>"><?= (int) $cat['is_active'] === 1 ? 'Active' : 'Inactive' ?></label>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/app/views/partials/admin_footer.php'; ?>

