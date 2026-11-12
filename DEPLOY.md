# Deploy SKalinga to Render with Supabase

Render runs the PHP application in Apache, and Supabase provides PostgreSQL. Local XAMPP remains supported at `http://localhost/SKalinga/`. Render serves the app at `/SKalinga/` and redirects its root URL there. Cloudflare Pages and Workers are not used.

## Turnstile configuration

Set these environment variables in Render and in your local `.env` for XAMPP:

- `TURNSTILE_SITE_KEY`
- `TURNSTILE_SECRET_KEY`
- `TURNSTILE_ENABLED` (`true` in production, `false` only for local development)
- `TURNSTILE_FAIL_OPEN` (`false` by default; keep `false` in production)

Cloudflare's testing keys are documented for QA only. Use them in local testing or staging, never in production:

- Always-pass site key: `1x00000000000000000000AA`
- Always-block site key: `2x00000000000000000000BB`
- Matching dummy secret keys:
  - Always-pass: `1x0000000000000000000000000000000AA`
  - Always-block: `2x0000000000000000000000000000000BB`

The dummy secret keys must stay in local `.env` files and never be committed to Git. `api/public-config.php` only exposes the public site key and enabled flag; the secret never leaves the server.

## 1. Supabase

Apply all PostgreSQL migrations, including `database/postgres/migrations/011_login_email_otp.sql` and `database/postgres/migrations/012_email_quota_activation_sms.sql`, before enabling email OTP or youth activation. The schema and migrations should otherwise already be applied:

1. Use the Supabase **Session pooler** connection, port `5432`, database `postgres`.
2. Do not use the transaction pooler on port `6543`.
3. Keep the `anon` key out of this PHP application. The server-side `service_role` key is required only for private Storage operations and must never be exposed to browsers or committed to Git.

## 2. Create the Render demo service

1. In Render, choose **New → Web Service**.
2. Connect GitHub and select the private repository `arbys27/skalinga1`, branch `main`.
3. Render detects the root `Dockerfile`. Choose the **Free** instance type. The Docker image runs Apache and installs PHP PostgreSQL extensions.
4. Add these variables in the service's **Environment** settings:

   | Variable | Value |
   |---|---|
   | `DB_HOST` | Supabase Session Pooler host |
   | `DB_USER` | Session Pooler username shown by Supabase, e.g. `postgres.<PROJECT_REF>` |
   | `DB_PASSWORD` | Supabase database password |
   | `SITE_KEY` | Private random 64-character hexadecimal string |
   | `APP_ENV` | `production` |
   | `MAIL_DRIVER` | `emailjs` |
   | `LOGIN_OTP_MODE` | `email` |
   | `EMAILJS_SERVICE_ID` | EmailJS service ID |
   | `EMAILJS_TEMPLATE_ID` | Login OTP template ID |
   | `EMAILJS_RESET_TEMPLATE_ID` | Password reset OTP template ID |
   | `EMAILJS_PUBLIC_KEY` | EmailJS public key |
   | `EMAILJS_PRIVATE_KEY` | EmailJS private key; enable server-side and non-browser access in Account → Security |
   | `EMAILJS_MONTHLY_LIMIT` | Optional monthly cap; defaults to `200` |
   | `SMS_DRIVER` | `unisms` |
   | `UNISMS_API_KEY` | UniSMS bearer-token API key |
   | `UNISMS_SENDER_ID` | Sender ID approved for your UniSMS account |
   | `SUPABASE_URL` | Supabase project URL, e.g. `https://<PROJECT_REF>.supabase.co` |
   | `SUPABASE_SERVICE_ROLE_KEY` | Supabase service-role key; keep server-side and never expose it to browsers |
   | `SUPABASE_STORAGE_BUCKET` | Private Storage bucket name; use `printing` |

   Never put actual secret values in GitHub or this chat. UniSMS is used only for the youth activation phone check. EmailJS private keys must remain server-side.

   Generate `SITE_KEY` in PowerShell:

   ```powershell
   $bytes = New-Object byte[] 32
   $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
   $rng.GetBytes($bytes)
   [BitConverter]::ToString($bytes).Replace('-', '').ToLower()
   ```

