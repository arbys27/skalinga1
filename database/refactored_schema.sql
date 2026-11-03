-- ============================================================================
-- SKalinga Youth Portal - Refactored Database Schema
-- Normalized structure with separate tables for auth, profile, and password resets
-- ============================================================================

-- ============================================================================
-- 1. USERS TABLE - Authentication & Account Management
-- ============================================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique user ID',
    `email` VARCHAR(255) UNIQUE NOT NULL COMMENT 'Email address (login credential)',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
    `member_id` VARCHAR(20) UNIQUE NOT NULL COMMENT 'Unique member ID (SK-YYYY-NNNN)',
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active' COMMENT 'Account status',
    `email_verified` TINYINT(1) DEFAULT 0 COMMENT 'Email verification status',
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Email verification timestamp',
    `last_login` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last login timestamp',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Account creation timestamp',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    
    INDEX `idx_email` (`email`),
    INDEX `idx_member_id` (`member_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User authentication and account management';

-- ============================================================================
-- 2. YOUTH_PROFILES TABLE - Personal & Profile Information
-- ============================================================================
CREATE TABLE IF NOT EXISTS `youth_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Profile record ID',
    `user_id` INT NOT NULL UNIQUE COMMENT 'Foreign key to users table',
    
    -- Personal Information
    `firstname` VARCHAR(100) NOT NULL COMMENT 'First name',
    `lastname` VARCHAR(100) NOT NULL COMMENT 'Last name',
    `birthday` DATE COMMENT 'Date of birth',
    `age` INT COMMENT 'Age (auto-calculated from birthday)',
    `gender` ENUM('Male', 'Female', 'Other', 'Prefer not to say') COMMENT 'Gender identity',
    
    -- Contact Information
    `phone` VARCHAR(20) COMMENT 'Contact phone number (11-digit)',
    `address` LONGTEXT COMMENT 'Full physical address',
    `barangay` VARCHAR(100) DEFAULT 'San Antonio' COMMENT 'Barangay location',
    
    -- Profile Customization
    `avatar_path` VARCHAR(255) COMMENT 'Path to profile picture',
    `bio` TEXT COMMENT 'User biography',
    
    -- Record Management
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Profile creation timestamp',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last profile update timestamp',
    
    -- Foreign Key
    CONSTRAINT `fk_youth_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    
    -- Indexes
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_firstname` (`firstname`),
    INDEX `idx_lastname` (`lastname`),
    INDEX `idx_barangay` (`barangay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Youth profile and personal information';

-- ============================================================================
-- 3. PASSWORD_RESETS TABLE - OTP & Password Recovery
-- ============================================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Reset request ID',
    `user_id` INT NOT NULL COMMENT 'Foreign key to users table',
    `otp_code` VARCHAR(6) COMMENT 'One-time password (6-digit code)',
    `otp_attempts` INT DEFAULT 0 COMMENT 'Number of failed OTP attempts',
    `is_used` TINYINT(1) DEFAULT 0 COMMENT 'Whether OTP has been used',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'OTP creation timestamp',
    `expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'OTP expiration time (15 mins)',
    
    -- Foreign Key
    CONSTRAINT `fk_password_resets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    
    -- Indexes
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_otp_code` (`otp_code`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Password reset OTP requests';

-- ============================================================================
-- VIEWS FOR COMMON QUERIES
-- ============================================================================

-- View: User profile with auth info
CREATE OR REPLACE VIEW `user_profiles_full` AS
SELECT 
    u.id,
    u.email,
    u.member_id,
    u.status,
    u.created_at AS user_created_at,
    p.firstname,
    p.lastname,
    p.birthday,
    p.age,
    p.gender,
    p.phone,
    p.address,
    p.barangay,
    p.avatar_path,
    p.bio,
    p.updated_at AS profile_updated_at
FROM `users` u
LEFT JOIN `youth_profiles` p ON u.id = p.user_id;

-- View: Active users count by barangay
CREATE OR REPLACE VIEW `active_users_by_barangay` AS
SELECT 
    p.barangay,
    COUNT(u.id) AS active_count,
    COUNT(DISTINCT p.gender) AS gender_diversity
FROM `users` u
JOIN `youth_profiles` p ON u.id = p.user_id
WHERE u.status = 'active'
GROUP BY p.barangay
ORDER BY p.barangay;

-- ============================================================================
-- 4. ADMINS TABLE - Administrative Users & Access Control
-- ============================================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique admin ID',
    `username` VARCHAR(50) UNIQUE NOT NULL COMMENT 'Admin username for login',
    `email` VARCHAR(255) UNIQUE NOT NULL COMMENT 'Admin email address',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
    `role` ENUM('superadmin', 'staff') DEFAULT 'staff' COMMENT 'Admin role (superadmin has full access)',
    `status` ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Account status',
    `last_login` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last login timestamp',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Account creation timestamp',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin accounts and access control';

-- ============================================================================
-- MIGRATION SCRIPT (Optional - if migrating from old schema)
-- ============================================================================

-- If you still have the old youth_registrations table, uncomment below to migrate:
/*
-- Step 1: Create users from youth_registrations
INSERT INTO users (email, password_hash, member_id, status, email_verified)
SELECT email, password_hash, member_id, 
       CASE WHEN status = 'active' THEN 'active' ELSE 'inactive' END,
       1
FROM youth_registrations;

-- Step 2: Create profiles from youth_registrations
INSERT INTO youth_profiles (user_id, firstname, lastname, birthday, age, gender, phone, address, barangay)
SELECT u.id, yr.firstname, yr.lastname, yr.birthday, yr.age, yr.gender, yr.contact, yr.address, yr.barangay
FROM youth_registrations yr
JOIN users u ON yr.email = u.email;

-- Step 3: Drop old table (after verifying data)
-- DROP TABLE youth_registrations;
*/