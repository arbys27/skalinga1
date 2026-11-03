# SKalinga San Antonio — Full System Module Test Cases

## Document purpose

This is the execution-oriented QA test plan for the current SKalinga codebase. It is organized by module and follows the actual pages, JavaScript, PHP APIs, sessions, uploads, and database entities found in the repository.

The tester should record:

- **Status:** Not Run / Pass / Fail / Blocked
- **Evidence:** screenshot, browser console, network response, API response, database query result, or log reference
- **Defect ID:** linked issue when a case fails

Unless a case says otherwise, test both:

- Desktop Chromium at 1440px.
- Mobile viewport at 375px wide.

Never use production personal data. Use synthetic accounts, files, names, phone numbers, and CSV records.

---

## 1. Test environment and baseline

### 1.1 Environment assumptions

| Item | Expected/current implementation |
|---|---|
| Web server | Apache/XAMPP serving `http://localhost/SKalinga/` |
| PHP | PHP 8.x; syntax baseline previously verified on PHP 8.2.12 |
| Database | MySQL/MariaDB database `skalinga_youth` |
| Browser | Chromium-based browser with DevTools |
| Accounts | One youth, one `staff`/SK official, one `superadmin` |
| Test files | Valid/invalid JPG, PNG, PDF, DOCX, XLSX, TXT, oversized files, renamed MIME-spoofed files |
| Test data | At least two youth users, three resources, three events, incidents in each status, and printing requests in each status |

### 1.2 Baseline smoke cases

| ID | Steps | Expected |
|---|---|---|
| ENV-001 | Start Apache and MySQL; open `index.html`. | Redirect reaches `login.html`; no PHP/JS fatal error. |
| ENV-002 | Open `login.html`, inspect DevTools Console and Network. | Page renders without blocking errors; external dependency failures are visible and non-fatal. |
| ENV-003 | Request `api/me.php` without a session. | HTTP 401 JSON response; no HTML warning or stack trace. |
| ENV-004 | Request `api/check_admin_session.php` without a session. | HTTP 401/JSON response; no protected data. |
| ENV-005 | Open every HTML page directly without a session. | Public pages remain usable; protected pages redirect to login. |
| ENV-006 | Use browser refresh during each authenticated module. | Session remains valid; data reloads without duplicate submissions. |
| ENV-007 | Stop the database and load a data page. | Visible user-facing error/retry state; no raw SQL credentials or exception text. |
| ENV-008 | Restore the database and retry. | Module recovers without a full browser restart. |
| ENV-009 | Run `php -l` over all PHP files. | Zero syntax errors. |
| ENV-010 | Load the application at 375px, 768px, and desktop widths. | No critical clipping, horizontal page scroll, inaccessible modal, or hidden submit control. |

---

# 2. Authentication, session, registration, and recovery

## 2.1 Login page — `login.html` / `api/login.php`

### Controls and UI checks

| ID | Test flow | Expected |
|---|---|---|
| AUTH-001 | Open login page. Inspect logo, title, identifier input, password input, submit button, password visibility control, forgot-password control, registration link, and error region. | All controls are visible, labeled, keyboard reachable, and visually aligned. |
| AUTH-002 | Submit with both fields empty. | Native/server validation identifies both required fields; no request with empty credentials. |
| AUTH-003 | Submit identifier only. | Password-required validation appears. |
| AUTH-004 | Submit password only. | Identifier-required validation appears. |
| AUTH-005 | Toggle password visibility twice. | Type changes password/text and returns correctly; password value is not lost. |
| AUTH-006 | Press Tab through the complete form and press Enter on submit. | Logical focus order; Enter submits once. |
| AUTH-007 | Submit valid youth email/password. | HTTP success, role `youth`, redirect to `youth-portal.html`, authenticated session created. |
| AUTH-008 | Submit valid youth voter ID/password. | Same successful youth result. |
| AUTH-009 | Submit valid youth phone/password. | Same successful youth result. |
| AUTH-010 | Submit valid admin username/password. | Role maps correctly to `admin` or `sk_official`; redirect to admin dashboard. |
| AUTH-011 | Submit valid admin email/password. | Same successful admin result. |
| AUTH-012 | Submit wrong password. | HTTP 401; generic invalid-credentials message; no account-existence disclosure. |
| AUTH-013 | Submit unknown identifier. | Same generic invalid-credentials response. |
| AUTH-014 | Submit inactive/suspended user credentials. | Login rejected; no protected session. |
| AUTH-015 | Submit an identifier that matches both admin and youth. | HTTP 409 collision response; no account selected implicitly. |
| AUTH-016 | Submit ten or more invalid attempts from one IP within the rate window. | Rate-limit response; no database overload; clear retry guidance. |
| AUTH-017 | Add `redirect=javascript:...`, external URL, path traversal, and unknown filename. | Redirect is restricted to the server allowlist; no open redirect/XSS. |
| AUTH-018 | Submit duplicate clicks rapidly. | One effective login request/session; no duplicate activity-log corruption. |
| AUTH-019 | Inspect response bodies and browser storage. | No password, password hash, OTP, or sensitive database detail returned. |
| AUTH-020 | Login, copy session cookie, log out, then replay the cookie. | Replay is rejected; session is destroyed. |
| AUTH-021 | Login, inspect session identifier before/after. | Session identifier changes after authentication. |
| AUTH-022 | Login with Unicode/whitespace variants of identifiers. | Normalization behavior is consistent and documented; no unintended account collision. |

## 2.2 Logout and session lifecycle — `api/logout.php`, `api/me.php`

| ID | Test flow | Expected |
|---|---|---|
| AUTH-023 | Click logout from youth portal. | Session is cleared and browser reaches login. |
| AUTH-024 | Click logout from each admin page. | Same result regardless of direct link or JavaScript confirmation. |
| AUTH-025 | Cancel logout confirmation. | User remains on page and session remains valid. |
| AUTH-026 | Call `api/me.php` while authenticated as youth. | `authenticated: true`, role `youth`, correct user summary. |
| AUTH-027 | Call `api/me.php` while authenticated as admin/SK official. | Correct role and identifier, no password fields. |
| AUTH-028 | Call protected API after logout. | HTTP 401 or redirect; no data mutation. |
| AUTH-029 | Open a protected page from browser history after logout. | Client guard redirects; page does not expose protected data. |
| AUTH-030 | Let session expire or invalidate it server-side while page is open; trigger an API call. | 401 handled visibly; stale protected UI is not presented as current. |

## 2.3 Youth activation — `youth-register.html`, `masterlist_verify.php`, `masterlist_activate.php`

| ID | Test flow | Expected |
|---|---|---|
| AUTH-031 | Open registration as unauthenticated user. | Two-step activation UI renders with progress state. |
| AUTH-032 | Submit empty verification step. | Required voter ID, name, and birthday errors. |
| AUTH-033 | Submit unknown voter ID. | Exact no-match response; no masterlist data leakage. |
| AUTH-034 | Submit correct voter ID with wrong name. | Verification fails. |
| AUTH-035 | Submit correct voter ID/name with wrong birthday. | Verification fails. |
| AUTH-036 | Submit correct voter ID/name/birthday. | Masterlist profile is returned and step two opens. |
| AUTH-037 | Test invalid phone length, letters, spaces, and invalid prefix. | Server and client reject invalid phone format. |
| AUTH-038 | Test optional email blank, invalid email, and duplicate email. | Blank is allowed if intended; invalid/duplicate values reject safely. |
| AUTH-039 | Test password below minimum and mismatched confirmation. | Form cannot activate. |
| AUTH-040 | Submit without privacy consent. | Activation is blocked. |
| AUTH-041 | Activate valid record. | User, profile, and masterlist claim are created atomically. |
| AUTH-042 | Repeat activation for the same record. | Rejected as already claimed; no duplicate user/profile. |
| AUTH-043 | Force a database failure during activation. | Transaction rolls back user, profile, and claim together. |
| AUTH-044 | Refresh/back between steps. | No accidental duplicate activation or stale verification bypass. |

