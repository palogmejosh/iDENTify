# Troubleshooting Database Setup Issues

## ❌ ERROR: "Commands out of sync" or "#2014" in phpMyAdmin

### Problem
When running `alter_database_setup_complete.sql` in phpMyAdmin, you get:
```
#2014 - Commands out of sync; you can't run this command now
```
or
```
Missing expression (near "ON" at position 25)
```

### Cause
phpMyAdmin has issues handling SQL files with `DELIMITER` statements (used for stored procedures and triggers). This is a known phpMyAdmin limitation.

### ✅ SOLUTION: Use Command Line Instead

**The migration MUST be run via MySQL command line, NOT phpMyAdmin.**

---

## 🚀 CORRECT SETUP PROCEDURE

### Option 1: Use the Automated Script (EASIEST)
Double-click: `migrations\setup_database.bat`

This script will:
1. Create the database
2. Run `database_setup.sql`
3. Run `alter_database_setup_complete.sql`

All done automatically!

### Option 2: Manual Command Line Setup

Open PowerShell or CMD and run:

```powershell
# Step 1: Create database (if not exists)
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS identify_db;"

# Step 2: Run base setup
C:\xampp\mysql\bin\mysql.exe -u root identify_db -e "SOURCE C:\xampp\htdocs\identify\migrations\database_setup.sql"

# Step 3: Run complete migration
C:\xampp\mysql\bin\mysql.exe -u root -e "SOURCE C:\xampp\htdocs\identify\migrations\alter_database_setup_complete.sql"
```

---

## ❌ ERROR: Table doesn't exist

### Problem
```
ERROR 1146 (42S02): Table 'identify_db.patients' doesn't exist
```

### Cause
You tried to run `alter_database_setup_complete.sql` **BEFORE** running `database_setup.sql`.

### ✅ SOLUTION: Run in Correct Order

You **MUST** run the scripts in this order:

1. **First:** `database_setup.sql` (creates base tables)
2. **Then:** `alter_database_setup_complete.sql` (adds enhancements)

---

## ⚠️ Common Mistakes

### Mistake 1: Using phpMyAdmin for Complete Migration
**Problem:** phpMyAdmin can't handle DELIMITER statements  
**Solution:** Use command line (see above)

### Mistake 2: Running Scripts Out of Order
**Problem:** Tables don't exist when trying to ALTER them  
**Solution:** Run `database_setup.sql` FIRST

### Mistake 3: Running Original File
**Problem:** `alter_database_setup.sql` has errors (missing USE statement)  
**Solution:** Use `alter_database_setup_complete.sql` instead

### Mistake 4: Missing Database
**Problem:** Database doesn't exist  
**Solution:** Create it first with `CREATE DATABASE identify_db;`

---

## ✅ Verification After Setup

To verify everything was installed correctly, run:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root identify_db -e "SELECT 'TABLES' as Type, COUNT(*) as Count FROM information_schema.tables WHERE table_schema = 'identify_db' UNION ALL SELECT 'PROCEDURES', COUNT(*) FROM information_schema.routines WHERE routine_schema = 'identify_db' AND routine_type = 'PROCEDURE' UNION ALL SELECT 'TRIGGERS', COUNT(*) FROM information_schema.triggers WHERE trigger_schema = 'identify_db' UNION ALL SELECT 'VIEWS', COUNT(*) FROM information_schema.views WHERE table_schema = 'identify_db' UNION ALL SELECT 'EVENTS', COUNT(*) FROM information_schema.events WHERE event_schema = 'identify_db';"
```

You should see:
```
+------------+-------+
| Type       | Count |
+------------+-------+
| TABLES     |    20 |
| PROCEDURES |     9 |
| TRIGGERS   |     4 |
| VIEWS      |     7 |
| EVENTS     |     1 |
+------------+-------+
```

---

## 🔧 Reset Database (Start Fresh)

If you need to start over:

```sql
-- WARNING: This will delete ALL data!
DROP DATABASE IF EXISTS identify_db;
CREATE DATABASE identify_db;
```

Then run the setup scripts again in the correct order.

---

## 📊 What Each File Does

| File | Purpose | Run When |
|------|---------|----------|
| `database_setup.sql` | Creates base tables (users, patients, etc.) | **FIRST** - Always run this before migrations |
| `alter_database_setup_complete.sql` | Adds all enhancements (procedures, triggers, views, events, columns) | **SECOND** - After base setup |
| `setup_database.bat` | Automated script that runs both in correct order | Use this for easy setup! |

---

## 💡 Why Command Line?

### MySQL Command Line ✅
- Properly handles DELIMITER statements
- Handles stored procedures correctly
- Handles triggers correctly
- Can process large files
- Shows detailed error messages

### phpMyAdmin ❌
- Has issues with DELIMITER
- "Commands out of sync" errors
- May timeout on large files
- Can't handle complex procedure syntax

**Recommendation:** Always use command line for database migrations!

---

## 🆘 Still Having Issues?

### Check these:

1. **Is XAMPP MySQL running?**
   - Start XAMPP Control Panel
   - Make sure MySQL status is "Running"

2. **Is the database created?**
   ```sql
   SHOW DATABASES LIKE 'identify_db';
   ```

3. **Are you in the correct directory?**
   ```powershell
   cd C:\xampp\htdocs\identify
   ```

4. **Do you need a MySQL password?**
   If your MySQL has a password, add `-p`:
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root -p identify_db < migrations/database_setup.sql
   ```

5. **Check file paths**
   Make sure the SQL files are in:
   ```
   C:\xampp\htdocs\identify\migrations\
   ```

---

## 📞 Quick Reference

### ✅ Correct Setup (Command Line)
```powershell
# Option A: Automated
migrations\setup_database.bat

# Option B: Manual
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS identify_db;"
C:\xampp\mysql\bin\mysql.exe -u root identify_db -e "SOURCE C:\xampp\htdocs\identify\migrations\database_setup.sql"
C:\xampp\mysql\bin\mysql.exe -u root -e "SOURCE C:\xampp\htdocs\identify\migrations\alter_database_setup_complete.sql"
```

### ❌ Wrong (phpMyAdmin)
Don't try to import `alter_database_setup_complete.sql` in phpMyAdmin - it will fail!

---

**Last Updated:** October 6, 2025  
**Issue Resolution:** Successfully resolved via command line execution
