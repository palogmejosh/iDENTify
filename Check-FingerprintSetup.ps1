# DigitalPersona Fingerprint Scanner Diagnostic Script
# Run this script to diagnose fingerprint scanner issues

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "🔍 DigitalPersona Scanner Diagnostic Tool" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$issues = @()
$fixes = @()

# Check 1: DigitalPersona Installation
Write-Host "[1/7] Checking DigitalPersona installation..." -ForegroundColor Yellow
$dpPath = "C:\Program Files\DigitalPersona"
$dpPathx86 = "C:\Program Files (x86)\DigitalPersona"

if (Test-Path $dpPath) {
    Write-Host "  ✅ Found: $dpPath" -ForegroundColor Green
} elseif (Test-Path $dpPathx86) {
    Write-Host "  ✅ Found: $dpPathx86" -ForegroundColor Green
    $dpPath = $dpPathx86
} else {
    Write-Host "  ❌ DigitalPersona NOT installed" -ForegroundColor Red
    $issues += "DigitalPersona software not found"
    $fixes += "Download and install DigitalPersona Lite Client from: https://www.crossmatch.com/company/support/downloads/"
}

# Check 2: DpHost Service
Write-Host "`n[2/7] Checking DpHost service..." -ForegroundColor Yellow
$service = Get-Service -Name "DpHost" -ErrorAction SilentlyContinue

if ($service) {
    Write-Host "  ✅ Service found: $($service.DisplayName)" -ForegroundColor Green
    Write-Host "  Status: $($service.Status)" -ForegroundColor $(if ($service.Status -eq 'Running') { 'Green' } else { 'Yellow' })
    
    if ($service.Status -ne 'Running') {
        $issues += "DpHost service is not running"
        $fixes += "Start the service by running as Administrator: Start-Service -Name DpHost"
        
        Write-Host "`n  Attempting to start service..." -ForegroundColor Yellow
        try {
            Start-Service -Name "DpHost" -ErrorAction Stop
            Start-Sleep -Seconds 2
            $serviceCheck = Get-Service -Name "DpHost"
            if ($serviceCheck.Status -eq 'Running') {
                Write-Host "  ✅ Service started successfully!" -ForegroundColor Green
            }
        } catch {
            Write-Host "  ⚠️  Cannot start service automatically. Error: $($_.Exception.Message)" -ForegroundColor Red
            Write-Host "  ⚠️  You need to run this script as Administrator" -ForegroundColor Yellow
        }
    }
} else {
    Write-Host "  ❌ DpHost service NOT found" -ForegroundColor Red
    $issues += "DpHost service not installed"
    $fixes += "Reinstall DigitalPersona Lite Client"
}

# Check 3: USB Scanner Device
Write-Host "`n[3/7] Checking for fingerprint scanner device..." -ForegroundColor Yellow
$devices = Get-PnpDevice -Class "Biometric" -Status "OK" -ErrorAction SilentlyContinue | Where-Object { $_.FriendlyName -like "*Digital*" -or $_.FriendlyName -like "*fingerprint*" }

if ($devices) {
    foreach ($device in $devices) {
        Write-Host "  ✅ Found: $($device.FriendlyName)" -ForegroundColor Green
        Write-Host "     Status: $($device.Status)" -ForegroundColor Green
    }
} else {
    Write-Host "  ❌ No fingerprint scanner detected" -ForegroundColor Red
    $issues += "Fingerprint scanner not detected"
    $fixes += "1. Connect the U.are.U 4500 scanner via USB"
    $fixes += "2. Check Device Manager for any warnings"
    $fixes += "3. Try a different USB port"
}

# Check 4: Web Server SDK Files
Write-Host "`n[4/7] Checking WebSDK files..." -ForegroundColor Yellow
$sdkPath = "C:\xampp\htdocs\iDENTify\node_modules\@digitalpersona"

$requiredFiles = @(
    "$sdkPath\websdk\dist\websdk.client.min.js",
    "$sdkPath\core\dist\es5.bundles\index.umd.min.js",
    "$sdkPath\devices\dist\es5.bundles\index.umd.min.js"
)

$allFilesExist = $true
foreach ($file in $requiredFiles) {
    $fileName = Split-Path $file -Leaf
    if (Test-Path $file) {
        Write-Host "  ✅ $fileName" -ForegroundColor Green
    } else {
        Write-Host "  ❌ $fileName NOT FOUND" -ForegroundColor Red
        $allFilesExist = $false
    }
}

