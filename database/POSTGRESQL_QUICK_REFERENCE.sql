-- ============================================================================
-- SKalinga PostgreSQL Quick Reference & Common Operations
-- For Supabase Migration
-- ============================================================================

-- ============================================================================
-- PART 1: DATA EXPORT QUERIES (Run on old MySQL to prepare for import)
-- ============================================================================

-- Get row counts to verify import
SELECT 'users' as table_name, COUNT(*) as count FROM users
UNION ALL SELECT 'youth_profiles', COUNT(*) FROM youth_profiles
UNION ALL SELECT 'resources', COUNT(*) FROM resources
UNION ALL SELECT 'borrow_records', COUNT(*) FROM borrow_records
UNION ALL SELECT 'events', COUNT(*) FROM events
UNION ALL SELECT 'incidents', COUNT(*) FROM incidents
UNION ALL SELECT 'printing_requests', COUNT(*) FROM printing_requests
UNION ALL SELECT 'password_resets', COUNT(*) FROM password_resets
UNION ALL SELECT 'admins', COUNT(*) FROM admins;

-- Check email validation before migration
SELECT id, email FROM users WHERE email NOT LIKE '%@%.%' OR email IS NULL;

-- Check date format issues
SELECT id, created_at, updated_at FROM users WHERE created_at IS NULL;

-- Find records with missing required fields
SELECT id, email, member_id FROM users WHERE email IS NULL OR member_id IS NULL;

-- ============================================================================
-- PART 2: POST-IMPORT VERIFICATION QUERIES (Run in PostgreSQL/Supabase)
-- ============================================================================

-- Verify all tables are imported correctly
SELECT schemaname, tablename FROM information_schema.tables 
WHERE schemaname = 'public';

-- Count rows in each table
SELECT 'users' as table_name, COUNT(*) as count FROM users
UNION ALL SELECT 'youth_profiles', COUNT(*) FROM youth_profiles
UNION ALL SELECT 'resources', COUNT(*) FROM resources
UNION ALL SELECT 'borrow_records', COUNT(*) FROM borrow_records
UNION ALL SELECT 'events', COUNT(*) FROM events
UNION ALL SELECT 'incidents', COUNT(*) FROM incidents
UNION ALL SELECT 'printing_requests', COUNT(*) FROM printing_requests
UNION ALL SELECT 'password_resets', COUNT(*) FROM password_resets
UNION ALL SELECT 'admins', COUNT(*) FROM admins;

-- Reset all sequences to max ID + 1
SELECT setval('users_id_seq', (SELECT MAX(id) + 1 FROM users));
SELECT setval('youth_profiles_id_seq', (SELECT MAX(id) + 1 FROM youth_profiles));
SELECT setval('resources_item_id_seq', (SELECT MAX(CAST(SUBSTR(item_id, LENGTH(item_id)-2) AS INTEGER)) From resources));
SELECT setval('borrow_records_borrow_id_seq', (SELECT MAX(borrow_id) + 1 FROM borrow_records));
SELECT setval('events_id_seq', (SELECT MAX(id) + 1 FROM events));
SELECT setval('incidents_id_seq', (SELECT MAX(id) + 1 FROM incidents));
SELECT setval('printing_requests_request_id_seq', (SELECT MAX(request_id) + 1 FROM printing_requests));
SELECT setval('password_resets_id_seq', (SELECT MAX(id) + 1 FROM password_resets));
SELECT setval('admins_id_seq', (SELECT MAX(id) + 1 FROM admins));

-- Check data integrity - users with profiles
SELECT u.id, u.email, u.member_id, 
       COALESCE(yp.firstname || ' ' || yp.lastname, 'NO PROFILE') as name
FROM users u
LEFT JOIN youth_profiles yp ON u.id = yp.user_id;

-- Check for orphaned records (child records without parents)
SELECT * FROM youth_profiles WHERE user_id NOT IN (SELECT id FROM users);
SELECT * FROM password_resets WHERE user_id NOT IN (SELECT id FROM users);
SELECT * FROM borrow_records WHERE item_id NOT IN (SELECT item_id FROM resources);

-- ============================================================================
-- PART 3: DATA FIX QUERIES (In case of import issues)
-- ============================================================================

-- Fix NULL timestamps (set to current time if blank)
UPDATE users SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL;
UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;
UPDATE youth_profiles SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL;
UPDATE youth_profiles SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;

-- Convert boolean values (if imported as 0/1)
UPDATE users SET email_verified = TRUE WHERE email_verified::text = '1';
UPDATE users SET email_verified = FALSE WHERE email_verified::text = '0';

-- Fix date format issues (if necessary)
UPDATE password_resets 
SET expires_at = expires_at AT TIME ZONE 'UTC' 
WHERE expires_at IS NOT NULL;

