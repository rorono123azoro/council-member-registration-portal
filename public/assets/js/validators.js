// Client-side validation rules mirroring App\Services\Validator (for UX only; server validates authoritatively).

const NAME_RE = /^[\p{L}\p{M} .-]+$/u;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const MOBILE_RE = /^[6-9]\d{9}$/;
const PIN_RE = /^\d{6}$/;
const ID_NO_RE = /^[A-Za-z0-9\-/ ]{4,60}$/;

const MAX_FILE_BYTES = 2 * 1024 * 1024;
const PHOTO_TYPES = ['image/jpeg', 'image/png'];
const ID_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

export function normalizeMobile(raw) {
    const cleaned = String(raw || '').replace(/[\s\-()]+/g, '');
    if (/^\+91([6-9]\d{9})$/.test(cleaned)) {
        return cleaned.slice(3);
    }
    if (/^91([6-9]\d{9})$/.test(cleaned)) {
        return cleaned.slice(2);
    }
    if (/^0([6-9]\d{9})$/.test(cleaned)) {
        return cleaned.slice(1);
    }
    return cleaned;
}

export function validateField(name, value, inputEl = null) {
    const val = typeof value === 'string' ? value.trim() : '';

    switch (name) {
        case 'full_name':
            if (!val) return 'Enter full name.';
            if (val.length < 2) return 'Full name must be at least 2 characters.';
            if (val.length > 120) return 'Full name must not exceed 120 characters.';
            if (!NAME_RE.test(val)) return 'Full name may only contain letters, spaces, dots, and hyphens.';
            return '';

        case 'father_spouse_name':
            if (!val) return 'Enter father or spouse name.';
            if (val.length < 2) return 'Father/Spouse name must be at least 2 characters.';
            if (val.length > 120) return 'Father/Spouse name must not exceed 120 characters.';
            if (!NAME_RE.test(val)) return 'Father/Spouse name may only contain letters, spaces, dots, and hyphens.';
            return '';

        case 'dob': {
            if (!val) return 'Enter date of birth.';
            const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(val);
            if (!m) return 'Enter a valid date of birth (YYYY-MM-DD).';
            const y = Number(m[1]);
            const mo = Number(m[2]);
            const d = Number(m[3]);
            const dt = new Date(y, mo - 1, d);
            if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) {
                return 'Enter a valid calendar date of birth.';
            }
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (dt > today) return 'Date of birth cannot be in the future.';
            let age = today.getFullYear() - y;
            const monthDiff = today.getMonth() - (mo - 1);
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < d)) {
                age -= 1;
            }
            if (age < 18) return 'Applicant must be at least 18 years old.';
            if (age > 100) return 'Date of birth indicates age over 100 years; please verify.';
            return '';
        }

        case 'gender':
            if (!['male', 'female', 'other'].includes(val)) return 'Select a valid gender.';
            return '';

        case 'email':
            if (!val) return 'Enter email address.';
            if (val.length > 150) return 'Email must not exceed 150 characters.';
            if (!EMAIL_RE.test(val)) return 'Enter a valid email address.';
            return '';

        case 'mobile': {
            if (!val) return 'Enter 10-digit mobile number.';
            const norm = normalizeMobile(val);
            if (!MOBILE_RE.test(norm)) return 'Enter a valid 10-digit mobile number starting with 6–9.';
            return '';
        }

        case 'address':
            if (!val) return 'Enter residential address.';
            if (val.length < 5) return 'Address must be at least 5 characters.';
            if (val.length > 500) return 'Address must not exceed 500 characters.';
            return '';

        case 'city':
            if (!val) return 'Enter city.';
            if (val.length < 2) return 'City must be at least 2 characters.';
            if (val.length > 80) return 'City must not exceed 80 characters.';
            return '';

        case 'state':
            if (!val) return 'Enter state.';
            if (val.length < 2) return 'State must be at least 2 characters.';
            if (val.length > 80) return 'State must not exceed 80 characters.';
            return '';

        case 'pincode':
            if (!val) return 'Enter PIN code.';
            if (!PIN_RE.test(val)) return 'Enter a valid 6-digit PIN code.';
            return '';

        case 'category_id':
            if (!val || !/^\d+$/.test(val)) return 'Select a council category.';
            return '';

        case 'id_proof_type':
            if (!val) return 'Select an ID proof type.';
            return '';

        case 'id_proof_no':
            if (!val) return 'Enter ID proof number.';
            if (val.length < 4) return 'ID proof number must be at least 4 characters.';
            if (val.length > 60) return 'ID proof number must not exceed 60 characters.';
            if (!ID_NO_RE.test(val)) return 'ID proof number may only contain letters, numbers, spaces, hyphens, or slashes.';
            return '';

        case 'photo': {
            const file = inputEl?.files?.[0] || null;
            if (!file) return 'Upload Passport Photo (JPG or PNG, max 2 MB).';
            if (file.size <= 0) return 'Uploaded file is empty.';
            if (file.size > MAX_FILE_BYTES) return 'File must not exceed 2 MB.';
            if (!PHOTO_TYPES.includes(file.type) && !/\.(jpe?g|png)$/i.test(file.name)) {
                return 'Invalid photo format. Allowed: JPG, PNG.';
            }
            return '';
        }

        case 'id_proof': {
            const file = inputEl?.files?.[0] || null;
            if (!file) return 'Upload ID Proof (PDF, JPG, or PNG, max 2 MB).';
            if (file.size <= 0) return 'Uploaded file is empty.';
            if (file.size > MAX_FILE_BYTES) return 'File must not exceed 2 MB.';
            if (!ID_TYPES.includes(file.type) && !/\.(pdf|jpe?g|png)$/i.test(file.name)) {
                return 'Invalid ID proof format. Allowed: PDF, JPG, PNG.';
            }
            return '';
        }

        case 'captcha_answer':
            if (!val || !/^\d+$/.test(val)) return 'Enter the numeric answer to the verification question.';
            return '';

        case 'consent':
            if (!inputEl?.checked) return 'You must confirm the declaration and consent before submitting.';
            return '';

        default:
            return '';
    }
}

export function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

