<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Models\Category;
use App\Models\Member;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\Validator;

$currentAdmin = Auth::requireAdmin();

$memberId = (int) ($_GET['id'] ?? 0);
$member   = $memberId > 0 ? Member::findById($memberId) : null;

if ($member === null) {
    http_response_code(404);
    $pageTitle = 'Member Not Found';
    $activeNav = 'members';
    require BASE_PATH . '/app/views/partials/admin_header.php';
    echo '<div class="portal-card p-5 text-center">'
        . '<h1 class="h3 mb-2">404 — Member Not Found</h1>'
        . '<p class="text-secondary mb-3">The requested registration record does not exist.</p>'
        . '<a href="/admin/members.php" class="btn btn-primary">Back to Members</a>'
        . '</div>';
    require BASE_PATH . '/app/views/partials/admin_footer.php';
    exit;
}

$actionRes  = AdminController::handleMemberActions($memberId, $currentAdmin);
$member     = Member::findById($memberId); // reload updated row
$categories = Category::all();

$returnQs = trim((string) ($_GET['return'] ?? ''));
// Ensure safe internal return query string only (SEC-10)
$backUrl = '/admin/members.php' . ($returnQs !== '' && !str_contains($returnQs, '://') ? ('?' . $returnQs) : '');

$docsByType = [];
foreach ($member['documents'] ?? [] as $doc) {
    $docsByType[$doc['doc_type']] = $doc;
}

$pageTitle = 'Member ' . $member['reg_no'];
$activeNav = 'members';
require BASE_PATH . '/app/views/partials/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <a href="<?= e($backUrl) ?>" class="small text-decoration-none d-inline-block mb-1">&larr; Back to Members</a>
        <h1 class="mb-0 d-flex align-items-center gap-2 flex-wrap">
            <span><?= e($member['full_name']) ?></span>
            <span class="badge badge-status-<?= e($member['status']) ?> text-capitalize fs-6"><?= e($member['status']) ?></span>
        </h1>
        <div class="font-monospace small text-secondary mt-1"><?= e($member['reg_no']) ?></div>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm" id="toggleEditBtn">Edit Details</button>
</div>

