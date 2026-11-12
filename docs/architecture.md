# architecture.md — Council Member Registration Portal

## 1. Overview
A server-rendered PHP web app with a vanilla JS front end, Bootstrap 5 styling and a MySQL database. No framework. Two areas: a public registration form and a protected admin panel.

## 2. Stack
| Layer | Choice | Notes |
| --- | --- | --- |
| Markup/Behaviour | HTML5, vanilla JS (ES6 modules, fetch) | No build step |
| Styling | Bootstrap 5.3 + `custom.css` token overrides | See design.md |
| Backend | PHP 8.1+ (PDO, sessions) | No framework; Composer for PHPMailer, phpdotenv |
| Database | MySQL 8, InnoDB, utf8mb4 | Prepared statements only |
| Web server | Apache or Nginx, HTTPS | Document root = `/public` |

## 3. Request flow
1. Browser loads `public/index.php` (form rendered with CSRF token).
2. JS validates on blur and on submit, then sends `FormData` via `fetch` to `public/api/register.php`.
3. Endpoint: verify CSRF → verify captcha → rate-limit check → `Validator` → `Uploader` → `Member::create()` (PDO, transaction) → `Mailer::sendConfirmation()`.
4. Endpoint returns JSON: `{ ok: true, reg_no }` or `{ ok: false, errors: { field: message } }`.
5. JS shows field errors, or redirects to `success.php?ref=<reg_no>`.

Admin pages are plain PHP pages behind a session check; actions (approve, reject, export) are POST + CSRF.

## 4. Folder structure
```text
council-portal/
├── public/                  # only web-accessible folder
│   ├── index.php            # registration page
│   ├── success.php
│   ├── api/
│   │   ├── register.php
│   │   └── check-unique.php # live email/mobile duplicate check
│   ├── admin/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── dashboard.php
│   │   ├── members.php
│   │   ├── member.php       # detail + approve/reject
│   │   ├── export.php       # CSV
│   │   └── file.php         # authenticated file download
│   └── assets/
│       ├── css/ (bootstrap.min.css, custom.css)
│       ├── js/  (register.js, validators.js, admin.js)
│       └── img/
├── app/
│   ├── bootstrap.php        # autoload, env, session, error handling
│   ├── config/ (app.php, db.php)
│   ├── controllers/ (RegisterController.php, AdminController.php)
│   ├── models/ (Member.php, Admin.php, Category.php, AuditLog.php)
│   ├── services/ (Validator.php, Uploader.php, Mailer.php, Csrf.php, Captcha.php, RateLimiter.php)
│   └── views/ (partials/header.php, footer.php, form sections)
├── storage/
│   ├── uploads/             # outside web root
│   └── logs/
├── database/ (schema.sql, seed.sql)
├── docs/ (the six .md files)
├── .env  /  .env.example
└── composer.json
```

## 5. Data model
Tables: `members`, `categories`, `documents`, `admins`, `audit_log`, `login_attempts`.
- `members.email`, `members.mobile`, `members.reg_no` have UNIQUE indexes.
- `members.status` ENUM('pending','approved','rejected'), indexed with `created_at`.
- `documents.member_id` FK → `members.id` ON DELETE CASCADE.
- Files stored on disk under `storage/uploads/<year>/<random>.ext`; only the path is in MySQL.

## 6. Key components
- **Validator**: single source of validation rules, shared by all endpoints. JS mirrors the same rules for UX only.
- **Uploader**: checks size, extension and real MIME (`finfo`), renames randomly, moves to `storage/`.
- **Csrf**: per-session token, checked on every POST.
- **RateLimiter**: max N submissions per IP per hour, stored in DB.
- **AuditLog**: records every admin action with old/new value and IP.

## 7. Environments
Local (XAMPP/Docker) → Staging (copy of production) → Production (HTTPS, `display_errors=0`).

## 8. Non-functional targets
- Page weight under 500 KB excluding uploads; load under 2 s on 4G.
- Works from 360 px width; WCAG AA contrast.
- Daily DB backup via cron; 30-day retention.
