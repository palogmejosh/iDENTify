@echo off
echo ========================================
echo Starting DigitalPersona Service
echo ========================================
echo.

echo Checking service status...
sc query DpHost

echo.
echo Attempting to start service...
echo (You may need to run this as Administrator)
echo.

net start DpHost

echo.
echo Checking service status again...
sc query DpHost

echo.
echo Press any key to exit...
pause >nul