## 2.4 Password reset and OTP

Affected routes include `request_password_reset.php`, `reset_password.php`, `send_login_otp.php`, `verify_login_otp.php`, `send_registration_otp.php`, `verify_registration_otp.php`, `send_sms_otp.php`, and `verify_sms_otp.php`.

| ID | Test flow | Expected |
|---|---|---|
| AUTH-045 | Request reset with empty/invalid phone. | HTTP 400 validation response. |
| AUTH-046 | Request reset for unknown phone. | Generic response does not reveal account existence. |
| AUTH-047 | Request reset for known phone. | OTP delivery attempt occurs without exposing user ID or debug field. |
| AUTH-048 | Submit correct OTP before expiry. | Password reset succeeds once. |
| AUTH-049 | Submit wrong OTP repeatedly. | Attempts are limited/rate-limited; no unlimited guessing. |
| AUTH-050 | Submit expired OTP. | Reset rejected. |
| AUTH-051 | Reuse a successful OTP. | Rejected as already used. |
| AUTH-052 | Submit concurrent reset requests with same OTP. | Only one request can consume OTP and update password. |
| AUTH-053 | Inspect OTP database record. | OTP is hashed, expiring, attempt-limited, and not logged in plaintext. |
| AUTH-054 | Block SMS provider or induce timeout. | User receives a safe failure/retry state; no success-shaped false fallback. |
| AUTH-055 | Inspect TLS and credential configuration. | TLS verification enabled; credential comes from environment/secret manager. |
| AUTH-056 | Send cross-origin request to OTP route. | CORS is restricted to trusted origin; authentication operation is not broadly exposed. |

---

# 3. Shared youth portal shell and navigation

Affected page: [`youth-portal.html`](C:/xampp/htdocs/SKalinga/youth-portal.html).

| ID | Test flow | Expected |
|---|---|---|
| PORTAL-001 | Load portal as youth. | Correct name, avatar/initials, member information, navigation, service cards, and logout control appear. |
| PORTAL-002 | Click Events service card. | Navigates to `events.html`. |
| PORTAL-003 | Click Health service card. | Navigates to `health.html`. |
| PORTAL-004 | Click Borrow service card. | Navigates to `borrow.html`. |
| PORTAL-005 | Click Printing service card. | Navigates to `print.html`. |
| PORTAL-006 | Click Report service card. | Navigates to `report.html`. |
| PORTAL-007 | Click Public Disclosure service card. | Navigates to `public-disclosure.html`. |
| PORTAL-008 | Activate every card with keyboard only. | Cards are reachable and activate without mouse; if not, record accessibility defect. |
| PORTAL-009 | Load portal with profile API failure. | User sees an error/placeholder, not misleading permanent data. |
| PORTAL-010 | Load portal with empty counts/history. | Empty state is understandable and layout remains stable. |
| PORTAL-011 | Resize to mobile and rotate orientation. | Cards, header, FAB, and navigation remain usable. |
| PORTAL-012 | Inspect all external images/fonts/scripts blocked. | Layout remains usable; fallback state is graceful. |

---

# 4. Youth events module

Page: [`events.html`](C:/xampp/htdocs/SKalinga/events.html). APIs: `get_events.php`, `get_user_profile.php`.

| ID | Test flow | Expected |
|---|---|---|
| EVT-Y-001 | Load page authenticated as youth. | Profile/header loads; event list enters loading then loaded state. |
| EVT-Y-002 | Load valid upcoming/past event data. | Cards show title, type, date, time, location, image, capacity/status as appropriate. |
| EVT-Y-003 | Use search input with title, location, partial text, mixed case, and special characters. | Results filter correctly; no script execution. |
| EVT-Y-004 | Search with no match. | “No events” state appears and does not resemble a loading/error state. |
| EVT-Y-005 | Clear search/reset filters. | Full list returns. |
| EVT-Y-006 | Click each event card/details button. | Details modal opens with correct event and no stale previous content. |
| EVT-Y-007 | Close details using close control, backdrop, and Escape. | Modal closes; focus returns to triggering control. |
| EVT-Y-008 | Test long title, description, location, and image failure. | Text wraps; fallback image/alt text is usable. |
| EVT-Y-009 | Simulate `get_events.php` 401, 500, timeout, malformed JSON. | Visible actionable error and retry; no console-only failure. |
| EVT-Y-010 | Compare server data with `localStorage` fallback. | Server remains source of truth; stale local data cannot mask outage. |
| EVT-Y-011 | Open as unauthenticated, admin, and SK official. | Only intended youth access is allowed; role behavior is documented and consistent. |
| EVT-Y-012 | Use keyboard and screen reader landmarks. | Search, cards, modal, and back navigation are operable and announced. |
| EVT-Y-013 | Test 320px/375px/768px widths. | No clipped card actions or inaccessible modal content. |

---

# 5. Admin events dashboard

Page: [`dashboard-events.html`](C:/xampp/htdocs/SKalinga/dashboard-events.html). APIs: `get_events.php`, `add_event.php`, `update_event.php`, `delete_event.php`.

## 5.1 Navigation and table

| ID | Test flow | Expected |
|---|---|---|
| EVT-A-001 | Load as admin and SK official. | Dashboard shell, sidebar, profile role, filters, search, table, and action controls load. |
| EVT-A-002 | Load as youth/unauthenticated. | Redirect or 401/403; no event administration data. |
| EVT-A-003 | Use search by title/location. | Table filters correctly and safely. |
| EVT-A-004 | Use status/type/date filters and reset them. | Filter combinations produce correct rows; reset restores all. |
| EVT-A-005 | Test empty table and API failure. | Correct empty/error rows and retry behavior. |
| EVT-A-006 | Click view/details action for every row. | Modal shows correct complete record. |
| EVT-A-007 | Close view modal through every close path. | Modal closes and focus returns. |

## 5.2 Create/edit form

| ID | Test flow | Expected |
|---|---|---|
| EVT-A-008 | Open create modal. | All title, type, date, time, location, capacity, registration URL, description, status, and image controls appear. |
| EVT-A-009 | Submit empty form. | Required fields prevent submission. |
| EVT-A-010 | Test invalid date, time, end-before-start, negative capacity, oversized capacity, invalid URL, and overlong text. | Server rejects invalid values; no partial row. |
| EVT-A-011 | Upload valid JPG/PNG within size limit. | Preview/upload succeeds; stored path is server-generated and safe. |
| EVT-A-012 | Upload renamed executable/script, invalid MIME, oversized file, and duplicate filename. | Rejected by server; no executable/public unsafe file. |
| EVT-A-013 | Submit valid new event once. | One event created; table refreshes; success feedback appears. |
| EVT-A-014 | Double-click submit or retry after timeout. | No duplicate event unless request truly did not complete and user confirms retry. |
| EVT-A-015 | Edit every editable field and replace image. | Row updates; old image cleanup occurs only after new upload succeeds. |
| EVT-A-016 | Cancel create/edit with unsaved changes. | Confirmation appears or changes are discarded predictably. |
| EVT-A-017 | Delete event and cancel deletion. | Cancel leaves row; confirm removes intended row only. |
| EVT-A-018 | Delete nonexistent/already-deleted event. | Safe 404/error; no raw exception. |
| EVT-A-019 | Create events concurrently. | Event IDs remain unique; no `COUNT(*)` collision. |

