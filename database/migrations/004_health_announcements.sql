-- Health announcements migration. Safe to run repeatedly.

CREATE TABLE IF NOT EXISTS health_announcements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    category ENUM(
        'Dental Caravan',
        'Medical Mission',
        'Vaccination',
        'Free Check-up',
        'Mental Health',
        'Nutrition and Fitness',
        'Blood Donation',
        'Other'
    ) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    venue VARCHAR(255) NULL,
    slots INT NULL,
    requirements TEXT NULL,
    image_path VARCHAR(255) NULL,
    status ENUM('Draft', 'Published', 'Cancelled', 'Archived') NOT NULL DEFAULT 'Draft',
    published_at DATETIME NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_health_announcements_status (status),
    KEY idx_health_announcements_event_date (event_date),
    CONSTRAINT fk_health_announcements_created_by
        FOREIGN KEY (created_by) REFERENCES admins(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_health_announcements_updated_by
        FOREIGN KEY (updated_by) REFERENCES admins(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO health_announcements
    (title, category, description, event_date, start_time, end_time, venue, slots, requirements, image_path, status, published_at, created_by, updated_by)
SELECT
    'Dental Caravan',
    'Dental Caravan',
    'Free dental consultation, cleaning, and basic oral health guidance for youth participants.',
    CURDATE() + INTERVAL 17 DAY,
    '09:00:00',
    '15:00:00',
    'Barangay Hall',
    50,
    'Bring your Digital Youth ID and wear a face mask if recommended by the clinic.',
    NULL,
    'Published',
    NOW(),
    NULL,
    NULL
WHERE NOT EXISTS (SELECT 1 FROM health_announcements WHERE title = 'Dental Caravan');

INSERT INTO health_announcements
    (title, category, description, event_date, start_time, end_time, venue, slots, requirements, image_path, status, published_at, created_by, updated_by)
SELECT
    'Free Check-up Day',
    'Free Check-up',
    'General check-up and health consultation day for youth residents.',
    CURDATE() + INTERVAL 21 DAY,
    '08:00:00',
    '12:00:00',
    'Rural Health Unit',
    NULL,
    'Bring your Digital Youth ID and any available medical records.',
    NULL,
    'Published',
    NOW(),
    NULL,
    NULL
WHERE NOT EXISTS (SELECT 1 FROM health_announcements WHERE title = 'Free Check-up Day');

INSERT INTO health_announcements
    (title, category, description, event_date, start_time, end_time, venue, slots, requirements, image_path, status, published_at, created_by, updated_by)
SELECT
    'Blood Donation Drive',
    'Blood Donation',
    'Join the community blood donation drive and help save lives.',
    CURDATE() + INTERVAL 28 DAY,
    '10:00:00',
    '16:00:00',
    'Municipal Gym',
    100,
    'Bring your Digital Youth ID and eat a full meal before donating.',
    NULL,
    'Published',
    NOW(),
    NULL,
    NULL
WHERE NOT EXISTS (SELECT 1 FROM health_announcements WHERE title = 'Blood Donation Drive');
