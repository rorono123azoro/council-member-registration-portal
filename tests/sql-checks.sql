-- Council Member Registration Portal — SQL Verification Checks
-- Run: mysql -u root -p council_portal < tests/sql-checks.sql

USE `council_portal`;

-- 1. Verify tables exist
SHOW TABLES;

-- 2. Verify utf8mb4 and InnoDB on members
SHOW TABLE STATUS WHERE Name IN ('members', 'categories', 'documents', 'admins', 'audit_log', 'login_attempts', 'rate_limits');

-- 3. Verify unique constraints and indexes on members
SHOW INDEX FROM `members`;

-- 4. Verify foreign keys including ON DELETE CASCADE on documents
SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'council_portal';

-- 5. Verify seeded categories (active + inactive)
SELECT id, name, code, is_active FROM `categories` ORDER BY id;

-- 6. Verify seeded admin has bcrypt password hash and must_change_password = 1
SELECT id, username, email, role, is_active, must_change_password, LEFT(password_hash, 4) AS hash_prefix
FROM `admins`;

-- 7. Verify query plan uses indexes for filtered list (PERF-03)
EXPLAIN SELECT m.id, m.reg_no, m.full_name, m.status, m.created_at
FROM `members` m
WHERE m.status = 'pending'
ORDER BY m.created_at DESC
LIMIT 20 OFFSET 0;

