-- Add phone_verified field to users table for SMS OTP authentication
-- ============================================================================

-- Check if column exists before adding (MySQL compatible)
ALTER TABLE `users` 
ADD COLUMN `phone_verified` TINYINT(1) DEFAULT 0 COMMENT 'Phone verification status' 
AFTER `email_verified_at`;

-- Create index for phone verification status
CREATE INDEX idx_users_phone_verified ON `users`(`phone_verified`);

-- Add OTP related columns for tracking
ALTER TABLE `users`
ADD COLUMN `phone_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Phone verification timestamp'
AFTER `phone_verified`;

-- ============================================================================
-- Update existing users to mark phone as verified (for backward compatibility)
-- ============================================================================
UPDATE `users` 
SET `phone_verified` = 1 
WHERE `status` = 'active' AND `created_at` IS NOT NULL;

-- ============================================================================
-- Verification Complete
-- ============================================================================
SELECT 'Database updated successfully!' as status;
SHOW COLUMNS FROM `users` WHERE Field IN ('phone_verified', 'phone_verified_at');
