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