## 5.3 Database/API checks

| ID | Test flow | Expected |
|---|---|---|
| EVT-A-020 | Inspect POST payload and response. | Expected field names, HTTP status, and JSON shape. |
| EVT-A-021 | Force insert failure after image upload. | Transaction/error handling removes orphan file or reports cleanup. |
| EVT-A-022 | Submit SQL metacharacters in text fields. | Stored as text; no SQL injection. |
| EVT-A-023 | Access add/update/delete with youth session. | 403/401 and no mutation. |

---

# 6. Health announcements modules

Youth page: [`health.html`](C:/xampp/htdocs/SKalinga/health.html). Admin page: [`admin-health-announcements.html`](C:/xampp/htdocs/SKalinga/admin-health-announcements.html). APIs under `api/health_announcements/`.

| ID | Test flow | Expected |
|---|---|---|
| HLTH-001 | Load youth page. | Loading, error, empty, upcoming, past, and detail states are distinct. |
| HLTH-002 | Use category chips and upcoming/past toggle. | Correct filtering; active state is visible and keyboard accessible. |
| HLTH-003 | Search by title/category/venue. | Correct case-insensitive results; no unsafe HTML injection. |
| HLTH-004 | Open announcement details and close via all controls. | Correct record; accessible modal behavior. |
| HLTH-005 | Load no announcements. | Clear empty state and no broken cards. |
| HLTH-006 | Load API 401/500/timeout/malformed response. | Visible error and retry; no raw exception. |
| HLTH-007 | Open admin page as youth/unauthenticated. | Access denied/redirect. |
| HLTH-008 | Create announcement with every valid category/status/date/time/slot combination. | Correct validation and stored row. |
| HLTH-009 | Submit missing title/category/description/date/venue. | Field-specific validation. |
| HLTH-010 | Submit invalid date/time/category/status/negative slots. | Rejected server-side. |
| HLTH-011 | Upload valid JPG/PNG/WEBP and invalid/oversized/MIME-spoofed file. | Only allowed files accepted. |
| HLTH-012 | Preview announcement. | Preview matches final public rendering and escapes user text. |
| HLTH-013 | Save draft. | Draft appears to admin but not youth public listing. |
| HLTH-014 | Publish. | Youth sees announcement according to publication rules. |
| HLTH-015 | Cancel/archive/unpublish. | Public visibility/status changes correctly and reversibly where intended. |
| HLTH-016 | Edit announcement and replace/remove image. | Correct update and safe old-file cleanup. |
| HLTH-017 | Delete announcement, cancel deletion, repeat deletion. | Correct confirmation and idempotent failure handling. |
| HLTH-018 | Test pagination, per-page limits, invalid page values, and sort values. | Bounds are enforced; no SQL manipulation. |
| HLTH-019 | Verify activity log for create/update/status/delete. | Audit entry contains actor, action, timestamp, and safe identifier. |

---

# 7. Youth borrowing/history module

Page: [`borrow.html`](C:/xampp/htdocs/SKalinga/borrow.html).

| ID | Test flow | Expected |
|---|---|---|
| BOR-Y-001 | Load page as youth. | Profile and borrowing history load with correct identity. |
| BOR-Y-002 | Load no history. | Clear empty state and request guidance. |
| BOR-Y-003 | Load active, returned, and overdue records. | Status, dates, quantity, and item names are accurate. |
| BOR-Y-004 | Click refresh repeatedly. | No duplicate rows or stuck loading overlay. |
| BOR-Y-005 | Open digital ID modal. | Correct member data/QR; close and keyboard behavior work. |
| BOR-Y-006 | Force profile/history 401 or 500. | Visible error and safe redirect/retry. |
| BOR-Y-007 | Login as Youth A and attempt to view Youth B history. | Only Youth A records appear. |
| BOR-Y-008 | Resize mobile and test long item names/statuses. | Cards/table remain readable without clipped controls. |

---

# 8. Admin resources and lending

Page: [`dashboard-resources.html`](C:/xampp/htdocs/SKalinga/dashboard-resources.html). APIs: `get_resources.php`, `add_resource.php`, `borrow_resource.php`, `return_resource.php`, `get_borrow_records.php`, `delete_borrow_record.php`.

## 8.1 Resource inventory

| ID | Test flow | Expected |
|---|---|---|
| RES-001 | Load dashboard as admin/SK official. | Inventory table, search, status filters, add-item control, borrow/return controls render. |
| RES-002 | Search by item/borrower and clear search. | Correct filtered rows and reset. |
| RES-003 | Add resource with valid name, description, quantity. | Resource created with quantity and available count equal. |
| RES-004 | Submit empty/negative/decimal/very large quantity. | Server rejects invalid quantity. |
| RES-005 | Add duplicate item identifier/name. | Duplicate policy is enforced; no accidental overwrite. |
| RES-006 | Simulate add API error. | Form re-enables; visible error; no duplicate retry. |
| RES-007 | Delete resource with active borrow records. | Deletion follows explicit business rule and preserves integrity. |

## 8.2 Borrow flow

| ID | Test flow | Expected |
|---|---|---|
| RES-008 | Open borrow modal. | Item selector, youth ID/manual input, borrower info, quantity, due date, confirmation controls render. |
| RES-009 | Search/select a youth by QR/member ID. | Correct name/member data populate; no mismatch. |
| RES-010 | Enter nonexistent member ID or mismatched name. | Borrow blocked; server verifies identity. |
| RES-011 | Submit missing item/member/name/due date. | Field validation. |
| RES-012 | Submit past/invalid due date. | Rejected or explicit policy applied. |
| RES-013 | Borrow quantity 1, exact available quantity, zero, negative, and above available. | Valid quantities succeed; invalid/over-limit requests reject. |
| RES-014 | Confirm valid borrow. | Record created; available decremented exactly once; table refreshes. |
| RES-015 | Double-click confirmation or retry after timeout. | No duplicate borrow or double decrement. |
| RES-016 | Run two concurrent borrows that together exceed availability. | Database row lock/transaction prevents negative availability. |

## 8.3 Return/delete flow

| ID | Test flow | Expected |
|---|---|---|
| RES-017 | Click return on active borrow. | Confirmation shows correct record; return restores quantity once. |
| RES-018 | Cancel return. | No state change. |
| RES-019 | Return already-returned record. | Rejected without incrementing available count. |
| RES-020 | Delete borrow record before and after return. | Business rule is explicit; inventory remains correct. |
| RES-021 | Force failure during return transaction. | Record and resource count roll back together. |
| RES-022 | Test status/filter/detail modal. | Correct status and dates shown; no stale row data. |
| RES-023 | Access every resource API as youth/unauthenticated. | 401/403 and no mutation. |

---

# 9. Youth printing module

Page: [`print.html`](C:/xampp/htdocs/SKalinga/print.html). APIs: `upload_print_request.php`, `get_user_print_requests.php`, `get_user_profile.php`.

