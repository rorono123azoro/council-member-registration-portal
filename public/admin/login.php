<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Services\Csrf;

$sessionExpired = !empty($_SESSION['session_expired']);
unset($_SESSION['session_expired']);

$result = AdminController::handleLogin();
$error = $result['error'];
$identifier = $result['identifier'];
$councilName = (string) appConfig('council_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= e($councilName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="/assets/css/custom.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<div class="site-top-bar"></div>
<main class="flex-grow-1 d-flex align-items-center justify-content-center p-3">
    <div class="portal-card p-4 p-md-5 w-100" style="max-width: 440px;">
        <div class="text-center mb-4">
            <img src="/assets/img/logo.svg" alt="<?= e($councilName) ?> Emblem" width="48" height="48" class="mb-2">
            <h1 class="h3 mb-1">Admin Sign In</h1>
            <p class="small text-secondary mb-0"><?= e($councilName) ?></p>
        </div>

        <?php if ($sessionExpired): ?>
            <div class="alert alert-warning small" role="alert">
                Your session expired due to inactivity. Please sign in again.
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger small" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/admin/login.php" novalidate>
            <?= Csrf::field('admin_login') ?>
            <div class="mb-3">
                <label for="identifier" class="form-label">Username or Email <span class="required-mark">*</span></label>
                <input type="text" class="form-control" id="identifier" name="identifier" value="<?= e($identifier) ?>" required autofocus autocomplete="username">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password <span class="required-mark">*</span></label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Sign In</button>
            <div class="text-center">
                <a href="/index.php" class="small text-decoration-none">&larr; Back to Registration Portal</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>

