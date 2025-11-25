-- COMPREHENSIVE Fixed version of alter_database_setup.sql
-- Includes ALL tables, columns, views, procedures, triggers, and events
-- Safe for fresh installation or existing database (idempotent)

USE identify_db;

-- after you ran the database_setup.sql file in the sql editor, run this file to alter everything in the database

-- run this file to add the status column to the patients table
ALTER TABLE patients
ADD COLUMN status ENUM('Approved', 'Disapproved', 'Pending') NOT NULL DEFAULT 'Pending';
ALTER TABLE patients
  ADD COLUMN gender VARCHAR(10) AFTER first_name,
  ADD COLUMN birth_date DATE AFTER gender;
  ALTER TABLE patients ADD COLUMN nickname VARCHAR(100) AFTER first_name;
  ALTER TABLE patients ADD middle_initial VARCHAR(10) AFTER first_name;


-- run this file to add the first_name and last_name columns to the patient_pir table
ALTER TABLE patient_pir
ADD COLUMN first_name VARCHAR(100),
ADD COLUMN last_name VARCHAR(100);

-- run this for patient_health
ALTER TABLE patient_health
  ADD COLUMN last_medical_physical_yes BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN physician_name_addr_yes   BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE dental_examination
  ADD COLUMN checked_by_ci INT NULL COMMENT 'FK â†’ users.id (Clinical Instructor)',
  ADD CONSTRAINT fk_checked_ci
      FOREIGN KEY (checked_by_ci) REFERENCES users(id)
      ON UPDATE CASCADE
      ON DELETE SET NULL,
  ADD INDEX idx_checked_ci (checked_by_ci);

-- column already exists, but make sure it is NOT NULLable:
ALTER TABLE dental_examination
  MODIFY checked_by_ci INT NULL;

ALTER TABLE informed_consent
  MODIFY consent_treatment     VARCHAR(20),
  MODIFY consent_drugs         VARCHAR(20),
  MODIFY consent_changes       VARCHAR(20),
  MODIFY consent_radiographs   VARCHAR(20),
  MODIFY consent_removal_teeth VARCHAR(20),
  MODIFY consent_crowns        VARCHAR(20),
  MODIFY consent_dentures      VARCHAR(20),
  MODIFY consent_fillings      VARCHAR(20),
  MODIFY consent_guarantee     VARCHAR(20);

ALTER TABLE patient_pir
  ADD COLUMN thumbmark VARCHAR(255) NULL COMMENT 'Base-64 PNG or file path';

ALTER TABLE patient_health
    ADD COLUMN last_medical_physical_no      BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN abnormal_bleeding_yes         BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN thirsty                       BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN abnormal_bleeding_no          BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN bruise_easily_yes             BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN bruise_easily_no              BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN blood_transfusion_yes         BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN blood_transfusion_no          BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN blood_disorder_yes            BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN blood_disorder_no             BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN head_neck_radiation_yes       BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN head_neck_radiation_no        BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE dental_examination
  ADD COLUMN tooth_chart_photo LONGBLOB NULL COMMENT 'Original tooth-chart photo (JPG/PNG)',
  MODIFY tooth_chart_drawing LONGTEXT NULL COMMENT 'Base-64 PNG drawing overlay';

ALTER TABLE dental_examination
  ADD COLUMN tooth_chart_drawing_path VARCHAR(255) NULL
  COMMENT 'relative path to the saved drawing PNG';

/* Run once in MySQL */
ALTER TABLE patient_pir
  MODIFY patient_signature LONGBLOB NULL COMMENT 'PNG bytes';

ALTER TABLE patient_health
  MODIFY patient_signature LONGBLOB NULL;

ALTER TABLE dental_examination
  MODIFY patient_signature LONGBLOB NULL;

ALTER TABLE informed_consent
  MODIFY patient_signature LONGBLOB NULL;

ALTER TABLE progress_notes
  MODIFY patient_signature LONGBLOB NULL;

/* revert to file-path instead of BLOB */
ALTER TABLE patient_pir
  MODIFY patient_signature VARCHAR(255) NULL COMMENT 'path to PNG';

ALTER TABLE patient_health
  MODIFY patient_signature VARCHAR(255) NULL;

ALTER TABLE dental_examination
  MODIFY patient_signature VARCHAR(255) NULL;

ALTER TABLE informed_consent
  MODIFY patient_signature VARCHAR(255) NULL;

ALTER TABLE progress_notes
  MODIFY patient_signature VARCHAR(255) NULL;

ALTER TABLE informed_consent
  MODIFY patient_signature LONGTEXT NULL COMMENT 'base-64 PNG',
  MODIFY witness_signature LONGTEXT NULL COMMENT 'base-64 PNG';

ALTER TABLE informed_consent
  MODIFY patient_signature VARCHAR(255) NULL COMMENT 'path to PNG',
  MODIFY witness_signature VARCHAR(255) NULL COMMENT 'path to PNG';

/* 1. widen the column to hold a plain name, no longer a file path */
ALTER TABLE informed_consent
  MODIFY witness_signature VARCHAR(100) NULL COMMENT 'Witness full name';

ALTER TABLE informed_consent MODIFY witness_signature VARCHAR(255) NULL;

ALTER TABLE informed_consent ADD COLUMN data_privacy_date DATE NULL AFTER data_privacy_patient_sign;

-- Add a new column to store the path for the new signature drawing
ALTER TABLE informed_consent 
ADD COLUMN data_privacy_signature_path LONGTEXT NULL COMMENT 'Path to data privacy signature image' AFTER data_privacy_signature;

-- (Optional but recommended) Update the comment on the old column for clarity
ALTER TABLE informed_consent 
MODIFY COLUMN data_privacy_signature VARCHAR(255) NULL COMMENT 'Printed name for data privacy section';

ALTER TABLE informed_consent
  ADD COLUMN consent_endodontics VARCHAR(20) NULL COMMENT 'Initial for endodontics consent',
  ADD COLUMN consent_periodontal VARCHAR(20) NULL COMMENT 'Initial for periodontal consent';

ALTER TABLE users
ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = inactive';

ALTER TABLE users
ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT 'User status (active/inactive)';

-- Migration: Update periodontal examination fields to use ENUM types for radio button implementation
-- Date: 2025-08-19
-- Purpose: Change periodontal fields from VARCHAR to ENUM to support radio button selection

-- Drop the old VARCHAR columns that will be replaced with proper structure
ALTER TABLE patient_health
  DROP COLUMN IF EXISTS perio_gingiva,
  DROP COLUMN IF EXISTS perio_healthy,
  DROP COLUMN IF EXISTS perio_inflamed,
  DROP COLUMN IF EXISTS perio_degree_inflame,
  DROP COLUMN IF EXISTS perio_mild,
  DROP COLUMN IF EXISTS perio_moderate,
  DROP COLUMN IF EXISTS perio_severe,
  DROP COLUMN IF EXISTS perio_deposits,
  DROP COLUMN IF EXISTS perio_light,
  DROP COLUMN IF EXISTS perio_mod_deposits,
  DROP COLUMN IF EXISTS perio_heavy;

-- Add new columns with proper ENUM types for radio button selection
ALTER TABLE patient_health
  ADD COLUMN perio_gingiva_status ENUM('Healthy', 'Inflamed') NULL COMMENT 'Gingiva status',
  ADD COLUMN perio_inflammation_degree ENUM('Mild', 'Moderate', 'Severe') NULL COMMENT 'Degree of inflammation',
  ADD COLUMN perio_deposits_degree ENUM('Light', 'Moderate', 'Heavy') NULL COMMENT 'Degree of deposits';

-- Keep the perio_other field for additional notes (already exists as VARCHAR)
-- No changes needed for perio_other field

ALTER TABLE patient_pir ADD COLUMN medications_taken TEXT, ADD COLUMN allergies TEXT, ADD COLUMN past_illnesses TEXT, ADD COLUMN last_physician_exam TEXT, ADD COLUMN hospitalization TEXT, ADD COLUMN bleeding_tendencies TEXT, ADD COLUMN female_specific TEXT, ADD COLUMN systems_summary TEXT;

ALTER TABLE patient_pir MODIFY COLUMN clinic ENUM('I','II','III','IV') DEFAULT NULL;

-- Migration script to add created_by field to patients table
-- Run this script in your MySQL database to enable Clinician patient filtering

-- Step 1: Check if column already exists, if not add it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'patients' 
    AND COLUMN_NAME = 'created_by'
);

-- Add the created_by column if it doesn't exist
SET @sql = CASE 
    WHEN @column_exists = 0 THEN 
        'ALTER TABLE patients ADD COLUMN created_by INT NULL COMMENT "ID of the user who created this patient"'
    ELSE 
        'SELECT "Column created_by already exists" AS message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add foreign key constraint if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'patients' 
    AND CONSTRAINT_NAME = 'fk_patients_created_by'
);

SET @sql = CASE 
    WHEN @fk_exists = 0 AND @column_exists = 0 THEN 
        'ALTER TABLE patients ADD CONSTRAINT fk_patients_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    ELSE 
        'SELECT "Foreign key constraint already exists or column was not created" AS message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Add index if it doesn't exist
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'patients' 
    AND INDEX_NAME = 'idx_created_by'
);

SET @sql = CASE 
    WHEN @idx_exists = 0 AND @column_exists = 0 THEN 
        'ALTER TABLE patients ADD INDEX idx_created_by (created_by)'
    ELSE 
        'SELECT "Index already exists or column was not created" AS message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 4: Show final table structure
SELECT 'Migration completed. Current patients table structure:' AS message;
DESCRIBE patients;

-- Migration to add COD (Coordinator of Dental) role and patient assignment functionality
-- Run this script to add the new COD user type and assignment tracking

