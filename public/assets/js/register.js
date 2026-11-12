import { formatBytes, normalizeMobile, validateField } from './validators.js';

const DRAFT_STORAGE_KEY = 'council_reg_draft_v1';
const STEP_FIELDS = {
    1: ['full_name', 'father_spouse_name', 'dob', 'gender'],
    2: ['email', 'mobile', 'address', 'city', 'state', 'pincode'],
    3: ['category_id', 'id_proof_type', 'id_proof_no'],
    4: ['photo', 'id_proof', 'captcha_answer', 'consent'],
};

const form = document.getElementById('registerForm');
if (form) {
    const steps = Array.from(form.querySelectorAll('.form-step'));
    const pills = Array.from(document.querySelectorAll('[data-step-pill]'));
    const progressBar = document.getElementById('regProgressBar');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const submitSpinner = document.getElementById('submitSpinner');
    const submitBtnText = document.getElementById('submitBtnText');
    const errorSummary = document.getElementById('formErrorSummary');

    let currentStep = 1;
    let isSubmitting = false;
    const uniqueErrors = { email: '', mobile: '' };

    const showFieldError = (fieldName, message) => {
        const input = form.querySelector(`[name="${fieldName}"]`);
        const errEl = document.getElementById(`${fieldName}_error`);
        if (input) {
            if (message) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
            } else {
                input.classList.remove('is-invalid');
                if ((input.type !== 'file' && input.type !== 'checkbox' && input.value.trim() !== '') ||
                    (input.type === 'checkbox' && input.checked) ||
                    (input.type === 'file' && input.files && input.files.length > 0)) {
                    input.classList.add('is-valid');
                }
            }
        }
        if (errEl) {
            errEl.textContent = message;
        }
    };

    const validateSingleField = (fieldName) => {
        const input = form.querySelector(`[name="${fieldName}"]`);
        if (!input) return true;
        let msg = validateField(fieldName, input.value, input);
        if (!msg && (fieldName === 'email' || fieldName === 'mobile') && uniqueErrors[fieldName]) {
            msg = uniqueErrors[fieldName];
        }
        showFieldError(fieldName, msg);
        return msg === '';
    };

    const validateStep = (stepNumber) => {
        const fields = STEP_FIELDS[stepNumber] || [];
        let firstInvalid = null;
        let allValid = true;
        for (const name of fields) {
            const ok = validateSingleField(name);
            if (!ok) {
                allValid = false;
                if (!firstInvalid) {
                    firstInvalid = form.querySelector(`[name="${name}"]`);
                }
            }
        }
        if (firstInvalid) {
            firstInvalid.focus();
        }
        return allValid;
    };

    const renderStep = () => {
        steps.forEach((el) => {
            const stepNo = Number(el.dataset.step);
            el.classList.toggle('d-none', stepNo !== currentStep);
        });
        pills.forEach((pill) => {
            const pillStep = Number(pill.dataset.stepPill);
            pill.classList.toggle('is-active', pillStep === currentStep);
            pill.classList.toggle('is-done', pillStep < currentStep);
        });
        const pct = currentStep * 25;
        if (progressBar) {
            progressBar.style.width = `${pct}%`;
            progressBar.setAttribute('aria-valuenow', String(pct));
        }
        prevBtn.classList.toggle('d-none', currentStep === 1);
        nextBtn.classList.toggle('d-none', currentStep === 4);
        submitBtn.classList.toggle('d-none', currentStep !== 4);
    };

    const saveDraft = () => {
        const data = {};
        const draftFields = [
            'full_name', 'father_spouse_name', 'dob', 'gender',
            'email', 'mobile', 'address', 'city', 'state', 'pincode',
            'category_id', 'id_proof_type', 'id_proof_no',
        ];
        for (const name of draftFields) {
            const el = form.querySelector(`[name="${name}"]`);
            if (el) data[name] = el.value;
        }
        try {
            localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(data));
        } catch {
            // ignore storage quota issues
        }
    };

    const loadDraft = () => {
        try {
            const raw = localStorage.getItem(DRAFT_STORAGE_KEY);
            if (!raw) return;
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                Object.entries(parsed).forEach(([k, v]) => {
                    const el = form.querySelector(`[name="${k}"]`);
                    if (el && typeof v === 'string' && el.type !== 'file') {
                        el.value = v;
                    }
                });
            }
        } catch {
            // ignore malformed draft
        }
    };

    const checkUnique = async (fieldName) => {
        const input = form.querySelector(`[name="${fieldName}"]`);
        const statusEl = document.getElementById(`${fieldName}_status`);
        if (!input) return;

        let val = input.value.trim();
        if (fieldName === 'mobile') {
            val = normalizeMobile(val);
            if (/^[6-9]\d{9}$/.test(val)) {
                input.value = val;
            }
        }

        const localErr = validateField(fieldName, val, input);
        if (localErr) {
            uniqueErrors[fieldName] = '';
            if (statusEl && fieldName === 'email') statusEl.textContent = '';
            return;
        }

        try {
            const param = encodeURIComponent(val);
            const res = await fetch(`/api/check-unique.php?${fieldName}=${param}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const json = await res.json();
            if (json.ok && json.data && json.data.available === false) {
                uniqueErrors[fieldName] = fieldName === 'email'
                    ? 'An application with this email address already exists.'
                    : 'An application with this mobile number already exists.';
                showFieldError(fieldName, uniqueErrors[fieldName]);
            } else {
                uniqueErrors[fieldName] = '';
                showFieldError(fieldName, '');
            }
        } catch {
            // ignore transient network check error
        }
    };

    // File preview & removal (UI-09)
    const setupFileUpload = (fieldName, previewId, nameId, sizeId, removeBtnId, thumbId = null) => {
        const input = form.querySelector(`[name="${fieldName}"]`);
        const preview = document.getElementById(previewId);
        const nameEl = document.getElementById(nameId);
        const sizeEl = document.getElementById(sizeId);
        const removeBtn = document.getElementById(removeBtnId);
        const thumbEl = thumbId ? document.getElementById(thumbId) : null;

        if (!input) return;

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) {
                preview?.classList.add('d-none');
                validateSingleField(fieldName);
                return;
            }
            if (nameEl) nameEl.textContent = file.name;
            if (sizeEl) sizeEl.textContent = formatBytes(file.size);
            if (thumbEl && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    thumbEl.src = String(ev.target?.result || '');
                };
                reader.readAsDataURL(file);
            }
            preview?.classList.remove('d-none');
            validateSingleField(fieldName);
        });

        removeBtn?.addEventListener('click', () => {
            input.value = '';
            if (thumbEl) thumbEl.src = '';
            preview?.classList.add('d-none');
            validateSingleField(fieldName);
        });
    };

    setupFileUpload('photo', 'photoPreview', 'photoName', 'photoSize', 'removePhotoBtn', 'photoThumb');
    setupFileUpload('id_proof', 'idProofPreview', 'idProofName', 'idProofSize', 'removeIdProofBtn');

    // Attach blur & input validation
    Object.values(STEP_FIELDS).flat().forEach((name) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (!el) return;

        el.addEventListener('blur', () => {
            if (name === 'email' || name === 'mobile') {
                checkUnique(name);
            }
            validateSingleField(name);
        });

        el.addEventListener('input', () => {
            if (name === 'email' || name === 'mobile') {
                uniqueErrors[name] = '';
            }
            saveDraft();
            if (el.classList.contains('is-invalid')) {
                validateSingleField(name);
            }
        });

        el.addEventListener('change', () => {
            saveDraft();
            validateSingleField(name);
        });
    });

    nextBtn.addEventListener('click', () => {
        errorSummary.classList.add('d-none');
        if (validateStep(currentStep) && currentStep < 4) {
            currentStep += 1;
            renderStep();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    prevBtn.addEventListener('click', () => {
        errorSummary.classList.add('d-none');
        if (currentStep > 1) {
            currentStep -= 1;
            renderStep();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    const setSubmitting = (loading) => {
        isSubmitting = loading;
        submitBtn.disabled = loading;
        prevBtn.disabled = loading;
        submitSpinner.classList.toggle('d-none', !loading);
        submitBtnText.textContent = loading ? 'Submitting...' : 'Submit Registration';
    };

    const showSummary = (msg) => {
        errorSummary.textContent = msg;
        errorSummary.classList.remove('d-none');
        errorSummary.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (isSubmitting) return;

        errorSummary.classList.add('d-none');
        if (!validateStep(4)) {
            showSummary('Please fix the highlighted fields before submitting.');
            return;
        }

        setSubmitting(true);
        try {
            const formData = new FormData(form);
            const res = await fetch('/api/register.php', {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json' },
            });

            let json = null;
            try {
                json = await res.json();
            } catch {
                json = null;
            }

            if (res.ok && json?.ok && json?.data?.reg_no) {
                try {
                    localStorage.removeItem(DRAFT_STORAGE_KEY);
                } catch {
                    // ignore
                }
                window.location.replace(`/success.php?ref=${encodeURIComponent(json.data.reg_no)}`);
                return;
            }

            if (json?.errors && typeof json.errors === 'object') {
                const entries = Object.entries(json.errors);
                let firstStepWithError = null;
                let firstFieldEl = null;

                for (const [field, msg] of entries) {
                    if (field === '_general') continue;
                    showFieldError(field, String(msg));
                    for (const [s, fList] of Object.entries(STEP_FIELDS)) {
                        if (fList.includes(field)) {
                            const sNum = Number(s);
                            if (firstStepWithError === null || sNum < firstStepWithError) {
                                firstStepWithError = sNum;
                                firstFieldEl = form.querySelector(`[name="${field}"]`);
                            }
                        }
                    }
                }

                if (firstStepWithError !== null && firstStepWithError !== currentStep) {
                    currentStep = firstStepWithError;
                    renderStep();
                }

                const generalMsg = json.errors._general
                    ? String(json.errors._general)
                    : 'Please review and correct the highlighted errors below.';
                showSummary(generalMsg);
                if (firstFieldEl) {
                    firstFieldEl.focus();
                }
            } else {
                showSummary('A server error occurred while submitting your application. Please try again.');
            }
        } catch {
            showSummary('Network error: could not reach the server. Check your connection and try again.');
        } finally {
            setSubmitting(false);
        }
    });

    loadDraft();
    renderStep();
}

