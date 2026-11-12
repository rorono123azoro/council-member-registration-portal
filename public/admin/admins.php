<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\Admin;
use App\Models\AuditLog;
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

        if ($action === 'create') {
            $name     = trim((string) ($_POST['name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email    = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role     = (string) ($_POST['role'] ?? 'reviewer');

            if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                http_response_code(422);
                $error = 'Provide a valid name, username (3–60 alphanumeric), email, and password (min 8 chars).';
            } elseif (Admin::existsByUsernameOrEmail($username, $email)) {
                http_response_code(409);
                $error = 'An admin with that username or email already exists.';
            } else {
                $newId = Admin::create($name, $username, $email, $password, $role, 1);
                AuditLog::record((int) $currentAdmin['id'], 'admin.created', 'admin', $newId, null, ['username' => $username, 'role' => $role], clientIp());
                $success = 'Admin account created (password change required on first login).';
            }
        } elseif ($action === 'update') {
            $id       = (int) ($_POST['id'] ?? 0);
            $newRole  = (string) ($_POST['role'] ?? 'reviewer');
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            $target   = $id > 0 ? Admin::findById($id) : null;

            if ($target === null) {
                http_response_code(404);
                $error = 'Admin account not found.';
            } elseif (
                $target['role'] === 'super_admin'
                && (int) $target['is_active'] === 1
                && ($newRole !== 'super_admin' || $isActive === 0)
                && Admin::countActiveSuperAdmins() <= 1
            ) {
                // MEM-20: cannot remove/demote last active super admin
                http_response_code(422);
                $error = 'Cannot deactivate or demote the last active Super Admin.';
            } else {
                Admin::updateRoleAndStatus($id, $newRole, $isActive);
                AuditLog::record(
                    (int) $currentAdmin['id'],
                    'admin.updated',
                    'admin',
                    $id,
                    ['role' => $target['role'], 'is_active' => $target['is_active']],
                    ['role' => $newRole, 'is_active' => $isActive],
                    clientIp()
                );
                $success = 'Admin account updated.';
            }
        }
    }
}

$admins = Admin::all();

$pageTitle = 'Admins';
$activeNav = 'admins';
require BASE_PATH . '/app/views/partials/admin_header.php';
?>
<h1 class="mb-4">Admin Accounts</h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="portal-card p-4">
            <h2 class="h5 mb-3">Create Admin</h2>
            <form method="post" action="/admin/admins.php">
                <?= Csrf::field('admin') ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label for="adm_name" class="form-label">Full Name <span class="required-mark">*</span></label>
                    <input type="text" class="form-control" id="adm_name" name="name" maxlength="120" required>
                </div>
                <div class="mb-3">
                    <label for="adm_username" class="form-label">Username <span class="required-mark">*</span></label>
                    <input type="text" class="form-control" id="adm_username" name="username" maxlength="60" required>
                </div>
                <div class="mb-3">
                    <label for="adm_email" class="form-label">Email <span class="required-mark">*</span></label>
                    <input type="email" class="form-control" id="adm_email" name="email" maxlength="150" required>
                </div>
                <div class="mb-3">
                    <label for="adm_role" class="form-label">Role <span class="required-mark">*</span></label>
                    <select class="form-select" id="adm_role" name="role">
                        <option value="reviewer">Reviewer</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="adm_password" class="form-label">Temporary Password <span class="required-mark">*</span></label>
                    <input type="password" class="form-control" id="adm_password" name="password" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Create Account</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="portal-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Admin</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th class="text-end">Save</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $adm): ?>
                            <tr>
                                <form method="post" action="/admin/admins.php">
                                    <?= Csrf::field('admin') ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int) $adm['id'] ?>">
                                    <td>
                                        <div class="fw-medium"><?= e($adm['name']) ?></div>
                                        <div class="text-secondary"><?= e($adm['username']) ?> &middot; <?= e($adm['email']) ?></div>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="role" aria-label="Role">
                                            <option value="reviewer" <?= $adm['role'] === 'reviewer' ? 'selected' : '' ?>>Reviewer</option>
                                            <option value="super_admin" <?= $adm['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="adm_act_<?= (int) $adm['id'] ?>" <?= (int) $adm['is_active'] === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="adm_act_<?= (int) $adm['id'] ?>"><?= (int) $adm['is_active'] === 1 ? 'Active' : 'Disabled' ?></label>
                                        </div>
                                    </td>
                                    <td class="text-secondary"><?= e($adm['last_login'] ?? 'Never') ?></td>
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

