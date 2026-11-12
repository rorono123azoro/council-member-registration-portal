# test.md — Test Cases for the Council Member Registration Portal

How to use: run every case in the phase where it first applies (Phase column). Priority follows prd.md: **M** = Must pass, **S** = Should pass, **C** = Could. Mark results in section 17. Methods: `php -l`, `curl`, MySQL queries, built-in PHP server (`php -S localhost:8000 -t public`), browser or code inspection (mark "static-verified" if no browser).

Valid sample data used below:
`name=Rahul Sharma, father=Mohan Sharma, dob=1995-04-12, gender=male, email=rahul@example.com, mobile=9876543210, address=12 MG Road, city=Agra, state=Uttar Pradesh, pincode=282001, category=1, id_proof_no=ABCDE1234F`, photo = 100 KB jpg, ID proof = 200 KB pdf, consent = checked.

## 1. Setup and environment (Phase 0)
| ID | Scenario | Steps | Expected | Phase | Pri |
| --- | --- | --- | --- | --- | --- |
| ENV-01 | Folder structure | Compare tree with architecture.md | All folders and files exist | 0 | M |
| ENV-02 | PHP syntax | `php -l` on every `.php` file | No syntax errors | 0 | M |
| ENV-03 | Env config | Copy `.env.example` to `.env`, fill DB values | App reads values, no hard-coded credentials | 0 | M |
| ENV-04 | `.env` ignored | Check `.gitignore` | `.env`, `vendor/`, `storage/uploads/*` ignored | 0 | M |
| ENV-05 | DB connection | Load any page | Connects; on bad credentials shows generic error and logs details, no stack trace | 0 | M |
| ENV-06 | Required extensions | Check pdo_mysql, fileinfo, mbstring, openssl | All loaded, otherwise clear setup error | 0 | S |
| ENV-07 | Document root | Request `/app/`, `/storage/`, `/.env`, `/database/schema.sql` via browser or curl | 403 or 404, never file contents | 0 | M |

## 2. Database (Phase 1)
| ID | Scenario | Steps | Expected | Phase | Pri |
| --- | --- | --- | --- | --- | --- |
| DB-01 | Fresh schema import | Import `schema.sql` into an empty DB | Runs with zero errors | 1 | M |
| DB-02 | Seed import | Import `seed.sql` | Categories and one admin exist; admin password is hashed | 1 | M |
| DB-03 | Charset | `SHOW CREATE TABLE members` | utf8mb4, InnoDB | 1 | M |
| DB-04 | Unique email | Insert two members with same email | Second insert fails (duplicate key) | 1 | M |
| DB-05 | Unique mobile | Same for mobile | Second insert fails | 1 | M |
| DB-06 | Unique reg_no | Same for reg_no | Second insert fails | 1 | M |
| DB-07 | Foreign keys | Insert member with invalid category_id | Rejected | 1 | M |
| DB-08 | Cascade | Delete a member row with documents | Documents rows removed | 1 | S |
| DB-09 | Status default | Insert member without status | Status = pending | 1 | M |
| DB-10 | Indexes | `SHOW INDEX` | Index on status and created_at exist | 1 | S |
| DB-11 | Unicode | Insert Hindi name "राहुल शर्मा" | Stored and read back correctly | 1 | S |

## 3. Core services (Phase 1)
| ID | Scenario | Steps | Expected | Phase | Pri |
| --- | --- | --- | --- | --- | --- |
| SVC-01 | Validator valid | Pass valid sample data | No errors | 1 | M |
| SVC-02 | Validator required | Omit each required field in turn | Field-specific error each time | 1 | M |
| SVC-03 | CSRF generate | Load form twice in one session | Token present, stable per session | 1 | M |
| SVC-04 | CSRF verify | Submit without token / wrong token | 403, nothing saved | 1 | M |
| SVC-05 | Output escaping | Echo `<script>alert(1)</script>` through helper | Rendered as text, not executed | 1 | M |
| SVC-06 | Rate limiter | Exceed submission limit from one IP | 429 with friendly message | 1 | M |
| SVC-07 | Strict types | Grep for `declare(strict_types=1);` | Present in every PHP file | 1 | S |

