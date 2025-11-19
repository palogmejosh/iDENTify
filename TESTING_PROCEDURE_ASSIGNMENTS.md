# Testing Guide: Log Procedures Assignment Feature

## Overview
This feature allows COD users to assign logged procedures to Clinical Instructors for review and approval. It follows the same workflow as Patient Assignments with automatic assignment capabilities.

## Prerequisites
Before testing, ensure:
1. Database table `procedure_assignments` has been created (run `migration_procedure_assignments.sql`)
2. You have test accounts for each role:
   - Clinician (to log procedures)
   - Admin (to log procedures)
   - COD (to assign procedures)
   - Clinical Instructor (to accept/deny procedures)

## Test Flow

### Step 1: Log a Procedure (Clinician or Admin)
1. **Login as Clinician or Admin**
2. Navigate to **"Log Procedure"** from the sidebar
3. Select a patient from the dropdown
4. Choose a procedure/treatment plan
5. (Optional) Add procedure details and chair number
6. Select clinician name
7. Click **"Submit Log"**
8. ✅ Verify: Success message appears

### Step 2: View Logged Procedures (COD)
1. **Login as COD**
2. Navigate to **"Log Procedures Assignment"** from the sidebar (new tab)
3. ✅ Verify: You see the procedures logged in Step 1
4. ✅ Verify: Online Clinical Instructors section shows CIs currently online
5. ✅ Verify: Filters work (search, assignment status, date range)

### Step 3: Manual Assignment (COD)
1. Still logged in as COD on "Log Procedures Assignment" page
2. Find an unassigned procedure in the table
3. Click **"Manual Assign"** button
4. ✅ Verify: Modal opens with procedure details
5. Select a Clinical Instructor from the dropdown
6. (Optional) Add notes
7. Click **"Assign Procedure"**
8. ✅ Verify: Success message "Procedure assigned to Clinical Instructor successfully!"
9. ✅ Verify: Procedure status changes to "Pending Review"