-- USE identify_db;

-- Add the patient assignments table to track COD assignments
CREATE TABLE IF NOT EXISTS patient_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    cod_user_id INT NOT NULL, -- COD who made the assignment
    clinical_instructor_id INT NULL, -- Clinical Instructor assigned to review
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    assignment_status ENUM('pending', 'accepted', 'completed', 'rejected') DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (cod_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (clinical_instructor_id) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for better performance
    INDEX idx_patient_assignments_patient (patient_id),
    INDEX idx_patient_assignments_cod (cod_user_id),
    INDEX idx_patient_assignments_ci (clinical_instructor_id),
    INDEX idx_patient_assignments_status (assignment_status)
);

-- Add approval tracking for Clinical Instructors
CREATE TABLE IF NOT EXISTS patient_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    assignment_id INT NOT NULL,
    clinical_instructor_id INT NOT NULL,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approval_notes TEXT,
    approved_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES patient_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (clinical_instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_patient_approvals_patient (patient_id),
    INDEX idx_patient_approvals_assignment (assignment_id),
    INDEX idx_patient_approvals_ci (clinical_instructor_id),
    INDEX idx_patient_approvals_status (approval_status),
    
    -- Ensure one approval per assignment
    UNIQUE KEY unique_assignment_approval (assignment_id)
);