| ID | Test flow | Expected |
|---|---|---|
| PRINT-Y-001 | Load page as youth. | Upload form, file control, document title, print type, paper size, copies, submit button, history, and digital ID controls render. |
| PRINT-Y-002 | Submit empty form. | Required field messages; no upload request. |
| PRINT-Y-003 | Enter valid title, print type, paper size, copies, and document. | Preview/selection state is accurate. |
| PRINT-Y-004 | Test zero, negative, decimal, huge, and nonnumeric copies. | Invalid values are blocked server-side. |
| PRINT-Y-005 | Upload allowed PDF/DOC/DOCX/JPG/JPEG/PNG/TXT. | Allowed content succeeds only when genuinely valid. |
| PRINT-Y-006 | Upload executable renamed as allowed extension, invalid MIME, oversized file, empty file, and duplicate name. | Server rejects unsafe/invalid content. |
| PRINT-Y-007 | Submit valid request once. | Request created with correct youth/member identity and Pending status. |
| PRINT-Y-008 | Double-submit and retry after network timeout. | No duplicate request or orphan upload. |
| PRINT-Y-009 | Load history with Pending/Printing/Completed/Claimed records. | Correct status labels, dates, title, and file metadata. |
| PRINT-Y-010 | Login as Youth A and inspect Youth B request path/query. | Only own records returned. |
| PRINT-Y-011 | Load profile/history API failure. | Visible error and retry; controls re-enable. |
| PRINT-Y-012 | Open digital ID modal and close every way. | Correct data and accessible focus behavior. |
| PRINT-Y-013 | Test mobile upload/form/history layout. | Inputs and submit controls remain reachable. |
| PRINT-Y-014 | Inspect upload storage and response. | No unsafe filename/path traversal; no internal filesystem path exposed. |

---

# 10. Admin printing dashboard

Page: [`dashboard-printing.html`](C:/xampp/htdocs/SKalinga/dashboard-printing.html). APIs: `get_print_requests.php`, `update_print_status.php`, `delete_print_request.php`, `get_user.php`.

| ID | Test flow | Expected |
|---|---|---|
| PRINT-A-001 | Load dashboard as admin/SK official. | Sidebar, profile, search, filters, request table, details, status, delete, QR/manual lookup controls render. |
| PRINT-A-002 | Open as youth/unauthenticated. | Access denied; no request data. |
| PRINT-A-003 | Search by member ID/name/title and clear. | Correct filtered rows. |
| PRINT-A-004 | Filter by Pending/Printing/Completed/Claimed and reset. | Correct statuses and counts. |
| PRINT-A-005 | Open request details. | Correct file metadata, member, copies, type, paper size, notes, timestamps. |
| PRINT-A-006 | Change status through every valid transition. | Server validates transition; table and youth history update. |
| PRINT-A-007 | Submit invalid status or nonexistent request ID. | Safe 400/404; no mutation. |
| PRINT-A-008 | Delete request and cancel confirmation. | Cancel preserves; confirm deletes intended row and handles file cleanup. |
| PRINT-A-009 | Delete already-deleted request. | Safe idempotent error; no raw exception. |
| PRINT-A-010 | Scan valid QR/member ID. | Correct youth lookup populates intended fields. |
| PRINT-A-011 | Scan malformed, unknown, or another user’s ID. | Clear validation/not-found response; no unrelated data leak. |
| PRINT-A-012 | Click every action rapidly. | No duplicate status updates/delete requests. |
| PRINT-A-013 | Simulate API 401/500/timeout. | Visible error, table state preserved, retry available. |
| PRINT-A-014 | Verify status update/delete authorization for SK official and youth. | Intended role matrix enforced; youth cannot mutate. |
| PRINT-A-015 | Test table at mobile width and long filenames. | No inaccessible action buttons or unreadable status. |

---

# 11. Youth incident reporting

Page: [`report.html`](C:/xampp/htdocs/SKalinga/report.html). APIs: `submit_incident.php`, `get_user_incidents.php`.

| ID | Test flow | Expected |
|---|---|---|
| INC-Y-001 | Load page as youth. | Category, description, location, urgency, photo input, selected-file list, submit, history, and modal controls render. |
| INC-Y-002 | Submit empty form. | Required validation for category, description, and location. |
| INC-Y-003 | Test every valid category and urgency. | Values submit and persist correctly. |
| INC-Y-004 | Submit invalid category/urgency by tampering request. | Server rejects. |
| INC-Y-005 | Submit boundary-length description/location. | Limits are enforced without truncation surprises. |
| INC-Y-006 | Select zero, one, and multiple valid images. | Files preview/list correctly; remove control removes only intended file. |
| INC-Y-007 | Upload non-image, renamed script, oversized image, and malformed upload. | Server rejects by MIME/content/size; no unsafe file stored. |
| INC-Y-008 | Submit valid incident once. | Incident created under authenticated member ID; confirmation shown. |
| INC-Y-009 | Double-submit or refresh after submission. | No accidental duplicate incident. |
| INC-Y-010 | Load incident history with all statuses and notes. | Only own records; correct status/date/photo rendering. |
| INC-Y-011 | Force submit/history API failure. | Visible actionable error, not only console log. |
| INC-Y-012 | Test emergency/high urgency presentation and confirmation. | User sees appropriate warning without accidental submission. |
| INC-Y-013 | Test mobile layout, keyboard flow, screen-reader labels, and photo controls. | Complete report can be submitted accessibly. |

---

# 12. Admin incident dashboard

Page: [`dashboard-incidents.html`](C:/xampp/htdocs/SKalinga/dashboard-incidents.html). APIs: `get_incidents.php`, `update_incident_status.php`, `delete_incident.php`.

| ID | Test flow | Expected |
|---|---|---|
| INC-A-001 | Load dashboard. | Table, search, status filter, detail modal, photo lightbox, notes, update, delete controls render. |
| INC-A-002 | Search by member/name/location and clear. | Correct results. |
| INC-A-003 | Filter every status and empty state. | Correct rows and clear empty message. |
| INC-A-004 | Open detail for no-photo and multi-photo incidents. | Correct text/photos; lightbox opens/closes safely. |
| INC-A-005 | Add admin notes with special characters and long text. | Escaped display, persisted note, no XSS. |
| INC-A-006 | Update status through valid statuses. | Correct persistence and youth history visibility. |
| INC-A-007 | Tamper with invalid status/nonexistent ID. | Rejected; no affected-row false success. |
| INC-A-008 | Delete and cancel delete. | Correct confirmation and deletion. |
| INC-A-009 | Attempt delete/update as youth. | 403/401 and no mutation. |
| INC-A-010 | Simulate failed image load and API failure. | Placeholder/error state; no broken layout. |
| INC-A-011 | Verify incident photo paths cannot expose arbitrary server files. | Only controlled upload paths render. |
| INC-A-012 | Test mobile table, lightbox, notes form, and action buttons. | All actions remain usable. |

---

# 13. Youth public disclosures

Page: [`public-disclosure.html`](C:/xampp/htdocs/SKalinga/public-disclosure.html). API: `api/public_disclosures/list.php`.

| ID | Test flow | Expected |
|---|---|---|
| DISC-Y-001 | Load as youth. | Published disclosure cards/table render with title/category/description/file metadata. |
| DISC-Y-002 | Filter every category and reset. | Correct published documents only. |
| DISC-Y-003 | Open/download each supported file type. | Correct file opens/downloads; no path disclosure beyond intended URL. |
| DISC-Y-004 | Load draft/cancelled/archived records. | Hidden from youth public list according to status. |
| DISC-Y-005 | Load empty and API failure states. | Clear empty/error/retry UI. |
| DISC-Y-006 | Attempt page/API access unauthenticated/admin/youth. | Role policy is consistent with code/documentation. |
| DISC-Y-007 | Test long titles/descriptions and mobile layout. | Text wraps and cards remain usable. |

---

# 14. Admin public disclosures

Page: [`dashboard-public_disclosure.html`](C:/xampp/htdocs/SKalinga/dashboard-public_disclosure.html). APIs: `list_admin.php`, `create.php`, `delete.php`.

