<?php

declare(strict_types=1);

use App\Services\Captcha;
use App\Services\Csrf;

require BASE_PATH . '/app/views/partials/header.php';
?>
<section class="hero-strip py-4 mb-4">
    <div class="container portal-container">
        <div class="form-card-container mx-auto">
            <h1 class="mb-1">Council Member Registration</h1>
            <p class="mb-0 opacity-75">Complete the four steps below to submit your membership application. Takes about 5 minutes.</p>
        </div>
    </div>
</section>

<div class="container portal-container pb-4">
    <div class="form-card-container mx-auto">

        <noscript>
            <div class="alert alert-warning" role="alert">
                JavaScript is required for multi-step navigation and document upload validation. Please enable JavaScript in your browser.
            </div>
        </noscript>

        <!-- Stepper & Progress Bar -->
        <div class="mb-3" aria-label="Registration progress">
            <div class="progress mb-3" style="height: 6px;">
                <div class="progress-bar bg-primary" id="regProgressBar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="stepper" id="regStepper">
                <div class="step-pill is-active" data-step-pill="1">
                    <div class="step-num">1</div>
                    <div>Personal</div>
                </div>
                <div class="step-pill" data-step-pill="2">
                    <div class="step-num">2</div>
                    <div>Contact &amp; Address</div>
                </div>
                <div class="step-pill" data-step-pill="3">
                    <div class="step-num">3</div>
                    <div>Council Details</div>
                </div>
                <div class="step-pill" data-step-pill="4">
                    <div class="step-num">4</div>
                    <div>Documents &amp; Submit</div>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="portal-card p-3 p-md-4">
            <div id="formErrorSummary" class="alert alert-danger d-none" role="alert" aria-live="assertive"></div>

            <form id="registerForm" method="post" action="/api/register.php" enctype="multipart/form-data" novalidate>
                <?= Csrf::field('register') ?>
                <?= Captcha::honeypotField() ?>

                <!-- STEP 1: Personal -->
                <div class="form-step" data-step="1">
                    <h2 class="h4 mb-3 pb-2 border-bottom">Step 1: Personal Information</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" maxlength="120" required autocomplete="name" aria-describedby="full_name_error">
                            <div class="invalid-feedback" id="full_name_error"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="father_spouse_name" class="form-label">Father / Spouse Name <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="father_spouse_name" name="father_spouse_name" maxlength="120" required aria-describedby="father_spouse_name_error">
                            <div class="invalid-feedback" id="father_spouse_name_error"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="dob" class="form-label">Date of Birth <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="date" class="form-control" id="dob" name="dob" required aria-describedby="dob_help dob_error">
                            <div class="form-text" id="dob_help">Applicant must be at least 18 years old.</div>
                            <div class="invalid-feedback" id="dob_error"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender <span class="required-mark" aria-hidden="true">*</span></label>
                            <select class="form-select" id="gender" name="gender" required aria-describedby="gender_error">
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            <div class="invalid-feedback" id="gender_error"></div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Contact & Address -->
                <div class="form-step d-none" data-step="2">
                    <h2 class="h4 mb-3 pb-2 border-bottom">Step 2: Contact &amp; Address</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" maxlength="150" required autocomplete="email" aria-describedby="email_status email_error">
                            <div class="form-text" id="email_status"></div>
                            <div class="invalid-feedback" id="email_error"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile Number <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="tel" class="form-control" id="mobile" name="mobile" inputmode="numeric" maxlength="14" placeholder="10-digit mobile number" required autocomplete="tel" aria-describedby="mobile_status mobile_error">
                            <div class="form-text" id="mobile_status">10 digits starting with 6–9.</div>
                            <div class="invalid-feedback" id="mobile_error"></div>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Residential Address <span class="required-mark" aria-hidden="true">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" maxlength="500" required autocomplete="street-address" aria-describedby="address_error"></textarea>
                            <div class="invalid-feedback" id="address_error"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="city" class="form-label">City / Town <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="city" name="city" maxlength="80" required autocomplete="address-level2" aria-describedby="city_error">
                            <div class="invalid-feedback" id="city_error"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="state" class="form-label">State <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="state" name="state" maxlength="80" required autocomplete="address-level1" aria-describedby="state_error">
                            <div class="invalid-feedback" id="state_error"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="pincode" class="form-label">PIN Code <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="pincode" name="pincode" inputmode="numeric" maxlength="6" required autocomplete="postal-code" aria-describedby="pincode_error">
                            <div class="invalid-feedback" id="pincode_error"></div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Council Details -->
                <div class="form-step d-none" data-step="3">
                    <h2 class="h4 mb-3 pb-2 border-bottom">Step 3: Council Details</h2>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="category_id" class="form-label">Council Category <span class="required-mark" aria-hidden="true">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required aria-describedby="category_id_error">
                                <option value="">Select council category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>">
                                        <?= e($cat['name']) ?> (<?= e($cat['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" id="category_id_error"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="id_proof_type" class="form-label">ID Proof Type <span class="required-mark" aria-hidden="true">*</span></label>
                            <select class="form-select" id="id_proof_type" name="id_proof_type" required aria-describedby="id_proof_type_error">
                                <?php foreach ($idProofTypes as $type): ?>
                                    <option value="<?= e($type) ?>"><?= e($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" id="id_proof_type_error"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="id_proof_no" class="form-label">ID Proof Number <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="id_proof_no" name="id_proof_no" maxlength="60" required aria-describedby="id_proof_no_error">
                            <div class="invalid-feedback" id="id_proof_no_error"></div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Documents & Declaration -->
                <div class="form-step d-none" data-step="4">
                    <h2 class="h4 mb-3 pb-2 border-bottom">Step 4: Documents &amp; Declaration</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="photo" class="form-label">Passport Photo (JPG / PNG, max 2 MB) <span class="required-mark" aria-hidden="true">*</span></label>
                            <div class="upload-zone" id="photoZone">
                                <input type="file" class="form-control" id="photo" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required aria-describedby="photo_error">
                                <div id="photoPreview" class="d-none mt-2 d-flex align-items-center gap-3">
                                    <img id="photoThumb" src="" alt="Uploaded passport photo preview" class="upload-preview-thumb">
                                    <div class="small flex-grow-1 text-truncate">
                                        <div class="fw-medium text-truncate" id="photoName"></div>
                                        <div class="text-secondary" id="photoSize"></div>
                                    </div>
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="removePhotoBtn">Remove</button>
                                </div>
                            </div>
                            <div class="invalid-feedback d-block" id="photo_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="id_proof" class="form-label">ID Proof Document (PDF / JPG / PNG, max 2 MB) <span class="required-mark" aria-hidden="true">*</span></label>
                            <div class="upload-zone" id="idProofZone">
                                <input type="file" class="form-control" id="id_proof" name="id_proof" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required aria-describedby="id_proof_error">
                                <div id="idProofPreview" class="d-none mt-2 d-flex align-items-center gap-3">
                                    <div class="small flex-grow-1 text-truncate">
                                        <div class="fw-medium text-truncate" id="idProofName"></div>
                                        <div class="text-secondary" id="idProofSize"></div>
                                    </div>
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="removeIdProofBtn">Remove</button>
                                </div>
                            </div>
                            <div class="invalid-feedback d-block" id="id_proof_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="captcha_answer" class="form-label">Verification: <?= e($captchaQuestion) ?> <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="captcha_answer" name="captcha_answer" inputmode="numeric" maxlength="4" required aria-describedby="captcha_answer_error">
                            <div class="invalid-feedback" id="captcha_answer_error"></div>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="p-3 rounded border bg-light">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="consent" name="consent" required aria-describedby="consent_error">
                                    <label class="form-check-label small" for="consent">
                                        I hereby declare that all details and documents provided in this registration are true and accurate to the best of my knowledge, and I consent to their verification by the Council Secretariat. <span class="required-mark" aria-hidden="true">*</span>
                                    </label>
                                    <div class="invalid-feedback" id="consent_error"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Footer -->
                <div class="d-flex flex-column-reverse flex-sm-row justify-content-between gap-2 mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-outline-secondary d-none" id="prevBtn">Back</button>
                    <div class="ms-sm-auto d-flex gap-2 w-100 w-sm-auto justify-content-end">
                        <button type="button" class="btn btn-primary w-100 w-sm-auto px-4" id="nextBtn">Next</button>
                        <button type="submit" class="btn btn-primary w-100 w-sm-auto px-4 d-none" id="submitBtn">
                            <span class="spinner-border spinner-border-sm d-none" id="submitSpinner" role="status" aria-hidden="true"></span>
                            <span id="submitBtnText">Submit Registration</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="module" src="/assets/js/register.js"></script>
<?php require BASE_PATH . '/app/views/partials/footer.php'; ?>