-- Update the users table to support COD role (if not already done)
-- Check if we need to modify the role enum to include COD
SET @sql = (SELECT IF(
    (SELECT COUNT(*) 
     FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'users' 
     AND COLUMN_NAME = 'role'
     AND COLUMN_TYPE LIKE '%COD%') > 0,
    'SELECT "COD role already exists" as message',
    'ALTER TABLE users MODIFY COLUMN role ENUM("Admin", "Clinician", "Clinical Instructor", "COD") NOT NULL'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE users 
CHANGE COLUMN status account_status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';

-- Create sample COD user (you can modify or remove this)
INSERT IGNORE INTO users (username, full_name, email, password, role, account_status, is_active) 
VALUES ('cod_admin', 'COD Administrator', 'cod@example.com', 'password123', 'COD', 'active', 1);

-- Add trigger to automatically create assignment when patient is created by Clinician
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS after_patient_insert 
    AFTER INSERT ON patients 
    FOR EACH ROW 
BEGIN
    DECLARE clinician_role VARCHAR(50);
    
    -- Check if the creator is a Clinician
    SELECT role INTO clinician_role 
    FROM users 
    WHERE id = NEW.created_by;
    
    -- If created by Clinician, create a pending assignment record
    IF clinician_role = 'Clinician' THEN
        INSERT INTO patient_assignments (patient_id, cod_user_id, assignment_status, notes)
        SELECT NEW.id, u.id, 'pending', CONCAT('Auto-created assignment for patient created by clinician')
        FROM users u 
        WHERE u.role = 'COD' AND u.account_status = 'active' 
        LIMIT 1;
    END IF;
END$$

DELIMITER ;

-- Add some useful views for COD workflow

-- View for COD to see all patients needing assignment
CREATE OR REPLACE VIEW cod_pending_assignments AS
SELECT 
    p.id as patient_id,
    p.first_name,
    p.last_name,
    p.email,
    p.phone,
    p.status as patient_status,
    p.created_at as patient_created_at,
    u_clinician.full_name as created_by_clinician,
    pa.id as assignment_id,
    pa.assignment_status,
    pa.assigned_at,
    pa.notes as assignment_notes,
    u_ci.full_name as assigned_clinical_instructor
FROM patients p
JOIN users u_clinician ON p.created_by = u_clinician.id
LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
LEFT JOIN users u_ci ON pa.clinical_instructor_id = u_ci.id
WHERE u_clinician.role = 'Clinician'
ORDER BY p.created_at DESC;

-- View for Clinical Instructors to see their assigned patients
CREATE OR REPLACE VIEW clinical_instructor_assignments AS
SELECT 
    p.id as patient_id,
    p.first_name,
    p.last_name,
    p.email,
    p.phone,
    p.status as patient_status,
    p.created_at as patient_created_at,
    u_clinician.full_name as created_by_clinician,
    pa.id as assignment_id,
    pa.assignment_status,
    pa.assigned_at,
    pa.notes as assignment_notes,
    pa.clinical_instructor_id,
    papp.approval_status,
    papp.approval_notes,
    papp.approved_at
FROM patients p
JOIN users u_clinician ON p.created_by = u_clinician.id
JOIN patient_assignments pa ON p.id = pa.patient_id
LEFT JOIN patient_approvals papp ON pa.id = papp.assignment_id
WHERE pa.clinical_instructor_id IS NOT NULL
ORDER BY pa.assigned_at DESC;

-- Add indexes for better performance on existing tables
ALTER TABLE patients 
ADD INDEX IF NOT EXISTS idx_patients_created_by (created_by),
ADD INDEX IF NOT EXISTS idx_patients_status (status),
ADD INDEX IF NOT EXISTS idx_patients_created_at (created_at);

-- Add some sample data for testing (you can remove this section if not needed)
-- Insert sample Clinical Instructor if it doesn't exist
INSERT IGNORE INTO users (username, full_name, email, password, role, account_status, is_active) 
VALUES ('ci_instructor1', 'Dr. Clinical Instructor', 'ci@example.com', 'password123', 'Clinical Instructor', 'active', 1);

COMMIT;

-- Display success message
SELECT 'COD role and patient assignment functionality has been successfully added!' as migration_status;

-- =========================================================
-- Profile Feature Migration - Add profile_picture column
-- Date: 2025-09-21
-- Purpose: Add profile picture support to the users table
-- =========================================================

-- Add profile_picture column to users table
ALTER TABLE users 
ADD COLUMN profile_picture VARCHAR(255) NULL 
COMMENT 'Path to user profile picture stored in uploads/profile_photos/';

-- Add index for better performance when querying profile pictures
CREATE INDEX idx_users_profile_picture ON users(profile_picture);

-- Update the existing 'COD' enum value to match your role if needed
-- (Based on your codebase, it seems you use 'COD' role which is not in the original enum)
ALTER TABLE users 
MODIFY COLUMN role ENUM('Admin', 'Clinician', 'Clinical Instructor', 'COD') NOT NULL;

-- Optional: You can run this to verify the changes
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'users' 
-- AND TABLE_SCHEMA = DATABASE()
-- ORDER BY ORDINAL_POSITION;
-- Migration: Add hint columns for patient-Clinical Instructor matching
-- Date: 2025-09-21
-- Description: Adds treatment_hint to patients table and specialty_hint to users table

-- Add treatment_hint column to patients table
-- This will store what kind of treatment the patient needs (e.g., "Orthodontics", "Oral Surgery", "Periodontics", etc.)
ALTER TABLE `patients` 
ADD COLUMN `treatment_hint` VARCHAR(255) NULL DEFAULT NULL 
COMMENT 'Hint about what kind of treatment this patient needs for better Clinical Instructor assignment'
AFTER `status`;

-- Add specialty_hint column to users table  
-- This will store what kind of patients/treatments the Clinical Instructor accepts
ALTER TABLE `users` 
ADD COLUMN `specialty_hint` VARCHAR(255) NULL DEFAULT NULL 
COMMENT 'For Clinical Instructors: specialty/treatment type they accept (e.g., Orthodontics, Oral Surgery, etc.)'
AFTER `profile_picture`;

-- Optional: Update existing Clinical Instructors with default specialty hint
-- You can uncomment this if you want to set a default value for existing Clinical Instructors
-- UPDATE `users` 
-- SET `specialty_hint` = 'General Dentistry' 
-- WHERE `role` = 'Clinical Instructor' AND (`specialty_hint` IS NULL OR `specialty_hint` = '');

-- Verification queries (run these to verify the migration worked):
-- SELECT COUNT(*) as patients_with_treatment_hint FROM patients WHERE treatment_hint IS NOT NULL;
-- SELECT COUNT(*) as clinical_instructors_with_specialty FROM users WHERE role = 'Clinical Instructor' AND specialty_hint IS NOT NULL;
-- DESCRIBE patients;
-- DESCRIBE users;

-- SQL Script to Add Online/Offline Status Tracking to Users Table
-- Run this script in your MySQL database (identify_db)

USE identify_db;

-- Step 1: Rename the existing 'status' column to 'account_status' for clarity
-- This distinguishes between account status (active/inactive) and online status
-- Step 2: Add 'last_activity' column to track when user was last active
-- This will be updated every time user performs an action while logged in
ALTER TABLE users 
ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time user performed any action';

-- Step 3: Add 'connection_status' column to track login/logout status
-- This will be set to 'online' on login and 'offline' on logout
ALTER TABLE users 
ADD COLUMN connection_status ENUM('online', 'offline') NOT NULL DEFAULT 'offline' COMMENT 'Current connection status of user';

-- Step 4: Update existing users to have offline status initially
UPDATE users SET connection_status = 'offline';

-- Optional: View the updated table structure
-- DESCRIBE users;

-- The table now has:
-- - account_status: active/inactive (account enabled/disabled)
-- - connection_status: online/offline (currently logged in or not)  
-- - last_activity: timestamp of last action (for timeout detection)
-- Migration to fix Online Clinical Instructor synchronization issues
-- This addresses timezone problems and improves performance

-- 1. Set timezone to ensure consistency
SET time_zone = '+00:00'; -- Use UTC for consistency

-- 2. Add indexes for better performance on online status queries
CREATE INDEX IF NOT EXISTS idx_users_role_status ON users (role, account_status, connection_status);
CREATE INDEX IF NOT EXISTS idx_users_last_activity ON users (last_activity);
CREATE INDEX IF NOT EXISTS idx_patient_assignments_ci_status ON patient_assignments (clinical_instructor_id, assignment_status);

-- 3. Update any existing timestamps to ensure they're in UTC
-- (Only run this if you're having timezone issues)
-- UPDATE users SET last_activity = CONVERT_TZ(last_activity, @@session.time_zone, '+00:00') WHERE last_activity IS NOT NULL;

-- 4. Create a trigger to automatically update last_activity on any user table update
-- This ensures activity is always tracked properly
DELIMITER //
DROP TRIGGER IF EXISTS update_user_activity_trigger//
CREATE TRIGGER update_user_activity_trigger 
    BEFORE UPDATE ON users 
    FOR EACH ROW 
BEGIN
    -- Only update last_activity if the user is online and it's not being explicitly set
    IF NEW.connection_status = 'online' AND OLD.last_activity = NEW.last_activity THEN
        SET NEW.last_activity = NOW();
    END IF;
END//
DELIMITER ;

-- 5. Add a stored procedure to clean up offline users
DELIMITER //
DROP PROCEDURE IF EXISTS CleanupOfflineUsers//
CREATE PROCEDURE CleanupOfflineUsers()
BEGIN
    -- Mark users as offline if they've been inactive for more than 5 minutes
    UPDATE users 
    SET connection_status = 'offline' 
    WHERE connection_status = 'online' 
      AND role = 'Clinical Instructor'
      AND account_status = 'active'
      AND last_activity IS NOT NULL 
      AND last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
      
    SELECT ROW_COUNT() as users_marked_offline;
END//
DELIMITER ;

-- 6. Create an event to automatically run cleanup every 2 minutes
-- (This requires the MySQL Event Scheduler to be enabled)
DROP EVENT IF EXISTS cleanup_offline_users_event;
CREATE EVENT cleanup_offline_users_event
ON SCHEDULE EVERY 2 MINUTE
DO
    CALL CleanupOfflineUsers();

-- 7. Ensure the event scheduler is enabled (you may need to run this manually)
-- SET GLOBAL event_scheduler = ON;

-- 8. Add a view for easily getting online CIs with proper timezone handling
DROP VIEW IF EXISTS online_clinical_instructors;
CREATE VIEW online_clinical_instructors AS
SELECT 
    u.id,
    u.full_name,
    u.email,
    u.specialty_hint,
    u.connection_status,
    u.last_activity,
    CASE 
        WHEN u.last_activity IS NULL THEN 'online'
        WHEN u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online'
        ELSE 'offline'
    END as computed_status,
    TIMESTAMPDIFF(MINUTE, u.last_activity, NOW()) as minutes_since_activity,
    COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) as current_patient_count
FROM users u
LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id 
WHERE u.role = 'Clinical Instructor' 
    AND u.account_status = 'active' 
    AND u.connection_status = 'online'
GROUP BY u.id, u.full_name, u.email, u.specialty_hint, u.connection_status, u.last_activity
ORDER BY current_patient_count ASC, u.full_name ASC;

-- 9. Insert some test data if needed (uncomment to use)
/*
-- Test data: Create a test CI user if none exists
INSERT IGNORE INTO users (username, full_name, email, password, role, account_status, connection_status, last_activity)
VALUES 
    ('test_ci', 'Test Clinical Instructor', 'test_ci@example.com', 'test123', 'Clinical Instructor', 'active', 'online', NOW()),
    ('test_ci2', 'Test CI Two', 'test_ci2@example.com', 'test123', 'Clinical Instructor', 'active', 'online', DATE_SUB(NOW(), INTERVAL 2 MINUTE));
*/

-- 10. Show the results
SELECT 'Migration completed successfully. Online CI system optimized.' as status;

-- Check the current state
SELECT 
    COUNT(*) as total_cis,
    SUM(CASE WHEN connection_status = 'online' THEN 1 ELSE 0 END) as online_cis,
    SUM(CASE WHEN connection_status = 'offline' THEN 1 ELSE 0 END) as offline_cis
FROM users 
WHERE role = 'Clinical Instructor' AND account_status = 'active';

-- Final comprehensive migration to fix Online Clinical Instructor issues
-- This fixes the display problems, timezone issues, and performance

-- ===================================
-- 1. BASIC SETUP AND CHARSET FIXES
-- ===================================

SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;
SET time_zone = SYSTEM;

-- Update charset for key columns to prevent collation errors
ALTER TABLE users 
MODIFY COLUMN role ENUM('Admin','Clinician','Clinical Instructor','COD') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
MODIFY COLUMN account_status ENUM('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
MODIFY COLUMN connection_status ENUM('online','offline') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- ===================================
-- 2. PERFORMANCE INDEXES
-- ===================================

CREATE INDEX IF NOT EXISTS idx_users_role_online ON users (role, account_status, connection_status);
CREATE INDEX IF NOT EXISTS idx_users_last_activity ON users (last_activity);
CREATE INDEX IF NOT EXISTS idx_patient_assignments_ci ON patient_assignments (clinical_instructor_id, assignment_status);

-- ===================================
-- 3. FIX EXISTING DATA
-- ===================================

-- Update all Clinical Instructors to have recent activity for testing
UPDATE users 
SET connection_status = 'online', 
    last_activity = NOW() 
WHERE role = 'Clinical Instructor' 
  AND account_status = 'active';

-- ===================================
-- 4. AUTOMATED CLEANUP PROCEDURES
-- ===================================

-- Create procedure to clean up offline users
DELIMITER //
DROP PROCEDURE IF EXISTS CleanupOfflineUsers//
CREATE PROCEDURE CleanupOfflineUsers()
BEGIN
    UPDATE users 
    SET connection_status = 'offline' 
    WHERE connection_status = 'online' 
      AND role = 'Clinical Instructor'
      AND account_status = 'active'
      AND last_activity IS NOT NULL 
      AND TIMESTAMPDIFF(MINUTE, last_activity, NOW()) > 5;
      
    SELECT ROW_COUNT() as users_marked_offline;
END//
DELIMITER ;

-- Create event to run cleanup every 2 minutes (optional)
SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS cleanup_offline_users_event;
CREATE EVENT IF NOT EXISTS cleanup_offline_users_event
ON SCHEDULE EVERY 2 MINUTE
DO
    CALL CleanupOfflineUsers();

-- ===================================
-- 5. VERIFICATION QUERIES
-- ===================================

-- Test the main query that the application uses
SELECT 'Testing main CI query...' as test_section;

SELECT 
    u.id,
    u.full_name,
    u.email,
    u.specialty_hint,
    u.connection_status,
    u.last_activity,
    COALESCE(pa_counts.current_patient_count, 0) as current_patient_count,
    TIMESTAMPDIFF(MINUTE, u.last_activity, NOW()) as minutes_since_activity
FROM users u
LEFT JOIN (
    SELECT 
        clinical_instructor_id,
        COUNT(*) as current_patient_count
    FROM patient_assignments 
    WHERE assignment_status IN ('accepted', 'pending')
    GROUP BY clinical_instructor_id
) pa_counts ON u.id = pa_counts.clinical_instructor_id
WHERE u.role = 'Clinical Instructor' 
    AND u.account_status = 'active' 
    AND u.connection_status = 'online'
ORDER BY current_patient_count ASC, u.full_name ASC;

-- ===================================
-- 6. FINAL STATUS REPORT
-- ===================================

SELECT 'MIGRATION COMPLETED SUCCESSFULLY!' as status;

SELECT 
    'Online CI System Status' as report_section,
    COUNT(*) as total_clinical_instructors,
    SUM(CASE WHEN connection_status = 'online' THEN 1 ELSE 0 END) as currently_online,
    SUM(CASE WHEN connection_status = 'offline' THEN 1 ELSE 0 END) as currently_offline,
    NOW() as current_database_time
FROM users 
WHERE role = 'Clinical Instructor' AND account_status = 'active';

-- Show which CIs should be visible in the interface
SELECT 
    'CIs that should appear in interface' as report_section,
    id,
    full_name,
    email,
    connection_status,
    last_activity,
    TIMESTAMPDIFF(MINUTE, last_activity, NOW()) as minutes_since_activity,
    CASE 
        WHEN TIMESTAMPDIFF(MINUTE, last_activity, NOW()) <= 5 THEN 'WILL SHOW'
        ELSE 'WILL HIDE'
    END as interface_visibility
FROM users 
WHERE role = 'Clinical Instructor' 
    AND account_status = 'active' 
    AND connection_status = 'online'
ORDER BY full_name;

-- ===================================
-- TROUBLESHOOTING NOTES:
-- ===================================
/*
If CIs are still not appearing:

1. Check if event scheduler is enabled:
   SHOW VARIABLES LIKE 'event_scheduler';

2. Manually update a CI to be online:
   UPDATE users SET connection_status = 'online', last_activity = NOW() 
   WHERE id = [CI_ID];

3. Check timezone consistency:
   SELECT NOW(), UTC_TIMESTAMP();

4. Test the PHP function by accessing:
   http://yoursite.com/test_api.php

5. Check browser console for JavaScript errors

6. Verify the COD user can access api_online_ci.php
*/

-- Migration: Fix Online Clinical Instructors Synchronization
-- Date: 2025-10-04
-- Description: Adds indexes and procedures to improve connection_status management

USE identify_db;

-- ========================================================
-- 1. Add indexes for better query performance
-- ========================================================

-- Check if index exists before creating
SET @index_exists = (
    SELECT COUNT(1) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'identify_db' 
    AND TABLE_NAME = 'users' 
    AND INDEX_NAME = 'idx_role_connection_status'
);

SET @create_index = IF(@index_exists = 0, 
    'CREATE INDEX idx_role_connection_status ON users(role, connection_status, account_status, last_activity)',
    'SELECT "Index idx_role_connection_status already exists"'
);

PREPARE stmt FROM @create_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================================
-- 2. Create or update stored procedure to cleanup offline users
-- ========================================================

DROP PROCEDURE IF EXISTS CleanupInactiveUsers;

DELIMITER //

CREATE PROCEDURE CleanupInactiveUsers(IN inactive_minutes INT)
BEGIN
    -- Mark users as offline if they haven't been active for the specified minutes
    -- Default to 10 minutes if not specified
    SET inactive_minutes = COALESCE(inactive_minutes, 10);
    
    UPDATE users 
    SET connection_status = 'offline' 
    WHERE connection_status = 'online' 
      AND last_activity IS NOT NULL 
      AND TIMESTAMPDIFF(MINUTE, last_activity, NOW()) > inactive_minutes;
      
    -- Return count of users marked offline
    SELECT ROW_COUNT() as users_marked_offline;
END //

DELIMITER ;

-- ========================================================
-- 3. Create procedure to force all Clinical Instructors online (for testing)
-- ========================================================

DROP PROCEDURE IF EXISTS SetAllCIsOnline;

DELIMITER //

CREATE PROCEDURE SetAllCIsOnline()
BEGIN
    UPDATE users 
    SET connection_status = 'online', 
        last_activity = NOW() 
    WHERE role = 'Clinical Instructor' 
      AND account_status = 'active';
      
    -- Return count of users marked online
    SELECT ROW_COUNT() as cis_marked_online;
END //

DELIMITER ;

-- ========================================================
-- 4. Create procedure to get online CIs count
-- ========================================================

DROP PROCEDURE IF EXISTS GetOnlineCIsCount;

DELIMITER //

CREATE PROCEDURE GetOnlineCIsCount()
BEGIN
    SELECT 
        COUNT(*) as online_count,
        GROUP_CONCAT(full_name SEPARATOR ', ') as online_ci_names
    FROM users 
    WHERE role = 'Clinical Instructor' 
      AND account_status = 'active'
      AND connection_status = 'online';
END //

DELIMITER ;

-- ========================================================
-- 5. Create view for easy monitoring of online CIs
-- ========================================================

DROP VIEW IF EXISTS v_online_clinical_instructors;

CREATE VIEW v_online_clinical_instructors AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.email,
    u.specialty_hint,
    u.connection_status,
    u.last_activity,
    TIMESTAMPDIFF(MINUTE, u.last_activity, NOW()) as minutes_since_activity,
    COALESCE(pa_counts.current_patient_count, 0) as current_patient_count
FROM users u
LEFT JOIN (
    SELECT 
        clinical_instructor_id,
        COUNT(*) as current_patient_count
    FROM patient_assignments 
    WHERE assignment_status IN ('accepted', 'pending')
    GROUP BY clinical_instructor_id
) pa_counts ON u.id = pa_counts.clinical_instructor_id
WHERE u.role = 'Clinical Instructor' 
  AND u.account_status = 'active'
  AND u.connection_status = 'online'
ORDER BY current_patient_count ASC, u.full_name ASC;

-- ========================================================
-- 6. Initialize all active Clinical Instructors to online status (for migration)
-- ========================================================

-- Set all active CIs to online with current timestamp
UPDATE users 
SET connection_status = 'online', 
    last_activity = NOW() 
WHERE role = 'Clinical Instructor' 
  AND account_status = 'active'
  AND connection_status IS NULL OR connection_status = '';

-- ========================================================
-- 7. Display migration results
-- ========================================================

SELECT 'Migration completed successfully!' as status;

-- Show current online CIs
SELECT 
    id, 
    full_name, 
    email, 
    connection_status, 
    last_activity,
    TIMESTAMPDIFF(MINUTE, last_activity, NOW()) as minutes_since_activity
FROM users 
WHERE role = 'Clinical Instructor' 
  AND account_status = 'active'
ORDER BY connection_status DESC, last_activity DESC;

-- ========================================================
-- USAGE EXAMPLES:
-- ========================================================

-- To cleanup inactive users (mark as offline if inactive for 10+ minutes):
-- CALL CleanupInactiveUsers(10);

-- To force all CIs online (useful for testing):
-- CALL SetAllCIsOnline();

-- To get count of online CIs:
-- CALL GetOnlineCIsCount();

-- To view all online CIs with patient counts:
-- SELECT * FROM v_online_clinical_instructors;

-- Migration: Automatic Patient Assignment to Clinical Instructors
-- Date: 2025-10-04
-- Description: Implements automatic patient assignment without manual actions

USE identify_db;

-- ========================================================
-- 1. Create stored procedure for automatic assignment
-- ========================================================

DROP PROCEDURE IF EXISTS AutoAssignPatientToCI;

DELIMITER //

CREATE PROCEDURE AutoAssignPatientToCI(
    IN p_patient_id INT,
    IN p_treatment_hint VARCHAR(255)
)
proc_label: BEGIN
    DECLARE v_best_ci_id INT;
    DECLARE v_ci_name VARCHAR(100);
    DECLARE v_ci_specialty VARCHAR(255);
    DECLARE v_ci_patient_count INT;
    DECLARE v_assignment_exists INT DEFAULT 0;
    DECLARE v_assignment_id INT;
    DECLARE v_auto_notes TEXT;
    
    -- Check if patient is already assigned
    SELECT COUNT(*), id INTO v_assignment_exists, v_assignment_id
    FROM patient_assignments 
    WHERE patient_id = p_patient_id
    LIMIT 1;
    
    -- Skip if already assigned
    IF v_assignment_exists > 0 THEN
        -- Update assignment status to ensure it's accepted
        UPDATE patient_assignments 
        SET assignment_status = 'accepted', 
            updated_at = NOW()
        WHERE id = v_assignment_id;
        
        -- Log instead of returning result (triggers can't return result sets)
        -- SELECT CONCAT('Patient already assigned (Assignment ID: ', v_assignment_id, ')') as result;
        LEAVE proc_label;
    END IF;
    
    -- Try to find CI with matching specialty (if treatment hint provided)
    IF p_treatment_hint IS NOT NULL AND p_treatment_hint != '' THEN
        SELECT 
            u.id,
            u.full_name,
            u.specialty_hint,
            COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) as patient_count
        INTO v_best_ci_id, v_ci_name, v_ci_specialty, v_ci_patient_count
        FROM users u
        LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
        WHERE u.role = 'Clinical Instructor'
            AND u.account_status = 'active'
            AND u.connection_status = 'online'
            AND u.specialty_hint IS NOT NULL
            AND (
                LOWER(u.specialty_hint) LIKE LOWER(CONCAT('%', p_treatment_hint, '%')) OR
                LOWER(p_treatment_hint) LIKE LOWER(CONCAT('%', u.specialty_hint, '%'))
            )
        GROUP BY u.id, u.full_name, u.specialty_hint
        ORDER BY patient_count ASC, RAND()
        LIMIT 1;
    END IF;
    
    -- If no matching specialty found, get any online CI with least patients
    IF v_best_ci_id IS NULL THEN
        SELECT 
            u.id,
            u.full_name,
            u.specialty_hint,
            COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) as patient_count
        INTO v_best_ci_id, v_ci_name, v_ci_specialty, v_ci_patient_count
        FROM users u
        LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
        WHERE u.role = 'Clinical Instructor'
            AND u.account_status = 'active'
            AND u.connection_status = 'online'
        GROUP BY u.id, u.full_name, u.specialty_hint
        ORDER BY patient_count ASC, RAND()
        LIMIT 1;
    END IF;
    
    -- If still no CI found, fail gracefully
    IF v_best_ci_id IS NULL THEN
        -- Log instead of returning result (triggers can't return result sets)
        -- SELECT 'No online Clinical Instructors available for automatic assignment.' as result;
        LEAVE proc_label;
    END IF;
    
    -- Prepare assignment notes
    SET v_auto_notes = CONCAT('Auto-assigned to CI with lowest patient count (', v_ci_patient_count, ' patients)');
    IF v_ci_specialty IS NOT NULL AND p_treatment_hint IS NOT NULL AND p_treatment_hint != '' THEN
        SET v_auto_notes = CONCAT(v_auto_notes, '. Specialty match: ', v_ci_specialty, ' for ', p_treatment_hint);
    END IF;
    
    -- Create new assignment (using COD user ID = 1 as system user)
    INSERT INTO patient_assignments 
    (patient_id, cod_user_id, clinical_instructor_id, assignment_status, notes, assigned_at, created_at, updated_at)
    VALUES (p_patient_id, 1, v_best_ci_id, 'accepted', v_auto_notes, NOW(), NOW(), NOW());
    
    SET v_assignment_id = LAST_INSERT_ID();
    
    -- Create approval record
    INSERT INTO patient_approvals 
    (patient_id, assignment_id, clinical_instructor_id, approval_status, created_at, updated_at)
    VALUES (p_patient_id, v_assignment_id, v_best_ci_id, 'pending', NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        clinical_instructor_id = v_best_ci_id,
        approval_status = 'pending',
        updated_at = NOW();
    
    -- Success (no return statement needed - triggers can't return result sets)
    -- SELECT CONCAT('Patient automatically assigned to ', v_ci_name, ' (ID: ', v_best_ci_id, ')') as result;
    
END //

DELIMITER ;

-- ========================================================
-- 2. Create trigger for automatic assignment on patient creation
-- ========================================================

-- Drop existing trigger if exists
DROP TRIGGER IF EXISTS trg_auto_assign_patient_after_insert;

DELIMITER //

CREATE TRIGGER trg_auto_assign_patient_after_insert
AFTER INSERT ON patients
FOR EACH ROW
BEGIN
    DECLARE v_treatment_hint VARCHAR(255);
    
    -- Get treatment hint from new patient record
    SET v_treatment_hint = NEW.treatment_hint;
    
    -- Call auto-assignment procedure with a slight delay to ensure patient is fully created
    -- Using a separate stored procedure allows for better error handling
    CALL AutoAssignPatientToCI(NEW.id, v_treatment_hint);
    
END //

DELIMITER ;

-- ========================================================
-- 3. Create trigger for automatic re-assignment on status change
-- ========================================================

-- This trigger reassigns if a patient's assignment is cancelled or CI becomes unavailable
DROP TRIGGER IF EXISTS trg_auto_reassign_on_status_change;

DELIMITER //

CREATE TRIGGER trg_auto_reassign_on_status_change
AFTER UPDATE ON patient_assignments
FOR EACH ROW
BEGIN
    DECLARE v_treatment_hint VARCHAR(255);
    
    -- If assignment status changes to 'cancelled' or 'rejected', auto-reassign
    IF OLD.assignment_status != 'cancelled' AND NEW.assignment_status = 'cancelled' THEN
        -- Get treatment hint from patient record
        SELECT treatment_hint INTO v_treatment_hint
        FROM patients 
        WHERE id = NEW.patient_id
        LIMIT 1;
        
        -- Call auto-assignment procedure
        CALL AutoAssignPatientToCI(NEW.patient_id, v_treatment_hint);
    END IF;
    
END //

DELIMITER ;

-- ========================================================
-- 4. Auto-assign any existing unassigned patients
-- ========================================================

-- Create temporary procedure for one-time assignment of existing patients
DROP PROCEDURE IF EXISTS AssignAllUnassignedPatients;

DELIMITER //

CREATE PROCEDURE AssignAllUnassignedPatients()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_patient_id INT;
    DECLARE v_treatment_hint VARCHAR(255);
    DECLARE cur CURSOR FOR 
        SELECT p.id, p.treatment_hint
        FROM patients p
        LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
        WHERE pa.id IS NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_patient_id, v_treatment_hint;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Auto-assign each unassigned patient
        CALL AutoAssignPatientToCI(v_patient_id, v_treatment_hint);
    END LOOP;
    
    CLOSE cur;
    
    SELECT 'All unassigned patients have been auto-assigned.' as result;
END //

DELIMITER ;

-- Execute the one-time assignment
CALL AssignAllUnassignedPatients();

-- ========================================================
-- 5. Create view for monitoring automatic assignments
-- ========================================================

DROP VIEW IF EXISTS v_auto_assignment_stats;

CREATE VIEW v_auto_assignment_stats AS
SELECT 
    DATE(pa.assigned_at) as assignment_date,
    COUNT(*) as total_assignments,
    COUNT(CASE WHEN pa.notes LIKE '%Auto-assigned%' THEN 1 END) as auto_assignments,
    COUNT(CASE WHEN pa.notes NOT LIKE '%Auto-assigned%' THEN 1 END) as manual_assignments,
    GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') as assigned_cis
FROM patient_assignments pa
LEFT JOIN users u ON pa.clinical_instructor_id = u.id
WHERE pa.assigned_at IS NOT NULL
GROUP BY DATE(pa.assigned_at)
ORDER BY assignment_date DESC;

-- ========================================================
-- 6. Display migration results
-- ========================================================

SELECT 'âœ… Migration completed successfully!' as status;

-- Show auto-assignment statistics
SELECT * FROM v_auto_assignment_stats LIMIT 10;

-- Show current assignments
SELECT 
    p.id as patient_id,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    p.treatment_hint,
    u.full_name as assigned_ci,
    pa.assignment_status,
    pa.assigned_at,
    CASE 
        WHEN pa.notes LIKE '%Auto-assigned%' THEN 'Auto'
        ELSE 'Manual'
    END as assignment_type
FROM patients p
LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
LEFT JOIN users u ON pa.clinical_instructor_id = u.id
ORDER BY pa.assigned_at DESC
LIMIT 10;

-- ========================================================
-- USAGE NOTES:
-- ========================================================

-- To manually trigger auto-assignment for a specific patient:
-- CALL AutoAssignPatientToCI(patient_id, 'treatment_hint');

-- To assign all unassigned patients:
-- CALL AssignAllUnassignedPatients();

-- To view assignment statistics:
-- SELECT * FROM v_auto_assignment_stats;

-- To disable auto-assignment (drop trigger):
-- DROP TRIGGER IF EXISTS trg_auto_assign_patient_after_insert;

-- To re-enable auto-assignment (recreate trigger - see above)

-- ========================================================
-- FINAL MIGRATION: Fix Trigger Result Set Error
-- Date: 2025-10-04
-- Issue: "Not allowed to return a result set from a trigger"
-- Solution: Remove SELECT result statements from stored procedures
-- ========================================================

USE identify_db;

-- ========================================================
-- 1. Drop and recreate AutoAssignPatientToCI (FINAL VERSION)
-- ========================================================

DROP PROCEDURE IF EXISTS AutoAssignPatientToCI;

DELIMITER //

CREATE PROCEDURE AutoAssignPatientToCI(
    IN p_patient_id INT,
    IN p_treatment_hint VARCHAR(255)
)
proc_label: BEGIN
    DECLARE v_best_ci_id INT;
    DECLARE v_ci_name VARCHAR(100);
    DECLARE v_ci_specialty VARCHAR(255);
    DECLARE v_ci_patient_count INT DEFAULT 0;
    DECLARE v_assignment_exists INT DEFAULT 0;
    DECLARE v_assignment_id INT;
    DECLARE v_auto_notes TEXT;
    DECLARE v_cod_user_id INT;
    
    -- Get a COD user for assignments
    SELECT MIN(id) INTO v_cod_user_id
    FROM users 
    WHERE role = 'COD' AND account_status = 'active';
    
    -- Default to user ID 1 if no COD found
    SET v_cod_user_id = IFNULL(v_cod_user_id, 1);
    
    -- Check if patient has existing assignment
    SELECT COUNT(*) INTO v_assignment_exists
    FROM patient_assignments 
    WHERE patient_id = p_patient_id;
    
    -- If assignment exists but no CI assigned, update it
    IF v_assignment_exists > 0 THEN
        SELECT COUNT(*) INTO v_assignment_exists
        FROM patient_assignments 
        WHERE patient_id = p_patient_id 
        AND clinical_instructor_id IS NOT NULL;
        
        -- If CI is already assigned, skip
        IF v_assignment_exists > 0 THEN
            LEAVE proc_label;
        END IF;
    END IF;
    
    -- Find CI with matching specialty (if treatment hint provided)
    SET v_best_ci_id = NULL;
    IF p_treatment_hint IS NOT NULL AND p_treatment_hint != '' THEN
        SELECT 
            u.id,
            u.full_name,
            u.specialty_hint,
            COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END)
        INTO v_best_ci_id, v_ci_name, v_ci_specialty, v_ci_patient_count
        FROM users u
        LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
        WHERE u.role = 'Clinical Instructor'
            AND u.account_status = 'active'
            AND u.connection_status = 'online'
            AND u.specialty_hint IS NOT NULL
            AND (
                LOWER(u.specialty_hint) LIKE LOWER(CONCAT('%', p_treatment_hint, '%')) OR
                LOWER(p_treatment_hint) LIKE LOWER(CONCAT('%', u.specialty_hint, '%'))
            )
        GROUP BY u.id, u.full_name, u.specialty_hint
        ORDER BY COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) ASC, RAND()
        LIMIT 1;
    END IF;
    
    -- If no specialty match, get any online CI with least patients
    IF v_best_ci_id IS NULL THEN
        SELECT 
            u.id,
            u.full_name,
            u.specialty_hint,
            COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END)
        INTO v_best_ci_id, v_ci_name, v_ci_specialty, v_ci_patient_count
        FROM users u
        LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
        WHERE u.role = 'Clinical Instructor'
            AND u.account_status = 'active'
            AND u.connection_status = 'online'
        GROUP BY u.id, u.full_name, u.specialty_hint
        ORDER BY COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) ASC, RAND()
        LIMIT 1;
    END IF;
    
    -- If no online CI found, exit gracefully
    IF v_best_ci_id IS NULL THEN
        LEAVE proc_label;
    END IF;
    
    -- Prepare assignment notes
    SET v_auto_notes = CONCAT('Auto-assigned to CI with lowest patient count (', v_ci_patient_count, ' patients)');
    IF v_ci_specialty IS NOT NULL AND p_treatment_hint IS NOT NULL AND p_treatment_hint != '' THEN
        SET v_auto_notes = CONCAT(v_auto_notes, '. Specialty match: ', v_ci_specialty, ' for ', p_treatment_hint);
    END IF;
    
    -- Update existing assignment or create new one
    IF EXISTS (SELECT 1 FROM patient_assignments WHERE patient_id = p_patient_id) THEN
        -- Update existing assignment
        UPDATE patient_assignments 
        SET clinical_instructor_id = v_best_ci_id,
            assignment_status = 'accepted',
            notes = v_auto_notes,
            assigned_at = NOW(),
            updated_at = NOW()
        WHERE patient_id = p_patient_id;
        
        SELECT id INTO v_assignment_id FROM patient_assignments WHERE patient_id = p_patient_id LIMIT 1;
    ELSE
        -- Create new assignment
        INSERT INTO patient_assignments 
        (patient_id, cod_user_id, clinical_instructor_id, assignment_status, notes, assigned_at, created_at, updated_at)
        VALUES (p_patient_id, v_cod_user_id, v_best_ci_id, 'accepted', v_auto_notes, NOW(), NOW(), NOW());
        
        SET v_assignment_id = LAST_INSERT_ID();
    END IF;
    
    -- Create or update approval record
    INSERT INTO patient_approvals 
    (patient_id, assignment_id, clinical_instructor_id, approval_status, created_at, updated_at)
    VALUES (p_patient_id, v_assignment_id, v_best_ci_id, 'pending', NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        clinical_instructor_id = v_best_ci_id,
        approval_status = 'pending',
        updated_at = NOW();
    
END //

DELIMITER ;

-- ========================================================
-- 2. Fix AssignAllUnassignedPatients (remove result sets)
-- ========================================================

DROP PROCEDURE IF EXISTS AssignAllUnassignedPatients;

DELIMITER //

CREATE PROCEDURE AssignAllUnassignedPatients()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_patient_id INT;
    DECLARE v_treatment_hint VARCHAR(255);
    DECLARE cur CURSOR FOR 
        SELECT p.id, p.treatment_hint
        FROM patients p
        LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
        WHERE pa.clinical_instructor_id IS NULL OR pa.id IS NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_patient_id, v_treatment_hint;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        CALL AutoAssignPatientToCI(v_patient_id, v_treatment_hint);
    END LOOP;
    
    CLOSE cur;
    
END //

DELIMITER ;

-- ========================================================
-- 3. Fix any existing incomplete assignments
-- ========================================================

-- Update existing incomplete assignments
CALL AssignAllUnassignedPatients();

-- ========================================================
-- 4. Test the fix
-- ========================================================

-- Show current assignments status
SELECT 
    p.id as patient_id,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    p.treatment_hint,
    IFNULL(u.full_name, 'UNASSIGNED') as assigned_ci,
    pa.assignment_status,
    CASE 
        WHEN pa.notes LIKE '%Auto-assigned%' THEN 'Auto'
        ELSE 'Manual'
    END as assignment_type
FROM patients p
LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
LEFT JOIN users u ON pa.clinical_instructor_id = u.id
ORDER BY p.id DESC
LIMIT 10;

-- ========================================================
-- 5. Verification
-- ========================================================

SELECT 'âœ… MIGRATION COMPLETED SUCCESSFULLY!' as status;
SELECT 'Trigger error should now be fixed. Try creating a new patient as Clinician.' as instruction;

-- Show procedure status
SELECT 
    ROUTINE_NAME as procedure_name,
    CREATED,
    LAST_ALTERED
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = 'identify_db'
AND ROUTINE_NAME IN ('AutoAssignPatientToCI', 'AssignAllUnassignedPatients')
ORDER BY ROUTINE_NAME;

-- Show trigger status
SELECT 
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    ACTION_TIMING
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE EVENT_OBJECT_TABLE = 'patients'
AND TRIGGER_SCHEMA = 'identify_db'
ORDER BY TRIGGER_NAME;

-- ========================================================
-- SIMPLE Migration: Fix Assignment Status for Clinical Instructors
-- Date: 2025-10-04
-- Purpose: Update existing auto-accepted assignments to pending
-- ========================================================

-- STEP 1: Make sure you select "identify_db" database in phpMyAdmin first!

-- ========================================================
-- 1. Update existing auto-accepted assignments to pending
-- ========================================================

UPDATE patient_assignments 
SET assignment_status = 'pending',
    updated_at = NOW()
WHERE assignment_status = 'accepted'
  AND clinical_instructor_id IS NOT NULL
  AND notes NOT LIKE '%CI Response:%';

-- Show how many were updated
SELECT CONCAT('Updated ', ROW_COUNT(), ' assignments to pending status') as result;

-- ========================================================
-- 2. Clean up orphaned approval records
-- ========================================================

-- Create a temporary table with IDs to delete
CREATE TEMPORARY TABLE temp_approvals_to_delete AS
SELECT pa.id as approval_id
FROM patient_approvals pa
INNER JOIN patient_assignments pas ON pa.assignment_id = pas.id
WHERE pas.assignment_status = 'pending';

-- Delete the orphaned approvals
DELETE FROM patient_approvals
WHERE id IN (SELECT approval_id FROM temp_approvals_to_delete);

-- Show how many were deleted
SELECT CONCAT('Cleaned up ', ROW_COUNT(), ' orphaned approval records') as result;

-- Drop the temporary table
DROP TEMPORARY TABLE IF EXISTS temp_approvals_to_delete;

-- ========================================================
-- 3. Create monitoring view
-- ========================================================

DROP VIEW IF EXISTS v_assignment_status_summary;

CREATE VIEW v_assignment_status_summary AS
SELECT 
    u.full_name as clinical_instructor,
    u.email as ci_email,
    COUNT(CASE WHEN pa.assignment_status = 'pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN pa.assignment_status = 'accepted' THEN 1 END) as accepted_count,
    COUNT(CASE WHEN pa.assignment_status = 'rejected' THEN 1 END) as rejected_count,
    COUNT(CASE WHEN pa.assignment_status = 'completed' THEN 1 END) as completed_count,
    COUNT(*) as total_assignments
FROM users u
LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
WHERE u.role = 'Clinical Instructor'
  AND u.account_status = 'active'
GROUP BY u.id, u.full_name, u.email
ORDER BY pending_count DESC, u.full_name ASC;

-- ========================================================
-- 4. Show results
-- ========================================================

SELECT 'âœ… Migration completed successfully!' as status;

-- Show current assignment status distribution
SELECT 
    assignment_status,
    COUNT(*) as count
FROM patient_assignments
GROUP BY assignment_status
ORDER BY assignment_status;

-- Show pending assignments that need CI action
SELECT 
    COUNT(*) as pending_assignments_needing_action
FROM patient_assignments
WHERE assignment_status = 'pending'
  AND clinical_instructor_id IS NOT NULL;

-- Show the summary view
SELECT * FROM v_assignment_status_summary;

-- ========================================================
-- Migration: Fix AutoAssignPatientToCI Procedure
-- Date: 2025-10-04
-- Purpose: Update the stored procedure to set assignment_status 
--          to 'pending' instead of 'accepted' for auto-assignments
-- ========================================================

-- IMPORTANT: Make sure you've selected the identify_db database in phpMyAdmin first!

-- ========================================================
-- 1. Drop and recreate AutoAssignPatientToCI with 'pending' status
-- ========================================================

DROP PROCEDURE IF EXISTS AutoAssignPatientToCI;

DELIMITER //

CREATE PROCEDURE AutoAssignPatientToCI(
    IN p_patient_id INT,
    IN p_treatment_hint VARCHAR(255)
)
proc_label: BEGIN
    DECLARE v_best_ci_id INT;
    DECLARE v_ci_name VARCHAR(100);
    DECLARE v_ci_specialty VARCHAR(255);
    DECLARE v_ci_patient_count INT DEFAULT 0;
    DECLARE v_assignment_exists INT DEFAULT 0;
    DECLARE v_assignment_id INT;
    DECLARE v_auto_notes TEXT;
    DECLARE v_cod_user_id INT;
    
    -- Get a COD user for assignments
    SELECT MIN(id) INTO v_cod_user_id
    FROM users 
    WHERE role = 'COD' AND account_status = 'active';
    
    -- Default to user ID 1 if no COD found
    SET v_cod_user_id = IFNULL(v_cod_user_id, 1);
    
    -- Check if patient has existing assignment
    SELECT COUNT(*) INTO v_assignment_exists
    FROM patient_assignments 
    WHERE patient_id = p_patient_id;
    
    -- If assignment exists but no CI assigned, update it
    IF v_assignment_exists > 0 THEN
        SELECT COUNT(*) INTO v_assignment_exists
        FROM patient_assignments 
        WHERE patient_id = p_patient_id 
        AND clinical_instructor_id IS NOT NULL;
        
        -- If CI is already assigned, skip
        IF v_assignment_exists > 0 THEN
            LEAVE proc_label;
        END IF;
    END IF;
    
    -- Find CI with matching specialty (if treatment hint provided)
    SET v_best_ci_id = NULL;
    IF p_treatment_hint IS NOT NULL AND p_treatment_hint != '' THEN
        SELECT 
            u.id,
            u.full_name,
            u.specialty_hint,
            COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END)
        INTO v_best_ci_id, v_ci_name, v_ci_specialty, v_ci_patient_count
        FROM users u
        LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
        WHERE u.role = 'Clinical Instructor'
            AND u.account_status = 'active'
            AND u.connection_status = 'online'
            AND u.specialty_hint IS NOT NULL
            AND (
                LOWER(u.specialty_hint) LIKE LOWER(CONCAT('%', p_treatment_hint, '%')) OR
                LOWER(p_treatment_hint) LIKE LOWER(CONCAT('%', u.specialty_hint, '%'))
            )
        GROUP BY u.id, u.full_name, u.specialty_hint
        ORDER BY COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) ASC, RAND()
        LIMIT 1;
    END IF;
    
    -- If no specialty match, get any online CI with least patients
    IF v_best_ci_id IS NULL THEN
        SELECT 
            u.id,
            u.full_name,
            u.specialty_hint,
            COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END)
        INTO v_best_ci_id, v_ci_name, v_ci_specialty, v_ci_patient_count
        FROM users u
        LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
        WHERE u.role = 'Clinical Instructor'
            AND u.account_status = 'active'
            AND u.connection_status = 'online'
        GROUP BY u.id, u.full_name, u.specialty_hint
        ORDER BY COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) ASC, RAND()
        LIMIT 1;
    END IF;
    
    -- If no online CI found, exit gracefully
    IF v_best_ci_id IS NULL THEN
        LEAVE proc_label;
    END IF;
    
    -- Prepare assignment notes
    SET v_auto_notes = CONCAT('Auto-assigned to CI with lowest patient count (', v_ci_patient_count, ' patients)');
    IF v_ci_specialty IS NOT NULL AND p_treatment_hint IS NOT NULL AND p_treatment_hint != '' THEN
        SET v_auto_notes = CONCAT(v_auto_notes, '. Specialty match: ', v_ci_specialty, ' for ', p_treatment_hint);
    END IF;
    
    -- Update existing assignment or create new one WITH 'pending' STATUS
    IF EXISTS (SELECT 1 FROM patient_assignments WHERE patient_id = p_patient_id) THEN
        -- Update existing assignment with PENDING status
        UPDATE patient_assignments 
        SET clinical_instructor_id = v_best_ci_id,
            assignment_status = 'pending',  -- CHANGED FROM 'accepted' TO 'pending'
            notes = v_auto_notes,
            assigned_at = NOW(),
            updated_at = NOW()
        WHERE patient_id = p_patient_id;
        
        SELECT id INTO v_assignment_id FROM patient_assignments WHERE patient_id = p_patient_id LIMIT 1;
    ELSE
        -- Create new assignment with PENDING status
        INSERT INTO patient_assignments 
        (patient_id, cod_user_id, clinical_instructor_id, assignment_status, notes, assigned_at, created_at, updated_at)
        VALUES (p_patient_id, v_cod_user_id, v_best_ci_id, 'pending', v_auto_notes, NOW(), NOW(), NOW());  -- CHANGED FROM 'accepted' TO 'pending'
        
        SET v_assignment_id = LAST_INSERT_ID();
    END IF;
    
    -- DO NOT create approval record until CI accepts the assignment
    -- The approval record will be created when CI accepts via updateAssignmentStatus function
    