| ID | Test flow | Expected |
|---|---|---|
| DISC-A-001 | Load admin page as admin/SK official. | Upload form, category/status controls, feedback region, table, delete actions render. |
| DISC-A-002 | Submit missing title/category/document. | Specific validation; no stored file. |
| DISC-A-003 | Upload valid PDF/DOC/DOCX/XLS/XLSX/JPG/PNG under 10MB. | Upload and database row succeed. |
| DISC-A-004 | Upload invalid MIME, renamed executable, oversized, empty, and truncated file. | Server rejects. |
| DISC-A-005 | Use every valid category/status. | Correct stored/public behavior. |
| DISC-A-006 | Upload same original filename repeatedly. | Server-generated unique names; no overwrite. |
| DISC-A-007 | Force database insert failure after upload. | Uploaded file is cleaned up or failure is clearly tracked. |
| DISC-A-008 | Delete document and cancel delete. | Correct row/file behavior; no arbitrary path deletion. |
| DISC-A-009 | Access admin routes as youth/unauthenticated. | 401/403; no file metadata leak. |
| DISC-A-010 | Test API failure, mobile table, keyboard, and feedback region. | Usable and accessible. |

---

# 15. Youth profile and digital ID

Page: [`profile.html`](C:/xampp/htdocs/SKalinga/profile.html). Related APIs: `get_user_profile.php`, `update_profile.php`, `update_user.php`.

| ID | Test flow | Expected |
|---|---|---|
| PROF-001 | Load profile as youth. | Name, email/member ID, birthday, gender, phone, address, bio, avatar, digital ID controls display correctly. |
| PROF-002 | Submit profile with each field unchanged. | No unintended blanking or data loss. |
| PROF-003 | Change first/last name, phone, address, bio. | Server validates and persists after reload. |
| PROF-004 | Submit short names, invalid phone, invalid gender, invalid age/birthday, missing required values. | Server rejects with field-specific errors. |
| PROF-005 | Change password with correct current password. | Password changes; old password fails and new password succeeds. |
| PROF-006 | Change password with wrong current password. | No password change; transaction rolls back. |
| PROF-007 | Submit empty new password/current password combinations. | No accidental password change. |
| PROF-008 | Select profile photo and cancel/change selection. | Preview updates only; persistence behavior is explicit. |
| PROF-009 | Click Save and reload page. | Values persist. Current code must be checked for the known demo-only save behavior. |
| PROF-010 | Open/close digital ID modal using button, close, backdrop, Escape, and keyboard. | Modal is accessible and focus returns. |
| PROF-011 | Load profile API failure/401. | Visible error or redirect; no fake saved state. |
| PROF-012 | Test Youth A/B isolation by manipulating request/session. | Cannot update/read another user. |
| PROF-013 | Test mobile form, keyboard labels, and screen-reader announcements. | Complete profile workflow is usable. |

---

# 16. Youth registry module

Page: [`youth-registry.html`](C:/xampp/htdocs/SKalinga/youth-registry.html). APIs: `get_youth.php`, `add_youth.php`, `update_youth.php`, `delete_youth.php`, `get_user.php`.

| ID | Test flow | Expected |
|---|---|---|
| REG-001 | Load as admin/SK official. | Registry table, search/filter, add, edit, delete, QR/detail controls render. |
| REG-002 | Load as youth/unauthenticated. | Access denied. |
| REG-003 | Search by name/member/email/phone and clear. | Correct filtering with safe escaping. |
| REG-004 | Filter active/inactive/status and empty state. | Correct results. |
| REG-005 | Open add form and inspect every input/select. | Labels, defaults, required states, and controls are correct. |
| REG-006 | Submit empty/invalid/overlong/duplicate email or phone. | Validation and uniqueness enforced server-side. |
| REG-007 | Add valid youth. | User/profile created; password hashed; member ID unique. |
| REG-008 | Edit every mutable field. | Correct row updates; immutable identifiers remain protected. |
| REG-009 | Delete youth with related records and cancel deletion. | Explicit cascade/business rule; no accidental unrelated deletion. |
| REG-010 | Open QR/detail modal and scan/use generated identity. | Correct youth data and close behavior. |
| REG-011 | Test duplicate rapid actions and API failure. | No duplicate mutation; actionable errors. |
| REG-012 | Test mobile table/modal and keyboard operation. | Usable without horizontal action loss. |

---

# 17. KK Masterlist import, verification, and activation

Page: [`admin-masterlist.html`](C:/xampp/htdocs/SKalinga/admin-masterlist.html). APIs: `masterlist_list.php`, `masterlist_import.php`, `masterlist_verify.php`, `masterlist_activate.php`.

| ID | Test flow | Expected |
|---|---|---|
| ML-001 | Load as admin/SK official. | Search, claimed filter, CSV template, upload, preview, import controls render. |
| ML-002 | Load as youth/unauthenticated. | Admin page denied. |
| ML-003 | Click CSV template. | Downloaded columns match required format. |
| ML-004 | Upload valid CSV with one row. | Preview shows one valid row and import button enables. |
| ML-005 | Upload CSV with missing required columns. | Clear missing-column errors. |
| ML-006 | Upload blank CSV/header-only CSV. | Clear empty-file response. |
| ML-007 | Upload invalid date, age, missing fields, malformed row, and extra columns. | Row-level errors identify line and field. |
| ML-008 | Upload duplicate voter IDs within file. | Duplicate rows marked; valid rows handled according to policy. |
| ML-009 | Upload voter IDs already in database. | Existing rows reported; no duplicate insert. |
| ML-010 | Preview then import valid rows. | Only valid rows insert transactionally; counts match. |
| ML-011 | Force insert failure mid-import. | Entire import rolls back. |
| ML-012 | Search by voter ID/name and claimed/unclaimed filter. | Correct pagination/results. |
| ML-013 | Verify valid youth identity. | Correct profile returned without exposing unrelated records. |
| ML-014 | Verify mismatched identity/no-match. | Exact safe no-match message. |
| ML-015 | Activate one verified record. | User/profile/claim transaction succeeds. |
| ML-016 | Activate same record twice/concurrently. | Only one account is created. |
| ML-017 | Upload oversized/non-CSV/fake upload. | Rejected before processing; no resource exhaustion. |
| ML-018 | Test mobile import panel, preview table, and feedback. | All controls remain usable. |

---

# 18. Admin dashboard and analytics

Page: [`admin-dashboard.html`](C:/xampp/htdocs/SKalinga/admin-dashboard.html); charts use Chart.js.

| ID | Test flow | Expected |
|---|---|---|
| DASH-001 | Load as admin/SK official. | Summary cards, charts, recent activity, sidebar, profile, and logout render. |
| DASH-002 | Load as youth/unauthenticated. | Access denied/redirect. |
| DASH-003 | Verify chart data with known database counts. | Counts, labels, and date ranges are correct. |
| DASH-004 | Load with empty database. | Empty charts/cards do not throw JS errors. |
| DASH-005 | Load with API/database failure. | Visible fallback; no misleading zero values. |
| DASH-006 | Navigate every sidebar item. | Correct page/active state and no broken links. |
| DASH-007 | Test chart rendering with CDN unavailable. | Dashboard remains usable or shows explicit dependency error. |
| DASH-008 | Test mobile chart/table/sidebar layout. | No clipped chart or inaccessible navigation. |

---

# 19. Admin settings and shared logout

Page: [`dashboard-settings.html`](C:/xampp/htdocs/SKalinga/dashboard-settings.html).

