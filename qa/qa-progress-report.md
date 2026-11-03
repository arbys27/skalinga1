# SKalinga QA Progress Report

**Audit date:** 2026-10-23  
**Scope:** Current working tree, including uncommitted and untracked project files  
**Method:** Source review, local read-only HTTP smoke checks, and a limited browser viewport check. No application code or database rows were changed.

## Overall summary

| Status | Count | Percentage |
|---|---:|---:|
| Pass | 18 | 32.7% |
| Fail | 26 | 47.3% |
| Blocked | 0 | 0.0% |
| Not Tested | 11 | 20.0% |
| **Total** | **55** | **100.0%** |

**Execution progress:** 44 of 55 test cases have a Pass or Fail result (**80.0% executed**). The pass rate among executed cases is **40.9%** (18 of 44). No module is Ready because no module has all test cases passing. Cases marked Pass are supported by code inspection or an explicitly stated local smoke check; they do not imply full end-to-end acceptance.

### Validation performed and limits

- Local read-only HTTP checks returned HTTP 200 for `login.html`, `index.php`, and `api/get_events.php`; `api/me.php` returned HTTP 401 without authentication. Several protected list endpoints also returned HTTP 401 while logged out.
- The login page showed horizontal overflow at a 320-pixel viewport: document width was 320px while the usable client width was 305px. This is recorded as AUTH-009.
- A narrow browser check of the youth portal at 375px, 768px, and 1280px did not show horizontal overflow; this does not cover the other pages or their interactions.
- PHP CLI is unavailable and `composer.json` defines no test scripts. No automated suite or PHP syntax check could be run. Browser, database mutation, upload, email/SMS delivery, and multi-role end-to-end flows were not comprehensively tested.
- Existing `QA_TEST_CASES.md` includes historical execution notes; those results were not treated as fresh verification for this audit.

## Per-module progress

`% Complete` is the share of cases with a Pass, Fail, or Blocked result; Not Tested cases do not count as executed. `Failing` means at least one Fail exists; `In progress` means none failed but some remain untested; `Ready` requires all cases to pass.

| Module | Total | Pass | Fail | Blocked | Not Tested | % Complete | Overall status |
|---|---:|---:|---:|---:|---:|---:|---|
| Authentication & Session | 10 | 5 | 3 | 0 | 2 | 80.0% | Failing |
| Youth Account Activation | 4 | 1 | 2 | 0 | 1 | 75.0% | Failing |
| Youth Registry & Digital ID | 4 | 1 | 0 | 0 | 3 | 25.0% | In progress |
| Admin Dashboard | 2 | 1 | 1 | 0 | 0 | 100.0% | Failing |
| Events | 6 | 2 | 4 | 0 | 0 | 100.0% | Failing |
| Resources & Lending | 4 | 1 | 2 | 0 | 1 | 75.0% | Failing |
| Printing Services | 4 | 0 | 3 | 0 | 1 | 75.0% | Failing |
| Incident Reporting | 4 | 1 | 3 | 0 | 0 | 100.0% | Failing |
| Health Announcements & Services | 4 | 2 | 1 | 0 | 1 | 75.0% | Failing |
| Public Disclosure | 4 | 2 | 2 | 0 | 0 | 100.0% | Failing |
| Youth Profile | 2 | 0 | 1 | 0 | 1 | 50.0% | Failing |
| Settings | 1 | 0 | 1 | 0 | 0 | 100.0% | Failing |
| Shared UI & Navigation | 4 | 2 | 1 | 0 | 1 | 75.0% | Failing |
| Deployment & Operations | 2 | 0 | 2 | 0 | 0 | 100.0% | Failing |
| **Total** | **55** | **18** | **26** | **0** | **11** | **80.0%** | **Failing** |

## Critical and High failures

No Critical failures were recorded. The following High failures are sorted by severity; test IDs map to detailed evidence and fixes in `test-case-tracker.csv`.

