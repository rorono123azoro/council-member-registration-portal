<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Server-side input validator.
 * Returns an array of field-level error messages (empty = valid).
 */
final class Validator
{
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $input)
    {
        $this->data = $input;
    }

    // ── Fluent rules ──────────────────────────────────────────────────────────

    public function required(string $field, string $label): static
    {
        $v = trim((string) ($this->data[$field] ?? ''));
        if ($v === '') {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label): static
    {
        if (isset($this->errors[$field])) return $this;
        $v = trim((string) ($this->data[$field] ?? ''));
        if (mb_strlen($v) < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): static
    {
        if (isset($this->errors[$field])) return $this;
        $v = trim((string) ($this->data[$field] ?? ''));
        if (mb_strlen($v) > $max) {
            $this->errors[$field] = "{$label} must not exceed {$max} characters.";
        }
        return $this;
    }

    public function regex(string $field, string $pattern, string $message): static
    {
        if (isset($this->errors[$field])) return $this;
        $v = trim((string) ($this->data[$field] ?? ''));
        if (!preg_match($pattern, $v)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function email(string $field, string $label): static
    {
        if (isset($this->errors[$field])) return $this;
        $v = trim((string) ($this->data[$field] ?? ''));
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} must be a valid email address.";
        }
        return $this;
    }

    public function date(string $field, string $label, string $format = 'Y-m-d'): static
    {
        if (isset($this->errors[$field])) return $this;
        $v = trim((string) ($this->data[$field] ?? ''));
        $d = \DateTime::createFromFormat($format, $v);
        if (!$d || $d->format($format) !== $v) {
            $this->errors[$field] = "{$label} must be a valid date (YYYY-MM-DD).";
        }
        return $this;
    }

    public function minAge(string $field, int $years, string $label): static
    {
        if (isset($this->errors[$field])) return $this;
        $v = trim((string) ($this->data[$field] ?? ''));
        try {
            $dob  = new \DateTimeImmutable($v);
            $diff = $dob->diff(new \DateTimeImmutable());
            if ($diff->y < $years) {
                $this->errors[$field] = "{$label} must indicate age of at least {$years} years.";
            }
        } catch (\Exception) {
            // date() already caught above
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): static
    {
        if (isset($this->errors[$field])) return $this;
        $v = (string) ($this->data[$field] ?? '');
        if (!in_array($v, $allowed, true)) {
            $this->errors[$field] = "{$label} has an invalid value.";
        }
        return $this;
    }

    public function numeric(string $field, string $label): static
    {
        if (isset($this->errors[$field])) return $this;
        $v = trim((string) ($this->data[$field] ?? ''));
        if (!ctype_digit($v)) {
            $this->errors[$field] = "{$label} must contain digits only.";
        }
        return $this;
    }

    public function checked(string $field, string $label): static
    {
        if (empty($this->data[$field])) {
            $this->errors[$field] = "{$label} must be checked.";
        }
        return $this;
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function value(string $field): string
    {
        return trim((string) ($this->data[$field] ?? ''));
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Validate a full registration POST payload.
     * Returns [ 'errors' => [...], 'clean' => [...] ]
     */
    public static function registration(array $post): array
    {
        $v = new self($post);

        // Step 1 — Personal
        $v->required('full_name',          'Full name')
          ->minLength('full_name', 2,      'Full name')
          ->maxLength('full_name', 120,    'Full name')
          ->regex('full_name', '/^[\p{L} .\-]+$/u', 'Full name may only contain letters, spaces, dots and hyphens.');

        $v->required('father_spouse_name',       'Father/Spouse name')
          ->minLength('father_spouse_name', 2,   'Father/Spouse name')
          ->maxLength('father_spouse_name', 120, 'Father/Spouse name')
          ->regex('father_spouse_name', '/^[\p{L} .\-]+$/u', 'Father/Spouse name may only contain letters, spaces, dots and hyphens.');

        $v->required('dob', 'Date of birth')
          ->date('dob', 'Date of birth')
          ->minAge('dob', 18, 'Date of birth');

        $v->required('gender', 'Gender')
          ->in('gender', ['M', 'F', 'O'], 'Gender');

        // Step 2 — Contact & Address
        $v->required('email', 'Email')
          ->maxLength('email', 150, 'Email')
          ->email('email', 'Email');

        $v->required('mobile', 'Mobile')
          ->numeric('mobile', 'Mobile')
          ->minLength('mobile', 10, 'Mobile')
          ->maxLength('mobile', 10, 'Mobile')
          ->regex('mobile', '/^[6-9]\d{9}$/', 'Mobile must be a valid 10-digit Indian number starting with 6–9.');

        $v->required('address', 'Address')
          ->minLength('address', 5, 'Address')
          ->maxLength('address', 500, 'Address');

        $v->required('city',  'City')
          ->maxLength('city',  80, 'City');

        $v->required('state', 'State')
          ->maxLength('state', 80, 'State');

        $v->required('pin',  'PIN code')
          ->numeric('pin',  'PIN code')
          ->minLength('pin', 6, 'PIN code')
          ->maxLength('pin', 6, 'PIN code');

        // Step 3 — Council details
        $v->required('category_id', 'Council category');

        $v->required('id_proof_number', 'ID proof number')
          ->minLength('id_proof_number', 3, 'ID proof number')
          ->maxLength('id_proof_number', 60, 'ID proof number');

        // Step 4 — Declaration
        $v->checked('consent', 'Declaration consent');

        return [
            'errors' => $v->errors(),
            'clean'  => $v->cleanData(),
        ];
    }

    public function cleanData(): array
    {
        $clean = [];
        foreach ($this->data as $k => $v) {
            if (is_string($v)) {
                $clean[$k] = trim($v);
            } else {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }
}

