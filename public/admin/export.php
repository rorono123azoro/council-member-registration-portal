<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Services\Auth;

$currentAdmin = Auth::requireAdmin();
$filters      = AdminController::extractMemberFilters();

AdminController::streamCsvExport($filters, $currentAdmin);