END //

DELIMITER ;

-- ========================================================
-- 2. Verification
-- ========================================================

SELECT 'âœ… AutoAssignPatientToCI procedure updated successfully!' as status;
SELECT 'New patients will now be assigned with PENDING status' as change_description;
SELECT 'Procedure has been updated - test by adding a new patient' as next_step;

-- ========================================================
-- NOTES:
-- ========================================================
/*
This migration updates the AutoAssignPatientToCI stored procedure to:

1. Set assignment_status to 'pending' instead of 'accepted' (lines 124 and 132)
2. Remove automatic creation of approval records (they'll be created when CI accepts)

After running this migration:
- New patients will be auto-assigned to online CIs with 'pending' status
- CIs must accept the assignment before it becomes 'accepted'
- The workflow matches the manual COD assignment workflow

The trigger trg_auto_assign_patient_after_insert will continue to work,
but now it assigns with 'pending' status instead of 'accepted'.
*/

-- =========================================================
-- Migration: Add Patient Transfer Functionality for Clinical Instructors
-- Date: 2025-10-04
-- Description: Allows Clinical Instructors to transfer assigned patients to other Clinical Instructors
-- =========================================================

USE identify_db;

-- =========================================================
-- 1. Create patient_transfers table to track transfer requests
-- =========================================================