<?php if ($actionRes['error'] !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($actionRes['error']) ?></div>
<?php endif; ?>
<?php if ($actionRes['success'] !== ''): ?>
    <div class="alert alert-success" role="alert"><?= e($actionRes['success']) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Member Details + Edit Form -->
    <div class="col-lg-7">
        <div class="portal-card p-4 mb-4">
            <h2 class="h5 mb-3 pb-2 border-bottom">Application Details</h2>
            <dl class="row mb-0 small">
                <dt class="col-sm-4 text-secondary">Full Name</dt>
                <dd class="col-sm-8 fw-medium"><?= e($member['full_name']) ?></dd>

                <dt class="col-sm-4 text-secondary">Father / Spouse</dt>
                <dd class="col-sm-8"><?= e($member['father_spouse_name']) ?></dd>

                <dt class="col-sm-4 text-secondary">Date of Birth</dt>
                <dd class="col-sm-8"><?= e($member['dob']) ?></dd>

                <dt class="col-sm-4 text-secondary">Gender</dt>
                <dd class="col-sm-8 text-capitalize"><?= e($member['gender']) ?></dd>

                <dt class="col-sm-4 text-secondary">Email</dt>
                <dd class="col-sm-8"><?= e($member['email']) ?></dd>

                <dt class="col-sm-4 text-secondary">Mobile</dt>
                <dd class="col-sm-8"><?= e($member['mobile']) ?></dd>

                <dt class="col-sm-4 text-secondary">Address</dt>
                <dd class="col-sm-8">
                    <?= e($member['address']) ?><br>
                    <?= e($member['city']) ?>, <?= e($member['state']) ?> — <?= e($member['pincode']) ?>
                </dd>

                <dt class="col-sm-4 text-secondary">Council Category</dt>
                <dd class="col-sm-8"><?= e($member['category_name']) ?> (<?= e($member['category_code']) ?>)</dd>

                <dt class="col-sm-4 text-secondary">ID Proof</dt>
                <dd class="col-sm-8"><?= e($member['id_proof_type']) ?>: <span class="font-monospace"><?= e($member['id_proof_no']) ?></span></dd>

                <dt class="col-sm-4 text-secondary">Submitted At</dt>
                <dd class="col-sm-8"><?= e($member['created_at']) ?></dd>

                <?php if (!empty($member['reviewed_at'])): ?>
                    <dt class="col-sm-4 text-secondary">Reviewed</dt>
                    <dd class="col-sm-8">
                        <?= e($member['reviewed_at']) ?>
                        <?php if (!empty($member['reviewer_name'])): ?>
                            by <?= e($member['reviewer_name']) ?>
                        <?php endif; ?>
                    </dd>
                <?php endif; ?>

                <?php if (!empty($member['decision_reason'])): ?>
                    <dt class="col-sm-4 text-secondary">Remarks / Reason</dt>
                    <dd class="col-sm-8"><?= e($member['decision_reason']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Edit Member Form (A7, MEM-12, MEM-13) -->
        <div class="portal-card p-4 <?= empty($actionRes['editErrors']) ? 'd-none' : '' ?>" id="editMemberCard">
            <h2 class="h5 mb-3 pb-2 border-bottom">Edit Member Details</h2>
            <form method="post" action="/admin/member.php?id=<?= (int) $member['id'] ?>&amp;return=<?= e(urlencode($returnQs)) ?>">
                <?= Csrf::field('admin') ?>
                <input type="hidden" name="action" value="edit">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="edit_full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control <?= isset($actionRes['editErrors']['full_name']) ? 'is-invalid' : '' ?>" id="edit_full_name" name="full_name" value="<?= e($_POST['full_name'] ?? $member['full_name']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['full_name'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_father" class="form-label">Father / Spouse Name</label>
                        <input type="text" class="form-control <?= isset($actionRes['editErrors']['father_spouse_name']) ? 'is-invalid' : '' ?>" id="edit_father" name="father_spouse_name" value="<?= e($_POST['father_spouse_name'] ?? $member['father_spouse_name']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['father_spouse_name'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_dob" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control <?= isset($actionRes['editErrors']['dob']) ? 'is-invalid' : '' ?>" id="edit_dob" name="dob" value="<?= e($_POST['dob'] ?? $member['dob']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['dob'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_gender" class="form-label">Gender</label>
                        <select class="form-select" id="edit_gender" name="gender" required>
                            <?php $selG = (string) ($_POST['gender'] ?? $member['gender']); ?>
                            <?php foreach (Validator::ALLOWED_GENDERS as $g): ?>
                                <option value="<?= e($g) ?>" <?= $selG === $g ? 'selected' : '' ?>><?= e(ucfirst($g)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control <?= isset($actionRes['editErrors']['email']) ? 'is-invalid' : '' ?>" id="edit_email" name="email" value="<?= e($_POST['email'] ?? $member['email']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['email'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_mobile" class="form-label">Mobile</label>
                        <input type="text" class="form-control <?= isset($actionRes['editErrors']['mobile']) ? 'is-invalid' : '' ?>" id="edit_mobile" name="mobile" value="<?= e($_POST['mobile'] ?? $member['mobile']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['mobile'] ?? '') ?></div>
                    </div>
                    <div class="col-12">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control <?= isset($actionRes['editErrors']['address']) ? 'is-invalid' : '' ?>" id="edit_address" name="address" rows="2" required><?= e($_POST['address'] ?? $member['address']) ?></textarea>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['address'] ?? '') ?></div>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_city" class="form-label">City</label>
                        <input type="text" class="form-control <?= isset($actionRes['editErrors']['city']) ? 'is-invalid' : '' ?>" id="edit_city" name="city" value="<?= e($_POST['city'] ?? $member['city']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['city'] ?? '') ?></div>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_state" class="form-label">State</label>
                        <input type="text" class="form-control <?= isset($actionRes['editErrors']['state']) ? 'is-invalid' : '' ?>" id="edit_state" name="state" value="<?= e($_POST['state'] ?? $member['state']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['state'] ?? '') ?></div>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_pincode" class="form-label">PIN Code</label>
                        <input type="text" class="form-control <?= isset($actionRes['editErrors']['pincode']) ? 'is-invalid' : '' ?>" id="edit_pincode" name="pincode" value="<?= e($_POST['pincode'] ?? $member['pincode']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['pincode'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_category" class="form-label">Category</label>
                        <select class="form-select" id="edit_category" name="category_id" required>
                            <?php $selCat = (string) ($_POST['category_id'] ?? $member['category_id']); ?>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" <?= $selCat === (string) $cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="edit_id_type" class="form-label">ID Type</label>
                        <select class="form-select" id="edit_id_type" name="id_proof_type" required>
                            <?php $selIdType = (string) ($_POST['id_proof_type'] ?? $member['id_proof_type']); ?>
                            <?php foreach (Validator::ALLOWED_ID_TYPES as $t): ?>
                                <option value="<?= e($t) ?>" <?= $selIdType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="edit_id_no" class="form-label">ID No</label>
                        <input type="text" class="form-control <?= isset($actionRes['editErrors']['id_proof_no']) ? 'is-invalid' : '' ?>" id="edit_id_no" name="id_proof_no" value="<?= e($_POST['id_proof_no'] ?? $member['id_proof_no']) ?>" required>
                        <div class="invalid-feedback"><?= e($actionRes['editErrors']['id_proof_no'] ?? '') ?></div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Document Previews & Approve/Reject -->
    <div class="col-lg-5">
        <div class="portal-card p-4 mb-4">
            <h2 class="h5 mb-3 pb-2 border-bottom">Uploaded Documents</h2>

            <?php if (isset($docsByType['photo'])): $photoDoc = $docsByType['photo']; ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold">Passport Photo</span>
                        <a href="/admin/file.php?id=<?= (int) $photoDoc['id'] ?>" class="btn btn-outline-secondary btn-sm">Download</a>
                    </div>
                    <img src="/admin/file.php?id=<?= (int) $photoDoc['id'] ?>&amp;mode=inline" alt="Applicant passport photo" class="img-thumbnail" style="max-height:180px;">
                </div>
            <?php endif; ?>

            <?php if (isset($docsByType['id_proof'])): $idDoc = $docsByType['id_proof']; ?>
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold">ID Proof (<?= e($idDoc['mime_type']) ?>)</span>
                        <a href="/admin/file.php?id=<?= (int) $idDoc['id'] ?>" class="btn btn-outline-secondary btn-sm">Download</a>
                    </div>
                    <?php if (str_starts_with((string) $idDoc['mime_type'], 'image/')): ?>
                        <img src="/admin/file.php?id=<?= (int) $idDoc['id'] ?>&amp;mode=inline" alt="Applicant ID proof" class="img-thumbnail w-100">
                    <?php else: ?>
                        <iframe src="/admin/file.php?id=<?= (int) $idDoc['id'] ?>&amp;mode=inline" title="ID Proof PDF Preview" class="w-100 border rounded" style="height:260px;"></iframe>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="portal-card p-4">
            <h2 class="h5 mb-3 pb-2 border-bottom">Review Decision</h2>
            <form method="post" action="/admin/member.php?id=<?= (int) $member['id'] ?>&amp;return=<?= e(urlencode($returnQs)) ?>" id="decisionForm">
                <?= Csrf::field('admin') ?>
                <div class="mb-3">
                    <label for="decision_reason" class="form-label">Decision Remarks / Rejection Reason</label>
                    <textarea class="form-control" id="decision_reason" name="decision_reason" rows="3" maxlength="500" placeholder="Required when rejecting; optional remarks for approval"><?= e($member['decision_reason'] ?? '') ?></textarea>
                    <div class="invalid-feedback" id="decision_reason_error"></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="action" value="approve" class="btn btn-success flex-grow-1" id="approveBtn">Approve</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger flex-grow-1" id="rejectBtn">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/app/views/partials/admin_footer.php'; ?>

