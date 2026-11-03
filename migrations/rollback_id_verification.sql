-- Run only after a database backup.
DROP TABLE IF EXISTS `id_qr_tokens`;
DROP TABLE IF EXISTS `id_verifications`;
DROP TABLE IF EXISTS `otp_challenges`;
DROP TABLE IF EXISTS `security_audit_log`;
DROP TABLE IF EXISTS `otp_rate_buckets`;
DROP TABLE IF EXISTS `event_attendance`;
DROP TABLE IF EXISTS `health_service_requests`;

DROP PROCEDURE IF EXISTS `rollback_id_verification`;
DELIMITER //
CREATE PROCEDURE `rollback_id_verification`()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'borrow_records'
          AND column_name = 'verification_id'
    ) THEN
        ALTER TABLE `borrow_records` DROP COLUMN `verification_id`;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'printing_requests'
          AND column_name = 'release_verification_id'
    ) THEN
        ALTER TABLE `printing_requests` DROP COLUMN `release_verification_id`;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'printing_requests'
          AND column_name = 'verification_id'
    ) THEN
        ALTER TABLE `printing_requests` DROP COLUMN `verification_id`;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'incidents'
          AND column_name = 'verification_id'
    ) THEN
        ALTER TABLE `incidents` DROP COLUMN `verification_id`;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'users'
          AND column_name = 'verification_flagged'
    ) THEN
        ALTER TABLE `users` DROP COLUMN `verification_flagged`;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'youth_profiles'
          AND column_name = 'phone_changed_at'
    ) THEN
        ALTER TABLE `youth_profiles` DROP COLUMN `phone_changed_at`;
    END IF;

END//
DELIMITER ;

CALL `rollback_id_verification`();
DROP PROCEDURE IF EXISTS `rollback_id_verification`;