-- Ensure all status fields have valid values
UPDATE users SET status = 'active' WHERE status NOT IN ('active', 'inactive', 'suspended');
UPDATE admins SET status = 'active' WHERE status NOT IN ('active', 'inactive');

-- Fix enum values (case-sensitive in PostgreSQL)
UPDATE incidents SET status = 'pending' WHERE status = 'Pending';
UPDATE incidents SET status = 'in_progress' WHERE status = 'In Progress';
UPDATE incidents SET urgency = 'low' WHERE urgency = 'Low';
UPDATE incidents SET urgency = 'medium' WHERE urgency = 'Medium';
UPDATE incidents SET urgency = 'emergency' WHERE urgency = 'Emergency';

-- ============================================================================
-- PART 4: COMMON QUERIES FOR APPLICATION LOGIC
-- ============================================================================

-- Get user with full profile
SELECT u.id, u.email, u.member_id, u.status,
       yp.firstname, yp.lastname, yp.phone, yp.address, yp.barangay, yp.age
FROM users u
LEFT JOIN youth_profiles yp ON u.id = yp.user_id
WHERE u.id = $1;

-- Get active users count by barangay
SELECT yp.barangay, COUNT(u.id) as active_users
FROM users u
INNER JOIN youth_profiles yp ON u.id = yp.user_id
WHERE u.status = 'active'
GROUP BY yp.barangay
ORDER BY active_users DESC;

-- Get borrowed items for a user
SELECT br.borrow_id, r.name, br.quantity, br.borrow_date, br.due_date, br.status
FROM borrow_records br
INNER JOIN resources r ON br.item_id = r.item_id
WHERE br.member_id = $1
ORDER BY br.borrow_date DESC;

-- Get overdue borrowed items
SELECT br.borrow_id, br.borrower_name, r.name, br.due_date, CURRENT_TIMESTAMP - br.due_date as days_overdue
FROM borrow_records br
INNER JOIN resources r ON br.item_id = r.item_id
WHERE br.status = 'Borrowed' AND br.due_date < CURRENT_TIMESTAMP
ORDER BY br.due_date ASC;

-- Get upcoming events
SELECT id, event_id, title, date, start_time, location, capacity - registered_count as spots_left
FROM events
WHERE date >= CURRENT_DATE AND status = 'Upcoming'
ORDER BY date ASC
LIMIT 10;

-- Get pending incidents by urgency
SELECT id, member_id, category, location, urgency, submitted_date
FROM incidents
WHERE status = 'pending'
ORDER BY CASE urgency WHEN 'emergency' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
         submitted_date DESC;

-- Get pending printing requests
SELECT request_id, member_id, member_name, document_title, copies, print_type, paper_size, created_at
FROM printing_requests
WHERE status = 'Pending'
ORDER BY created_at ASC;

-- Get admin activity log (last logins)
SELECT id, username, email, last_login, created_at
FROM admins
WHERE status = 'active'
ORDER BY last_login DESC NULLS LAST;

-- ============================================================================
-- PART 5: ANALYTICS QUERIES
-- ============================================================================

-- Total active users
SELECT COUNT(*) as total_active_users FROM users WHERE status = 'active';

-- New registrations this month
SELECT COUNT(*) as new_this_month FROM users 
WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)
AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE);

-- Gender diversity
SELECT gender, COUNT(*) as count
FROM youth_profiles
WHERE gender IS NOT NULL
GROUP BY gender
ORDER BY count DESC;

-- Age distribution
SELECT 
    CASE WHEN age < 18 THEN 'Under 18'
         WHEN age < 25 THEN '18-24'
         WHEN age < 30 THEN '25-29'
         ELSE '30+'
    END as age_group,
    COUNT(*) as count
FROM youth_profiles
WHERE age IS NOT NULL
GROUP BY age_group
ORDER BY age_group;

-- Barangay statistics
SELECT yp.barangay, 
       COUNT(DISTINCT u.id) as total_members,
       COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_members
FROM youth_profiles yp
LEFT JOIN users u ON yp.user_id = u.id
GROUP BY yp.barangay
ORDER BY total_members DESC;

-- Resource borrowing statistics
SELECT r.name, 
       r.quantity as total_available,
       COUNT(br.borrow_id) as times_borrowed,
       r.quantity - COALESCE(r.available, 0) as currently_borrowed
FROM resources r
LEFT JOIN borrow_records br ON r.item_id = br.item_id AND br.status = 'Borrowed'
GROUP BY r.name, r.quantity, r.available
ORDER BY COUNT(br.borrow_id) DESC;

-- Event attendance rate
SELECT title, date, capacity, registered_count,
       ROUND(100.0 * registered_count / capacity, 2) as capacity_percentage
