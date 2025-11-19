@echo off
REM ============================================
REM Database Setup Script for iDENTify System
REM ============================================

echo.
echo ========================================
echo  iDENTify Database Setup
echo ========================================
echo.

REM Set MySQL path
set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe
set PROJECT_PATH=%~dp0..

REM Check if MySQL is accessible
if not exist "%MYSQL_PATH%" (
    echo ERROR: MySQL not found at %MYSQL_PATH%
    echo Please make sure XAMPP is installed correctly.
    pause
    exit /b 1
)

echo [Step 1/3] Creating database...
"%MYSQL_PATH%" -u root -e "CREATE DATABASE IF NOT EXISTS identify_db;"
if errorlevel 1 (
    echo ERROR: Failed to create database!
    pause
    exit /b 1
)
echo ✓ Database created successfully!
echo.

echo [Step 2/3] Running base setup (database_setup.sql)...
"%MYSQL_PATH%" -u root identify_db -e "SOURCE %~dp0database_setup.sql"
if errorlevel 1 (
    echo ERROR: Failed to run base setup!
    pause
    exit /b 1
)
echo ✓ Base setup completed!
echo.

echo [Step 3/3] Running migrations (alter_database_setup_complete.sql)...
"%MYSQL_PATH%" -u root -e "SOURCE %~dp0alter_database_setup_complete.sql"
if errorlevel 1 (
    echo ERROR: Failed to run migrations!
    pause
    exit /b 1
)
echo ✓ Migrations completed!
echo.

echo ========================================
echo  Setup Complete!
echo ========================================
echo.
echo Database: identify_db
echo All tables and columns have been created.
echo.

REM Verify the setup
echo Verifying installation...
echo.
"%MYSQL_PATH%" -u root identify_db -e "SHOW TABLES;"
echo.

echo Setup completed successfully!
echo You can now use the iDENTify system.
echo.
pause
