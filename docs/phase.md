# phase.md — Phased Delivery Plan

Estimate: 3–4 weeks for one developer. Tick items as they are completed and note changes in memory.md.

## Phase 0 — Setup (1 day)
- [ ] Create repo, branches (`main`, `develop`) and folder structure from architecture.md
- [ ] Local environment (XAMPP or Docker), `.env.example`, Composer (PHPMailer, phpdotenv)
- [ ] `bootstrap.php`: autoload, env, session, error handling
**Exit:** blank page renders from `public/index.php` and connects to the DB.

## Phase 1 — Foundation (4–5 days)
- [ ] Finalise field list with the owner (resolve memory.md open questions)
- [ ] `schema.sql` and seed data (categories, first admin)
- [ ] PDO connection class, Csrf, Validator, RateLimiter services
- [ ] `custom.css` with design tokens; shared header and footer partials
**Exit:** database created, theme applied, services unit-checked by hand.

## Phase 2 — Public registration (6–7 days)
- [ ] 4-step responsive form with stepper (design.md)
- [ ] JS validators and inline error handling
- [ ] `api/register.php` with transaction and duplicate handling
- [ ] Uploader service with MIME and size checks, image preview
- [ ] Live duplicate check (`check-unique.php`)
- [ ] Captcha and rate limit
- [ ] Confirmation email, success page, print acknowledgement
**Exit:** an applicant can register end to end on mobile and desktop.

## Phase 3 — Admin panel (6–7 days)
- [ ] Login, logout, session timeout, lockout
- [ ] Dashboard stat cards and recent registrations
- [ ] Members list: search, filters, sort, pagination
- [ ] Member detail, document preview via authenticated `file.php`
- [ ] Approve / reject with reason, email notification, audit log entry
- [ ] CSV export
- [ ] Edit member, audit log page, manage admins and categories (if time allows)
**Exit:** an admin can process a registration from submission to decision.

## Phase 4 — Hardening and launch (4–5 days)
- [ ] Run the full checklist in prd.md section 9
- [ ] Security review against rules.md section 2
- [ ] Cross-browser and device testing; accessibility pass
- [ ] Staging deploy, then production deploy with HTTPS
- [ ] `display_errors` off, file permissions, backup cron, uptime check
- [ ] Handover notes: admin guide, backup and restore steps
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
Planning complete. Next action: confirm open questions in memory.md, then begin Phase 0.
