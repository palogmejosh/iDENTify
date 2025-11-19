# Log Procedure Feature - Implementation Guide

## Overview
This feature adds a new "Log Procedure" tab page exclusively for Clinician user-role. Clinicians can:
1. Select a patient from their own patient list
2. View auto-filled patient information (name, age, sex, procedure details/hint)
3. Select a treatment plan from the patient's dental examination record
4. Submit the procedure log with their clinician name

## Files Created/Modified

### New Files Created:
1. **migrations/add_procedure_logs_table.sql** - Database migration to create the `procedure_logs` table
2. **clinician_log_procedure.php** - Main page for logging procedures (Clinician-only access)
3. **get_treatment_plans.php** - AJAX endpoint to fetch treatment plans for selected patient
4. **save_procedure_log.php** - Backend handler to save procedure logs to database
5. **README_LOG_PROCEDURE.md** - This documentation file

### Files Modified:
1. **patients.php** - Added "Log Procedure" menu item to sidebar for Clinicians
2. **dashboard.php** - Added "Log Procedure" menu item to sidebar for Clinicians
3. **profile.php** - Added "Log Procedure" menu item to sidebar for Clinicians
4. **settings.php** - Added "Log Procedure" menu item to sidebar for Clinicians

## Installation Steps

### Step 1: Run Database Migration
Execute the SQL migration file in your MySQL database to create the `procedure_logs` table:

```bash
# Option 1: Using MySQL command line
mysql -u root -p identify_db < C:\xampp\htdocs\iDENTify\migrations\add_procedure_logs_table.sql

# Option 2: Using phpMyAdmin
# 1. Open phpMyAdmin (http://localhost/phpmyadmin)
# 2. Select the 'identify_db' database
# 3. Click on the 'SQL' tab
# 4. Copy and paste the contents of migrations/add_procedure_logs_table.sql
# 5. Click 'Go' to execute
```

### Step 2: Verify Database Table
After running the migration, verify the table was created:

```sql
-- Run this query in phpMyAdmin or MySQL command line:
DESCRIBE procedure_logs;

-- Expected output should show these columns:
-- id, patient_id, clinician_id, patient_name, age, sex, 
-- procedure_selected, procedure_details, clinician_name, 
-- logged_at, created_at, updated_at
```

### Step 3: Clear Browser Cache
Clear your browser cache or do a hard refresh (Ctrl+F5) to ensure the latest changes are loaded.

## Testing Instructions

### Prerequisites for Testing:
1. You must have a user account with the "Clinician" role
2. The clinician must have created at least one patient
3. The patient should have a dental examination record with treatment plans

### Test Scenario 1: Basic Flow
1. **Login as Clinician**
   - Navigate to: http://localhost/iDENTify/
   - Login with clinician credentials

2. **Access Log Procedure Page**
   - From dashboard or patients page, click "Log Procedure" in the sidebar
   - URL should be: http://localhost/iDENTify/clinician_log_procedure.php

3. **Select a Patient**
   - Click the "Select Patient" dropdown
   - You should see only patients created by you (the logged-in clinician)
   - Select a patient from the list

4. **Verify Auto-Fill**
   - Once patient is selected, the following fields should auto-populate:
     * Patient's Name (with middle initial if available)
     * Age
     * Sex/Gender
     * Procedure Details (Hint) - from patients.treatment_hint field