## 4. Registration form — validation (Phase 2)
| ID | Field | Input | Expected | Pri |
| --- | --- | --- | --- | --- |
| VAL-01 | Name | Empty | "Name is required" | M |
| VAL-02 | Name | 1 character | Rejected (min 2) | M |
| VAL-03 | Name | 121 characters | Rejected (max 120) | M |
| VAL-04 | Name | `Rahul123`, `Rahul@#` | Rejected | M |
| VAL-05 | Name | `Anne-Marie O.` , `राहुल` | Accepted | S |
| VAL-06 | Name | Leading/trailing spaces | Trimmed and accepted | S |
| VAL-07 | Email | Empty, `abc`, `a@b`, `a@b.`, `a b@c.com` | Rejected | M |
| VAL-08 | Email | 151 characters | Rejected | M |
| VAL-09 | Email | Mixed case `Rahul@Example.COM` | Stored lowercased; duplicate check case-insensitive | M |
| VAL-10 | Mobile | Empty, 9 digits, 11 digits, letters | Rejected | M |
| VAL-11 | Mobile | Starts with 0–5 (e.g. 5123456789) | Rejected | M |
| VAL-12 | Mobile | `+91 98765 43210` | Normalised to 10 digits or rejected with clear message | S |
| VAL-13 | DOB | Empty, invalid (`2000-02-31`), future date | Rejected | M |
| VAL-14 | DOB | Age exactly 18 today | Accepted | M |
| VAL-15 | DOB | Age 17 years 364 days | Rejected | M |
| VAL-16 | DOB | Age over 100 | Rejected or warned per prd | S |
| VAL-17 | Gender | Value outside allowed list | Rejected | M |
| VAL-18 | PIN | 5 digits, 7 digits, letters | Rejected | M |
| VAL-19 | PIN | `282001` | Accepted | M |
| VAL-20 | Address | Empty / only spaces | Rejected | M |
| VAL-21 | Address | 1000+ characters | Rejected at the set limit | S |
| VAL-22 | Category | Missing, non-numeric, non-existent ID | Rejected | M |
| VAL-23 | Category | Inactive category ID | Rejected | M |
| VAL-24 | Consent | Unchecked | Rejected | M |
| VAL-25 | State/City | Empty | Rejected | M |
| VAL-26 | ID proof no. | Empty / invalid format | Rejected | M |
| VAL-27 | All fields | SQL payload `' OR 1=1 --` in each text field | Stored literally or rejected, never executed | M |
| VAL-28 | All fields | `<script>alert(1)</script>` in each text field | Rejected or stored; displayed escaped everywhere | M |
| VAL-29 | All fields | Emoji, null byte `%00`, very long unicode | Handled, no 500 error | S |
| VAL-30 | Multiple errors | Submit empty form | All field errors returned together | M |

## 5. Registration form — client behaviour (Phase 2)
| ID | Scenario | Expected | Pri |
| --- | --- | --- | --- |
| UI-01 | Page load | Header, hero, stepper and step 1 visible; no console errors | M |
| UI-02 | Stepper navigation | Next blocked until current step valid; Back keeps data | M |
| UI-03 | Inline validation | Error appears on blur; clears when fixed | M |
| UI-04 | Error summary | On failed submit, alert at top and first invalid field focused | M |
| UI-05 | Double-submit | Button disabled with spinner while request is in flight; second click does nothing | M |
| UI-06 | Network failure | Offline submit shows friendly error and re-enables button | M |
| UI-07 | Server error 500 | Generic message, no stack trace shown | M |
| UI-08 | Server field errors | JSON `errors` mapped under the right fields | M |
| UI-09 | Image preview | Selecting a photo shows a thumbnail; remove button clears it | S |
| UI-10 | Mobile number input | Numeric keypad (`inputmode`), max length 10 | S |
| UI-11 | Progress bar | Reflects the current step | S |
| UI-12 | Draft saving | Reload mid-form restores entered text (not files) | C |
| UI-13 | Back button after success | Does not allow resubmission of the same data | S |
| UI-14 | Success redirect | Goes to `success.php?ref=<reg_no>` showing the number | M |
| UI-15 | Print acknowledgement | Print view shows only the acknowledgement | S |

