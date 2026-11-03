-- ============================================================================
-- SKalinga Events Table for Events Management
-- ============================================================================

CREATE TABLE IF NOT EXISTS `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Auto-incremented ID',
    `event_id` VARCHAR(20) UNIQUE NOT NULL COMMENT 'Event ID (EVT-YYYY-NNNN)',
    `title` VARCHAR(255) NOT NULL COMMENT 'Event title',
    `description` LONGTEXT COMMENT 'Event description',
    `event_type` VARCHAR(100) NOT NULL COMMENT 'Event type (Training, Sports, Cultural, Community, Other)',
    `date` DATE NOT NULL COMMENT 'Event date',
    `start_time` TIME NOT NULL COMMENT 'Event start time',
    `end_time` TIME COMMENT 'Event end time',
    `location` VARCHAR(255) NOT NULL COMMENT 'Event location',
    `capacity` INT DEFAULT 0 COMMENT 'Maximum number of attendees',
    `registered_count` INT DEFAULT 0 COMMENT 'Current number of registrations',
    `registration_link` VARCHAR(500) COMMENT 'Registration/Google Form link',
    `image_path` VARCHAR(500) COMMENT 'Path to event image file',
    `status` ENUM('Upcoming', 'Ongoing', 'Past') DEFAULT 'Upcoming' COMMENT 'Event status',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Event creation timestamp',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_date` (`date`),
    INDEX `idx_start_time` (`start_time`),
    INDEX `idx_status` (`status`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SK Events management table with image support';
