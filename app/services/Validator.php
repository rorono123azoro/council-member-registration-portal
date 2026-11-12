<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;

/**
 * Server-side input validator.
 * Single source of truth for validation rules (mirrored in public/assets/js/validators.js).
 */
final class Validator
{
    public const ALLOWED_GENDERS = ['male', 'female', 'other'];
    public const ALLOWED_ID_TYPES = [
        'Aadhaar Card',
        'PAN Card',
        'Voter ID',
        'Passport',
        'Driving Licence',
    ];

    private array $errors = [];
    private array $data   = [];

    public function __construct(array $input)
    {
        foreach ($input as $k => $v) {
            if (is_string($v)) {
                // Strip null bytes and normalize line breaks cleanly (VAL-29)
                $v = str_replace("\0", '', $v);
                $this->data[$k] = trim($v);
            } else {
                $this->data[$k] = $v;
            }
        }
    }

    public function required(string $field, string $label): static
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v === '') {
            $this->errors[$field] = "Enter {$label}.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $v = (string) ($this->data[$field] ?? '');
        if (mb_strlen($v, 'UTF-8') < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $v = (string) ($this->data[$field] ?? '');
        if (mb_strlen($v, 'UTF-8') > $max) {
            $this->errors[$field] = "{$label} must not exceed {$max} characters.";
        }
        return $this;
    }

    public function regex(string $field, string $pattern, string $message): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $v = (string) ($this->data[$field] ?? '');
        if (!preg_match($pattern, $v)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function email(string $field, string $label): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $v = (string) ($this->data[$field] ?? '');
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Enter a valid {$label}.";
            return $this;
        }
        $parts = explode('@', $v);
        if (count($parts) !== 2 || !str_contains($parts[1], '.')) {
            $this->errors[$field] = "Enter a valid {$label}.";
            return $this;
        }
        $this->data[$field] = mb_strtolower($v, 'UTF-8');
        return $this;
    }

    public function normalizeMobile(string $field): static
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v === '') {
            return $this;
        }
        $digitsOnly = preg_replace('/[\s\-()]+/', '', $v) ?? $v;
        if (preg_match('/^\+91([6-9]\d{9})$/', $digitsOnly, $m)) {
            $this->data[$field] = $m[1];
        } elseif (preg_match('/^91([6-9]\d{9})$/', $digitsOnly, $m)) {
            $this->data[$field] = $m[1];
        } elseif (preg_match('/^0([6-9]\d{9})$/', $digitsOnly, $m)) {
            $this->data[$field] = $m[1];
        } else {
            $this->data[$field] = $digitsOnly;
        }
        return $this;
    }

    public function dob(string $field, int $minAge = 18, int $maxAge = 100): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $v = (string) ($this->data[$field] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            $this->errors[$field] = 'Enter a valid date of birth (YYYY-MM-DD).';
            return $this;
        }
        $year  = (int) $m[1];
        $month = (int) $m[2];
        $day   = (int) $m[3];
        if (!checkdate($month, $day, $year)) {
            $this->errors[$field] = 'Enter a valid calendar date of birth.';
            return $this;
        }

        try {
            $dob   = new \DateTimeImmutable($v);
            $today = new \DateTimeImmutable('today');
        } catch (\Throwable) {
            $this->errors[$field] = 'Enter a valid date of birth.';
            return $this;
        }

        if ($dob > $today) {
            $this->errors[$field] = 'Date of birth cannot be in the future.';
            return $this;
        }

        $age = $dob->diff($today)->y;
        if ($age < $minAge) {
            $this->errors[$field] = "Applicant must be at least {$minAge} years old.";
            return $this;
        }
        if ($age > $maxAge) {
            $this->errors[$field] = "Date of birth indicates age over {$maxAge} years; please verify.";
            return $this;
        }

        return $this;
    }

    public function in(string $field, array $allowed, string $label): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $v = (string) ($this->data[$field] ?? '');
        if (!in_array($v, $allowed, true)) {
            $this->errors[$field] = "Select a valid {$label}.";
        }
        return $this;
    }

    public function digits(string $field, int $length, string $label): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $v = (string) ($this->data[$field] ?? '');
        if (!preg_match('/^\d{' . $length . '}$/', $v)) {
            $this->errors[$field] = "Enter a valid {$length}-digit {$label}.";
        }
        return $this;
    }

    public function checked(string $field, string $label): static
    {
        $val = $this->data[$field] ?? null;
        if ($val !== '1' && $val !== 'on' && $val !== 'true' && $val !== true && $val !== 1) {
            $this->errors[$field] = "Please accept the {$label}.";
        }
        return $this;
    }

    public function addError(string $field, string $message): static
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function cleanData(): array
    {
        return $this->data;
    }

    /**
     * Validate shared member fields (used by both registration and admin edit).
     *
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function memberFields(array $post, bool $requireActiveCategory = true): array
    {
        $v = new self($post);

        // Normalize mobile first (VAL-12)
        $v->normalizeMobile('mobile');

        // Step 1 — Personal
        $v->required('full_name', 'full name')
          ->minLength('full_name', 2, 'Full name')
          ->maxLength('full_name', 120, 'Full name')
          ->regex('full_name', '/^[\p{L}\p{M} .\-]+$/u', 'Full name may only contain letters, spaces, dots, and hyphens.');

        $v->required('father_spouse_name', 'father or spouse name')
          ->minLength('father_spouse_name', 2, 'Father/Spouse name')
          ->maxLength('father_spouse_name', 120, 'Father/Spouse name')
          ->regex('father_spouse_name', '/^[\p{L}\p{M} .\-]+$/u', 'Father/Spouse name may only contain letters, spaces, dots, and hyphens.');

        $v->required('dob', 'date of birth')
          ->dob('dob', 18, 100);

        $v->required('gender', 'gender')
          ->in('gender', self::ALLOWED_GENDERS, 'gender');

        // Step 2 — Contact & Address
        $v->required('email', 'email address')
          ->maxLength('email', 150, 'Email')
          ->email('email', 'email address');

        $v->required('mobile', '10-digit mobile number')
          ->regex('mobile', '/^[6-9]\d{9}$/', 'Enter a valid 10-digit mobile number starting with 6–9.');

        $v->required('address', 'residential address')
          ->minLength('address', 5, 'Address')
          ->maxLength('address', 500, 'Address');

        $v->required('city', 'city')
          ->minLength('city', 2, 'City')
          ->maxLength('city', 80, 'City');

        $v->required('state', 'state')
          ->minLength('state', 2, 'State')
          ->maxLength('state', 80, 'State');

        $v->required('pincode', 'PIN code')
          ->digits('pincode', 6, 'PIN code');

        // Step 3 — Council details
        $v->required('category_id', 'council category');
        $clean = $v->cleanData();
        $catIdRaw = (string) ($clean['category_id'] ?? '');
        if ($catIdRaw !== '') {
            if (!ctype_digit($catIdRaw) || (int) $catIdRaw <= 0) {
                $v->addError('category_id', 'Select a valid council category.');
            } else {
                $cat = Category::find((int) $catIdRaw);
                if ($cat === null || ($requireActiveCategory && (int) $cat['is_active'] !== 1)) {
                    $v->addError('category_id', 'Selected council category is unavailable.');
                }
            }
        }

        if (!isset($clean['id_proof_type']) || $clean['id_proof_type'] === '') {
            $clean['id_proof_type'] = 'PAN Card';
            $v = self::withUpdatedData($v, $clean);
        } else {
            $v->in('id_proof_type', self::ALLOWED_ID_TYPES, 'ID proof type');
        }

        $v->required('id_proof_no', 'ID proof number')
          ->minLength('id_proof_no', 4, 'ID proof number')
          ->maxLength('id_proof_no', 60, 'ID proof number')
          ->regex('id_proof_no', '/^[A-Za-z0-9\-\/ ]{4,60}$/', 'ID proof number may only contain letters, numbers, spaces, hyphens, or slashes.');

        return [
            'errors' => $v->errors(),
            'clean'  => $v->cleanData(),
        ];
    }

    /**
     * Validate a full registration submission (including consent).
     *
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function registration(array $post): array
    {
        $result = self::memberFields($post, true);
        $errors = $result['errors'];
        $clean  = $result['clean'];

        $consent = $clean['consent'] ?? null;
        if ($consent !== '1' && $consent !== 'on' && $consent !== 'true' && $consent !== true && $consent !== 1) {
            $errors['consent'] = 'You must confirm the declaration and consent before submitting.';
        } else {
            $clean['consent'] = 1;
        }

        return [
            'errors' => $errors,
            'clean'  => $clean,
        ];
    }

    private static function withUpdatedData(self $instance, array $data): self
    {
        $instance->data = $data;
        return $instance;
    }
}