| Test ID | Module | Finding |
|---|---|---|
| AUTH-007 | Authentication & Session | SMS OTP verification accepts a syntactically valid code without checking it against a challenge. |
| ACT-001 | Youth Account Activation | Activation can be called without server-side proof that verification completed. |
| ACT-002 | Youth Account Activation | Activation attempts are not rate-limited. |
| EVT-005 | Events | User-controlled event text is rendered through unescaped `innerHTML` in admin views. |
| RES-002 | Resources & Lending | Fractional quantities are truncated and borrower identity is accepted from request fields without server-side verification. |
| RES-003 | Resources & Lending | The standalone resources setup schema omits a quantity column required by borrow/return/delete actions. |
| INC-002 | Incident Reporting | User-submitted incident descriptions are rendered through unescaped `innerHTML` in admin views. |
| INC-004 | Incident Reporting | Incident schema setup is attempted from a youth page through admin-only endpoints and SQL that is not compatible with the configured MySQL connection. |
| OPS-001 | Deployment & Operations | Deployment definitions install PostgreSQL extensions while the application configures a MySQL DSN. |
| OPS-002 | Deployment & Operations | Setup endpoints include a table drop and recreation of an admin account using a hard-coded password. |

## Recent changes

The latest visible Git commits are dated 2026-02-26:

- `f9384fc` changed password-reset request handling — affects AUTH-006 and the reset flow.
- `1d5ed04` changed the index redirect — affects entry routing and OPS-001 smoke coverage.
- `5200616` changed password reset behavior — affects reset verification and AUTH-006.
- `d422301` updated OTP handling and added SMS test tooling — affects AUTH-007 and OTP flows.
- `38a7b7f` changed event, borrowing, incident, and report behavior — affects Events, Resources & Lending, and Incident Reporting test cases.

The working tree contains extensive uncommitted modifications and untracked files across pages, APIs, scripts, and styles. Git history cannot reliably date or attribute those working-copy changes; this report evaluates the current files and does not infer their authorship or recency.

## Features still missing or incomplete

- The admin Health Services page and Settings page show under-maintenance content rather than working management features.
- The youth profile form's submit handler is demo-only and is not wired to the existing profile update API.
- SMS verification does not validate the OTP; reset OTP storage is plaintext, and the activation endpoint lacks a bound verification token and rate limit.
- Public disclosure administration advertises draft support but has no draft/publish controls; the read path is youth-authenticated despite the public-board wording.
- Printing has incomplete status controls and an unwired manual-request form; deleting a request leaves its uploaded file.
- Event IDs derived from record counts can collide; invalid image replacement can delete the current image. Event registration appears external-link based, with no in-app registration/update flow identified; confirm whether that is intentional.
- Resource fresh-install schema does not match the API's required columns. Borrow input does not verify borrower identity and silently coerces non-integer quantities.
- Incident setup is coupled to page load and uses incompatible SQL for the configured database. Incident uploads lack content and aggregate-size/count validation.
- Dashboard query failures may be displayed as valid zero metrics. Some admin error paths use native alert dialogs rather than the shared toast component.
- Login has horizontal overflow at 320px. Responsive, sidebar-highlight, logout confirmation, and empty/loading behavior still need a complete browser pass across modules.

## Recommended fix order

1. Remove or disable destructive setup endpoints in production and reconcile the MySQL DSN with deployment dependencies before release.
2. Fix OTP verification and activation proof/rate limiting so verification and account claims cannot be bypassed.
3. Safely render user-controlled event and incident text; then correct borrower identity/quantity validation and the fresh-install resource schema.
4. Move incident setup into a compatible migration, validate file contents and aggregate limits, and preserve files on failed event-image replacement.
5. Finish the incomplete profile, health-services, settings, disclosure-draft, and printing workflows; then run full role, responsive, CSRF, and database-failure browser tests.