FROM events
WHERE capacity > 0
ORDER BY capacity_percentage DESC;

-- ============================================================================
-- PART 6: MAINTENANCE & OPTIMIZATION QUERIES
-- ============================================================================

-- Analyze table for optimization
ANALYZE users;
ANALYZE youth_profiles;
ANALYZE borrow_records;
ANALYZE events;

-- Vacuum (cleanup dead rows) - run weekly
VACUUM FULL users;
VACUUM FULL youth_profiles;
VACUUM FULL borrow_records;

-- Check table sizes
SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Get database size
SELECT pg_size_pretty(pg_database_size('postgres')) as database_size;

-- Check for unused indexes
SELECT schemaname, tablename, indexname, idx_scan, pg_size_pretty(pg_relation_size(indexrelid)) as size
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY pg_relation_size(indexrelid) DESC;

-- Get slow queries (if query logging is enabled)
SELECT query, calls, mean_exec_time, max_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;

-- ============================================================================
-- PART 7: TROUBLESHOOTING & DIAGNOSTICS
-- ============================================================================

-- Check if AUTO_INCREMENT was reset correctly
SELECT DISTINCT
    (SELECT MAX(id) + 1 FROM users)::text as users_next_id,
    (SELECT MAX(id) + 1 FROM youth_profiles)::text as youth_profiles_next_id,
    (SELECT MAX(borrow_id) + 1 FROM borrow_records)::text as borrow_records_next_id;

-- Check for duplicate emails (data integrity issue)
SELECT email, COUNT(*) as count
FROM users
GROUP BY email
HAVING COUNT(*) > 1;

-- Check for duplicate member_ids
SELECT member_id, COUNT(*) as count
FROM users
GROUP BY member_id
HAVING COUNT(*) > 1;

-- Check for broken foreign keys (orphaned records)
SELECT COUNT(*) FROM borrow_records WHERE item_id NOT IN (SELECT item_id FROM resources);
SELECT COUNT(*) FROM youth_profiles WHERE user_id NOT IN (SELECT id FROM users);
SELECT COUNT(*) FROM password_resets WHERE user_id NOT IN (SELECT id FROM users);

-- Get MySQL DATETIME edge case (check in source before import)
SELECT id, created_at FROM users WHERE created_at = '0000-00-00 00:00:00';

-- List all tables and their row counts
SELECT 
    tablename,
    (SELECT COUNT(*) FROM information_schema.columns c WHERE c.table_name = t.tablename) as column_count,
    (xpath('/row/@cnt', query_to_xml('SELECT COUNT(*) as cnt FROM ' || schemaname || '.' || tablename, false, true, '')))[1]::text::int as row_count
FROM pg_tables t
WHERE schemaname = 'public'
ORDER BY tablename;

-- ============================================================================
-- PART 8: RLS (ROW LEVEL SECURITY) DIAGNOSTIC QUERIES
-- ============================================================================

-- Check if RLS is enabled on tables
SELECT tablename, rowsecurity FROM pg_tables 
WHERE schemaname = 'public' 
ORDER BY tablename;

-- List all RLS policies
SELECT schemaname, tablename, policyname, permissive, roles, qual
FROM pg_policies
WHERE schemaname = 'public';

-- Check if current user passes RLS policy
-- (Only works after auth is set up)
-- SELECT * FROM users; -- This will be filtered by RLS if enabled

-- Count RLS policies per table
SELECT tablename, COUNT(*) as policy_count
FROM pg_policies
WHERE schemaname = 'public'
GROUP BY tablename
ORDER BY policy_count DESC;

-- ============================================================================
-- PART 9: USEFUL ADMINISTRATIVE COMMANDS
-- ============================================================================

-- Kill all connections to database (useful before maintenance)
SELECT pg_terminate_backend(pg_stat_activity.pid)
FROM pg_stat_activity
WHERE pg_stat_activity.datname = 'postgres'
AND pid <> pg_backend_pid();

-- Create backup (in PostgreSQL format)
-- Run in terminal, not in SQL editor:
-- pg_dump "postgresql://postgres:password@db.xxxxx.supabase.co/postgres" > backup.sql

-- Get backup from Supabase (via Dashboard > Settings > Backups)
-- Download and restore locally with:
-- psql "postgresql://postgres:password@localhost:5432/local_db" < backup.sql

-- List active connections
SELECT usename, application_name, state, query
FROM pg_stat_activity
WHERE datname = 'postgres'
ORDER BY query_start DESC;

-- Get query currently running (for long-running operations)
SELECT pid, now() - query_start AS duration, query
FROM pg_stat_activity
WHERE state = 'active'
ORDER BY query_start DESC;

