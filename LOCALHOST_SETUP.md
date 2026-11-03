# Local XAMPP Setup

This guide covers local Apache/PHP testing. The application uses Supabase PostgreSQL; local MySQL is not used.

1. Start Apache in XAMPP. Enable the `pdo_pgsql` and `pgsql` PHP extensions and restart Apache.
2. Apply `database/postgres/schema.sql` and the ordered files in `database/postgres/migrations/` to the Supabase project.
3. Copy `includes/config.local.example.php` to `includes/config.local.php` and configure the Supabase Session Pooler host, username, password, and a private 64-character `SITE_KEY`.
4. Create an administrator from a terminal with `php scripts/create_admin.php`.
5. Open `http://localhost/SKalinga/` and test the login and masterlist registration flows.

Do not use the legacy MySQL dump or old documented test accounts. For Railway deployment with Supabase, follow [DEPLOY.md](DEPLOY.md).
