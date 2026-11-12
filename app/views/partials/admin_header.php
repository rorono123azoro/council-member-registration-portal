<?php

declare(strict_types=1);

use App\Services\Csrf;

$pageTitle    = $pageTitle ?? 'Admin Panel';
$activeNav    = $activeNav ?? '';
$currentAdmin = $currentAdmin ?? ['name' => 'Admin', 'role' => 'reviewer'];
$councilName  = (string) appConfig('council_name');
$isSuperAdmin = ($currentAdmin['role'] ?? '') === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — Admin | <?= e($councilName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="/assets/css/custom.css" rel="stylesheet">
</head>
<body>
<div class="admin-shell d-flex flex-column flex-lg-row">
    <!-- Mobile Topbar -->
    <header class="d-lg-none bg-white border-bottom px-3 py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <img src="/assets/img/logo.svg" alt="Council Emblem" width="32" height="32">
            <span class="fw-semibold small" style="color:#1f4e79;">Council Admin</span>
        </div>
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebarOffcanvas" aria-controls="adminSidebarOffcanvas" aria-label="Toggle navigation menu">
            Menu
        </button>
    </header>

    <!-- Desktop Sidebar & Mobile Offcanvas -->
    <aside class="offcanvas-lg offcanvas-start admin-sidebar text-white d-flex flex-column" tabindex="-1" id="adminSidebarOffcanvas" aria-label="Admin Navigation">
        <div class="offcanvas-header border-bottom border-light border-opacity-25 d-lg-none">
            <h2 class="offcanvas-title h6 mb-0">Admin Navigation</h2>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebarOffcanvas" aria-label="Close"></button>
        </div>
        <div class="p-3 border-bottom border-light border-opacity-25 d-none d-lg-flex align-items-center gap-2">
            <img src="/assets/img/logo.svg" alt="Council Emblem" width="36" height="36">
            <div>
                <div class="fw-semibold small lh-sm">Council Portal</div>
                <div class="text-white-50" style="font-size:0.75rem;">Admin Console</div>
            </div>
        </div>
        <div class="offcanvas-body d-flex flex-column p-3 flex-grow-1">
            <nav class="nav flex-column gap-1 flex-grow-1">
                <a class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="/admin/dashboard.php">Dashboard</a>
                <a class="nav-link <?= $activeNav === 'members' ? 'active' : '' ?>" href="/admin/members.php">Members</a>
                <?php if ($isSuperAdmin): ?>
                    <a class="nav-link <?= $activeNav === 'categories' ? 'active' : '' ?>" href="/admin/categories.php">Categories</a>
                    <a class="nav-link <?= $activeNav === 'admins' ? 'active' : '' ?>" href="/admin/admins.php">Admins</a>
                    <a class="nav-link <?= $activeNav === 'audit' ? 'active' : '' ?>" href="/admin/audit.php">Audit Log</a>
                <?php endif; ?>
                <a class="nav-link <?= $activeNav === 'password' ? 'active' : '' ?>" href="/admin/change-password.php">Change Password</a>
            </nav>
            <div class="pt-3 border-top border-light border-opacity-25 mt-3">
                <div class="small text-white-50 mb-2">
                    Signed in as <strong class="text-white"><?= e($currentAdmin['name']) ?></strong><br>
                    <span class="badge bg-light text-dark mt-1"><?= e($currentAdmin['role']) ?></span>
                </div>
                <form method="post" action="/admin/logout.php">
                    <?= Csrf::field('admin') ?>
                    <button type="submit" class="btn btn-outline-light btn-sm w-100">Logout</button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-grow-1 p-3 p-md-4">
        <div class="container-fluid px-0" style="max-width:1200px;">

