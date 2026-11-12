<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Member;

$ref = trim((string) ($_GET['ref'] ?? ''));
if (!preg_match('/^REG-\d{4}-\d{6}$/', $ref)) {
    header('Location: /index.php');
    exit;
}

$ack = Member::findPublicAckByRegNo($ref);
if ($ack === null) {
    header('Location: /index.php');
    exit;
}

$pageTitle = 'Registration Acknowledgement';
require BASE_PATH . '/app/views/partials/header.php';
?>
<div class="container portal-container py-4 py-md-5">
    <div class="form-card-container mx-auto">
        <div class="portal-card p-4 p-md-5 text-center">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:64px;height:64px;background-color:#d1fae5;color:#2e7d4f;">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                </svg>
            </div>
            <h1 class="mb-2">Registration Submitted</h1>
            <p class="text-secondary mb-4">Your application has been recorded for review by the Council Secretariat.</p>

            <div class="mb-4">
                <div class="small text-uppercase text-secondary fw-semibold mb-1">Registration Number</div>
                <div class="reg-no-badge" id="regNoDisplay"><?= e($ack['reg_no']) ?></div>
            </div>

            <div class="table-responsive mx-auto mb-4" style="max-width: 480px;">
                <table class="table table-bordered text-start mb-0 small">
                    <tbody>
                        <tr>
                            <th class="bg-light w-40">Council</th>
                            <td><?= e(appConfig('council_name')) ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Category</th>
                            <td><?= e($ack['category_name']) ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Status</th>
                            <td>
                                <span class="badge badge-status-<?= e($ack['status']) ?> text-capitalize">
                                    <?= e($ack['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Submitted At</th>
                            <td><?= e($ack['created_at']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 no-print">
                <button type="button" class="btn btn-primary px-4" id="printAckBtn">Print Acknowledgement</button>
                <a href="/index.php" class="btn btn-outline-secondary px-4">Return to Registration Form</a>
            </div>
        </div>
    </div>
</div>
<script type="module" src="/assets/js/success.js"></script>
<?php require BASE_PATH . '/app/views/partials/footer.php'; ?>