## 6. Registration API (Phase 2)
| ID | Scenario | Steps | Expected | Pri |
| --- | --- | --- | --- | --- |
| API-01 | Happy path | POST valid data + files with token | 200 `{ok:true, reg_no}`; row status=pending; 2 documents rows; files on disk | M |
| API-02 | Reg number | Create 3 registrations | Unique, correctly formatted, sequential | M |
| API-03 | Wrong method | GET `/api/register.php` | 405 | M |
| API-04 | Missing token | POST without CSRF | 403, no DB change | M |
| API-05 | Validation failure | POST invalid data | 422 with `errors`; no DB row; no files left on disk | M |
| API-06 | Duplicate email | Register twice with same email | Second: 409/422 with message on email field | M |
| API-07 | Duplicate mobile | Same mobile, different email | Rejected on mobile field | M |
| API-08 | Race condition | Send two identical requests simultaneously | Exactly one row created, other rejected | M |
| API-09 | Transaction | Force DB error after file upload | No partial member row; orphan file removed | S |
| API-10 | Unique check API | `check-unique.php?email=` existing / new | Returns taken / available; does not leak other member data | M |
| API-11 | Unique check abuse | 100 rapid calls | Rate-limited | S |
| API-12 | Response format | Inspect headers | `Content-Type: application/json`, no PHP warnings in body | M |
| API-13 | Mass assignment | POST extra field `status=approved` | Ignored; status stays pending | M |
| API-14 | Large body | POST above `post_max_size` | Clean error, no crash | S |
| API-15 | Confirmation email | After success | Email sent (or logged in dev) with reg number | S |
| API-16 | Email failure | Break SMTP | Registration still saved; failure logged; user not shown an error | S |

## 7. File uploads (Phase 2)
| ID | Scenario | Input | Expected | Pri |
| --- | --- | --- | --- | --- |
| UP-01 | Valid photo | 100 KB jpg and png | Accepted | M |
| UP-02 | Valid ID proof | pdf, jpg, png under 2 MB | Accepted | M |
| UP-03 | Oversized | 2.1 MB file | Rejected with size message | M |
| UP-04 | Exactly at limit | 2.0 MB file | Accepted | S |
| UP-05 | Wrong type | `.exe`, `.docx`, `.gif` | Rejected | M |
| UP-06 | Disguised PHP | `shell.php` renamed `shell.jpg` | Rejected by MIME check | M |
| UP-07 | Double extension | `shell.php.jpg`, `image.jpg.php` | Rejected or safely renamed; never executable | M |
| UP-08 | Fake MIME | PHP content with `Content-Type: image/jpeg` header | Rejected (finfo on content) | M |
| UP-09 | Zero-byte file | Empty file | Rejected | M |
| UP-10 | Missing required file | No photo / no ID proof | Rejected with message | M |
| UP-11 | Filename | Name with spaces, unicode, `../../x.jpg` | Stored under random name; no path traversal | M |
| UP-12 | Storage location | Inspect disk | Files in `storage/uploads/`, outside `public/` | M |
| UP-13 | Direct access | Request an uploaded file by URL | 403/404 | M |
| UP-14 | Execution blocked | `.htaccess` deny-all / no PHP execution in storage | Confirmed | M |
| UP-15 | Multiple files same name | Two users upload `photo.jpg` | No overwrite; distinct stored names | M |
| UP-16 | Upload error codes | Partial upload / no tmp dir | Handled with clean message | S |

## 8. Success page and emails (Phase 2)
| ID | Scenario | Expected | Pri |
| --- | --- | --- | --- |
| SUC-01 | Valid ref | Shows number and acknowledgement | M |
| SUC-02 | Invalid or missing ref | Redirect to form or safe message; no data leak | M |
| SUC-03 | Ref of another member | Shows only the reg number, no personal data | M |
| SUC-04 | Email content | Contains reg number, council name, no sensitive data | S |
| SUC-05 | Email header injection | Name containing `\r\nBcc:` | No extra headers sent | M |