5. After creating the service, generate its `onrender.com` domain in **Settings**. In EmailJS, confirm the sending service is connected and that server-side/non-browser requests are enabled. See [docs/EMAILJS_SETUP.md](./docs/EMAILJS_SETUP.md) for the exact template fields and content.
6. In **Settings**, set the health check path to `/healthz`. Render provides `PORT`; Apache listens on it. Wait for the first deployment to finish successfully.
7. Test `https://<your-render-domain>/SKalinga/`.

## Free-service limitations

Render Free is suitable for a temporary demo, not reliable production use:

- The service sleeps after 15 minutes without traffic; waking can take about a minute.
- Its filesystem is ephemeral. Uploaded files, PHP session files, and other local changes are lost on restart, spin-down, or redeploy.
- Free services cannot attach persistent disks. Do not use real or sensitive uploads for this demo.
- Keep the service to one instance; sessions are stored as local PHP files.

## 3. Check Supabase and create the administrator

The Render free plan may not provide an interactive service shell. Run these commands from your local project folder instead, using XAMPP PHP with `pdo_pgsql` and `pgsql` enabled:

1. Copy `.env.example` to `.env`. Fill in the same Supabase Session Pooler host, username, and database password as the Render variables. Use a private local `SITE_KEY`; it can differ from Render's. Fill in EmailJS settings for login OTP and UniSMS settings for youth activation testing.
2. Open PowerShell in the project folder and run:

   ```powershell
   C:\xampp\php\php.exe scripts\check-env.php
   C:\xampp\php\php.exe scripts\create_admin.php
   ```

   The checker verifies the live connection, expected tables, and RLS. The admin script prompts for an email and strong password and only runs in CLI mode. `.env` is ignored by Git; do not commit or share it.
3. Sign in at the Render `/SKalinga/` URL and confirm account/database changes in Supabase Table Editor.

## Local XAMPP

XAMPP continues to serve `http://localhost/SKalinga/`. Copy `.env.example` to `.env`, configure a separate `SITE_KEY` and the Supabase values, enable `pdo_pgsql` and `pgsql`, then restart Apache. The legacy ignored `includes/config.local.php` is also supported.

## Import the KK masterlist

After signing in as an admin, open **Admin Masterlist** (`admin-masterlist.html`). Download the CSV Template, fill in authorized records using the template headers and `YYYY-MM-DD` dates, and use **Import Masterlist** to upload it. Avoid uploading real personal records while using the Render Free demo.

## Troubleshooting

| Symptom | Checks and action |
|---|---|
| **Deploy fails or 502** | Check Render deploy logs. Confirm it detected the root Dockerfile and uses injected `PORT`; don't override the command to run PHP's built-in server. |
| **First visit is slow** | Free service may be waking from sleep; wait about a minute and reload. |
| **429 Too Many Requests** | Login, masterlist verification, and OTP requests are rate limited. Wait before retrying. |
| **Login session disappears** | Free-service restarts/spin-downs clear PHP file sessions. Sign in again after a wake or redeploy. |
| **Connection refused / DB connection failed** | Verify Supabase Session Pooler hostname, port `5432`, database `postgres`, exact username, password, and outbound connectivity. Run `php scripts/check-env.php` locally. |
| **`could not find driver`** | Check Render build logs; the Dockerfile installs `pdo_pgsql` and `pgsql`. |
| **Uploads disappear** | Expected on Render Free because storage is ephemeral. A paid service with a persistent disk or external object storage is needed to keep files. |
| **Private file URL returns 403** | Expected for direct incident/printing file paths. The authenticated `api/get_upload.php` endpoint serves private files. |

PHP errors are available in Render service logs. Apache blocks sensitive paths (`database/`, `includes/`, `logs/`, and `scripts/`) and SQL/Markdown/log/backup files.
