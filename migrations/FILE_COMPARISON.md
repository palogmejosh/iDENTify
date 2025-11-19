# Migration Files Comparison

## 📁 Available SQL Migration Files

### ❌ `alter_database_setup.sql` (ORIGINAL - DO NOT USE)
**Status:** Broken  
**Issues:**
- Missing `USE identify_db;` statement at the beginning
- Will fail with "No database selected" errors
- Not safe to run

**When to use:** NEVER (keep for reference only)

---

### ⚠️ `alter_database_setup_fixed.sql` (BASIC - INCOMPLETE)
**Status:** Functional but incomplete  
**What it includes:**
- ✅ Basic table structure modifications
- ✅ New columns (status, gender, birth_date, etc.)
- ✅ Core tables (patient_assignments, patient_approvals, procedure_logs)
- ✅ Foreign keys and indexes

**What it's MISSING:**
- ❌ Stored Procedures (0 procedures)
- ❌ Triggers (0 triggers)
- ❌ Views (0 views)  
- ❌ Events (0 events)
- ❌ Auto-assignment functionality
- ❌ Online CI tracking system
- ❌ Patient transfer functionality

**When to use:** NOT RECOMMENDED - use complete version instead

---

### ✅ `alter_database_setup_complete.sql` (COMPLETE - RECOMMENDED)
**Status:** Fully functional and comprehensive  
**What it includes:**

#### Tables (All included)
- ✅ patient_assignments
- ✅ patient_approvals
- ✅ patient_transfers
- ✅ procedure_logs
- ✅ All column additions to existing tables

#### Stored Procedures (11 procedures)
1. **AutoAssignPatientToCI** - Automatically assigns patients to Clinical Instructors
2. **AssignAllUnassignedPatients** - Batch assignment for existing patients
3. **CleanupOfflineUsers** - Marks inactive users as offline
4. **CleanupInactiveUsers** - Cleanup with configurable timeout
5. **SetAllCIsOnline** - Testing procedure to mark all CIs online
6. **GetOnlineCIsCount** - Get count of online Clinical Instructors
7. **AcceptPatientTransfer** - Handle patient transfer acceptance
8. **RejectPatientTransfer** - Handle patient transfer rejection
9. **CancelPatientTransfer** - Cancel pending transfer requests

#### Triggers (4 triggers)
1. **after_patient_insert** - Auto-create assignment when Clinician adds patient
2. **trg_auto_assign_patient_after_insert** - Automatically assign patient to CI
3. **trg_auto_reassign_on_status_change** - Reassign if assignment is cancelled
4. **update_user_activity_trigger** - Track user activity timestamps

#### Views (7 views)
1. **cod_pending_assignments** - COD dashboard view for pending assignments
2. **clinical_instructor_assignments** - CI dashboard view for their patients
3. **online_clinical_instructors** - List of currently online CIs
4. **v_online_clinical_instructors** - Enhanced online CI view with patient counts
5. **v_auto_assignment_stats** - Statistics on automatic assignments
6. **v_assignment_status_summary** - Summary of assignment statuses per CI
7. **v_patient_transfer_requests** - View for monitoring transfer requests

#### Events (2 scheduled events)
1. **cleanup_offline_users_event** - Runs every 2 minutes to mark inactive CIs offline
2. Second cleanup event variant for redundancy

#### All Enhancements
- ✅ COD role support
- ✅ Online/offline status tracking
- ✅ Automatic patient assignment
- ✅ Patient transfer functionality
- ✅ Profile pictures
- ✅ Specialty hints for matching
- ✅ Treatment hints
- ✅ Activity tracking
- ✅ Comprehensive indexing for performance

**When to use:** ⭐ **ALWAYS USE THIS FOR NEW INSTALLATIONS**

---

## 🎯 Quick Decision Guide

```
┌─────────────────────────────────────────┐
│ Which file should I use?                │
├─────────────────────────────────────────┤
│                                         │
│ For NEW device setup:                  │
│   → alter_database_setup_complete.sql  │
│                                         │
│ For existing database:                 │
│   → alter_database_setup_complete.sql  │
│                                         │
│ For reference only:                    │
│   → alter_database_setup.sql           │
│                                         │
│ NEVER use:                             │
│   → alter_database_setup.sql (broken)  │
│                                         │
└─────────────────────────────────────────┘
```

---

## 📊 Feature Comparison Matrix

| Feature | Original | Basic Fixed | Complete |
|---------|----------|-------------|----------|
| Database selection | ❌ | ✅ | ✅ |
| Table structure | ✅ | ✅ | ✅ |
| New columns | ✅ | ✅ | ✅ |
| Foreign keys | ✅ | ✅ | ✅ |
| Indexes | ✅ | ✅ | ✅ |
| Stored procedures | ✅ (11) | ❌ (0) | ✅ (11) |
| Triggers | ✅ (4) | ❌ (0) | ✅ (4) |
| Views | ✅ (7) | ❌ (0) | ✅ (7) |
| Events | ✅ (2) | ❌ (0) | ✅ (2) |
| Auto-assignment | ✅ | ❌ | ✅ |
| Online tracking | ✅ | ❌ | ✅ |
| Patient transfers | ✅ | ❌ | ✅ |
| Idempotent | ✅ | ✅ | ✅ |
| Safe to re-run | ❌ | ✅ | ✅ |

---

## 🚀 Recommended Setup Order

### For Fresh Installation

```bash
# Step 1: Create database
CREATE DATABASE identify_db;

# Step 2: Run base setup
mysql -u root identify_db < migrations/database_setup.sql

# Step 3: Run COMPLETE migration
mysql -u root < migrations/alter_database_setup_complete.sql
```

### For Existing Database

```bash
# Run COMPLETE migration only
mysql -u root < migrations/alter_database_setup_complete.sql
```

The complete migration is **idempotent** - it checks before creating objects, so it's safe to run multiple times.

---

## ✅ What You Get With Complete Migration

After running `alter_database_setup_complete.sql`, you will have:

### Functional Features
- ✅ Automatic patient assignment to online Clinical Instructors
- ✅ Load balancing (assigns to CI with fewest patients)
- ✅ Specialty matching (matches patient needs to CI expertise)
- ✅ Online/offline status tracking with automatic cleanup
- ✅ Patient transfer system between CIs
- ✅ Procedure logging for clinicians
- ✅ COD workflow dashboards via views
- ✅ CI workflow dashboards via views

### Database Objects
- **12 tables** (including core + new)
- **11 stored procedures** for business logic
- **4 triggers** for automation
- **7 views** for easy data access
- **2 scheduled events** for maintenance
- **25+ indexes** for performance

### Safety Features
- ✅ Idempotent (safe to run multiple times)
- ✅ Checks before creating (uses IF NOT EXISTS)
- ✅ Handles missing references gracefully
- ✅ Preserves existing data

---

## 📝 Summary

**Bottom Line:**  
Use **`alter_database_setup_complete.sql`** for all new installations and migrations.

It includes everything from the original file, but fixed and safe to run!

---

**Last Updated:** October 6, 2025  
**Maintained By:** iDENTify Development Team
