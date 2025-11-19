# Cleanup Script for Unused Node.js Files
# This script removes Node.js files that are NOT needed for fingerprint functionality
# The fingerprint feature now uses DigitalPersona WebSDK directly in the browser

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "iDENTify - Node.js Cleanup Script" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "This script will remove Node.js files that are no longer needed" -ForegroundColor Yellow
Write-Host "because fingerprint functionality now uses DigitalPersona WebSDK directly." -ForegroundColor Yellow
Write-Host ""

# Confirm before proceeding
$confirmation = Read-Host "Do you want to proceed with cleanup? (yes/no)"
if ($confirmation -ne "yes") {
    Write-Host "Cleanup cancelled." -ForegroundColor Red
    exit
}

Write-Host ""
Write-Host "Starting cleanup..." -ForegroundColor Green
Write-Host ""

# Set location to script directory
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptPath

# Track what was deleted
$deletedItems = @()
$failedItems = @()

# Function to safely remove item
function Remove-ItemSafely {
    param(
        [string]$Path,
        [string]$Description
    )
    
    if (Test-Path $Path) {
        try {
            Write-Host "  Removing: $Description" -ForegroundColor Yellow
            Remove-Item -Path $Path -Recurse -Force -ErrorAction Stop
            $script:deletedItems += $Description
            Write-Host "  ✓ Removed successfully" -ForegroundColor Green
        } catch {
            Write-Host "  ✗ Failed to remove: $_" -ForegroundColor Red
            $script:failedItems += $Description
        }
    } else {
        Write-Host "  - Not found (already deleted?): $Description" -ForegroundColor Gray
    }
    Write-Host ""
}

# 1. Remove fingerprint-bridge directory
Write-Host "[1/6] Removing fingerprint-bridge directory..." -ForegroundColor Cyan
Remove-ItemSafely -Path ".\fingerprint-bridge" -Description "fingerprint-bridge directory"

# 2. Remove app.js (demo/prototype file)
Write-Host "[2/6] Removing app.js (demo file)..." -ForegroundColor Cyan
Remove-ItemSafely -Path ".\app.js" -Description "app.js"

# 3. Check and remove package.json if it only contains fingerprint-bridge deps
Write-Host "[3/6] Checking package.json..." -ForegroundColor Cyan
if (Test-Path ".\package.json") {
    $packageContent = Get-Content ".\package.json" -Raw | ConvertFrom-Json
    
    # Check if it only has @digitalpersona/websdk
    $hasOnlyWebSDK = ($packageContent.dependencies.PSObject.Properties.Name.Count -eq 1) -and 
                     ($packageContent.dependencies.PSObject.Properties.Name -contains "@digitalpersona/websdk")
    
    if ($hasOnlyWebSDK) {
        Write-Host "  package.json only contains @digitalpersona/websdk - keeping it" -ForegroundColor Green
        Write-Host "  (This is needed for the WebSDK script reference)" -ForegroundColor Gray
    } else {
        Write-Host "  package.json contains other dependencies - review manually" -ForegroundColor Yellow
        Write-Host "  Dependencies found:" -ForegroundColor Gray
        $packageContent.dependencies.PSObject.Properties | ForEach-Object {
            Write-Host "    - $($_.Name): $($_.Value)" -ForegroundColor Gray
        }
    }
} else {
    Write-Host "  - package.json not found" -ForegroundColor Gray
}
Write-Host ""

# 4. Remove package-lock.json
Write-Host "[4/6] Removing package-lock.json..." -ForegroundColor Cyan
Remove-ItemSafely -Path ".\package-lock.json" -Description "package-lock.json"

# 5. Remove node_modules except @digitalpersona/websdk
Write-Host "[5/6] Cleaning node_modules (keeping @digitalpersona/websdk)..." -ForegroundColor Cyan
if (Test-Path ".\node_modules") {
    $nodeModulesItems = Get-ChildItem ".\node_modules" -Directory
    
    foreach ($item in $nodeModulesItems) {
        if ($item.Name -ne "@digitalpersona") {
            Remove-ItemSafely -Path $item.FullName -Description "node_modules\$($item.Name)"
        } else {
            Write-Host "  - Keeping: node_modules\@digitalpersona (needed for WebSDK)" -ForegroundColor Green
            Write-Host ""
        }
    }
    
    # Remove the .package-lock.json inside node_modules
    if (Test-Path ".\node_modules\.package-lock.json") {
        Remove-ItemSafely -Path ".\node_modules\.package-lock.json" -Description "node_modules\.package-lock.json"
    }
} else {
    Write-Host "  - node_modules directory not found" -ForegroundColor Gray
    Write-Host ""
}

# 6. Summary
Write-Host "[6/6] Cleanup Summary" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

if ($deletedItems.Count -gt 0) {
    Write-Host "Successfully deleted ($($deletedItems.Count) items):" -ForegroundColor Green
    foreach ($item in $deletedItems) {
        Write-Host "  ✓ $item" -ForegroundColor Green
    }
    Write-Host ""
}

if ($failedItems.Count -gt 0) {
    Write-Host "Failed to delete ($($failedItems.Count) items):" -ForegroundColor Red
    foreach ($item in $failedItems) {
        Write-Host "  ✗ $item" -ForegroundColor Red
    }
    Write-Host ""
}

Write-Host "Files kept for fingerprint functionality:" -ForegroundColor Cyan
Write-Host "  ✓ package.json (for @digitalpersona/websdk reference)" -ForegroundColor Green
Write-Host "  ✓ node_modules/@digitalpersona/ (WebSDK library)" -ForegroundColor Green
Write-Host "  ✓ js/digitalpersona-fingerprint.js (reference/documentation)" -ForegroundColor Green
Write-Host ""

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Cleanup completed!" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Test the fingerprint capture in edit_patient.php" -ForegroundColor White
Write-Host "2. Ensure scanner is connected and WebSDK is installed" -ForegroundColor White
Write-Host "3. Review FINGERPRINT_IMPLEMENTATION.md for details" -ForegroundColor White
Write-Host ""

# Pause to read results
Read-Host "Press Enter to exit"