### Step 4: Auto Assignment (COD)
1. Still logged in as COD on "Log Procedures Assignment" page
2. Find another unassigned procedure
3. Click **"Auto Assign"** button
4. Confirm the action
5. ✅ Verify: Success message "Procedure automatically assigned to {CI Name} successfully!"
6. ✅ Verify: System selects CI based on:
   - Specialty match (if procedure details match CI's specialty)
   - Lowest current workload
   - Online status

### Step 5: View Procedure Assignment (Clinical Instructor)
1. **Login as Clinical Instructor** (the one assigned in Step 3 or 4)
2. Navigate to **"Patient Assignments"** from the sidebar
3. ✅ Verify: Page title changed to "Patient & Procedure Assignments"
4. ✅ Verify: Two counters at top: "X Patient(s)" and "Y Procedure(s)"
5. ✅ Verify: Two tables displayed:
   - Pending Patient Assignment Requests (existing)
   - Pending Procedure Assignment Requests (new)
6. ✅ Verify: Assigned procedure appears in "Pending Procedure Assignment Requests" table

### Step 6: Accept Procedure Assignment (Clinical Instructor)
1. Still logged in as Clinical Instructor on "Patient Assignments" page
2. Find the pending procedure assignment
3. Click **"Review"** button
4. ✅ Verify: Modal opens with full procedure details:
   - Patient Name, Age/Sex
   - Procedure selected
   - Procedure details
   - Clinician who logged it
   - Logged date
   - Assignment notes (if any)
5. (Optional) Add notes about your decision
6. Click **"Accept Assignment"**
7. ✅ Verify: Success message "Procedure assignment has been accepted successfully!"
8. ✅ Verify: Row disappears from the table
9. ✅ Verify: Procedure counter decreases

### Step 7: Deny Procedure Assignment (Clinical Instructor)
1. Repeat Steps 3-4 to assign another procedure
2. Login as Clinical Instructor
3. Navigate to "Patient Assignments"
4. Click **"Review"** on a procedure
5. Click **"Deny Assignment"** instead
6. ✅ Verify: Success message "Procedure assignment has been denied successfully!"
7. ✅ Verify: Row disappears from the table
8. Go back to COD account
9. ✅ Verify: Procedure status shows "Rejected" and can be reassigned

### Step 8: Search and Filter (COD)
1. **Login as COD**
2. Navigate to "Log Procedures Assignment"
3. Test **Search** functionality:
   - Search by patient name ✅
   - Search by clinician name ✅
   - Search by procedure name ✅
4. Test **Assignment Status** filter:
   - All Status ✅
   - Unassigned ✅
   - Pending Review ✅
   - Accepted ✅
   - Rejected ✅
   - Completed ✅
5. Test **Date Range** filters:
   - Filter by date from ✅
   - Filter by date to ✅
6. Test **Pagination** (if more than 6 procedures)

### Step 9: Verify Assignment Status Flow (COD)
1. **Login as COD**
2. Navigate to "Log Procedures Assignment"
3. ✅ Verify each status means:
   - **Unassigned**: Not yet assigned to any CI (shows "Not Assigned")
   - **Pending Review**: Assigned to CI, waiting for acceptance (shows CI name)
   - **Accepted**: CI has accepted the assignment
   - **Rejected**: CI has denied the assignment (can be reassigned)
   - **Completed**: Procedure review completed

### Step 10: Online CI Status (COD)
1. **Login as COD**
2. Navigate to "Log Procedures Assignment"
3. Have another browser/device login as Clinical Instructor
4. ✅ Verify: CI appears in "Online Clinical Instructors" table
5. ✅ Verify: Shows CI's:
   - Name
   - Online status (green badge)
   - Specialty (if set)
   - Current workload (patient count with color coding)
6. Logout as CI on the other browser
7. Wait ~10 minutes or refresh
8. ✅ Verify: CI no longer shows in online list

## Edge Cases to Test

### Reassignment After Rejection
1. COD assigns procedure to CI_A
2. CI_A rejects it
3. ✅ Verify: COD can reassign to CI_B or use auto-assign again

### Multiple Procedures Same Patient
1. Log multiple procedures for same patient
2. ✅ Verify: Each procedure can be assigned independently
3. ✅ Verify: Same CI can have multiple procedures from same patient

### No Online CIs (Auto-Assign)
1. Ensure all CIs are offline
2. Try auto-assign
3. ✅ Verify: Error message "No Clinical Instructors are currently online"

### Specialty Matching
1. Set CI's specialty_hint (e.g., "Orthodontics")
2. Log procedure with matching details (e.g., "Braces Adjustment")
3. Use auto-assign
4. ✅ Verify: System prioritizes CI with matching specialty

### Admin Logging Procedures
1. Login as Admin
2. Navigate to "Log Procedure"
3. ✅ Verify: Admin can log procedures for ALL patients (not just own)
4. Complete procedure logging
5. ✅ Verify: Procedure appears in COD's assignment page

## Database Verification
After testing, verify database records:

```sql
-- Check procedure assignments
SELECT * FROM procedure_assignments ORDER BY assigned_at DESC LIMIT 10;

-- Check procedure logs
SELECT * FROM procedure_logs ORDER BY logged_at DESC LIMIT 10;

-- Verify foreign key constraints
SELECT 
    pa.id,
    pa.assignment_status,
    pl.patient_name,
    pl.procedure_selected,
    u_ci.full_name as assigned_to,
    u_cod.full_name as assigned_by
FROM procedure_assignments pa
LEFT JOIN procedure_logs pl ON pa.procedure_log_id = pl.id
LEFT JOIN users u_ci ON pa.clinical_instructor_id = u_ci.id
LEFT JOIN users u_cod ON pa.cod_user_id = u_cod.id
ORDER BY pa.assigned_at DESC;
```

## Known Behaviors
- Procedures can only be assigned once at a time (one CI per procedure)
- Rejected assignments can be reassigned to same or different CI
- Auto-assignment considers: online status, specialty match, current workload
- CIs must accept assignment before it appears in their completed work
- COD can view all logged procedures regardless of assignment status

## Troubleshooting

### Issue: Procedure not appearing in COD page
**Solution**: 
- Check if procedure was logged successfully in `procedure_logs` table
- Verify clinician account is active
- Check COD user has correct role in database

### Issue: Auto-assign not working
**Solution**:
- Verify at least one CI is online (logged in within last 5-10 minutes)
- Check CI account is active and has role 'Clinical Instructor'
- Review error message for specific issue

### Issue: CI not seeing assigned procedures
**Solution**:
- Verify assignment was created in `procedure_assignments` table
- Check CI is logged in with correct account
- Ensure assignment_status is 'pending'
- Clear browser cache and reload

### Issue: "Already Assigned" button disabled
**Solution**:
- This is expected behavior for procedures with status: accepted, completed
- Only unassigned, pending, or rejected procedures can be (re)assigned

## Success Criteria
✅ All test steps completed without errors
✅ Data flows correctly: Clinician → COD → CI
✅ Accept/Deny functionality works for procedures
✅ Auto-assignment intelligently distributes workload
✅ Manual assignment allows COD control
✅ Search and filters work correctly
✅ No disruption to existing patient assignment workflow
✅ Database integrity maintained (foreign keys, constraints)

## Report Issues
If you encounter any issues during testing:
1. Note the exact steps to reproduce
2. Check browser console for JavaScript errors
3. Check server logs for PHP errors
4. Verify database schema matches migration script
5. Document expected vs actual behavior
