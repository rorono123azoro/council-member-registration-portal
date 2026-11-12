# rules.md — Project Rules & Conventions

These rules apply to every contributor and every AI-assisted change. If a rule must be broken, record why in memory.md.

## 1. Stack rules
- Only: HTML5, vanilla JS, Bootstrap 5, PHP 8.1+, MySQL 8. No jQuery, React, Laravel or other frameworks.
- Composer packages allowed only: PHPMailer, vlucas/phpdotenv. Adding any other needs a note in memory.md.
- JS is ES6+, loaded as modules, no inline `onclick` handlers.

## 2. Security rules (non-negotiable)
1. Every DB query uses PDO prepared statements. Never concatenate user input into SQL.
2. All output is escaped with `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`.
3. Every POST checks a CSRF token.
4. Server-side validation is mandatory; JS validation is only for UX.
5. Passwords use `password_hash()` / `password_verify()`. Never store or log plain passwords.
6. Uploads: whitelist jpg, png, pdf; check real MIME via `finfo`; max 2 MB; random filenames; stored outside web root; downloaded only through an authenticated PHP script.
7. Secrets live in `.env`. `.env` is never committed.
8. `display_errors` is off in production; errors go to `storage/logs/`.
9. Admin pages call `requireAdmin()` first. Regenerate the session ID on login.
10. Rate-limit registration and login endpoints.

## 3. PHP conventions
- `declare(strict_types=1);` at the top of every file.
- PSR-12 style, 4-space indent, classes in PascalCase, methods in camelCase, files match class names.
- One responsibility per class; controllers stay thin, logic lives in services and models.
- Endpoints return JSON `{ ok: bool, data?: {}, errors?: {} }` with correct HTTP status codes.
- Never use `die()` with raw messages or `@` suppression.

## 4. JavaScript conventions
- `const`/`let` only; no globals; one module per feature.
- Use `fetch` with async/await; always handle network failure and show a message.
- Disable the submit button while a request is in flight.
- Do not duplicate rules by hand: keep validators in `validators.js` and mirror the PHP Validator.

## 5. CSS / HTML conventions
- Use Bootstrap utilities first; custom CSS only in `custom.css` using the tokens in design.md.
- Semantic HTML, one `<h1>` per page, every input has a `<label>`.
- Mobile first; test at 360 px.

## 6. Database conventions
- Tables plural snake_case; columns snake_case; FK named `<table>_id`.
- utf8mb4, InnoDB, UNIQUE indexes enforce duplicates (not only app checks).
- Schema changes go in numbered files under `database/migrations/` and are mirrored in `schema.sql`.
- Never delete members; use status values and the audit log.

## 7. Git & workflow
- Branches: `main` (production), `develop`, `feature/<name>`.
- Commit messages: imperative, present tense ("Add duplicate check").
- No commit without running the manual test checklist in prd.md for affected features.

## 8. Definition of done
A feature is done when: it works on mobile and desktop, has server-side validation, has error/empty/loading states, passes the security rules above, and is noted in phase.md.