5. **Select Treatment Plan**
   - The "Select Treatment Plan/Procedure" dropdown should now be enabled
   - Treatment plans are loaded from dental_examination.assessment_plan_json
   - Each option shows: Treatment Plan (Tooth: #) - Diagnosis
   - If no treatment plans exist, it will show "No treatment plans found for this patient"

6. **Verify Clinician Name**
   - The "Clinician Name" field should be pre-filled with your full name
   - This field is read-only

7. **Submit the Log**
   - Select a treatment plan
   - Click "Submit Log"
   - You should be redirected back with a success message
   - Message should say: "Procedure logged successfully!"

### Test Scenario 2: Verify Database Entry
After submitting a procedure log, verify the data was saved:

```sql
-- Run in phpMyAdmin or MySQL command line:
SELECT * FROM procedure_logs ORDER BY logged_at DESC LIMIT 1;

-- Verify the following:
-- - patient_id matches the selected patient
-- - clinician_id matches your user ID
-- - patient_name is correctly formatted
-- - age and sex are populated
-- - procedure_selected contains the treatment plan details
-- - procedure_details contains the treatment hint
-- - clinician_name matches your full name
-- - logged_at timestamp is current
```

### Test Scenario 3: Security (Non-Clinician Access)
1. **Test with Clinical Instructor or COD role**
   - Login with a non-Clinician account
   - Try to access: http://localhost/iDENTify/clinician_log_procedure.php
   - You should be redirected to dashboard.php
   - "Log Procedure" menu should NOT appear in sidebar

2. **Test AJAX Endpoint Security**
   - Login as non-Clinician
   - Try to access: http://localhost/iDENTify/get_treatment_plans.php?patient_id=1
   - Response should be: {"success":false,"error":"Unauthorized"}

### Test Scenario 4: Edge Cases
1. **Patient with No Treatment Plans**
   - Select a patient who hasn't completed Step 3 (Dental Examination)
   - Treatment plan dropdown should show: "No treatment plans found for this patient"
   - Submit button should remain active (HTML5 required validation will prevent submission)

2. **Patient with Empty Treatment Plans**
   - If assessment_plan_json contains empty plan entries, they should be filtered out
   - Only non-empty treatment plans should appear in dropdown

3. **Form Validation**
   - Try to submit without selecting a patient (required field)
   - Try to submit without selecting a treatment plan (required field)
   - Browser should show validation errors

## Feature Details

### Database Schema: procedure_logs Table
```sql
- id: INT AUTO_INCREMENT PRIMARY KEY
- patient_id: INT NOT NULL (FK to patients.id)
- clinician_id: INT NOT NULL (FK to users.id)
- patient_name: VARCHAR(255) - Full name at time of procedure
- age: INT - Patient age at time of procedure
- sex: VARCHAR(10) - Patient gender
- procedure_selected: TEXT - Selected treatment plan with details
- procedure_details: VARCHAR(255) - Treatment hint from patients table
- clinician_name: VARCHAR(255) - Full name of clinician
- logged_at: TIMESTAMP - When procedure was logged
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

### Field Mappings:
| Form Field | Database Source | Database Target |
|------------|----------------|-----------------|
| Patient Selection | patients.id | procedure_logs.patient_id |
| Patient's Name | patients.first_name + middle_initial + last_name | procedure_logs.patient_name |
| Age | patients.age | procedure_logs.age |
| Sex | patients.gender | procedure_logs.sex |
| Procedure Details (Hint) | patients.treatment_hint | procedure_logs.procedure_details |
| Treatment Plan Selection | dental_examination.assessment_plan_json | procedure_logs.procedure_selected |
| Clinician Name | users.full_name (logged-in user) | procedure_logs.clinician_name |

### Access Control:
- **Page Access**: Only users with role = 'Clinician' can access clinician_log_procedure.php
- **Patient List**: Clinicians only see patients where patients.created_by = their user ID
- **Treatment Plans**: Clinicians can only load treatment plans for their own patients
- **Sidebar Menu**: "Log Procedure" menu only appears for Clinician role

### Auto-Fill Behavior:
When a patient is selected from the dropdown:
1. JavaScript extracts data attributes from the selected option
2. Patient information fields are populated automatically
3. An AJAX request is sent to get_treatment_plans.php
4. Treatment plans are parsed from dental_examination.assessment_plan_json
5. Dropdown is populated with formatted treatment plan options
6. Format: "Treatment Plan (Tooth: #) - Diagnosis [Phase]"

## Troubleshooting

### Issue: "Log Procedure" menu doesn't appear
**Solution**: 
- Verify you're logged in as a Clinician (not Clinical Instructor or COD)
- Clear browser cache (Ctrl+Shift+Delete)
- Check if role is correctly set in users table

### Issue: No patients in dropdown
**Solution**:
- Verify the clinician has created patients
- Check patients.created_by column matches your user ID
- Run: `SELECT * FROM patients WHERE created_by = YOUR_USER_ID;`

### Issue: No treatment plans in dropdown
**Solution**:
- Verify the patient has completed Step 3 (Dental Examination)
- Check dental_examination table: `SELECT assessment_plan_json FROM dental_examination WHERE patient_id = PATIENT_ID;`
- Ensure assessment_plan_json contains valid JSON with non-empty 'plan' fields

### Issue: "Database error" when submitting
**Solution**:
- Check error logs: `C:\xampp\htdocs\iDENTify\error_log` or PHP error log
- Verify procedure_logs table exists: `SHOW TABLES LIKE 'procedure_logs';`
- Check foreign key constraints are satisfied
- Verify clinician_id and patient_id are valid

### Issue: "Unauthorized" error
**Solution**:
- Verify you're logged in as a Clinician
- Check session is active: var_dump($_SESSION);
- Verify requireAuth() is working in config.php

## Future Enhancements (Optional)

### Possible Extensions:
1. **View Procedure History**
   - Add a page to view all logged procedures
   - Filter by date, patient, or treatment type
   - Export to PDF or CSV

2. **Edit/Delete Procedure Logs**
   - Allow clinicians to edit their logged procedures
   - Add soft delete functionality (mark as deleted vs. hard delete)

3. **Procedure Statistics**
   - Dashboard widget showing procedure counts
   - Most common procedures chart
   - Procedures per month graph

4. **Notifications**
   - Notify Clinical Instructor when procedure is logged
   - Email notifications for completed procedures

5. **Additional Fields**
   - Add notes field for additional procedure details
   - Add duration/time spent field
   - Add complications/observations field
   - Add follow-up date field

## Support

For issues or questions:
1. Check the Troubleshooting section above
2. Review PHP error logs in XAMPP
3. Check browser console for JavaScript errors (F12 > Console)
4. Verify database structure matches expected schema

## Summary

This implementation provides a complete, secure, and user-friendly interface for clinicians to log procedures. The feature:
- ✅ Is accessible only to Clinician user-role
- ✅ Shows only the clinician's own patients
- ✅ Auto-fills patient information from the database
- ✅ Loads treatment plans from dental examination records
- ✅ Validates all inputs before submission
- ✅ Stores complete procedure log information
- ✅ Integrates seamlessly with existing navigation
- ✅ Follows the existing design patterns and styling
- ✅ Does not disrupt other functionalities

The system is now ready for testing and production use!
