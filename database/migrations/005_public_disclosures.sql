-- Public disclosure documents. Safe to run repeatedly.

CREATE TABLE IF NOT EXISTS public_disclosures (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    category ENUM('Budget and Finance', 'Programs and Projects', 'Memos and Resolutions', 'Other') NOT NULL DEFAULT 'Other',
    description TEXT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    status ENUM('Draft', 'Published') NOT NULL DEFAULT 'Published',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_public_disclosures_status (status),
    KEY idx_public_disclosures_category (category),
    CONSTRAINT fk_public_disclosures_created_by
        FOREIGN KEY (created_by) REFERENCES admins(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
