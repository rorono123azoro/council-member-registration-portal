# memory.md — Project Memory

Living log of decisions, assumptions and context. Update this file whenever something is decided, changed or learned. Newest entries first within each section.

## 1. Project snapshot
- **Name:** Council Member Registration Portal
- **Stack:** vanilla HTML/JS, Bootstrap 5, PHP 8.1+, MySQL 8
- **Owner:** (fill in)
- **Status:** Planning complete, build not started
- **Last updated:** 2026-11-12

## 2. Decisions
| Date | Decision | Reason |
| --- | --- | --- |
| 2026-11-12 | No PHP framework; layered structure under `app/` | User specified plain PHP; small scope |
| 2026-11-12 | Document root is `public/`; uploads in `storage/` outside web root | Security |
| 2026-11-12 | AJAX (fetch) form submit returning JSON | Better UX, inline errors |
| 2026-11-12 | Admin approval flow: pending → approved / rejected | Assumed, to be confirmed |
| 2026-11-12 | UI is designed by the assistant (design.md) because the sample UI was not provided | User instruction |

## 3. Assumptions (confirm and move to Decisions)
- "Council member" = a person registering to join a council or association.
- Admin must approve each registration.
- Email confirmation is sent; SMS/OTP is out of scope unless requested.
- English only at launch; Hindi labels optional later.
- Expected volume: up to a few thousand registrations; single server is enough.
- Uploads: passport photo (jpg/png) and ID proof (pdf/jpg/png), max 2 MB each.

## 4. Open questions
- [ ] Exact form fields and required documents
- [ ] Is OTP verification needed?
- [ ] Hosting provider, domain and SMTP details
- [ ] Council name, logo and brand colours (default navy `#1f4e79` is a placeholder)
- [ ] Data retention period and who may view ID documents

## 5. Environment notes
- Local: XAMPP or Docker (PHP 8.1, MySQL 8).
- Required PHP extensions: pdo_mysql, fileinfo, mbstring, openssl, gd (optional, thumbnails).
- Config keys in `.env`: `APP_ENV`, `APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `CAPTCHA_SITE_KEY`, `CAPTCHA_SECRET`.

## 6. Known issues / tech debt
(none yet)

## 7. Change log
- 2026-11-12: Created architecture.md, design.md, rules.md, memory.md, prd.md, phase.md.