CREATE TABLE IF NOT EXISTS patient_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    assignment_id INT NOT NULL,
    from_clinical_instructor_id INT NOT NULL,
    to_clinical_instructor_id INT NOT NULL,
    transfer_status ENUM('pending', 'accepted', 'rejected', 'cancelled') DEFAULT 'pending',
    transfer_reason TEXT,
    response_notes TEXT,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES patient_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (from_clinical_instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_clinical_instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes for better performance
    INDEX idx_patient_transfers_patient (patient_id),
    INDEX idx_patient_transfers_assignment (assignment_id),
    INDEX idx_patient_transfers_from_ci (from_clinical_instructor_id),
    INDEX idx_patient_transfers_to_ci (to_clinical_instructor_id),
    INDEX idx_patient_transfers_status (transfer_status),
    INDEX idx_patient_transfers_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2. Create view for monitoring patient transfers
-- =========================================================

DROP VIEW IF EXISTS v_patient_transfer_requests;

CREATE VIEW v_patient_transfer_requests AS
SELECT 
    pt.id as transfer_id,
    pt.patient_id,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    p.email as patient_email,
    p.treatment_hint,
    pt.assignment_id,
    pt.from_clinical_instructor_id,
    u_from.full_name as from_ci_name,
    u_from.email as from_ci_email,
    pt.to_clinical_instructor_id,
    u_to.full_name as to_ci_name,
    u_to.email as to_ci_email,
    pt.transfer_status,
    pt.transfer_reason,
    pt.response_notes,
    pt.requested_at,
    pt.responded_at,
    pa.assignment_status as current_assignment_status
FROM patient_transfers pt
JOIN patients p ON pt.patient_id = p.id
JOIN users u_from ON pt.from_clinical_instructor_id = u_from.id
JOIN users u_to ON pt.to_clinical_instructor_id = u_to.id
JOIN patient_assignments pa ON pt.assignment_id = pa.id
ORDER BY pt.requested_at DESC;

-- =========================================================
-- 3. Create stored procedure to handle transfer acceptance
-- =========================================================

DROP PROCEDURE IF EXISTS AcceptPatientTransfer;

DELIMITER //

CREATE PROCEDURE AcceptPatientTransfer(
    IN p_transfer_id INT,
    IN p_response_notes TEXT
)
BEGIN
    DECLARE v_patient_id INT;
    DECLARE v_assignment_id INT;
    DECLARE v_from_ci_id INT;
    DECLARE v_to_ci_id INT;
    DECLARE v_transfer_status VARCHAR(20);
    DECLARE v_current_assignment_status VARCHAR(20);
    
    -- Get transfer details
    SELECT 
        patient_id, 
        assignment_id, 
        from_clinical_instructor_id, 
        to_clinical_instructor_id,
        transfer_status
    INTO 
        v_patient_id, 
        v_assignment_id, 
        v_from_ci_id, 
        v_to_ci_id,
        v_transfer_status
    FROM patient_transfers
    WHERE id = p_transfer_id;
    
    -- Check if transfer is still pending
    IF v_transfer_status != 'pending' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Transfer request is no longer pending';
    END IF;
    
    -- Get current assignment status
    SELECT assignment_status INTO v_current_assignment_status
    FROM patient_assignments
    WHERE id = v_assignment_id;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Update transfer status to accepted
    UPDATE patient_transfers
    SET transfer_status = 'accepted',
        response_notes = p_response_notes,
        responded_at = NOW(),
        updated_at = NOW()
    WHERE id = p_transfer_id;
    
    -- Update the patient assignment to new CI
    UPDATE patient_assignments
    SET clinical_instructor_id = v_to_ci_id,
        notes = CONCAT(IFNULL(notes, ''), '\n\nTransferred from CI ', v_from_ci_id, ' to CI ', v_to_ci_id, ' on ', NOW()),
        updated_at = NOW()
    WHERE id = v_assignment_id;
    
    -- Update patient approvals if they exist
    UPDATE patient_approvals
    SET clinical_instructor_id = v_to_ci_id,
        approval_status = 'pending',
        updated_at = NOW()
    WHERE assignment_id = v_assignment_id;
    
    COMMIT;
    
END //

DELIMITER ;

-- =========================================================
-- 4. Create stored procedure to handle transfer rejection
-- =========================================================

DROP PROCEDURE IF EXISTS RejectPatientTransfer;

DELIMITER //

CREATE PROCEDURE RejectPatientTransfer(
    IN p_transfer_id INT,
    IN p_response_notes TEXT
)
BEGIN
    DECLARE v_transfer_status VARCHAR(20);
    
    -- Get transfer status
    SELECT transfer_status INTO v_transfer_status
    FROM patient_transfers
    WHERE id = p_transfer_id;
    
    -- Check if transfer is still pending
    IF v_transfer_status != 'pending' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Transfer request is no longer pending';
    END IF;
    
    -- Update transfer status to rejected
    UPDATE patient_transfers
    SET transfer_status = 'rejected',
        response_notes = p_response_notes,
        responded_at = NOW(),
        updated_at = NOW()
    WHERE id = p_transfer_id;
    
END //

DELIMITER ;

-- =========================================================
-- 5. Create stored procedure to cancel transfer request
-- =========================================================

DROP PROCEDURE IF EXISTS CancelPatientTransfer;

DELIMITER //

CREATE PROCEDURE CancelPatientTransfer(
    IN p_transfer_id INT,
    IN p_from_ci_id INT
)
BEGIN
    DECLARE v_transfer_status VARCHAR(20);
    DECLARE v_from_ci INT;
    
    -- Get transfer details
    SELECT transfer_status, from_clinical_instructor_id 
    INTO v_transfer_status, v_from_ci
    FROM patient_transfers
    WHERE id = p_transfer_id;
    
    -- Verify the CI making the request is the sender
    IF v_from_ci != p_from_ci_id THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Only the requesting CI can cancel this transfer';
    END IF;
    
    -- Check if transfer is still pending
    IF v_transfer_status != 'pending' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Only pending transfers can be cancelled';
    END IF;
    
    -- Update transfer status to cancelled
    UPDATE patient_transfers
    SET transfer_status = 'cancelled',
        responded_at = NOW(),
        updated_at = NOW()
    WHERE id = p_transfer_id;
    
END //

DELIMITER ;

-- =========================================================
-- 6. Display migration results
-- =========================================================

SELECT 'âœ… Patient Transfer functionality has been successfully added!' as migration_status;

-- Show table structure
DESCRIBE patient_transfers;

-- Show created procedures
SELECT 
    ROUTINE_NAME as procedure_name,
    CREATED,
    LAST_ALTERED
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = 'identify_db'
AND ROUTINE_NAME IN ('AcceptPatientTransfer', 'RejectPatientTransfer', 'CancelPatientTransfer')
ORDER BY ROUTINE_NAME;

-- =========================================================
-- USAGE NOTES:
-- =========================================================

/*
This migration adds the ability for Clinical Instructors to transfer patients:

1. Transfer Request Creation:
   - A CI with an assigned patient can initiate a transfer to another CI
   - Transfer request is created with 'pending' status

2. Transfer Request Response:
   - Receiving CI can accept or reject the transfer
   - CALL AcceptPatientTransfer(transfer_id, 'response notes');
   - CALL RejectPatientTransfer(transfer_id, 'response notes');

3. Transfer Request Cancellation:
   - Sending CI can cancel their own pending transfer
   - CALL CancelPatientTransfer(transfer_id, ci_id);

4. Automatic Updates:
   - When accepted: patient_assignments.clinical_instructor_id updated
   - When accepted: patient_approvals.clinical_instructor_id updated to new CI
   - Transfer history is preserved in patient_transfers table

5. View Transfer Requests:
   - SELECT * FROM v_patient_transfer_requests;
   - Shows all transfer requests with patient and CI details

Database changes:
- New table: patient_transfers
- New view: v_patient_transfer_requests  
- New stored procedures: AcceptPatientTransfer, RejectPatientTransfer, CancelPatientTransfer
*/
-- Migration: Add procedure_logs table for Clinician procedure logging
-- Date: 2025-10-05
-- Description: Creates table to store clinician procedure logs with patient and treatment plan information

CREATE TABLE IF NOT EXISTS `procedure_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `clinician_id` INT NOT NULL,
    `patient_name` VARCHAR(255) NOT NULL COMMENT 'Full name of patient at time of procedure',
    `age` INT NULL COMMENT 'Patient age at time of procedure',
    `sex` VARCHAR(10) NULL COMMENT 'Patient sex/gender',
    `procedure_selected` TEXT NULL COMMENT 'Selected treatment plan from dental_examination.assessment_plan_json',
    `procedure_details` VARCHAR(255) NULL COMMENT 'Additional procedure details/hint from patients.treatment_hint',
    `clinician_name` VARCHAR(255) NOT NULL COMMENT 'Full name of clinician who performed procedure',
    `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`clinician_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    
    -- Indexes for better performance
    INDEX `idx_procedure_logs_patient` (`patient_id`),
    INDEX `idx_procedure_logs_clinician` (`clinician_id`),
    INDEX `idx_procedure_logs_logged_at` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Stores clinician procedure logs with patient and treatment information';

-- Verification query
SELECT 'procedure_logs table created successfully!' AS message;
DESCRIBE procedure_logs;

-- Migration: Add chair_number column to procedure_logs table
-- Date: 2025-10-05
-- Description: Adds chair_number field to store the dental chair number where the procedure was performed

-- Add chair_number column to procedure_logs table
ALTER TABLE `procedure_logs` 
ADD COLUMN `chair_number` VARCHAR(50) NULL 
COMMENT 'Dental chair number where procedure was performed'
AFTER `procedure_details`;

-- Verification query
SELECT 'chair_number column added successfully!' AS message;
DESCRIBE procedure_logs;

-- Migration: Add status and remarks columns to procedure_logs table
-- Date: 2025-10-05
-- Description: Adds status tracking and remarks field for admin procedure log reporting

-- Add status column to track procedure completion status
ALTER TABLE `procedure_logs` 
ADD COLUMN `status` VARCHAR(50) NULL DEFAULT 'Completed'
COMMENT 'Status of the procedure (Completed, In Progress, etc.)'
AFTER `chair_number`;

-- Add remarks column to store additional notes from progress_notes
ALTER TABLE `procedure_logs` 
ADD COLUMN `remarks` TEXT NULL
COMMENT 'Remarks or additional notes about the procedure'
AFTER `status`;

-- Verification query
SELECT 'status and remarks columns added successfully!' AS message;
DESCRIBE procedure_logs;

-- =========================================================
-- Migration: Add Patient Status Calculation Support
-- Date: 2025-11-23
-- Description: Optimize database for patient status calculation
--              based on progress notes dates
-- =========================================================

USE identify_db;

-- Add index on progress_notes for better performance when calculating patient status
-- This index will speed up queries that need to find the most recent progress note per patient
CREATE INDEX IF NOT EXISTS idx_progress_notes_patient_date 
ON progress_notes(patient_id, date DESC);

-- =========================================================
-- Patient Status Calculation Logic (Documentation)
-- =========================================================
-- 
-- Patient Status is calculated dynamically based on progress notes:
-- 
-- INACTIVE: Last progress note date is older than 1 year from current date
--           Formula: MAX(progress_notes.date) < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
-- 
-- ACTIVE: Last progress note is within 1 year OR patient has no progress notes
--         Formula: MAX(progress_notes.date) IS NULL 
--                  OR MAX(progress_notes.date) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
-- 
-- Example Query:
-- SELECT 
--     p.id,
--     p.first_name,
--     p.last_name,
--     CASE 
--         WHEN MAX(pn.date) IS NULL THEN 'Active'
--         WHEN MAX(pn.date) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 'Active'
--         ELSE 'Inactive'
--     END AS patient_status
-- FROM patients p
-- LEFT JOIN progress_notes pn ON p.id = pn.patient_id
-- GROUP BY p.id;
-- 
-- =========================================================

-- Verification: Show sample patient status calculation
SELECT 
    'Sample Patient Status Calculation' as info,
    COUNT(*) as total_patients,
    SUM(CASE WHEN patient_status = 'Active' THEN 1 ELSE 0 END) as active_patients,
    SUM(CASE WHEN patient_status = 'Inactive' THEN 1 ELSE 0 END) as inactive_patients
FROM (
    SELECT 
        p.id,
        CASE 
            WHEN MAX(pn.date) IS NULL THEN 'Active'
            WHEN MAX(pn.date) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 'Active'
            ELSE 'Inactive'
        END AS patient_status
    FROM patients p
    LEFT JOIN progress_notes pn ON p.id = pn.patient_id
    GROUP BY p.id
) AS patient_status_summary;

SELECT 'Migration completed successfully!' as status;

-- =========================================================
-- Migration: Add Progress Notes Auto-Generated Columns
-- Description: Support auto-generated progress notes from procedure logs
-- =========================================================

USE identify_db;

-- Add columns to support auto-generated progress notes from procedure logs
-- Check if column exists before adding to avoid errors
SET @dbname = DATABASE();
SET @tablename = 'progress_notes';
SET @columnname1 = 'auto_generated';
SET @columnname2 = 'procedure_log_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname1)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname1, ' TINYINT(1) NULL DEFAULT 0 COMMENT ''1 if this progress note was auto-generated from a procedure log''')
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add procedure_log_id column
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname2)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname2, ' INT(11) NULL DEFAULT NULL COMMENT ''Reference to the procedure log that generated this progress note''')
));

PREPARE alterIfNotExists2 FROM @preparedStatement;
EXECUTE alterIfNotExists2;
DEALLOCATE PREPARE alterIfNotExists2;

-- Add index for procedure_log_id
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (index_name = 'idx_procedure_log_id')
  ) > 0,
  'SELECT 1',
  CONCAT('CREATE INDEX idx_procedure_log_id ON ', @tablename, ' (procedure_log_id)')
));

PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- 1.  Link progress_notes ↔ procedure_logs on procedure_log_id
--     (already exists, just add index for speed)
ALTER TABLE progress_notes
ADD INDEX idx_proc_log_id (procedure_log_id);
-- 2.  View that returns the latest remark per procedure_log
DROP VIEW IF EXISTS v_proc_latest_remark;
CREATE VIEW v_proc_latest_remark AS
SELECT  procedure_log_id,
remarks  -- the remark text written by CI
FROM    (
SELECT  procedure_log_id,
remarks,
ROW_NUMBER() OVER (PARTITION BY procedure_log_id
ORDER BY id DESC) AS rn
FROM    progress_notes
WHERE   procedure_log_id IS NOT NULL
AND   remarks IS NOT NULL
AND   remarks != ''
) AS t
WHERE   rn = 1;

-- =========================================================
-- Migration: Create Procedure Assignments Table
-- Description: Track procedure assignments from COD to Clinical Instructors
-- =========================================================

CREATE TABLE IF NOT EXISTS `procedure_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `procedure_log_id` int(11) NOT NULL COMMENT 'Reference to procedure_logs table',
  `cod_user_id` int(11) DEFAULT NULL COMMENT 'COD user who made the assignment',
  `clinical_instructor_id` int(11) NOT NULL COMMENT 'Clinical Instructor assigned to review the procedure',
  `assignment_status` enum('pending','accepted','rejected','completed') NOT NULL DEFAULT 'pending' COMMENT 'Status of the assignment',
  `notes` text DEFAULT NULL COMMENT 'Assignment notes from COD or CI',
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the procedure was assigned',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `procedure_log_id` (`procedure_log_id`),
  KEY `clinical_instructor_id` (`clinical_instructor_id`),
  KEY `cod_user_id` (`cod_user_id`),
  KEY `assignment_status` (`assignment_status`),
  CONSTRAINT `fk_procedure_assignments_procedure_log` FOREIGN KEY (`procedure_log_id`) REFERENCES `procedure_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_procedure_assignments_ci` FOREIGN KEY (`clinical_instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_procedure_assignments_cod` FOREIGN KEY (`cod_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks procedure assignments to Clinical Instructors';

-- Add index for faster queries
CREATE INDEX idx_procedure_assignments_status_ci ON procedure_assignments(clinical_instructor_id, assignment_status);

