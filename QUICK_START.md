# SKalinga Quick Start

1. Start Apache in XAMPP. The application uses Supabase PostgreSQL, not local MySQL.
2. Enable PHP `pdo_pgsql` and `pgsql` extensions, then restart Apache.
3. Apply `database/postgres/schema.sql` and the files in `database/postgres/migrations/` in order to Supabase.
4. Copy `includes/config.local.example.php` to `includes/config.local.php` and set the Session Pooler credentials and a private 64-character `SITE_KEY`.
5. Create the first administrator from the CLI: `php scripts/create_admin.php`.
6. Open [http://localhost/SKalinga/](http://localhost/SKalinga/) and test admin login, masterlist import, and youth account activation.

No sample account passwords are provided or seeded. See [README.md](README.md), [DEPLOY.md](DEPLOY.md), and [database/postgres/README.md](database/postgres/README.md) for local and Railway deployment details.
