# design.md — UI/UX Design System

Design direction: calm, official and trustworthy. Light theme, generous whitespace, one strong accent colour. Built on Bootstrap 5.3 with CSS variable overrides in `custom.css`.

## 1. Design tokens
```css
:root {
  --bs-primary: #1f4e79;        /* council navy */
  --bs-primary-rgb: 31, 78, 121;
  --accent: #c8963e;            /* muted gold, used sparingly */
  --bs-success: #2e7d4f;
  --bs-danger: #c0392b;
  --bs-warning: #d98e04;
  --bs-body-bg: #f5f7fa;
  --bs-body-color: #1d2733;
  --surface: #ffffff;
  --border: #dde3ea;
  --radius: 10px;
  --shadow: 0 2px 10px rgba(16, 24, 40, .06);
  --bs-body-font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
}
```

## 2. Typography
- Font: Inter (400, 500, 600). Fallback: system UI.
- Base 16 px / line-height 1.55. H1 28 px, H2 22 px, H3 18 px, labels 14 px / 500.
- Hindi text (if enabled): Noto Sans Devanagari.

## 3. Layout
- Container max width 1140 px; the form card is 860 px, centered.
- 8 px spacing scale (8, 16, 24, 32, 48).
- Breakpoints: Bootstrap defaults. Design mobile first.

## 4. Public registration page
1. **Header**: logo, council name, a thin navy bar.
2. **Hero strip**: title "Council Member Registration", one-line instruction, and a "takes about 5 minutes" note.
3. **Stepper** (4 steps) with progress bar: Personal → Contact & Address → Council Details → Documents & Declaration.
4. **Form card**: white surface, 10 px radius, soft shadow. Two columns on md+, one on mobile.
5. **Footer actions**: Back (outline), Next / Submit (primary, full width on mobile).
6. **Success screen**: green check, registration number in a large monospace badge, "Print acknowledgement" button.

## 5. Components
| Component | Spec |
| --- | --- |
| Inputs | Bootstrap `form-control`, 44 px height, floating-free labels above fields, required marked with a red asterisk |
| Validation | `is-valid` / `is-invalid` states, message under the field in 13 px red; error summary alert at the top on submit |
| Select | Native `form-select` (reliable on mobile) |
| Upload | Dashed drop zone, filename, size, thumbnail preview for images, remove button |
| Buttons | Primary navy, outline secondary; 44 px min height; loading spinner while submitting |
| Alerts/Toasts | Bootstrap toast for transient messages, alert for blocking errors |
| Badges | Status: pending = amber, approved = green, rejected = red |

## 6. Admin panel
- Left sidebar (collapses to offcanvas on mobile): Dashboard, Members, Categories, Admins, Logout.
- Dashboard: 4 stat cards (Total, Pending, Approved, Rejected), then a "Recent registrations" table.
- Members table: search box, status and category filters, date range, sortable columns, pagination (20 per page), row action "View".
- Member detail: two-column layout; left = details, right = documents preview and Approve / Reject panel (reject requires a reason).

## 7. States
Every screen defines: loading (skeleton or spinner), empty ("No members found"), error (clear message + retry), success.

## 8. Accessibility
- Visible labels, `aria-describedby` for errors, focus moved to first invalid field.
- Contrast AA minimum; focus ring never removed.
- Full keyboard use; `lang` attribute set; buttons are real `<button>` elements.

## 9. Content tone
Plain, polite, short. Error messages say what to do ("Enter a 10-digit mobile number"), not what went wrong.