| ID | Test flow | Expected |
|---|---|---|
| SET-001 | Load page as each admin role. | Correct identity and settings controls display. |
| SET-002 | Inspect every setting, button, toggle, and modal. | Controls have labels and either persist or clearly indicate informational status. |
| SET-003 | Open logout confirmation and cancel. | Session remains active. |
| SET-004 | Confirm logout. | Session clears and login page appears. |
| SET-005 | Use direct `api/logout.php`. | Same secure behavior as JavaScript logout. |
| SET-006 | Test mobile sidebar/settings/modal. | No inaccessible control. |

---

# 20. Shared UI, loader, notifications, and modal behavior

Shared assets include [`assets/js/loader.js`](C:/xampp/htdocs/SKalinga/assets/js/loader.js), `js/auth-guard.js`, common CSS, toasts, alerts, tables, and dialogs.

| ID | Test flow | Expected |
|---|---|---|
| UI-001 | Trigger a slow fetch/form submission. | Loader appears once and blocks duplicate action appropriately. |
| UI-002 | Resolve successful request. | Loader hides and success state appears. |
| UI-003 | Reject request, timeout, abort request, and return malformed JSON. | Loader hides; visible error appears; controls recover. |
| UI-004 | Trigger multiple concurrent requests. | Loader reference counting does not hide too early or remain stuck. |
| UI-005 | Inspect every toast/alert for long text and special characters. | Text is escaped, readable, timed correctly, and not clipped. |
| UI-006 | Keyboard through every modal. | Focus enters modal, stays within it where appropriate, Escape closes, focus restores. |
| UI-007 | Screen-reader test status/error/loading regions. | State changes are announced without duplicate noise. |
| UI-008 | Use browser back/forward across modal and page navigation. | No stale modal or duplicate submission. |
| UI-009 | Disable external CDN resources. | Local functionality remains acceptable or shows explicit fallback. |

---

# 21. API contract and authorization matrix

Run the following against every API route discovered under [`api/`](C:/xampp/htdocs/SKalinga/api).

| ID | Test flow | Expected |
|---|---|---|
| API-001 | Call GET-only route with POST and POST-only route with GET. | HTTP 405; no mutation. |
| API-002 | Call protected route without session. | HTTP 401 JSON. |
| API-003 | Call admin route as youth. | HTTP 403 JSON. |
| API-004 | Call youth-owned route as another youth. | No cross-user data or mutation. |
| API-005 | Send malformed JSON, empty body, duplicate fields, nulls, and unexpected fields. | Validation response; no warnings/stack traces. |
| API-006 | Send SQL metacharacters in every text/ID input. | No injection; safe stored/displayed text. |
| API-007 | Send HTML/script payloads and inspect every rendered response. | Output is escaped; no XSS. |
| API-008 | Verify content type for every JSON endpoint. | JSON content type and consistent response envelope. |
| API-009 | Verify status code for validation, auth, not found, conflict, and server failure. | Codes are semantically correct and consistent. |
| API-010 | Repeat every mutation with same request/body. | Idempotency or explicit duplicate behavior. |
| API-011 | Inspect error responses. | No SQL, filesystem, credential, stack trace, or internal exception details. |
| API-012 | Inspect CORS headers. | Only trusted origins; no unsafe wildcard on auth/state-changing routes. |
| API-013 | Inspect cookies. | `HttpOnly`, `Secure` where HTTPS is used, and appropriate `SameSite`. |

---

# 22. Database, migration, and data integrity testing

| ID | Test flow | Expected |
|---|---|---|
| DB-001 | Create clean database and apply base schema. | All required tables/constraints created. |
| DB-002 | Apply migrations in documented order. | Each migration succeeds once; rerun behavior is safe/documented. |
| DB-003 | Compare runtime SQL with active schema column names/types. | No PostgreSQL/MySQL syntax or schema mismatch. |
| DB-004 | Insert duplicate unique email/member/voter/event IDs. | Constraint/API conflict is handled safely. |
| DB-005 | Delete parent user with profile/password reset/related data. | Foreign-key/cascade behavior matches policy. |
| DB-006 | Fail each transaction at each major insert/update boundary. | No partial user/profile, borrow/resource, or status mutation. |
| DB-007 | Run concurrent activation. | One masterlist claim and one account only. |
| DB-008 | Run concurrent borrowing/returning. | Inventory never becomes negative or exceeds valid quantity. |
| DB-009 | Verify timestamps/time zones around dates and due dates. | Display and status calculations are consistent. |
| DB-010 | Query empty and maximum-size tables. | Pagination/queries remain bounded and responsive. |
| DB-011 | Inspect activity logs for sensitive values. | Passwords, OTPs, tokens, and full secret data are absent. |
| DB-012 | Backup/restore test database and rerun smoke flows. | Data and constraints restore correctly. |

---

# 23. File upload and storage testing

Applies to events, health images, incidents, printing, disclosures, and masterlist CSV.

| ID | Test flow | Expected |
|---|---|---|
| FILE-001 | Upload valid file at one byte below, exactly at, and one byte above documented limit. | Boundary behavior is correct. |
| FILE-002 | Rename executable/script to allowed extension. | MIME/content validation rejects it. |
| FILE-003 | Use path traversal filename `..\..\test`. | Server stores safe generated name only. |
| FILE-004 | Use Unicode, spaces, quotes, and very long original filename. | Safe handling; no broken path or response. |
| FILE-005 | Upload duplicate filename in separate requests. | No overwrite. |
| FILE-006 | Upload then force database failure. | Orphan cleanup occurs. |
| FILE-007 | Request uploaded file URL as another role/user. | Access follows document privacy policy. |
| FILE-008 | Try direct directory listing/path guessing. | Directory listing disabled; no unintended metadata. |
| FILE-009 | Upload many files in one incident. | Count/total-size limits are enforced. |
| FILE-010 | Upload empty, truncated, corrupt, and decompression-bomb-like file. | Rejected safely without resource exhaustion. |

---

# 24. Security and privacy test suite

| ID | Test flow | Expected |
|---|---|---|
| SEC-001 | Request all debug/setup/test routes without authentication. | Removed, disabled, or protected in deployment. |
| SEC-002 | Inspect repository/deployed responses for credentials/API keys. | No active secret is exposed; rotate any previously exposed credential. |
| SEC-003 | Attempt CSRF POST from another origin with authenticated cookie. | Request rejected. |
| SEC-004 | Attempt session fixation before/after login. | Session ID regenerates and attacker’s pre-login ID is invalid. |
| SEC-005 | Attempt open redirect through login redirect parameter. | Redirect remains same-origin allowlist. |
| SEC-006 | Attempt reflected/stored XSS in every free-text field. | Payload is escaped in table, modal, toast, and detail views. |
| SEC-007 | Attempt SQL injection in IDs, search, filters, and text. | No injection or error leakage. |
| SEC-008 | Attempt IDOR with another youth’s member/user/request/incident ID. | Access denied and no data leakage. |
| SEC-009 | Guess or reuse OTP repeatedly. | Cryptographically safe, rate-limited, single-use behavior. |
| SEC-010 | Inspect production error responses/logs. | Sensitive details absent from client; logs are controlled and redacted. |
| SEC-011 | Test privilege separation between `staff` and `superadmin`. | Destructive/setup operations match intended role policy. |
| SEC-012 | Verify privacy of youth phone, address, birthday, incident, and printing data. | Only authorized actor sees each field. |

---

# 25. Responsive and accessibility test suite

## 25.1 Responsive cases

