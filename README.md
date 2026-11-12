# Council Member Registration Portal

A secure, mobile-first registration portal and administrative review console built with **HTML5**, **vanilla JavaScript (ES6 modules)**, **Bootstrap 5.3**, **PHP 8.1+ (PDO)**, and **MySQL 8 / MariaDB**.

---

## 1. Prerequisites

- **PHP 8.1+** with extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl` (`zip` for Composer)
- **MySQL 8.0+** (or MariaDB 10.4+)
- **Composer** (only `phpmailer/phpmailer` and `vlucas/phpdotenv` are used)
- **Apache** (`mod_rewrite` / `.htaccess` enabled, `DocumentRoot` pointing to `public/`) or PHP built-in server for local development

---

## 2. Installation & Local Setup

```bash
# 1. Enter project directory
cd council-portal

# 2. Install Composer dependencies
composer install --no-dev --optimize-autoloader

# 3. Create environment configuration
cp .env.example .env
```

Edit `.env` with your database and SMTP settings:

```env
APP_ENV=local
APP_URL=http://localhost:8000
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata
COUNCIL_NAME="National Civic & Professional Council"

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=council_portal
DB_USER=root
DB_PASS=
```

### Import Database Schema & Seed Data

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p council_portal < database/seed.sql
```

### Start Local Development Server

```bash
php -S 127.0.0.1:8000 -t public tests/router.php
```

- Public Registration Form: `http://127.0.0.1:8000/`
- Admin Login: `http://127.0.0.1:8000/admin/login.php`

---

## 3. Admin Guide & Initial Credentials

Seeded Super Admin account (`database/seed.sql`):
- **Username:** `admin`
- **Email:** `admin@councilportal.local`
- **Temporary Password:** `Admin@1234`

> **Mandatory First Login Action:** The seeded admin account has `must_change_password = 1`. Immediately upon first sign-in, you are redirected to `/admin/change-password.php` to set a strong personal password before accessing the dashboard.

### Admin Capabilities
- **Dashboard (`/admin/dashboard.php`):** Live counts (Total, Pending, Approved, Rejected) and recent registrations.
- **Members (`/admin/members.php`):** Search by name/email/mobile/registration number, filter by status/category/date range, sort columns, paginate (20/page), and export filtered records to UTF-8 BOM CSV (`/admin/export.php`) with formula-injection protection.
- **Member Detail (`/admin/member.php`):** View full applicant data, preview/download uploaded photo and ID proof via authenticated `/admin/file.php`, edit member details (with audit diff), and **Approve** or **Reject** (reason required) with email notification.
- **Super Admin Console:** Manage council categories (`/admin/categories.php`), create/manage reviewer & super-admin accounts (`/admin/admins.php`, preventing deactivation of the last active super admin), and inspect the append-only audit log (`/admin/audit.php`).

---

## 4. Running Tests

```bash
# PHP syntax check on all application PHP files
find app public tests -name "*.php" -print0 | xargs -0 -n1 php -l

# Database & Core Services verification (Phase 1)
php tests/phase1-checks.php

# Start server on test ports or run smoke suite
php -S 127.0.0.1:8000 -t public tests/router.php &
BASE_URL=http://127.0.0.1:8000 bash tests/smoke-test.sh

# SQL schema, constraints, indexes, and EXPLAIN plan check
mysql -u root -p council_portal < tests/sql-checks.sql
```

---

## 5. Production Deployment & Backup

1. **Web Root:** Configure Apache/Nginx virtual host `DocumentRoot` strictly to `/path/to/council-portal/public`. Keep `app/`, `storage/`, `database/`, `vendor/`, and `.env` outside the web root.
2. **Environment Flags:** Set `APP_ENV=production` and `APP_DEBUG=false` in `.env` so `display_errors` is `0` and errors log only to `storage/logs/app.log` and `storage/logs/php_errors.log`.
3. **HTTPS & Cookies:** Serve exclusively over HTTPS; `app/bootstrap.php` automatically enables `session.cookie_secure=1` on HTTPS connections alongside `HttpOnly` and `SameSite=Lax`.
4. **Permissions:** Ensure `storage/uploads` and `storage/logs` are writable only by the PHP/web server user (`0750` dirs, `0640` files — never `777`).
5. **Placeholders:** Update `COUNCIL_NAME` in `.env` and replace `public/assets/img/logo.svg` with your official council emblem before launch.
6. **Daily Backup & Restore:**
   ```bash
   # Daily cron backup (30-day retention)
   0 2 * * * mysqldump --single-transaction -u backup_user -p'...' council_portal | gzip > /backups/council_portal_$(date +\%F).sql.gz
   0 3 * * * find /backups -name "council_portal_*.sql.gz" -mtime +30 -delete

   # Restore to fresh database
   gunzip -c /backups/council_portal_2026-11-12.sql.gz | mysql -u root -p council_portal
   ```

---

## 6. Security Self-Audit

| Check | Evidence | Result |
| --- | --- | --- |
| Prepared statements only | `grep` / `SEC-01` scan across `app/` confirmed all SQL uses PDO `prepare()` / `execute()` with bound parameters | PASS |
| Output escaping | `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) used across all views (`register_form.php`, `success.php`, `admin/*.php`); `SVC-05`, `MEM-01`, `SEC-03` passed | PASS |
| CSRF on every POST | `Csrf::validateRequest()` enforced on `api/register.php`, `admin/login.php`, `logout.php`, `change-password.php`, `member.php`, `categories.php`, `admins.php`; `API-04`, `AUTH-12`, `MEM-10` passed | PASS |
| Upload validation | `Uploader::process()` enforces whitelist (`jpg`,`png`,`pdf`), real MIME via `finfo`, max 2 MB, rejects double extensions & 0-byte files, random names in `storage/uploads/{year}/`, authenticated `admin/file.php`; `UP-01`..`UP-15` passed | PASS |
| Password hashing | `password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])` and `password_verify()` in `Admin.php` & `AdminController.php`; `AUTH-14` passed | PASS |
| Session hardening | `Auth::login()` calls `session_regenerate_id(true)`; `bootstrap.php` configures `HttpOnly`, `SameSite=Lax`, `Secure` (HTTPS), `use_strict_mode=1`, idle timeout; `AUTH-08`, `AUTH-09` passed | PASS |
| Rate limiting and lockout | `RateLimiter::check()` on `register`, `check-unique`, and `login`; `login_attempts` locks after 5 failures; `SVC-06`, `SEC-12`, `AUTH-04`, `AUTH-05` passed | PASS |
| No secrets in repo | `.env` excluded in `.gitignore`; `.env.example` contains placeholders only; `SEC-16` passed | PASS |
| Errors hidden in production | `bootstrap.php` sets `display_errors=0` when `APP_ENV=production` or `APP_DEBUG=false`, generic error handler logs to `storage/logs/app.log`; `DEP-02`, `ENV-05` passed | PASS |

