<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Services\Auth;
use App\Services\Csrf;

$currentAdmin = Auth::requireAdmin(null, true);
$res = AdminController::handleChangePassword($currentAdmin);
$mustChange = ((int) ($_SESSION['admin_user']['must_change_password'] ?? 0)) === 1;

$pageTitle = 'Change Password';
$activeNav = 'password';
require BASE_PATH . '/app/views/partials/admin_header.php';
?>
<h1 class="mb-3">Change Password</h1>

<?php if ($mustChange): ?>
    <div class="alert alert-warning" role="alert">
        You are using a temporary password. You must set a new password before accessing the admin console.
    </div>
<?php endif; ?>

<?php if ($res['error'] !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($res['error']) ?></div>
<?php endif; ?>

<?php if ($res['success'] !== ''): ?>
    <div class="alert alert-success" role="alert">
        <?= e($res['success']) ?> <a href="/admin/dashboard.php" class="alert-link">Go to Dashboard</a>.
    </div>
<?php endif; ?>

<div class="portal-card p-4" style="max-width: 520px;">
    <form method="post" action="/admin/change-password.php">
        <?= Csrf::field('admin') ?>
        <div class="mb-3">
            <label for="current_password" class="form-label">Current Password <span class="required-mark">*</span></label>
            <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="mb-3">
            <label for="new_password" class="form-label">New Password <span class="required-mark">*</span></label>
            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
            <div class="form-text">Minimum 8 characters with uppercase, lowercase, and a number.</div>
        </div>
        <div class="mb-4">
            <label for="confirm_password" class="form-label">Confirm New Password <span class="required-mark">*</span></label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/admin_footer.php'; ?>

