-- Masterlist-based youth account activation.
-- This migration targets the active schema's users/youth_profiles tables.

CREATE TABLE IF NOT EXISTS kk_masterlist (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voter_id VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    birthday DATE NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    sex VARCHAR(30) NOT NULL,
    address TEXT NOT NULL,
    purok_or_barangay VARCHAR(150) NOT NULL,
    is_claimed TINYINT(1) NOT NULL DEFAULT 0,
    claimed_by_user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kk_masterlist_voter_id (voter_id),
    KEY idx_kk_masterlist_name (last_name, first_name),
    KEY idx_kk_masterlist_claimed (is_claimed),
    CONSTRAINT fk_kk_masterlist_claimed_user
        FOREIGN KEY (claimed_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    attempt_type VARCHAR(50) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_lookup (ip_address, attempt_type, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    MODIFY COLUMN email VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS masterlist_id INT UNSIGNED NULL,
    ADD UNIQUE INDEX IF NOT EXISTS uq_users_masterlist_id (masterlist_id);

ALTER TABLE youth_profiles
    MODIFY COLUMN phone VARCHAR(20) NOT NULL;

SET @masterlist_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_users_masterlist_id'
);
SET @masterlist_fk_sql = IF(
    @masterlist_fk_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_users_masterlist_id FOREIGN KEY (masterlist_id) REFERENCES kk_masterlist (id) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE masterlist_fk_statement FROM @masterlist_fk_sql;
EXECUTE masterlist_fk_statement;
DEALLOCATE PREPARE masterlist_fk_statement;