| ID | Test flow | Expected |
|---|---|---|
| UX-001 | Test all landing/auth pages at 320px, 375px, 768px, 1024px, 1440px. | No clipped inputs/buttons or unusable scrolling. |
| UX-002 | Test all youth cards/list pages at mobile width. | Cards stack; text and actions remain visible. |
| UX-003 | Test every admin table at mobile width. | Horizontal scrolling is intentional and action controls remain reachable. |
| UX-004 | Test every modal with long content on mobile. | Modal scrolls internally and close control remains visible. |
| UX-005 | Test orientation change during form entry/upload. | Form values and state are retained. |
| UX-006 | Test browser zoom 200%. | No critical overlap or hidden controls. |

## 25.2 Accessibility cases

| ID | Test flow | Expected |
|---|---|---|
| A11Y-001 | Complete login and one youth/admin workflow keyboard-only. | All controls reachable and operable. |
| A11Y-002 | Inspect labels for every input/select/textarea/file control. | Programmatic labels exist and match purpose. |
| A11Y-003 | Test visible focus indicators. | Focus is always visible. |
| A11Y-004 | Test modal focus/escape/restore behavior. | Dialog interaction is complete and predictable. |
| A11Y-005 | Test screen-reader announcements for loading, success, error, and empty states. | State changes are announced once and meaningfully. |
| A11Y-006 | Inspect images/icons. | Informative images have useful alt text; decorative icons are hidden. |
| A11Y-007 | Test color-only status indicators. | Status also has text or accessible name. |
| A11Y-008 | Test validation errors with keyboard and screen reader. | Error is associated with field and focus moves appropriately. |
| A11Y-009 | Test clickable service cards and inline controls. | Semantically correct links/buttons, not mouse-only divs/spans. |

---

# 26. Cross-module end-to-end test cases

| ID | Flow | Expected |
|---|---|---|
| E2E-001 | Admin imports masterlist → youth verifies → youth activates → youth logs in. | One complete activation path succeeds and role/session are correct. |
| E2E-002 | Admin creates event → youth searches/views event → admin edits → youth sees update. | Public/admin data remains synchronized. |
| E2E-003 | Admin creates health draft → youth cannot see → admin publishes → youth sees → admin archives → youth no longer sees. | Status visibility lifecycle is correct. |
| E2E-004 | Admin creates resource → youth/member is borrowed item → admin returns item → youth history updates. | Inventory and user history remain consistent. |
| E2E-005 | Youth submits incident → admin views photo/details → admin adds note/status → youth sees status. | Ownership and workflow are preserved. |
| E2E-006 | Youth submits print request → admin scans member QR → status changes → youth sees final status. | Printing lifecycle and identity match. |
| E2E-007 | Admin uploads disclosure → youth filters/downloads → admin deletes → youth no longer sees it. | Publication/deletion behavior is correct. |
| E2E-008 | Youth edits profile → opens digital ID → logs out/in → profile and ID remain correct. | Profile persistence and session lifecycle work. |
| E2E-009 | Youth attempts admin URLs/APIs during each youth workflow. | Every protected surface rejects access consistently. |
| E2E-010 | Force network/database failure during each mutation and retry. | No duplicate data, orphan files, stuck loaders, or false success. |
| E2E-011 | Run full youth journey on 375px keyboard-only. | Complete journey is responsive and accessible. |
| E2E-012 | Run full admin journey on 1024px with one API failure per module. | Operator can recover without data corruption. |

---

# 27. Confirmed defect seed list

These should be preloaded into the test execution tracker and retested after fixes.

| Defect | Severity | Location | Retest |
|---|---|---|---|
| DEF-001 | Critical | `api/setup_admin.php` fixed-password/reset behavior | SEC-001, SEC-002 |
| DEF-002 | Critical | OTP routes hardcoded credential/TLS disabled | AUTH-055, SEC-002 |
| DEF-003 | High | `js/profile.js` demo-only save behavior | PROF-009 |
| DEF-004 | High | Missing CSRF controls | API-012, SEC-003 |
| DEF-005 | High | Legacy session authorization checks | API-002–API-004, SEC-011 |
| DEF-006 | High | Weak upload validation/public storage | FILE-001–FILE-008 |
| DEF-007 | High | Weak OTP generation/storage/attempt enforcement | AUTH-047–AUTH-053 |
| DEF-008 | High | Borrow inventory race/member identity trust | RES-010–RES-016, DB-008 |
| DEF-009 | Medium | Internal exception/error leakage | ENV-007, API-011, SEC-010 |
| DEF-010 | Medium | MySQL/PostgreSQL/deployment schema drift | DB-001–DB-003 |
| DEF-011 | Medium | Events localStorage fallback can mask server error | EVT-Y-010 |
| DEF-012 | Low | Modal/card accessibility gaps | UI-006, A11Y-001–A11Y-009 |

---

# 28. Execution summary template

| Area | Total cases | Passed | Failed | Blocked | Not run | Critical open defects |
|---|---:|---:|---:|---:|---:|---:|
| Environment/smoke | 10 | 4 | 1 | 0 | 5 | 0 |
| Authentication/recovery | 56 | 12 | 1 | 0 | 43 | 0 |
| Youth portal/navigation | 12 | 0 | 2 | 0 | 10 | 0 |
| Events | 36 | 1 | 2 | 0 | 33 | 0 |
| Health | 19 | 6 | 1 | 0 | 12 | 0 |
| Borrowing/resources | 31 | 5 | 5 | 0 | 21 | 0 |
| Printing | 29 | 0 | 0 | 0 | 29 | 0 |
| Incidents | 25 | 0 | 0 | 0 | 25 | 0 |
| Disclosures | 17 | 0 | 0 | 0 | 17 | 0 |
| Profile | 13 | 0 | 0 | 0 | 13 | 0 |
| Registry/masterlist | 30 | 1 | 1 | 0 | 28 | 0 |
| Dashboard/settings/shared UI | 23 | 0 | 2 | 0 | 21 | 0 |
| API/database/files | 35 | 2 | 4 | 0 | 29 | 0 |
| Security/UX/E2E | 39 | 0 | 2 | 0 | 37 | 0 |
| **Total executable cases** | **375** | **31** | **21** | **0** | **323** | **2 Critical defects listed; retest pending** |

The document contains **387 unique IDs**: **375 executable test cases** plus **12 confirmed-defect retest references** in the defect seed list. The 12 defect rows are references to existing tests, not additional executable cases. The category totals above now match the IDs in the test tables.

## Current execution record — 2026-10-09

Environment: local XAMPP, Apache, PHP 8.2.12, MariaDB, Chromium. This is a partial run, not a full release sign-off. Testing began read-only; the user later authorized CRUD using synthetic data and cleanup. Only the listed cases have a Pass or Fail status; all other executable cases remain **Not Run**. Youth-only mutations, password reset, file variants other than the noted PNG subcase, database-outage, rollback/fault-injection, and other unlisted cases were not run.

Successful and failed login probes were run against the documented local seed accounts. Normal authentication records (`login_attempts`, `last_login`, and `activity_logs`) may therefore have changed. After the user authorized synthetic CRUD, test-only resource rows, youth accounts, and a health announcement were created and cleaned up. A synthetic disclosure PNG was uploaded and removed through its delete API; a synthetic youth registry record was deleted through its API. The health delete endpoint archived rather than deleted its synthetic row, so that exact row was removed directly from the local test database after verifying its unique QA title and ID. All synthetic resource, borrow, health, youth, disclosure, and file fixtures were verified absent after cleanup. Health audit rows and authentication logs were retained. No actual youth records were intentionally edited or deleted.

