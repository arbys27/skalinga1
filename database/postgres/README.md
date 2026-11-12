# Supabase PostgreSQL schema

Run `schema.sql` in the Supabase SQL editor, followed by every file in `migrations/`
in numeric order (`001` through `013`). The files are safe to re-run.

Migration `007_freedom_wall.sql` creates the moderated youth Freedom Wall post
table and indexes. Apply it before using the Freedom Wall pages or APIs.

The admin board supports status/category/search filters, advisory safety flags,
and approve, reject, hide, and unhide actions. Public posts are limited to
approved, non-hidden entries; rejected posts and their reasons are visible only
to the submitting youth.

Migration `008_service_verification.sql` creates hashed, one-time service verification
tokens, per-service expiry/OTP settings, and verification audit logs. It currently
enables the print-claim service adapter; apply it before using the service verification
APIs or admin scanner. Set `otp_required = true` for the `print` row in
`service_verification_settings` to require SMS OTP for print claims.

Migration `009_incident_accountability.sql` adds incident handler snapshots,
resolution/closure metadata, and the admin-only incident status/assignment history.
Apply it before using the incident management dashboard's handler assignment and
history features.

Migration `013_printing_submission_verification.sql` adds the
`AwaitingVerification` printing status and verification metadata for the physical
and email-verified submission flow.

The original MySQL dump is `../skalinga_youth.sql`; the task-referenced
`database/skalinga_schema.sql` is not present in this repository. The converted
schema intentionally contains no imported personal records or password-bearing
account seeds. `seed_dev.sql` is optional and inserts only non-secret inventory
fixtures.

All application tables have row-level security enabled without policies. This
intentionally denies access through Supabase's anon and authenticated Data API
roles; the PHP application must connect using its configured privileged database
role.
