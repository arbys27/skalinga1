# Supabase PostgreSQL schema

Run `schema.sql` in the Supabase SQL editor, followed by every file in `migrations/`
in numeric order (`001` through `006`). The files are safe to re-run.

The original MySQL dump is `../skalinga_youth.sql`; the task-referenced
`database/skalinga_schema.sql` is not present in this repository. The converted
schema intentionally contains no imported personal records or password-bearing
account seeds. `seed_dev.sql` is optional and inserts only non-secret inventory
fixtures.

All application tables have row-level security enabled without policies. This
intentionally denies access through Supabase's anon and authenticated Data API
roles; the PHP application must connect using its configured privileged database
role.
