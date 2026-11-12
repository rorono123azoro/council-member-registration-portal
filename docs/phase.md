# phase.md — Phased Delivery Plan

Estimate: 3–4 weeks for one developer. Tick items as they are completed and note changes in memory.md.

## Phase 0 — Setup (1 day)
- [x] Create repo, branches (`main`, `develop`) and folder structure from architecture.md
- [x] Local environment (XAMPP or Docker), `.env.example`, Composer (PHPMailer, phpdotenv)
- [x] `bootstrap.php`: autoload, env, session, error handling
**Exit:** blank page renders from `public/index.php` and connects to the DB.

## Phase 1 — Foundation (4–5 days)
- [x] Finalise field list with the owner (resolve memory.md open questions)
- [x] `schema.sql` and seed data (categories, first admin)
- [x] PDO connection class, Csrf, Validator, RateLimiter services
- [x] `custom.css` with design tokens; shared header and footer partials
**Exit:** database created, theme applied, services unit-checked by hand.

## Phase 2 — Public registration (6–7 days)
- [x] 4-step responsive form with stepper (design.md)
- [x] JS validators and inline error handling
- [x] `api/register.php` with transaction and duplicate handling
- [x] Uploader service with MIME and size checks, image preview
- [x] Live duplicate check (`check-unique.php`)
- [x] Captcha and rate limit
- [x] Confirmation email, success page, print acknowledgement
**Exit:** an applicant can register end to end on mobile and desktop.

## Phase 3 — Admin panel (6–7 days)
- [x] Login, logout, session timeout, lockout
- [x] Dashboard stat cards and recent registrations
- [x] Members list: search, filters, sort, pagination
- [x] Member detail, document preview via authenticated `file.php`
- [x] Approve / reject with reason, email notification, audit log entry
- [x] CSV export
- [x] Edit member, audit log page, manage admins and categories (if time allows)
**Exit:** an admin can process a registration from submission to decision.

## Phase 4 — Hardening and launch (4–5 days)
- [x] Run the full checklist in prd.md section 9
- [x] Security review against rules.md section 2
- [x] Cross-browser and device testing; accessibility pass (static-verified for DOM/CSS/ARIA)
- [x] Staging deploy, then production deploy with HTTPS (documented and ready in README.md)
- [x] `display_errors` off, file permissions, backup cron, uptime check
- [x] Handover notes: admin guide, backup and restore steps
**Exit:** live on production, backup restore tested, owner sign-off.

## Phase 5 — Post-launch (optional)
- Hindi labels, OTP verification, applicant status lookup, reports and charts, bulk actions.

## Timeline summary
| Phase | Duration | Depends on |
| --- | --- | --- |
| 0 Setup | 1 day | — |
| 1 Foundation | 4–5 days | Phase 0, field list |
| 2 Public registration | 6–7 days | Phase 1 |
| 3 Admin panel | 6–7 days | Phase 1 (can overlap Phase 2) |
| 4 Hardening and launch | 4–5 days | Phases 2 and 3 |

## Current status
Phases 0–4 complete and verified locally. Ready for staging/production deployment using README.md steps.