## 9. Admin authentication and sessions (Phase 3)
| ID | Scenario | Steps | Expected | Pri |
| --- | --- | --- | --- | --- |
| AUTH-01 | Valid login | Correct credentials | Redirect to dashboard | M |
| AUTH-02 | Wrong password | Incorrect password | Generic "Invalid credentials" | M |
| AUTH-03 | Unknown email | Non-existent admin | Same generic message and similar timing | M |
| AUTH-04 | Lockout | 5 failed attempts | Account/IP locked for set time; message shown | M |
| AUTH-05 | Lockout expiry | Wait period ends | Login works again | S |
| AUTH-06 | Empty fields | Submit blank form | Validation message | M |
| AUTH-07 | Direct URL access | Open each admin page logged out | Redirect to login | M |
| AUTH-08 | Session fixation | Compare session ID before and after login | ID changed | M |
| AUTH-09 | Cookie flags | Inspect cookies | HttpOnly, SameSite, Secure (on HTTPS) | M |
| AUTH-10 | Logout | Click logout then Back | Session destroyed; protected pages inaccessible | M |
| AUTH-11 | Idle timeout | Idle beyond limit | Redirected to login | S |
| AUTH-12 | Login CSRF | POST login without token | 403 | M |
| AUTH-13 | SQL injection | `admin' OR '1'='1` | Login fails | M |
| AUTH-14 | Password storage | Check DB | `password_hash` format, never plain | M |
| AUTH-15 | Temporary password | First login with seed password | Forced to change password (or documented) | S |
| AUTH-16 | Roles | Reviewer opens super-admin pages | 403 | S |

## 10. Admin dashboard and members list (Phase 3)
| ID | Scenario | Expected | Pri |
| --- | --- | --- | --- |
| ADM-01 | Stat cards | Total, pending, approved, rejected match DB counts | M |
| ADM-02 | Recent registrations | Latest first, correct data | S |
| ADM-03 | List default | Newest first, 20 per page | M |
| ADM-04 | Pagination | Next/prev/page numbers correct; last page partial; page beyond range handled | M |
| ADM-05 | Search | By name, email, mobile, reg_no (partial, case-insensitive) | M |
| ADM-06 | Search injection | `%`, `_`, `' OR 1=1`, `<script>` | No error, no injection, escaped output | M |
| ADM-07 | Filter status | Each status shows only that status | M |
| ADM-08 | Filter category | Correct results | M |
| ADM-09 | Date range | Inclusive of both dates; invalid range handled | S |
| ADM-10 | Combined filters | Search + status + category together | M |
| ADM-11 | Sorting | Each sortable column asc/desc; invalid sort param ignored (whitelist) | S |
| ADM-12 | Empty state | No matches shows "No members found" | M |
| ADM-13 | Large data | Seed 5,000 members; list loads under 2 s | S |
| ADM-14 | Filters persist | Filters kept in URL after approve/back | S |

## 11. Member detail, approve/reject, export (Phase 3)
| ID | Scenario | Expected | Pri |
| --- | --- | --- | --- |
| MEM-01 | View detail | All submitted data displayed, escaped | M |
| MEM-02 | Invalid or missing ID | 404 page | M |
| MEM-03 | Document preview | Photo and ID proof viewable via authenticated `file.php` | M |
| MEM-04 | File access logged out | `file.php?id=` without session | Redirect/403 | M |
| MEM-05 | File ID tampering | Request non-existent / other IDs, `../` paths | 404, no traversal | M |
| MEM-06 | Approve | Status becomes approved, audit row written, email sent | M |
| MEM-07 | Reject without reason | Blocked with message | M |
| MEM-08 | Reject with reason | Status rejected, reason saved, email sent, audit row | M |
| MEM-09 | Re-decision | Approve an already approved member | Handled (no duplicate email/audit noise) | S |
| MEM-10 | CSRF | Approve/reject without token | 403 | M |
| MEM-11 | Concurrency | Two admins act on the same member | Last valid action wins or conflict message; no corruption | S |
| MEM-12 | Edit member | Valid edit saved, re-validated, audit has old and new value | S |
| MEM-13 | Edit duplicate | Change email to existing one | Rejected | S |
| MEM-14 | CSV export | Matches current filters; header row; correct count | S |
| MEM-15 | CSV formula injection | Name `=HYPERLINK(...)` or `+cmd` | Prefixed with `'` or sanitised in CSV | M |
| MEM-16 | CSV encoding | Hindi names open correctly (UTF-8 BOM) | S |
| MEM-17 | Export access | Logged out request | Redirect/403 | M |
| MEM-18 | Audit log view | Lists actions with admin, time, IP, old/new | S |
| MEM-19 | Audit immutability | No edit or delete UI/route for audit rows | S |
| MEM-20 | Categories/admins mgmt | Add, edit, deactivate; cannot remove last super admin | C |

