-- ============================================================================
-- SKalinga Youth Portal - Resources & Borrowing System
-- ============================================================================

-- ============================================================================
-- 1. RESOURCES TABLE - Borrowable Equipment & Materials
-- ============================================================================
CREATE TABLE IF NOT EXISTS `resources` (
    `item_id` VARCHAR(50) PRIMARY KEY COMMENT 'Unique item identifier (ITEM-YYYYMMDD-###)',
    `name` VARCHAR(255) NOT NULL COMMENT 'Item name (e.g., Projector, Microphone)',
    `description` TEXT COMMENT 'Detailed description, brand, condition, etc.',
    `quantity` INT NOT NULL DEFAULT 0 COMMENT 'Total quantity of this item',
    `available` INT NOT NULL DEFAULT 0 COMMENT 'Currently available units for borrowing',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Resource creation timestamp',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    
    INDEX `idx_name` (`name`),
    INDEX `idx_available` (`available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Borrowable resources and equipment inventory';

-- ============================================================================
-- 2. BORROW_RECORDS TABLE - Borrowing Transaction History
-- ============================================================================
CREATE TABLE IF NOT EXISTS `borrow_records` (
    `borrow_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique borrow transaction ID',
    `item_id` VARCHAR(50) NOT NULL COMMENT 'Foreign key to resources table',
    `member_id` VARCHAR(50) NOT NULL COMMENT 'Youth member ID (SK-YYYY-####)',
    `borrower_name` VARCHAR(255) NOT NULL COMMENT 'Full name of borrower',
    `borrow_date` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time item was borrowed',
    `due_date` DATETIME NOT NULL COMMENT 'Expected return date',
    `return_date` DATETIME NULL COMMENT 'Actual return date (NULL if still borrowed)',
    `return_condition` VARCHAR(500) NULL COMMENT 'Condition recorded when the item was returned',
    `status` ENUM('Borrowed', 'Returned', 'Overdue') DEFAULT 'Borrowed' COMMENT 'Current borrowing status',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    
    FOREIGN KEY (`item_id`) REFERENCES resources(`item_id`) ON DELETE RESTRICT,
    INDEX `idx_member_id` (`member_id`),
    INDEX `idx_item_id` (`item_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_borrow_date` (`borrow_date`),
    INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Records of borrowed resources and returns';
