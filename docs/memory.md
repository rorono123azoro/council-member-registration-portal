# memory.md — Project Memory

Living log of decisions, assumptions and context. Update this file whenever something is decided, changed or learned. Newest entries first within each section.

## 1. Project snapshot
- **Name:** Council Member Registration Portal
- **Stack:** vanilla HTML/JS, Bootstrap 5, PHP 8.1+, MySQL 8
- **Owner:** Council Secretariat (placeholder `National Civic & Professional Council` in `.env`)
- **Status:** Phases 0–4 Complete (local verification passed; browser UI tests static-verified)
- **Last updated:** 2026-11-12

## 2. Decisions
| Date | Decision | Reason |
| --- | --- | --- |
| 2026-11-12 | Dual anti-bot protection: session math challenge + hidden `website_url` honeypot | Satisfies SEC-13 (rejects missing/invalid captcha) and SEC-14 (silently drops honeypot bots) with zero third-party keys |
| 2026-11-12 | `X-Frame-Options: SAMEORIGIN` + `frame-ancestors 'self'` on `/admin/file.php`; `DENY` / `'none'` elsewhere | Blocks external clickjacking while permitting same-origin PDF iframe preview on member detail page |
| 2026-11-12 | `composer.json` PSR-4 maps `App\\Controllers\\`, `App\\Models\\`, `App\\Services\\` directly to lowercase `app/*` dirs | Matches `architecture.md` lowercase folder names cleanly under PSR-4 |
| 2026-11-12 | `Validator` regex `/^[\p{L}\p{M} .\-]+$/u` includes Unicode combining marks `\p{M}` | Supports Devanagari/Hindi vowel matras (e.g. `राहुल शर्मा`) required by VAL-05 and DB-11 |
| 2026-11-12 | Sequential `reg_no` (`REG-YYYY-NNNNNN`) generated from `AUTO_INCREMENT` ID inside DB transaction | Eliminates race condition on concurrent registrations (API-08, PERF-04) |
| 2026-11-12 | No PHP framework; layered structure under `app/` | User specified plain PHP; small scope |
| 2026-11-12 | Document root is `public/`; uploads in `storage/` outside web root | Security |
| 2026-11-12 | AJAX (fetch) form submit returning JSON | Better UX, inline errors |
| 2026-11-12 | Admin approval flow: pending → approved / rejected | Assumed, implemented with mandatory rejection reason |
| 2026-11-12 | UI is designed by the assistant (design.md) because the sample UI was not provided | User instruction |

## 3. Assumptions (confirm and move to Decisions)
- "Council member" = a person registering to join a council or association.
- Admin must approve each registration.
- Email confirmation is sent; when `MAIL_ENABLED=false` (local dev without SMTP), PHPMailer dispatch is simulated and logged to `storage/logs/mail.log`.
- English only at launch; Hindi labels optional later.
- Expected volume: up to a few thousand registrations; single server is enough.
- Uploads: passport photo (jpg/png) and ID proof (pdf/jpg/png), max 2 MB each.
- Seed admin temporary password is `Admin@1234` with `must_change_password = 1`, forcing immediate change on first login.

## 4. Open questions
- [x] Exact form fields and required documents (resolved per `prd.md` §6.1 & `test.md` sample data)
- [ ] Is OTP verification needed? (deferred to Phase 5 per `prd.md` §3)
- [ ] Hosting provider, domain and SMTP details (configurable via `.env`)
- [ ] Council name, logo and brand colours (`COUNCIL_NAME` and `public/assets/img/logo.svg` placeholders ready)
- [ ] Data retention period and who may view ID documents (restricted to authenticated admins via `file.php`)

## 5. Environment notes
- Local: XAMPP (PHP 8.2.12, MariaDB 10.4.32, Composer 2.11.2).
- Required PHP extensions verified loaded: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`.
- Config keys in `.env`: `APP_NAME`, `COUNCIL_NAME`, `APP_ENV`, `APP_URL`, `APP_DEBUG`, `APP_TIMEZONE`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `MAIL_ENABLED`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_ENCRYPTION`, `SESSION_LIFETIME`, `RATE_LIMIT_REGISTER`, `RATE_LIMIT_CHECK_UNIQUE`, `RATE_LIMIT_LOGIN`, `LOCKOUT_MINUTES`.

## 6. Known issues / tech debt
- Browser-specific interactive UI checks (`UI-01`..`UI-15`, `RES-01`..`RES-06`, `BRW-01`..`BRW-05`) and live staging/production server deployment were static-verified via DOM/CSS/JS inspection and curl HTTP tests rather than a live headless browser or remote host.
- Local DB engine is XAMPP MariaDB 10.4.32 (MySQL 8 compatible DDL/queries); production target should run MySQL 8.0+ as specified in `architecture.md`.

## 7. Change log
- 2026-11-12: Completed Phase 4 hardening, `tests/smoke-test.sh`, `tests/sql-checks.sql`, `tests/phase4-security-checks.php`, `README.md`, and security self-audit.
- 2026-11-12: Completed Phase 3 admin panel (`login.php`, `logout.php`, `change-password.php`, `dashboard.php`, `members.php`, `member.php`, `export.php`, `file.php`, `audit.php`, `categories.php`, `admins.php`, `admin.js`) and passed 38/38 automated checks.
- 2026-11-12: Completed Phase 2 public registration (`RegisterController.php`, `register_form.php`, `success.php`, `api/register.php`, `api/check-unique.php`, `register.js`, `validators.js`) and passed 56/56 automated checks.
- 2026-11-12: Completed Phase 1 foundation (`schema.sql`, `seed.sql`, `001_initial_schema.sql`, models, services, `custom.css`, view partials) and passed 16/16 DB/service checks.
- 2026-11-12: Completed Phase 0 setup (folder structure, git `main`/`develop`, Composer PSR-4 autoload + `phpmailer`/`phpdotenv`, `bootstrap.php`, `.htaccess` protections, `tests/router.php`).
- 2026-11-12: Created architecture.md, design.md, rules.md, memory.md, prd.md, phase.md.