if (-not $allFilesExist) {
    $issues += "SDK files missing in node_modules"
    $fixes += "Run in PowerShell: cd C:\xampp\htdocs\iDENTify; npm install @digitalpersona/websdk @digitalpersona/core @digitalpersona/devices"
}

# Check 5: Configuration File
Write-Host "`n[5/7] Checking configuration file..." -ForegroundColor Yellow
$configPath = "C:\xampp\htdocs\iDENTify\js\fingerprint-config.js"

if (Test-Path $configPath) {
    Write-Host "  ✅ Configuration file exists" -ForegroundColor Green
} else {
    Write-Host "  ❌ Configuration file missing" -ForegroundColor Red
    $issues += "fingerprint-config.js not found"
    $fixes += "Create js/fingerprint-config.js with SDK path configuration"
}

# Check 6: XAMPP Status
Write-Host "`n[6/7] Checking web server..." -ForegroundColor Yellow
$apacheService = Get-Service -Name "Apache*" -ErrorAction SilentlyContinue

if ($apacheService) {
    if ($apacheService.Status -eq 'Running') {
        Write-Host "  ✅ Apache is running" -ForegroundColor Green
    } else {
        Write-Host "  ⚠️  Apache is not running" -ForegroundColor Yellow
    }
} else {
    # Check if XAMPP is running via process
    $apacheProcess = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
    if ($apacheProcess) {
        Write-Host "  ✅ Apache/httpd process is running" -ForegroundColor Green
    } else {
        Write-Host "  ⚠️  Cannot detect Apache/XAMPP" -ForegroundColor Yellow
        $issues += "Web server might not be running"
        $fixes += "Start XAMPP Control Panel and start Apache"
    }
}

# Check 7: Firewall Ports
Write-Host "`n[7/7] Checking common DigitalPersona ports..." -ForegroundColor Yellow
$ports = @(52181, 8080, 8443)
$portOpen = $false

foreach ($port in $ports) {
    $connection = Test-NetConnection -ComputerName "127.0.0.1" -Port $port -WarningAction SilentlyContinue -InformationLevel Quiet
    if ($connection) {
        Write-Host "  ✅ Port $port is open" -ForegroundColor Green
        $portOpen = $true
    }
}

if (-not $portOpen) {
    Write-Host "  ⚠️  No DigitalPersona service ports detected" -ForegroundColor Yellow
    Write-Host "     This is normal if DpHost service is not running" -ForegroundColor Gray
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "📊 DIAGNOSTIC SUMMARY" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

if ($issues.Count -eq 0) {
    Write-Host "✅ ALL CHECKS PASSED!" -ForegroundColor Green
    Write-Host "`nYour fingerprint scanner should be working." -ForegroundColor Green
    Write-Host "If you still have issues, check the browser console for errors.`n" -ForegroundColor Yellow
} else {
    Write-Host "❌ ISSUES FOUND: $($issues.Count)`n" -ForegroundColor Red
    
    for ($i = 0; $i -lt $issues.Count; $i++) {
        Write-Host "Issue $($i+1): $($issues[$i])" -ForegroundColor Red
    }
    
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host "🔧 RECOMMENDED FIXES" -ForegroundColor Cyan
    Write-Host "========================================`n" -ForegroundColor Cyan
    
    for ($i = 0; $i -lt $fixes.Count; $i++) {
        Write-Host "$($i+1). $($fixes[$i])" -ForegroundColor Yellow
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "📚 ADDITIONAL INFORMATION" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "Test Page: http://localhost/iDENTify/test_fingerprint.html" -ForegroundColor Cyan
Write-Host "Documentation: See FINGERPRINT_SETUP.md" -ForegroundColor Cyan
Write-Host "`nTo re-run this diagnostic: .\Check-FingerprintSetup.ps1`n" -ForegroundColor Gray

# Output machine info for support
Write-Host "Machine Info (for support):" -ForegroundColor Gray
Write-Host "  OS: $([System.Environment]::OSVersion.VersionString)" -ForegroundColor Gray
Write-Host "  PowerShell: $($PSVersionTable.PSVersion)" -ForegroundColor Gray
Write-Host "  Username: $env:USERNAME" -ForegroundColor Gray
Write-Host "  Computer: $env:COMPUTERNAME`n" -ForegroundColor Gray