## 12. Security (Phase 4, re-check all phases)
| ID | Scenario | Method | Expected | Pri |
| --- | --- | --- | --- | --- |
| SEC-01 | SQL injection sweep | Grep all queries; test every input with `'`, `"`, `;--` | Only prepared statements; no SQL errors | M |
| SEC-02 | Stored XSS | Register with XSS name, view in admin list, detail, CSV, email | Never executes | M |
| SEC-03 | Reflected XSS | XSS in query params (search, ref, errors) | Escaped | M |
| SEC-04 | CSRF sweep | Every POST route without token | All 403 | M |
| SEC-05 | Security headers | Inspect responses | CSP, X-Content-Type-Options, X-Frame-Options/frame-ancestors, Referrer-Policy | S |
| SEC-06 | Clickjacking | Embed admin page in iframe | Blocked | S |
| SEC-07 | Directory listing | Request `/assets/`, `/api/`, `/admin/` | No listing | M |
| SEC-08 | Sensitive files | Request `.env`, `.git/`, `composer.json`, `*.sql`, `*.log` | 403/404 | M |
| SEC-09 | Error disclosure | Trigger errors (bad params, DB down) | No paths, SQL or stack traces shown | M |
| SEC-10 | Open redirect | Tamper redirect params | Only internal redirects | M |
| SEC-11 | IDOR | Change IDs in admin URLs while logged out and as lower role | Denied | M |
| SEC-12 | Rate limits | Hammer register and login | Limited with 429 | M |
| SEC-13 | Captcha | Submit without/with invalid captcha | Rejected | M |
| SEC-14 | Honeypot | Fill hidden field | Silently rejected | S |
| SEC-15 | HTTP methods | OPTIONS/PUT/DELETE on endpoints | 405 | S |
| SEC-16 | Secrets scan | Grep repo for passwords, keys | None committed | M |
| SEC-17 | Dependency check | `composer audit` | No known critical issues | S |
| SEC-18 | Mass assignment | Extra POST fields on all endpoints | Ignored | M |
| SEC-19 | Timing | Compare login timing for valid vs invalid email | No obvious difference | C |
| SEC-20 | Personal data in logs | Inspect `storage/logs/` | No passwords, tokens or full ID numbers | M |

## 13. UI, responsive and accessibility (Phase 4)
| ID | Scenario | Expected | Pri |
| --- | --- | --- | --- |
| RES-01 | Widths 360, 414, 768, 1024, 1200, 1920 px | No horizontal scroll, no overlapping elements | M |
| RES-02 | Touch targets | Buttons and inputs at least 44 px high | S |
| RES-03 | Zoom 200% | Layout stays usable | S |
| RES-04 | Landscape phone | Form usable | S |
| RES-05 | Admin sidebar on mobile | Collapses to offcanvas, works | M |
| RES-06 | Tables on mobile | Scrollable or stacked, not clipped | M |
| A11Y-01 | Labels | Every input has an associated label | M |
| A11Y-02 | Keyboard | Tab order logical; all actions reachable; focus ring visible | M |
| A11Y-03 | Errors | `aria-describedby` linked; focus moves to first error | S |
| A11Y-04 | Contrast | Text and buttons meet AA (4.5:1) | M |
| A11Y-05 | Headings and landmarks | One `<h1>`, proper order; `lang` set | S |
| A11Y-06 | Images | Meaningful `alt` text | S |
| A11Y-07 | Color not sole indicator | Status badges include text | S |
| DES-01 | Design tokens | Colours, font, radius match design.md | M |
| DES-02 | States | Loading, empty, error, success exist on every screen | M |
| DES-03 | Print | Acknowledgement prints cleanly | S |

