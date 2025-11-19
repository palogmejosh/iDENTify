# Script to update all pages with fixed header and sidebar navigation

$files = @(
    'dashboard.php',
    'cod_patients.php', 
    'settings.php',
    'profile.php',
    'users.php',
    'admin_procedures_log.php',
    'clinician_log_procedure.php',
    'ci_patient_assignments.php',
    'ci_patient_transfers.php'
)

foreach ($file in $files) {
    $filePath = "C:\xampp\htdocs\iDENTify\$file"
    
    if (Test-Path $filePath) {
        Write-Host "Processing: $file"
        
        $content = Get-Content $filePath -Raw
        
        # Pattern 1: Replace old header structure with includes
        $headerPattern = '(?s)<header class="bg-gradient-to-r.*?</header>\s*<div class="flex">\s*<!-- Sidebar -->\s*<nav class="w-64.*?</nav>\s*<!-- Main Content -->\s*<main class="flex-1'
        $headerReplacement = '<?php include ''includes/header.php''; ?>' + "`r`n" + '<?php include ''includes/sidebar.php''; ?>' + "`r`n`r`n" + '<!-- Main Content -->' + "`r`n" + '<main class="ml-64 mt-16'
        
        if ($content -match $headerPattern) {
            $content = $content -replace $headerPattern, $headerReplacement
            Write-Host "  - Replaced header and sidebar with includes"
        }
        
        # Pattern 2: Fix closing tags - remove extra </div> after </main>
        $content = $content -replace '(\s*</main>)\s*</div>', '$1'
        
        # Pattern 3: Update main tag classes to include ml-64 mt-16 and min-h-screen
        $content = $content -replace '<main class="flex-1\s+p-6\s+main-content', '<main class="ml-64 mt-16 p-6 min-h-screen'
        $content = $content -replace '<main class="flex-1\s+bg-gradient', '<main class="ml-64 mt-16 min-h-screen bg-gradient'
        $content = $content -replace '<main class="flex-1', '<main class="ml-64 mt-16 min-h-screen'
        
        # Save the updated content
        Set-Content $filePath -Value $content -NoNewline
        Write-Host "  - Updated: $file" -ForegroundColor Green
    } else {
        Write-Host "  - Not found: $file" -ForegroundColor Yellow
    }
}

Write-Host "`nAll files processed!" -ForegroundColor Cyan
