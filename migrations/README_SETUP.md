# Database Setup Guide for iDENTify System

## For Fresh Installation on a New Device

When setting up this system on a new device (fresh installation with no existing database), follow these steps **in order**:

### Step 1: Create the Database
First, create the database in MySQL/phpMyAdmin:

```sql
CREATE DATABASE identify_db;
```

### Step 2: Run the Base Setup Script
Run the `database_setup.sql` file to create all the base tables:

```bash
mysql -u root -p identify_db < migrations/database_setup.sql
```

Or in phpMyAdmin:
1. Select the `identify_db` database
2. Go to the "SQL" tab
3. Click "Choose File" and select `migrations/database_setup.sql`
4. Click "Go"

### Step 3: Run the Migration Script
Run the **COMPLETE** migration script to add all additional columns, tables, views, procedures, triggers, and events:

```bash
mysql -u root -p < migrations/alter_database_setup_complete.sql
```

Or in phpMyAdmin:
1. Go to the "SQL" tab
2. Click "Choose File" and select `migrations/alter_database_setup_complete.sql`
3. Click "Go"

---

## ⚠️ IMPORTANT NOTES

### Which file should I use?
- ❌ **DO NOT USE** `alter_database_setup.sql` (has errors, missing USE statement)
- ⚠️ **PARTIAL** `alter_database_setup_fixed.sql` (only basic schema, missing procedures/triggers/views)
- ✅ **USE** `alter_database_setup_complete.sql` (COMPLETE - includes everything!)
- The complete version includes ALL tables, columns, procedures, triggers, views, and events.

### Order Matters!
You **MUST** run the scripts in this order:
1. Create database (`CREATE DATABASE identify_db;`)
2. Run `database_setup.sql`
3. Run `alter_database_setup_complete.sql`

### Why This Order?
- `database_setup.sql` creates the **base schema** (users, patients, core tables)
- `alter_database_setup_complete.sql` adds **ALL enhancements**:
  - New columns and tables
  - Stored procedures (AutoAssignPatientToCI, CleanupOfflineUsers, etc.)
  - Triggers (auto-assignment, activity tracking)
  - Views (cod_pending_assignments, clinical_instructor_assignments, etc.)
  - Events (automated cleanup tasks)
  - Indexes for performance

---

## Complete Command-Line Setup (Windows XAMPP)

Open PowerShell in the project directory and run:

```powershell
# Step 1: Create the database
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS identify_db;"

# Step 2: Run base setup
C:\xampp\mysql\bin\mysql.exe -u root identify_db -e "SOURCE C:\xampp\htdocs\identify\migrations\database_setup.sql"

# Step 3: Run migrations
C:\xampp\mysql\bin\mysql.exe -u root -e "SOURCE C:\xampp\htdocs\identify\migrations\alter_database_setup_complete.sql"
```

---

## Verification

After running all scripts, verify the setup:

```bash
# Check all tables were created
C:\xampp\mysql\bin\mysql.exe -u root identify_db -e "SHOW TABLES;"
```

You should see these tables:
- appointments
- dental_examination
- informed_consent
- patient_approvals
- patient_assignments
- patient_health
- patient_pir
- patients
- procedure_logs
- progress_notes
- treatment_records
- users

```bash
# Check users table structure
C:\xampp\mysql\bin\mysql.exe -u root identify_db -e "DESCRIBE users;"
```

The `users` table should have these columns:
- id
- username
- full_name
- email
- password
- role (with COD option)
- created_at
- updated_at
- last_activity
- connection_status
- is_active
- account_status
- profile_picture
- specialty_hint

---

## For Existing Database (Migration Only)

If you already have a database with the base schema and only need to apply updates:

```bash
# Just run the complete migration script
C:\xampp\mysql\bin\mysql.exe -u root -e "SOURCE C:\xampp\htdocs\identify\migrations\alter_database_setup_complete.sql"
```

The script is **idempotent** - it checks if columns/tables exist before creating them, so it's safe to run multiple times.

---

## Troubleshooting

### Error: "No database selected"
**Solution:** Make sure the SQL file has `USE identify_db;` at the top, or specify the database in the command:
```bash
mysql -u root identify_db < file.sql
```

### Error: "Table doesn't exist"
**Solution:** Make sure you ran `database_setup.sql` first before running the migration script.

### Error: "Column already exists"
**Solution:** This is normal and safe to ignore. The fixed script handles duplicate columns gracefully.

### MySQL Password Required
If MySQL asks for a password, add `-p` flag:
```bash
C:\xampp\mysql\bin\mysql.exe -u root -p -e "..."
```

---

## Quick Reference

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `database_setup.sql` | Creates base schema | Fresh installation only |
| `alter_database_setup_complete.sql` | Adds ALL enhancements (procedures, triggers, views, tables) | After base setup OR on existing DB |
| `alter_database_setup_fixed.sql` | Adds ONLY basic schema changes | NOT RECOMMENDED - incomplete |
| `alter_database_setup.sql` | ❌ **DO NOT USE** | Has errors, missing USE statement |

---

## File Locations

- Base setup: `migrations/database_setup.sql`
- Migration (COMPLETE): `migrations/alter_database_setup_complete.sql` ⭐ **USE THIS**
- Migration (basic): `migrations/alter_database_setup_fixed.sql` (incomplete, missing procedures)
- ❌ Broken file: `migrations/alter_database_setup.sql` (keep for reference only)

---

**Last Updated:** October 6, 2025
**Database Name:** identify_db
**Tested On:** XAMPP (Windows), MySQL/MariaDB