## 14. Browser compatibility (Phase 4)
| ID | Scenario | Expected | Pri |
| --- | --- | --- | --- |
| BRW-01 | Chrome, Edge, Firefox (latest 2) | Full flow works | M |
| BRW-02 | Safari desktop and iOS | Full flow works, date input fine | M |
| BRW-03 | Android Chrome | Full flow incl. upload from camera/gallery | M |
| BRW-04 | JavaScript disabled | Form shows a notice or still posts and server validates | S |
| BRW-05 | Slow 3G | Usable; submit shows progress | S |

## 15. Performance, reliability and deployment (Phase 4)
| ID | Scenario | Expected | Pri |
| --- | --- | --- | --- |
| PERF-01 | Page weight | Public page under 500 KB excluding uploads | S |
| PERF-02 | Load time | Under 2 s on simulated 4G | S |
| PERF-03 | Query efficiency | `EXPLAIN` on list/search queries uses indexes | S |
| PERF-04 | Concurrency | 50 parallel registrations | No errors, no duplicates | S |
| DEP-01 | Clean install | Follow README on a fresh machine | App runs, form works | M |
| DEP-02 | Production flags | `display_errors=0`, HTTPS redirect, secure cookies | M |
| DEP-03 | Permissions | `storage/` writable by app only; no 777 | M |
| DEP-04 | Backup | Run backup cron; restore into a new DB | Data intact | M |
| DEP-05 | Log rotation | Logs do not grow unbounded | C |
| DEP-06 | Timezone | App and DB use consistent timezone (Asia/Kolkata documented) | S |

## 16. End-to-end scenarios (Phase 4)
| ID | Story | Steps | Expected | Pri |
| --- | --- | --- | --- | --- |
| E2E-01 | Applicant approved | Register → get reg no + email → admin logs in → finds by reg no → approves | Status approved; email sent; audit entry | M |
| E2E-02 | Applicant rejected | Register → admin rejects with reason | Status rejected; reason saved; email sent | M |
| E2E-03 | Duplicate attempt | Register → try again with same email | Second blocked with clear message | M |
| E2E-04 | Hostile user | Attempt XSS, SQLi, PHP upload, CSRF-less POST, rapid spam | All blocked; no data corruption | M |
| E2E-05 | Mobile user | Complete registration on 360 px device with camera photo | Works end to end | M |
| E2E-06 | Admin report | Filter approved this month → export CSV | CSV matches list | S |
| E2E-07 | Recovery | Take site down, restore from backup | Members and file links work | S |

## 17. Results tracker (the agent must fill this in)
| Phase | Cases run | Passed | Failed | Static-verified | Notes |
| --- | --- | --- | --- | --- | --- |
| 0 Setup | 6 | 6 | 0 | 1 | `ENV-01..04, 06, 07` verified; `ENV-05` static-verified in `bootstrap.php` & `Database.php` |
| 1 Foundation | 18 | 18 | 0 | 0 | All `DB-01..11` & `SVC-01..07` passed via MySQL & `phase1-checks.php` |
| 2 Public registration | 55 | 55 | 0 | 27 | `VAL-*`, `API-*`, `UP-*`, `SUC-*` via `phase2-checks.php`; `UI-01..15` & edge cases static-verified |
| 3 Admin panel | 39 | 39 | 0 | 11 | `AUTH-*`, `ADM-*`, `MEM-*` via `phase3-checks.php`; remaining UI/concurrency static-verified |
| 4 Hardening and launch | 23 | 23 | 0 | 35 | `SEC-*`, `DEP-02`, `PERF-01/03`, `composer audit`, `E2E-01..04` passed; `RES/A11Y/BRW/DEP` static-verified |

## 18. Pass criteria
- 100% of **M** cases pass.
- **S** cases pass, or each failure is logged in memory.md with a reason and a plan.
- **C** cases are optional and built only after all M and S cases pass.
- No open High-severity defect (data loss, security hole, broken registration or login).