-- ============================================================================
-- PART 10: COMMON ERROR FIXES
-- ============================================================================

-- Error: "relation does not exist"
-- Solution: PostgreSQL requires double quotes for case-sensitive names
SELECT * FROM "users"; -- Correct
SELECT * FROM users;   -- Also works if table is lowercase

-- Error: "duplicate key value violates unique constraint"
-- Solution: Check sequences and reset them
SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

-- Error: "failed to find conversion function"
-- Solution: Cast data type explicitly
SELECT CAST(some_field AS INTEGER) FROM table_name; -- int from varchar
SELECT CAST(some_field AS TIMESTAMP) FROM table_name; -- timestamp from text

-- Error: "value too long for type character varying"
-- Solution: Check and possibly increase VARCHAR length
ALTER TABLE users MODIFY email VARCHAR(500);

-- Error: "null value in column violates not-null constraint"
-- Solution: Find and fix null values
SELECT * FROM users WHERE email IS NULL;
UPDATE users SET email = 'unknown@example.com' WHERE email IS NULL;

-- ============================================================================
-- QUICK REFERENCE: POSTGRESQL vs MYSQL DIFFERENCES
-- ============================================================================
/*
MySQL                              PostgreSQL
INT AUTO_INCREMENT PRIMARY KEY      SERIAL PRIMARY KEY or INTEGER with SEQUENCE
VARCHAR(255)                        VARCHAR(255) (same)
DATETIME                            TIMESTAMP or TIMESTAMP WITH TIME ZONE
DATE                                DATE (same)
TIME                                TIME (same)
TINYINT(1)                          BOOLEAN
TEXT                                TEXT (same)
DECIMAL(10,2)                       DECIMAL(10,2) or NUMERIC(10,2)
LONGTEXT                            TEXT
ENGINE=InnoDB                       (not needed in PostgreSQL)
Backticks `table`                   Double quotes "table" or no quotes if lowercase
':' in passwords                    Allowed (PostgreSQL may need escaping)
UNSIGNED INT                        INTEGER or BIGINT
CURRENT_TIMESTAMP                   CURRENT_TIMESTAMP (same)
AUTO_INCREMENT after insert         Use setval() on sequence
CREATE INDEX IF NOT EXISTS          Same syntax
DEFAULT COLLATE                     COLLATE "en_US.UTF-8"
-> JSON                             More powerful JSONB type available
CHECK CONSTRAINT                    Same syntax (more strict in PostgreSQL)
FOREIGN KEY ON DELETE CASCADE       Same syntax (more strict in PostgreSQL)
*/

-- ============================================================================
-- FINAL VERIFICATION SCRIPT
-- Run this entire script to verify successful migration
-- ============================================================================

BEGIN;

-- 1. Check table creation
SELECT 'Step 1: Checking tables exist...' as step;
SELECT * FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';

-- 2. Verify row counts match expectations
SELECT 'Step 2: Checking data import...' as step;
SELECT 'users' as table, COUNT(*) as rows FROM users
UNION ALL SELECT 'youth_profiles', COUNT(*) FROM youth_profiles
UNION ALL SELECT 'resources', COUNT(*) FROM resources
UNION ALL SELECT 'borrow_records', COUNT(*) FROM borrow_records
UNION ALL SELECT 'events', COUNT(*) FROM events
UNION ALL SELECT 'incidents', COUNT(*) FROM incidents
UNION ALL SELECT 'printing_requests', COUNT(*) FROM printing_requests
UNION ALL SELECT 'password_resets', COUNT(*) FROM password_resets
UNION ALL SELECT 'admins', COUNT(*) FROM admins;

-- 3. Check data integrity
SELECT 'Step 3: Checking foreign keys...' as step;
SELECT 'Orphaned youth_profiles' as issue, COUNT(*) as count FROM youth_profiles WHERE user_id NOT IN (SELECT id FROM users)
UNION ALL SELECT 'Orphaned password_resets', COUNT(*) FROM password_resets WHERE user_id NOT IN (SELECT id FROM users)
UNION ALL SELECT 'Orphaned borrow_records', COUNT(*) FROM borrow_records WHERE item_id NOT IN (SELECT item_id FROM resources);

-- 4. Sample data verification
SELECT 'Step 4: Sampling data...' as step;
SELECT 'Sample user' as info, * FROM users LIMIT 1;
SELECT 'Sample profile' as info, * FROM youth_profiles LIMIT 1;

-- 5. Check sequences
SELECT 'Step 5: Verifying sequences...' as step;
SELECT schemaname, sequencename FROM information_schema.sequences WHERE schemaname = 'public';

COMMIT;

-- If all steps pass without errors, your migration is complete! ✅
