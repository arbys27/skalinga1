-- Create printing_requests table for Print Services System
CREATE TABLE IF NOT EXISTS printing_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(50) NOT NULL,
    member_name VARCHAR(255) NOT NULL,
    document_title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT,
    print_type ENUM('Black & White', 'Colored') NOT NULL DEFAULT 'Black & White',
    paper_size ENUM('A4', 'Short', 'Long') NOT NULL DEFAULT 'A4',
    copies INT NOT NULL DEFAULT 1,
    status ENUM('Pending', 'Printing', 'Completed', 'Claimed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    claimed_at DATETIME NULL,
    notes TEXT,
    FOREIGN KEY (member_id) REFERENCES users(member_id) ON DELETE CASCADE,
    INDEX idx_member_id (member_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