| ID | Status | Evidence |
|---|---|---|
| ENV-002 | Pass | Login page rendered in Chromium; no blocking page error observed. |
| ENV-003 | Pass | Unauthenticated `api/me.php` returned HTTP 401 with JSON and no stack trace. |
| ENV-004 | Pass | Unauthenticated `api/check_admin_session.php` returned HTTP 401 JSON. |
| ENV-009 | Pass | `php -l` passed for all 81 PHP files. |
| AUTH-001 | Fail | Login page has no Forgot Password control/link. |
| AUTH-002 | Pass | Empty login form fails native required-field validation. |
| AUTH-003 | Pass | Identifier-only form fails password-required validation. |
| AUTH-004 | Pass | Password-only form fails identifier-required validation. |
| AUTH-005 | Pass | Password visibility toggled to text and back to password. |
| AUTH-006 | Pass | Tab order reached password, visibility toggle, submit; Enter submitted valid admin credentials and reached the admin dashboard. |
| AUTH-007 | Pass | Documented youth email/password authenticated as role `youth`; browser reached youth portal and `api/me.php` confirmed role. |
| AUTH-010 | Pass | Documented admin username/password authenticated as role `admin`; browser reached admin dashboard. |
| AUTH-012 | Pass | Wrong password returned HTTP 401 and generic `Invalid credentials.` message. |
| AUTH-013 | Pass | Unknown identifier returned the same HTTP 401 and generic message as wrong password. |
| AUTH-019 | Pass | Tested login and `/me` response bodies contained no password, password hash, or OTP. |
| AUTH-026 | Pass | Authenticated youth `api/me.php` response reported `authenticated: true` and role `youth`. |
| AUTH-027 | Pass | Authenticated admin `api/me.php` response reported role `admin`; admin session endpoint reported `superadmin`. |
| EVT-A-013 | Fail | Valid synthetic event submission failed: ID generation attempted existing `EVT-2026-0002` and returned a duplicate-key SQL error. No event was inserted. |
| EVT-A-018 | Pass | Delete request for a nonexistent synthetic event returned a safe JSON “Event not found” error; no mutation occurred. |
| HLTH-008 | Pass | Created a future-dated synthetic Draft health announcement; API returned success and admin list returned its values. |
| HLTH-009 | Pass | Empty required fields and invalid category were rejected with HTTP 422; no record created by those requests. |
| HLTH-010 | Pass | Past date, negative slots, and end time before start were each rejected with HTTP 422. |
| HLTH-013 | Pass | Synthetic Draft was present in admin listing and absent from public listing. |
| HLTH-014 | Pass | Updated synthetic announcement to Published; public list then included it. |
| HLTH-016 | Pass | Synthetic announcement fields updated and were returned with the edited values. |
| HLTH-017 | Fail | `health_announcements/delete.php` returned “archived”; repeating the delete also returned success and left the row Archived. The test-only row was subsequently removed by an exact-ID/title cleanup query. |
| RES-003 | Pass | Synthetic resource creation returned a generated item ID with quantity and available both matching the submitted integer quantity. |
| RES-004 | Fail | Decimal quantity `1.5` was accepted and truncated to `1` by both resource creation and borrow APIs, instead of being rejected. Test resources were cleaned up. |
| RES-005 | Fail | Two resources with the same synthetic name were both created successfully with different generated item IDs; duplicate names are not rejected. Both rows were cleaned up. |
| RES-010 | Fail | Borrow API accepted a clearly nonexistent synthetic member ID and created a borrow record. The test record was deleted and inventory restored. |
| RES-013 | Fail | Zero, negative, and above-available borrow quantities were rejected; however decimal `1.5` was accepted as quantity `1`. |
| RES-014 | Pass | Synthetic borrow reduced available inventory by the borrowed amount; exact-available borrow reached zero; return restored inventory; cleanup left inventory at its original value. |
| RES-016 | Pass | Two concurrent synthetic requests against one available unit produced one successful borrow and one rejection; available quantity did not go below zero. |
| RES-019 | Pass | Repeated return of a synthetic already-returned borrow was rejected with HTTP 400; inventory stayed at its original count. |
| RES-020 | Pass | Deleting an active synthetic borrow restored its quantity; deleting a returned record did not increment inventory again. |
| REG-006 | Fail | Invalid email and empty required fields were rejected, and duplicate email returned 409. Two synthetic accounts with the same invalid phone value were both accepted, so phone uniqueness/validation is not enforced. Both accounts were deleted. |
| REG-007 | Pass | A synthetic youth account/profile was created with a generated member ID and deleted after verification. Source uses `password_hash()`; the API response did not include a password/hash. |
| ENV-010 | Fail | At 320/375px, horizontal document overflow was measured on youth events, borrowing, printing, and report pages; profile also overflowed at 320px. |
| PORTAL-001 | Fail | Portal displayed the youth identity, but Chromium reported `ReferenceError: fetchHealthAnnouncements is not defined`. |
| PORTAL-008 | Fail | Service cards are non-focusable `div` elements (`tabIndex=-1`) without link/button semantics. |
| EVT-Y-001 | Fail | Youth `get_user_profile.php` returned HTTP 403; events page then raised a `TypeError` while updating a missing profile element. |
| RES-001 | Fail | Admin resources/borrowing data requests failed JSON parsing because PHP emitted a duplicate `session_start()` notice. |
| SET-001 | Fail | Admin Settings page displayed “Under Maintenance” instead of settings controls. |
| SET-002 | Fail | No settings controls were available to inspect; page displayed “Under Maintenance”. |
| API-001 | Fail | POST to GET data route `api/get_events.php` returned HTTP 200/data instead of 405; tested POST-only routes returned 405 to GET. |
| API-002 | Pass | Tested protected APIs without a session returned 401 JSON. |
| API-003 | Pass | Youth-session requests to checked admin APIs returned 403 JSON. |
| API-008 | Fail | `api/get_resources.php` and `api/get_borrow_records.php` returned HTTP 200 with HTML notice text, not parseable JSON. |
| API-011 | Fail | The API warning response exposed the local server filesystem path and PHP source filename. |
| API-013 | Fail | Observed session cookie had no `HttpOnly` or `SameSite` attribute. HTTPS-only `Secure` behavior was not assessed on localhost. |
| UX-002 | Fail | Mobile youth pages showed horizontal overflow at 320/375px, including events and borrowing. |
| A11Y-009 | Fail | Youth service cards are clickable-looking `div`s without keyboard focusability or link/button semantics. |

Additional unscored subcase evidence: a synthetic PNG disclosure upload appeared in the admin list; the youth-only public-list API correctly returned 403 to the admin session; deleting the disclosure removed both the row and stored file (subsequent file request returned 404). Other supported file types and the delete-confirmation cancel path were not exercised, so the full DISC-A-003/DISC-A-008 cases remain Not Run.

The youth login route returned HTTP 429 during CRUD testing because the local IP had reached its authentication-attempt limit. Youth-only incident and printing mutations were not attempted or bypassed; those cases remain Not Run. The 429 was not treated as a valid AUTH-016 result because the attempts included successful logins and were not a controlled series of ten invalid attempts.

The remaining **323 executable cases are Not Run**. The **12 DEF retest references** were not separately rerun. Two Critical defects are listed in the defect seed list and remain unverified. Do not mark the suite Pass: this partial run found failures, and most workflows still need execution in a dedicated test database with synthetic fixtures.

## Release decision

- **Pass:** No open Critical/High defects; all required module and E2E cases pass.
- **Conditional:** Only Medium/Low defects remain with documented workarounds and presentation approval.
- **Fail:** Any Critical/High security, authorization, data-integrity, authentication, upload, or core workflow defect remains open.
