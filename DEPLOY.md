# Deploy SKalinga to Railway with Supabase

Railway runs the PHP application in Apache, and Supabase provides PostgreSQL. The existing XAMPP setup and `http://localhost/SKalinga/` URL continue to work. Railway also serves the app at `/SKalinga/`; its root URL redirects there. Cloudflare Pages and Workers are not used.

## 1. Prepare Supabase

1. Create a Supabase project (Singapore is a suitable region) and set a strong database password.
2. In the Supabase SQL Editor, run `database/postgres/schema.sql`, then every SQL file in `database/postgres/migrations/` in numeric order.
3. Open **Connect**, choose the **Session pooler**, and note its host and user. Use port **5432** and database `postgres`. Do not use the transaction pooler on port **6543**.
4. Keep the Supabase `anon` and `service_role` API keys out of this application. PHP connects directly to PostgreSQL using its database credentials.

## 2. Deploy the application on Railway

1. Push this repository to a Git provider connected to your Railway account, or deploy it with the Railway CLI from the repository root. Railway builds the root `Dockerfile`, which runs Apache with the PHP PostgreSQL extensions enabled.
2. In the Railway service, add these variables under **Variables**:

   | Variable | Value |
   |---|---|
   | `DB_HOST` | Supabase Session Pooler host |
   | `DB_USER` | `postgres.<PROJECT_REF>` (the Session Pooler username shown by Supabase) |
   | `DB_PASSWORD` | Supabase database password |
   | `SITE_KEY` | Private random 64-character hexadecimal string |
   | `APP_ENV` | `production` |
   | `EMAILJS_ORIGIN` | `https://<your-Railway-domain>` after the domain is generated |
   | `EMAILJS_SERVICE_ID` | EmailJS service ID |
   | `EMAILJS_TEMPLATE_ID` | EmailJS password-reset template ID |
   | `EMAILJS_PUBLIC_KEY` | EmailJS public key |

   If using SMS OTP, also set `SKALINGA_SMS_API_URL` and `SKALINGA_SMS_API_KEY` to the provider values. Never commit any of these values or put Supabase API keys in frontend code.

   Generate `SITE_KEY` in PowerShell:

   ```powershell
   $bytes = New-Object byte[] 32
   $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
   $rng.GetBytes($bytes)
   [BitConverter]::ToString($bytes).Replace('-', '').ToLower()
   ```

3. Add a Railway **Volume** mounted at `/var/www/html/uploads`. This persists printing, incident, health announcement, and disclosure files. Add a second volume mounted at `/var/www/html/assets/images/events` for event images. On first startup, tracked sample event images are copied into an empty event-image volume. Keep the service to one replica unless session storage is externalized.
4. In the service settings, set the health check path to `/healthz`. Railway supplies `PORT`; Apache listens on that port. Generate a public domain in **Networking** and wait for the deployment and health check to succeed.
5. From the service's **Shell** (or Railway CLI shell attached to the running service), run:

   ```sh
   php scripts/check-env.php
   ```

   It validates the PHP extensions, Supabase Session Pooler connection, expected tables, and RLS. If deployment has not finished, wait for the service to become healthy before running the command.
6. Create the first admin account from that same service shell:

   ```sh
   php scripts/create_admin.php
   ```

   Enter the email and a strong password when prompted. The script refuses HTTP execution.
7. Open `https://<your-Railway-domain>/SKalinga/`. Test admin/youth login, registration and OTP, events, printing, incidents, health announcements, disclosures, and verify database changes in the Supabase Table Editor.

`EMAILJS_ORIGIN` must exactly match the Railway HTTPS origin allowed in EmailJS. If you change the public domain, update the variable and redeploy. Railway terminates HTTPS before forwarding to Apache; the app trusts forwarded scheme and visitor-IP headers only when Railway environment variables identify the deployment.

## Local XAMPP remains available

For local development, copy `.env.example` to `.env`, fill in the Supabase Session Pooler values, a separate `SITE_KEY`, and the EmailJS settings. `.env` is ignored by Git and blocked by Apache; never commit or share it. The legacy `includes/config.local.php` remains supported. Enable `pdo_pgsql` and `pgsql` in the XAMPP PHP configuration and restart Apache. Local XAMPP continues to serve `http://localhost/SKalinga/`.

Run the local check with:

```powershell
C:\xampp\php\php.exe scripts\check-env.php
```

## Import the KK masterlist

After creating the admin account, sign in and open **Admin Masterlist** (`admin-masterlist.html`). Download the CSV Template, fill in authorized records using the template headers and `YYYY-MM-DD` dates, and use **Import Masterlist** to upload it. Review the import result and confirm records are available before testing youth registration.

## Troubleshooting

| Symptom | Checks and action |
|---|---|
| **502 Bad Gateway** | Wait for the image build and health check. Confirm the Dockerfile deployment uses Railway's injected `PORT`; do not override the start command to use PHP's built-in server. Review the deployment logs for Apache startup failures. |
| **429 Too Many Requests** | Login, masterlist verification, and OTP requests are rate limited. Wait before retrying and avoid repeated refreshes or OTP requests. |
| **URL changes between deployments** | Confirm a Railway-generated domain is enabled under **Networking**. A Railway domain is stable for the service; changing or regenerating it requires updating `EMAILJS_ORIGIN`. |
| **Login loops or session not retained** | Use the HTTPS Railway domain consistently. Confirm the app is running one replica and that forwarded HTTPS is reaching the app. Clear stale cookies if changing domains. |
| **Connection refused / DB connection failed** | Confirm the Supabase project is active and use its Session Pooler hostname, port `5432`, database `postgres`, exact `postgres.<PROJECT_REF>` username, and database password. Check Railway deployment logs and rerun `php scripts/check-env.php` in the service shell. |
| **`could not find driver`** | Ensure the latest Dockerfile build completed; it installs `pdo_pgsql` and `pgsql`. Redeploy after build failures and inspect the build logs. |
| **Uploads disappear after redeploy** | Check that both Railway volumes are attached to the exact mount paths listed above. |
| **Private upload URL returns 403** | This is expected for direct incident/printing file URLs. The authenticated `api/get_upload.php` endpoint serves private files. |

PHP errors are written to the Railway service logs. Sensitive application paths (including `database/`, `includes/`, `logs/`, and `scripts/`) and SQL/Markdown/log/backup files are blocked by Apache rules.
