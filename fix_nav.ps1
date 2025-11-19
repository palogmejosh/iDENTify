$files = @(
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
    $path = "C:\xampp\htdocs\iDENTify\$file"
    Write-Host "Processing $file..." -ForegroundColor Cyan
    
    $content = Get-Content $path -Raw -Encoding UTF8
    $updated = $false
    
    # Find and replace the header/sidebar section
    if ($content -match '(?s)<body[^>]*>\s*(?:<!--[^>]*-->)?<header class="bg-gradient-to-r.*?</header>\s*<div class="flex">\s*<!--\s*Sidebar\s*-->\s*<nav class="w-64.*?</nav>\s*<!--\s*Main Content\s*-->\s*<main class="flex-1') {
        $content = $content -replace '(?s)(<body[^>]*>)\s*(<header class="bg-gradient-to-r.*?</header>\s*<div class="flex">\s*<!--\s*Sidebar\s*-->\s*<nav class="w-64.*?</nav>\s*<!--\s*Main Content\s*-->\s*<main class="flex-1)', '$1

<?php include ''includes/header.php''; ?>
<?php include ''includes/sidebar.php''; ?>

<!-- Main Content -->
<main class="ml-64 mt-16 min-h-screen'
        
        $updated = $true
        Write-Host "  Replaced header and sidebar" -ForegroundColor Green
    }
    
    # Remove extra closing div after main
    if ($content -match '\s*</main>\s*</div>') {
        $content = $content -replace '(\s*</main>)\s*</div>', '$1'
        $updated = $true
        Write-Host "  Removed extra closing div" -ForegroundColor Green
    }
    
    if ($updated) {
        Set-Content $path -Value $content -NoNewline -Encoding UTF8
        Write-Host "Updated $file" -ForegroundColor Green
    } else {
        Write-Host "No changes needed for $file" -ForegroundColor Yellow
    }
}

Write-Host "Done!" -ForegroundColor Cyan
