-- ============================================================================
-- SKalinga Youth Portal - PostgreSQL Schema (Supabase Compatible)
-- Converted from MySQL with UUID primary keys and RLS ready
-- ============================================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================================================
-- 1. USERS TABLE - Authentication & Account Management
-- ============================================================================
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    member_id VARCHAR(20) UNIQUE NOT NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    email_verified BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP WITH TIME ZONE NULL,
    last_login TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for users table
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_member_id ON users(member_id);
CREATE INDEX idx_users_status ON users(status);

-- ============================================================================
-- 2. YOUTH_PROFILES TABLE - Personal & Profile Information
-- ============================================================================
CREATE TABLE youth_profiles (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    birthday DATE NULL,
    age INTEGER NULL,
    gender VARCHAR(50) NULL CHECK (gender IN ('Male', 'Female', 'Other', 'Prefer not to say')),
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    barangay VARCHAR(100) DEFAULT 'San Antonio',
    avatar_path VARCHAR(255) NULL,
    bio TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_youth_profiles_user_id ON youth_profiles(user_id);
CREATE INDEX idx_youth_profiles_firstname ON youth_profiles(firstname);
CREATE INDEX idx_youth_profiles_lastname ON youth_profiles(lastname);
CREATE INDEX idx_youth_profiles_barangay ON youth_profiles(barangay);
CREATE INDEX idx_youth_profiles_phone ON youth_profiles(phone);

-- ============================================================================
-- 3. RESOURCES TABLE - Borrowable Resources & Equipment Inventory
-- ============================================================================
CREATE TABLE resources (
    item_id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    quantity INTEGER NOT NULL DEFAULT 0,
    available INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_resources_name ON resources(name);
CREATE INDEX idx_resources_available ON resources(available);

-- ============================================================================
-- 4. BORROW_RECORDS TABLE - Records of Borrowed Resources
-- ============================================================================
CREATE TABLE borrow_records (
    borrow_id SERIAL PRIMARY KEY,
    item_id VARCHAR(50) NOT NULL REFERENCES resources(item_id) ON DELETE CASCADE,
    member_id VARCHAR(50) NOT NULL,
    borrower_name VARCHAR(255) NOT NULL,
    quantity INTEGER DEFAULT 1,
    borrow_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    due_date TIMESTAMP WITH TIME ZONE NOT NULL,
    return_date TIMESTAMP WITH TIME ZONE NULL,
    return_condition VARCHAR(500) NULL,
    status VARCHAR(20) DEFAULT 'Borrowed' CHECK (status IN ('Borrowed', 'Returned', 'Overdue')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_borrow_records_member_id ON borrow_records(member_id);
CREATE INDEX idx_borrow_records_item_id ON borrow_records(item_id);
CREATE INDEX idx_borrow_records_status ON borrow_records(status);
CREATE INDEX idx_borrow_records_borrow_date ON borrow_records(borrow_date);
CREATE INDEX idx_borrow_records_due_date ON borrow_records(due_date);

-- ============================================================================
-- 5. EVENTS TABLE - SK Events Management
-- ============================================================================
CREATE TABLE events (
    id SERIAL PRIMARY KEY,
    event_id VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    event_type VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    location VARCHAR(255) NOT NULL,
    capacity INTEGER DEFAULT 0,
    registered_count INTEGER DEFAULT 0,
    registration_link VARCHAR(500) NULL,
    image_path VARCHAR(500) NULL,
    status VARCHAR(20) DEFAULT 'Upcoming' CHECK (status IN ('Upcoming', 'Ongoing', 'Past')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_events_event_id ON events(event_id);
CREATE INDEX idx_events_date ON events(date);
CREATE INDEX idx_events_status ON events(status);
CREATE INDEX idx_events_event_type ON events(event_type);

-- ============================================================================
-- 6. INCIDENTS TABLE - Youth Incident/Support Reports
-- ============================================================================
CREATE TABLE incidents (
    id SERIAL PRIMARY KEY,
    member_id VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    urgency VARCHAR(20) NOT NULL DEFAULT 'low' CHECK (urgency IN ('low', 'medium', 'emergency')),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'closed')),
    photo_path VARCHAR(255) NULL,
    submitted_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    admin_notes TEXT NULL
);

CREATE INDEX idx_incidents_member_id ON incidents(member_id);
CREATE INDEX idx_incidents_status ON incidents(status);
CREATE INDEX idx_incidents_category ON incidents(category);
CREATE INDEX idx_incidents_urgency ON incidents(urgency);

-- ============================================================================
-- 7. PRINTING_REQUESTS TABLE - Document Printing Service
-- ============================================================================
CREATE TABLE printing_requests (
    request_id SERIAL PRIMARY KEY,
    member_id VARCHAR(50) NOT NULL,
    member_name VARCHAR(255) NOT NULL,
    document_title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INTEGER NULL,
    print_type VARCHAR(50) NOT NULL DEFAULT 'Black & White' CHECK (print_type IN ('Black & White', 'Colored')),
    paper_size VARCHAR(20) NOT NULL DEFAULT 'A4' CHECK (paper_size IN ('A4', 'Short', 'Long')),
    copies INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Printing', 'Completed', 'Claimed')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    claimed_at TIMESTAMP WITH TIME ZONE NULL,
    notes TEXT NULL
);

CREATE INDEX idx_printing_requests_member_id ON printing_requests(member_id);
CREATE INDEX idx_printing_requests_status ON printing_requests(status);
CREATE INDEX idx_printing_requests_created_at ON printing_requests(created_at);

-- ============================================================================
-- 8. PASSWORD_RESETS TABLE - OTP & Password Recovery
-- ============================================================================
CREATE TABLE password_resets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    otp_code VARCHAR(6) NULL,
    otp_attempts INTEGER DEFAULT 0,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP WITH TIME ZONE NULL
);

CREATE INDEX idx_password_resets_user_id ON password_resets(user_id);
CREATE INDEX idx_password_resets_otp_code ON password_resets(otp_code);
CREATE INDEX idx_password_resets_expires_at ON password_resets(expires_at);

-- ============================================================================
-- 9. ADMINS TABLE - Admin Accounts & Access Control
-- ============================================================================
CREATE TABLE admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'staff' CHECK (role IN ('superadmin', 'staff')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    last_login TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_admins_username ON admins(username);
CREATE INDEX idx_admins_email ON admins(email);
CREATE INDEX idx_admins_role ON admins(role);
CREATE INDEX idx_admins_status ON admins(status);

-- ============================================================================
-- VIEWS FOR COMMON QUERIES
-- ============================================================================

-- Active users by barangay
CREATE VIEW active_users_by_barangay AS
SELECT 
    yp.barangay,
    COUNT(u.id) as active_count,
    COUNT(DISTINCT yp.gender) as gender_diversity
FROM users u
INNER JOIN youth_profiles yp ON u.id = yp.user_id
WHERE u.status = 'active'
GROUP BY yp.barangay
ORDER BY yp.barangay ASC;

-- Full user profiles with all details
CREATE VIEW user_profiles_full AS
SELECT 
    u.id,
    u.email,
    u.member_id,
    u.status,
    u.created_at as user_created_at,
    yp.firstname,
    yp.lastname,
    yp.birthday,
    yp.age,
    yp.gender,
    yp.phone,
    yp.address,
    yp.barangay,
    yp.avatar_path,
    yp.bio,
    yp.updated_at as profile_updated_at
FROM users u
LEFT JOIN youth_profiles yp ON u.id = yp.user_id;

-- ============================================================================
-- NOTE: Row Level Security (RLS) can be enabled after data import
-- Uncomment and customize the following RLS policies as needed:
-- ============================================================================

-- ALTER TABLE users ENABLE ROW LEVEL SECURITY;
-- ALTER TABLE youth_profiles ENABLE ROW LEVEL SECURITY;
-- ALTER TABLE password_resets ENABLE ROW LEVEL SECURITY;

-- Policy: Users can only see their own profile
-- CREATE POLICY "Users can view own profile"
-- ON users FOR SELECT
-- USING (auth.uid()::text = id::text);

-- Policy: Users can only update their own profile
-- CREATE POLICY "Users can update own profile"
-- ON users FOR UPDATE
-- USING (auth.uid()::text = id::text);

-- ============================================================================
-- COMMENTS FOR DOCUMENTATION
-- ============================================================================
COMMENT ON TABLE users IS 'User authentication and account management';
COMMENT ON TABLE youth_profiles IS 'Youth profile and personal information';
COMMENT ON TABLE resources IS 'Borrowable resources and equipment inventory';
COMMENT ON TABLE borrow_records IS 'Records of borrowed resources and returns';
COMMENT ON TABLE events IS 'SK Events management table with image support';
COMMENT ON TABLE incidents IS 'Youth incident and support request tracking';
COMMENT ON TABLE printing_requests IS 'Document printing service requests';
COMMENT ON TABLE password_resets IS 'Password reset OTP requests';
COMMENT ON TABLE admins IS 'Admin accounts and access control';
