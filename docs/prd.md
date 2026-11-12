# prd.md — Product Requirements Document

## 1. Purpose
Let people register online as council members, and let admins review, approve and manage those registrations, replacing paper or ad-hoc collection.

## 2. Goals
- Registration takes under 5 minutes on a phone.
- No duplicate registrations (same email or mobile).
- Admins can find, review and decide on any registration in under a minute.
- Personal data is stored and handled securely.

## 3. Non-goals (v1)
Online payments, member login or self-service profile edits, SMS OTP, multi-language, mobile app.

## 4. Users
| Role | Description | Needs |
| --- | --- | --- |
| Applicant | Member of the public registering | Simple form, clear errors, confirmation |
| Reviewer | Admin who approves or rejects | Filtered list, document preview, decision with reason |
| Super admin | Manages admins and categories | Everything a reviewer has, plus user management and audit log |

## 5. User stories
**Applicant**
- As an applicant, I fill a multi-step form with inline validation so I can fix mistakes before submitting.
- As an applicant, I upload my photo and ID proof so my application is complete.
- As an applicant, I receive a registration number and an email so I have proof of submission.

**Admin**
- As an admin, I log in securely so only authorised people see data.
- As an admin, I search and filter members by status, category and date.
- As an admin, I view a member's details and documents and approve or reject with a reason.
- As an admin, I export filtered members to CSV.
- As a super admin, I see an audit log of every admin action.

## 6. Functional requirements
### 6.1 Registration form
| ID | Requirement | Priority |
| --- | --- | --- |
| R1 | 4-step form with progress indicator | Must |
| R2 | Fields: full name, father/spouse name, DOB, gender, email, mobile, address, city, state, PIN, council category, ID proof number | Must |
| R3 | Uploads: photo (jpg/png), ID proof (pdf/jpg/png), max 2 MB | Must |
| R4 | Client and server validation with field-level messages | Must |
| R5 | Unique email and mobile, live duplicate check | Must |
| R6 | Consent and declaration checkbox | Must |
| R7 | Captcha and rate limit | Must |
| R8 | Confirmation email with registration number | Should |
| R9 | Printable acknowledgement page | Should |
| R10 | Save progress in the browser (localStorage) between steps | Could |

### 6.2 Validation rules
- Name: 2–120 chars, letters, spaces, dots and hyphens.
- Mobile: 10 digits starting 6–9.
- Email: valid format, max 150 chars.
- DOB: valid date; applicant at least 18 years old (confirm).
- PIN: 6 digits.
- Files: allowed types only, max 2 MB, real MIME checked on the server.

### 6.3 Admin panel
| ID | Requirement | Priority |
| --- | --- | --- |
| A1 | Login with hashed passwords, lockout after 5 failed attempts | Must |
| A2 | Dashboard with total, pending, approved, rejected counts | Must |
| A3 | Members list with search, filters, sort, pagination | Must |
| A4 | Member detail with document preview | Must |
| A5 | Approve / reject (reason required), email notification | Must |
| A6 | CSV export of filtered list | Should |
| A7 | Edit member details | Should |
| A8 | Audit log view | Should |
| A9 | Manage admins and categories | Could |

## 7. Data
Tables: members, categories, documents, admins, audit_log, login_attempts (see architecture.md).

## 8. Non-functional requirements
- Security per rules.md; HTTPS only.
- Responsive from 360 px; WCAG AA contrast and keyboard access.
- Page load under 2 s on 4G; supports Chrome, Safari, Firefox, Edge (last 2 versions).
- Daily backups, 30-day retention.

## 9. Acceptance criteria / test checklist
- [ ] Valid submission creates a member, files, a registration number and an email.
- [ ] Empty, too-long and malicious input is rejected server-side with clear messages.
- [ ] Duplicate email or mobile is rejected, including two simultaneous submissions.
- [ ] A renamed `.php` file or oversized file is rejected.
- [ ] Admin pages redirect to login when logged out.
- [ ] Approve and reject update status, write an audit entry and send an email.
- [ ] CSV export matches the filtered list.
- [ ] Layout works at 360, 768 and 1200 px.

## 10. Success metrics
Registration completion rate above 80%, zero duplicate records, average admin review time under 1 minute, zero security incidents.

## 11. Risks
| Risk | Mitigation |
| --- | --- |
| Spam or bot registrations | Captcha, honeypot, rate limit |
| Malicious uploads | Strict whitelist, files outside web root |
| Personal data leak | HTTPS, restricted admin access, audit log |
| Scope growth | Change only through this PRD and phase.md |
