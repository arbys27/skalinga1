# SKalinga San Antonio

SKalinga is a youth e-governance platform for Barangay San Antonio, Binan, Laguna. It provides youth services, events, resource borrowing, incident reporting, printing requests, health services, and an admin workspace.

## Stack

- Frontend: plain HTML, CSS, and vanilla JavaScript
- Backend: PHP 8 with PDO prepared statements
- Database: Supabase PostgreSQL through the Session Pooler
- Database connection: PHP PDO PostgreSQL on port `5432`
- Theme: green `#228B57`, coral pink `#FF6B9D`, and sky blue `#5DADE2`

## Local Setup

1. Start Apache in XAMPP. MySQL is not used by the PHP application.
2. Enable `pdo_pgsql` and `pgsql` in XAMPP's PHP configuration, then restart Apache.
3. Apply [database/postgres/schema.sql](database/postgres/schema.sql) and its migrations in order to the Supabase project.
4. Copy `.env.example` to `.env` and enter the Session Pooler credentials, a private 64-character `SITE_KEY`, and optional provider settings. `.env` is ignored by Git; never commit it. The ignored `includes/config.local.php` remains supported for local setup.
5. Open `http://localhost/SKalinga/`. Create the first admin with `php scripts/create_admin.php`.

Database setup and migration details are in [database/postgres/README.md](database/postgres/README.md).

For a Render demo deployment with Supabase, follow [DEPLOY.md](DEPLOY.md). It covers the Supabase Session Pooler, Render environment variables, free-service limitations, the environment check, and admin creation.

## Authentication

All users use [login.html](login.html). The identifier accepts:

- Youth Voter ID
- Email address, when provided
- Philippine mobile number
- Admin username or email

The server determines the role from the database. The browser never submits or selects a role.

| Database account | Application role | Redirect |
| --- | --- | --- |
| `users` | `youth` | `youth-portal.html` |
| `admins` with role `staff` | `sk_official` | `admin-dashboard.html` |
| `admins` with role `superadmin` | `admin` | `admin-dashboard.html` |

Legacy `index.html`, `login-youth.html`, and `login-admin.html` redirect to `login.html`.

### Youth activation

Public self-registration is disabled. A youth must first appear in the official KK Masterlist:

1. An authorized official opens [admin-masterlist.html](admin-masterlist.html).
2. The official imports a CSV and previews validation errors and duplicate Voter IDs.
3. The youth opens [youth-register.html](youth-register.html).
4. Step 1 verifies Voter ID, full name, and birthday.
5. Step 2 displays the masterlist profile and collects the required mobile number, optional email, and password.
6. The account and masterlist claim are created in one database transaction.

One masterlist row can only activate one account. The exact no-match response is:

```text
No matching youth record was found. Please contact the Sangguniang Kabataan Office.
```

The masterlist schema uses the active repository table names `users` and `youth_profiles`; the task-level names `youth_users` and `admin_users` are not present in this codebase.

## Important API Routes

### Public disclosure board

- `GET api/public_disclosures/list.php` - published documents for authenticated youth users
- `GET api/public_disclosures/list_admin.php` - all documents for authenticated administrators
- `POST api/public_disclosures/create.php` - upload and publish a PDF, Word, Excel, JPG, or PNG document (10 MB maximum)
- `POST api/public_disclosures/delete.php` - remove a document and its stored file

The youth-facing page is read-only at `public-disclosure.html`. Administrators manage the board at `dashboard-public_disclosure.html`.

### Authentication

- `POST api/login.php` - unified role-based login
- `GET api/me.php` - current session user and role, or `401`
- `GET api/logout.php` - clears the session and redirects to `login.html`
- `POST api/masterlist_verify.php` - verifies a KK Masterlist record
- `POST api/masterlist_activate.php` - activates a verified youth account
- `POST api/request_password_reset.php` - sends a youth account password-reset OTP by email
- `POST api/reset_password.php` - verifies the OTP and updates the youth account password
- `GET api/get_user_borrow_records.php` - returns borrowing history for the authenticated youth account

Forgot-password OTP email settings (`EMAILJS_SERVICE_ID`, `EMAILJS_TEMPLATE_ID`, and `EMAILJS_PUBLIC_KEY`) are loaded from `.env` locally or deployment environment variables. In the EmailJS template, set **To Email** to `{{to_email}}` and include `{{otp}}` in the email body. The API also supplies `email`, `user_email`, and `recipient_email` as recipient aliases for templates using those variable names. Add `localhost` to the allowed domains in the EmailJS account security settings for local testing; set `EMAILJS_ORIGIN` to the deployed origin outside local development. OTPs expire after 15 minutes and are limited to five verification attempts. Admin and SK official accounts are not included in this youth-account recovery flow.

### Admin masterlist

- `POST api/masterlist_import.php` - authenticated CSV preview/import
- `GET api/masterlist_list.php` - authenticated search and claimed-status listing

Admin and SK official APIs enforce the session role on the server through `api/auth.php`. The shared [js/auth-guard.js](js/auth-guard.js) provides the matching client-side redirect for page navigation; it is not the security boundary.

## CSV Format

Use the **CSV Template** button on the admin masterlist page. Required columns:

```text
voter_id,first_name,last_name,middle_name,birthday,age,sex,address,purok_or_barangay
```

Birthday values must use `YYYY-MM-DD`. The importer reports missing fields, invalid dates/ages, duplicate Voter IDs in the file, and Voter IDs already in the database.

## Admin Setup
Create the first administrator from the command line with `php scripts/create_admin.php`. No sample accounts or passwords are seeded.

## Security Notes

- Passwords use `password_hash()` and are verified with `password_verify()`.
- Login attempts are rate-limited by IP using `login_attempts`.
- Successful authentication is recorded in `activity_logs`.
- Sessions regenerate on successful login.
- `api/me.php` is the source of truth for the current role.
- Admin and SK official APIs reject youth sessions with `403 Forbidden`.
- SQL uses PDO prepared statements.
- User-generated values are rendered with DOM text APIs in the new auth/masterlist UI.

## Testing Checklist

1. Apply the PostgreSQL schema and migrations, configure `includes/config.local.php`, and create an admin with the CLI tool.
2. Sign in with that admin and verify the admin dashboard redirect.
3. Import a valid KK Masterlist CSV and confirm it appears as available.
4. Activate a listed record and confirm the youth account reaches the youth portal.
5. Confirm the same masterlist row cannot be activated twice.
6. With a youth session, request an admin API and confirm `403 Forbidden`.
7. Verify `api/me.php` returns `authenticated: true` and the expected role.

## Troubleshooting

- Database errors: verify `includes/config.local.php`, the PostgreSQL PHP extensions, and the Session Pooler connection.
- Missing tables: apply [database/postgres/schema.sql](database/postgres/schema.sql) and all PostgreSQL migrations in order.
- Admin page redirects: confirm the account role is `staff` or `superadmin` and that the browser session is current.
- PHP errors: check the Render service logs for deployed errors, or the XAMPP PHP/Apache error logs for local errors.
