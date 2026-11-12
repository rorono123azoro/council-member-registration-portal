<?php

declare(strict_types=1);

$pageTitle   = $pageTitle ?? appConfig('name');
$councilName = (string) appConfig('council_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($councilName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="/assets/css/custom.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<div class="site-top-bar no-print"></div>
<header class="site-header py-3 no-print">
    <div class="container portal-container d-flex align-items-center justify-content-between gap-3">
        <a href="/index.php" class="d-flex align-items-center gap-3 text-decoration-none text-dark">
            <img src="/assets/img/logo.svg" alt="<?= e($councilName) ?> Emblem" width="40" height="40">
            <div>
                <div class="fw-semibold lh-sm" style="color:#1f4e79;"><?= e($councilName) ?></div>
                <div class="small text-secondary">Official Member Registration Portal</div>
            </div>
        </a>
        <a href="/admin/login.php" class="btn btn-outline-primary btn-sm px-3">Admin Login</a>
    </div>
</header>
<main class="flex-grow-1">

