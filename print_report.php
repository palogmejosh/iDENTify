<?php
require_once 'config.php';
requireAuth();

$patientId = (int)($_GET['id'] ?? 0);
$pages     = array_filter(explode(',', $_GET['pages'] ?? ''));

if (!$patientId || !$pages) die('Invalid request');

/* -------------------------------------------------
 * Patient core data
 * -------------------------------------------------*/
$pt  = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$pt->execute([$patientId]);
$patient = $pt->fetch(PDO::FETCH_ASSOC) ?: [];

function fetch($table, $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE patient_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$pir    = fetch('patient_pir',   $patientId);
$health = fetch('patient_health',$patientId);
$exam   = fetch('dental_examination',$patientId);
$consent= fetch('informed_consent',$patientId);

$h = fn($v) => htmlspecialchars($v ?? '');
$patientFullName = trim($h($patient['first_name']).' '.$h($patient['last_name']));

/* ----------  CI name for page-3 (Updated to use assigned CI) ---------- */
$ciName = getAssignedClinicalInstructor($patientId) ?: '';

/* ---------- Get current user info for clinician name sync ---------- */
$currentUser = getCurrentUser();
$currentUserName = $currentUser['full_name'] ?? '';

/* ===================================================================
 * Signature data for Page 5
 * =================================================================== */
$signaturePathPage5 = $consent['data_privacy_signature_path'] ?? '';
$notesStmt = $pdo->prepare("SELECT patient_signature FROM progress_notes WHERE patient_id = ? LIMIT 1");
$notesStmt->execute([$patientId]);
$signaturePrintedName = $notesStmt->fetchColumn() ?: $patientFullName; // Fallback to full name

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Patient Report – <?= $patientFullName ?></title>

  <!-- 1. ORIGINAL inline styles – still used by page-1, 3, 4, 5 -->
  <style>
    *{box-sizing:border-box}
    body{margin:0;padding:.5cm;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.4;color:#000;background:#fff}
    .page1{max-width:21cm;margin:0 auto}
    .header{text-align:center;margin-bottom:.5cm}
    .header h1{font-size:18px;font-weight:bold;margin:0}
    .header p{margin:2px 0;font-size:11px}
    .grid{display:grid;gap:4px 8px}
    .grid-3{grid-template-columns:1fr 1fr 1fr}
    .grid-2{grid-template-columns:1fr 1fr}
    .field label{font-weight:bold;font-size:11px;display:block;margin-bottom:1px}
    .field input,.field select{width:100%;padding:2px;border:1px solid #999;border-radius:2px;background:#fff;font-size:11px}
    .field input[readonly]{background:#f5f5f5}
    .section-title{font-size:14px;font-weight:bold;border-bottom:1px solid #000;margin:.5cm 0 .2cm}
    .photo-box{display:flex;align-items:center;gap:8px}
    .photo-frame,.thumb-frame{width:100px;height:100px;border:1px solid #999;display:flex;align-items:center;justify-content:center;font-size:10px;color:#666}
    .signature-box{display:flex;flex-direction:column;align-items:center}
    .signature-line{border-bottom:1px solid #000;min-width:250px;margin-top:4px}
    .asa-circles{display:flex;gap:10px;align-items:center;font-weight:bold}
    .page-break{page-break-after:always}
  </style>

  <!-- 2. NEW external CSS – ONLY for the second page -->
  <link rel="stylesheet" href="css/dental_examination.css">
  <link rel="stylesheet" href="css/informed_consent.css">
  <link rel="stylesheet" href="css/progress_notes.css">
</head>
<body>

<?php if (in_array('1', $pages)): ?>
<!-- =====================================================
     PAGE 1 – PATIENT INFORMATION RECORD (EXACT LAYOUT MATCH)
     ===================================================== -->

<style>
/* Adjusted layout - smaller but still readable */
@page { size: legal; margin: 0.4in; }

body { margin: 0; padding: 0; }

.page1 {
    width: 100%;
    font-family: Arial, sans-serif;
    font-size: 9px; /* Reduced from 10px */
    line-height: 1.1; /* Tighter line height */
    background-color: white;
}

/* Header section */
.page1-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px; /* Reduced from 15px */
    padding: 0;
}

.header-left {
    display: flex;
    align-items: center;
}

.logo-shield {
    width: 50px; /* Reduced from 60px */
    height: 50px; /* Reduced from 60px */
    margin-right: 12px; /* Reduced from 15px */
    display: flex;
    align-items: center;
    justify-content: center;
}

.university-info h1 {
    font-size: 24px; /* Reduced from 28px */
    font-weight: bold;
    margin: 0;
    letter-spacing: 1.5px; /* Reduced from 2px */
}

.university-info .tagline {
    font-size: 8px; /* Reduced from 9px */
    margin: 1px 0; /* Reduced from 2px */
}

.header-right {
    text-align: right;
    font-size: 9px; /* Reduced from 10px */
}

.form-code {
    font-weight: bold;
    margin-bottom: 4px; /* Reduced from 5px */
}

/* Title box */
.title-section {
    border: 2px solid #000;
    text-align: center;
    padding: 6px; /* Reduced from 8px */
    font-weight: bold;
    font-size: 12px; /* Reduced from 14px */
    margin-bottom: 8px; /* Reduced from 10px */
}

/* Personal Data Section */
.section-title {
    background-color: #d3d3d3;
    border: 1px solid #000;
    padding: 3px; /* Reduced from 4px */
    font-weight: bold;
    font-size: 10px; /* Reduced from 11px */
    margin: 0;
}

.personal-data-container {
    display: flex;
    border: 2px solid #000;
    border-top: none;
}

.personal-info-left {
    flex: 1;
}

.personal-info-right {
    width: 100px; /* Reduced from 120px */
    border-left: 1px solid #000;
}

.personal-data-table {
    width: 100%;
    border-collapse: collapse;
}

.personal-data-table td {
    border: 1px solid #000;
    padding: 3px; /* Reduced from 4px */
    font-size: 8px; /* Reduced from 9px */
    height: 30px; /* Reduced from 35px */
    vertical-align: top;
}

.photo-section {
    height: 75px; /* Reduced from 140px */
    border-bottom: 1px solid #000;
    display: flex;
    width: 100px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-size: 7px; /* Reduced from 8px */
}

.thumbmark-section {
    height: 50px;
    width: 100px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-size: 7px; /* Reduced from 8px */
}

/* Subjective Section */
.subjective-title {
    background-color: #d3d3d3;
    border: 2px solid #000;
    border-bottom: 1px solid #000;
    padding: 3px; /* Reduced from 4px */
    font-weight: bold;
    font-size: 10px; /* Reduced from 11px */
    margin: 12px 0 0 0; /* Reduced from 15px */
}

.case-history-container {
    border: 2px solid #000;
    border-top: none;
}

.case-history-header {
    display: flex;
    border-bottom: 1px solid #000;
}

.case-history-label {
    padding: 3px; /* Reduced from 4px */
    font-weight: bold;
    font-size: 9px; /* Reduced from 10px */
    flex: 1;
}

.case-history-fields {
    display: flex;
    border-left: 1px solid #000;
}

.case-field {
    padding: 3px; /* Reduced from 4px */
    text-align: center;
    font-size: 8px; /* Reduced from 9px */
    font-weight: bold;
    width: 70px; /* Reduced from 80px */
    border-left: 1px solid #000;
}

.case-field:first-child {
    border-left: none;
}

.case-content {
    display: flex;
    min-height: 70px; /* Reduced from 80px */
}

.chief-complaint {
    width: 170px; /* Reduced from 200px */
    padding: 6px; /* Reduced from 8px */
    border-right: 1px solid #000;
    font-size: 8px; /* Reduced from 9px */
    font-weight: bold;
}

.history-illness {
    flex: 1;
    padding: 6px; /* Reduced from 8px */
    font-size: 8px; /* Reduced from 9px */
    font-weight: bold;
}

/* Medical and Dental History */
.medical-dental-container {
    border: 2px solid #000;
    margin-top: 8px; /* Reduced from 10px */
    display: flex;
}

.medical-history-section,
.dental-history-section {
    flex: 1;
    padding: 6px; /* Reduced from 8px */
    font-size: 8px; /* Reduced from 9px */
}

.medical-history-section {
    border-right: 1px solid #000;
}

.history-header {
    font-weight: bold;
    margin-bottom: 6px; /* Reduced from 8px */
    text-align: center;
}

.history-item {
    margin-bottom: 3px; /* Reduced from 4px */
    line-height: 1.2; /* Reduced from 1.3 */
}

/* Family and Personal History */
.family-personal-container {
    border: 2px solid #000;
    margin-top: 8px; /* Reduced from 10px */
    display: flex;
    min-height: 85px; /* Reduced from 100px */
}

.family-history,
.personal-history {
    flex: 1;
    padding: 6px; /* Reduced from 8px */
    font-size: 8px; /* Reduced from 9px */
}

.family-history {
    border-right: 1px solid #000;
}

/* Review of Systems */
.systems-review-container {
    border: 2px solid #000;
    margin-top: 8px; /* Reduced from 10px */
}

.systems-review-title {
    background-color: #d3d3d3;
    padding: 3px; /* Reduced from 4px */
    font-weight: bold;
    font-size: 10px; /* Reduced from 11px */
    border-bottom: 1px solid #000;
}

.systems-content {
    display: flex;
    padding: 6px; /* Reduced from 8px */
    font-size: 7px; /* Reduced from 8px */
}

.system-column {
    flex: 1;
    margin-right: 12px; /* Reduced from 15px */
}

.system-column:last-child {
    margin-right: 0;
}

.system-item {
    margin-bottom: 6px; /* Reduced from 8px */
}

.system-label {
    font-weight: bold;
    margin-bottom: 1px; /* Reduced from 2px */
}

.dotted-line {
    border-bottom: 1px dotted #000;
    height: 10px; /* Reduced from 12px */
    margin-bottom: 1px; /* Reduced from 2px */
}

/* Bottom section */
.bottom-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 12px; /* Reduced from 15px */
    position: relative;
}

.health-assessment {
    border: 2px solid #000;
    padding: 6px; /* Reduced from 8px */
    width: 260px; /* Reduced from 300px */
    font-size: 9px; /* Reduced from 10px */
}

.asa-section {
    margin: 6px 0; /* Reduced from 8px */
    font-weight: bold;
}

.medical-alert-section {
    position: relative;
}

.medical-alert {
    background-color: #FFFF99;
    border: 3px solid #000;
    padding: 12px 20px; /* Reduced from 15px 25px */
    font-weight: bold;
    text-align: center;
    font-size: 12px; /* Reduced from 14px */
    position: relative;
    margin-bottom: 8px; /* Reduced from 10px */
}

.medical-alert::before {
    content: '';
    position: absolute;
    left: -18px; /* Reduced from -20px */
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-top: 18px solid transparent; /* Reduced from 20px */
    border-bottom: 18px solid transparent; /* Reduced from 20px */
    border-right: 18px solid #FFFF99; /* Reduced from 20px */
}

.official-stamp {
    border: 2px solid blue; /* Reduced from 3px */
    color: blue;
    padding: 15px; /* Reduced from 20px */
    text-align: center;
    font-weight: bold;
    font-size: 14px; /* Reduced from 16px */
    transform: rotate(-15deg);
    position: absolute;
    bottom: 8px; /* Reduced from 10px */
    right: 8px; /* Reduced from 10px */
    background-color: white;
}

/* Signature area */
.signature-area {
    margin-top: 15px; /* Reduced from 20px */
    font-size: 9px; /* Reduced from 10px */
    text-align: left;
}

.signature-line {
    border-bottom: 1px solid #000;
    width: 250px; /* Reduced from 300px */
    height: 18px; /* Reduced from 20px */
    margin-top: 4px; /* Reduced from 5px */
}</style>
</style>

<div class="page1">
    <!-- Header -->
    <div class="page1-header">
        <div class="header-left">
            <div class="logo-shield">
                <img src="idqktRTTZZ_1759196704064.svg" alt="LPU Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div class="university-info">
                <h1>LPU</h1>
                <div class="tagline">LYCEUM OF THE PHILIPPINES UNIVERSITY</div>
                <div class="tagline">BATANGAS CITY, BATANGAS, PHILIPPINES</div>
            </div>
        </div>
        <div class="header-right">
            <div class="form-code">FM-LPU-DENT-01/09<br>Page 1 of 5</div>
            <div class="tagline">College of Dentistry<br>Telephone No. (043)723-0706 loc. 175/176</div>
        </div>
    </div>

    <!-- Title -->
    <div class="title-section">PATIENT INFORMATION RECORD</div>

    <!-- Personal Data Section -->
    <div class="section-title">PERSONAL DATA</div>
    <div class="personal-data-container">
        <div class="personal-info-left">
            <table class="personal-data-table">
                <tr>
                    <td>Last name<br><strong><?= $h($patient['last_name']) ?></strong></td>
                    <td>First name<br><strong><?= $h($patient['first_name']) ?></strong></td>
                    <td>MI<br><strong><?= $h($pir['mi']) ?></strong></td>
                    <td>Nickname<br><strong><?= $h($pir['nickname']) ?></strong></td>
                    <td>Age/<br>Sex/<br>Gender<br><strong><?= $h($pir['age']) ?>/<?= $h($pir['gender']) ?></strong></td>
                    <td>Date of Birth<br><strong><?= $h($pir['date_of_birth']) ?></strong></td>
                    <td>Civil Status<br><strong><?= $h($pir['civil_status']) ?></strong></td>
                </tr>
                <tr>
                    <td colspan="3">Home Address<br><strong><?= $h($pir['home_address']) ?></strong></td>
                    <td>Home phone<br><strong><?= $h($pir['home_phone']) ?></strong></td>
                    <td>Mobile No<br><strong><?= $h($pir['mobile_no']) ?></strong></td>
                    <td colspan="2">Email<br><strong><?= $h($patient['email']) ?></strong></td>
                </tr>
                <tr>
                    <td>Occupation<br><strong><?= $h($pir['occupation']) ?></strong></td>
                    <td colspan="2">Work Address<br><strong><?= $h($pir['work_address']) ?></strong></td>
                    <td>Phone<br><strong><?= $h($pir['work_phone']) ?></strong></td>
                    <td colspan="3">Nationality/Ethnicity<br><strong><?= $h($pir['ethnicity']) ?></strong></td>
                </tr>
                <tr>
                    <td colspan="2">For minors: Parent/Guardian<br><strong><?= $h($pir['guardian_name']) ?></strong></td>
                    <td>Contact number<br><strong><?= $h($pir['guardian_contact']) ?></strong></td>
                    <td>Emergency Contact<br><strong><?= $h($pir['emergency_contact_name']) ?></strong></td>
                    <td colspan="3">Contact number<br><strong><?= $h($pir['emergency_contact_number']) ?></strong></td>
                </tr>
            </table>
        </div>
        <div class="personal-info-right">
            <div class="photo-section">
                <?php if (!empty($pir['photo']) && file_exists($pir['photo'])): ?>
                    <img src="<?= $h($pir['photo']) ?>" style="max-width: 90%; max-height: 90%; object-fit: cover;" alt="Patient Photo">
                <?php else: ?>
                    <span>No photo</span>
                <?php endif; ?>
                <div>1"x1" picture</div>
            </div>
            <div class="thumbmark-section">
                <?php if (!empty($pir['thumbmark']) && file_exists($pir['thumbmark'])): ?>
                    <img src="<?= $h($pir['thumbmark']) ?>" style="max-width: 90%; max-height: 90%; object-fit: cover;" alt="Thumbmark">
                <?php else: ?>
                    <span>No thumbmark</span>
                <?php endif; ?>
                <div>thumbmark</div>
            </div>
        </div>
    </div>

    <!-- Subjective Section -->
    <div class="subjective-title">SUBJECTIVE:</div>
    <div class="case-history-container">
        <div class="case-history-header">
            <div class="case-history-label">CASE HISTORY:</div>
            <div class="case-history-fields">
                <div class="case-field">Date today<br><strong><?= $h($pir['date_today']) ?></strong></div>
                <div class="case-field">Clinician<br><strong><?= $h($currentUserName ?: $pir['clinician']) ?></strong></div>
                <div class="case-field">Clinic (encircle)<br>
                    <div style="display: flex; gap: 5px; align-items: center; font-weight: bold;">
                        <?php foreach (['I','II','III','IV'] as $v): ?>
                            <span style="<?= $h($pir['clinic'])===$v ? 'border: 2px solid red; border-radius: 50%; padding: 3px 6px; margin: 0 2px;' : 'margin: 0 2px;' ?>"><?= $v ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="case-content">
            <div class="chief-complaint">
                <strong>CHIEF COMPLAINT</strong><br>
                <div style="font-weight: normal; margin-top: 5px;"><?= $h($pir['chief_complaint']) ?></div>
            </div>
            <div class="history-illness">
                <strong>HISTORY OF PRESENT ILLNESS</strong><br>
                <div style="font-weight: normal; margin-top: 5px;"><?= $h($pir['present_illness']) ?></div>
            </div>
        </div>
    </div>

    <!-- Medical and Dental History -->
    <div class="medical-dental-container">
        <div class="medical-history-section">
            <div class="history-header">MEDICAL HISTORY</div>
            <div class="history-item"><strong>Medications taken? (Why?)</strong><br><?= $h($pir['medications_taken']) ?></div>
            <div class="history-item"><strong>Allergy to</strong><br><?= $h($pir['allergies']) ?></div>
            <div class="history-item"><strong>Past and present illnesses?</strong><br><?= $h($pir['past_illnesses']) ?></div>
            <div class="history-item"><strong>Last time examined by a physician. (Why? Result?)</strong><br><?= $h($pir['last_physician_exam']) ?></div>
            <div class="history-item"><strong>Hospitalization experience?</strong><br><?= $h($pir['hospitalization']) ?></div>
            <div class="history-item"><strong>Bleeding tendencies?</strong><br><?= $h($pir['bleeding_tendencies']) ?></div>
            <div class="history-item"><strong>Females only (contraceptives, pregnancy, changes in menstrual pattern, breastfeeding?)</strong><br><?= $h($pir['female_specific']) ?></div>
        </div>
        <div class="dental-history-section">
            <div class="history-header">DENTAL HISTORY</div>
            <div style="font-weight: normal;"><?= nl2br($h($pir['dental_history'])) ?></div>
        </div>
    </div>

    <!-- Family and Personal History -->
    <div class="family-personal-container">
        <div class="family-history">
            <strong>FAMILY HISTORY</strong><br>
            <div style="font-weight: normal; margin-top: 5px;"><?= nl2br($h($pir['family_history'])) ?></div>
        </div>
        <div class="personal-history">
            <strong>PERSONAL AND SOCIAL HISTORY</strong><br>
            <div style="font-weight: normal; margin-top: 5px;"><?= nl2br($h($pir['personal_history'])) ?></div>
        </div>
    </div>

    <!-- Review of Systems -->
    <div class="systems-review-container">
        <div class="systems-review-title">REVIEW OF SYSTEMS (do not leave any blanks)</div>
        <div class="systems-content">
            <div class="system-column">
                <div class="system-item">
                    <div class="system-label">Skin</div>
                    <div class="dotted-line"><?= $h($pir['skin']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Extremities</div>
                    <div class="dotted-line"><?= $h($pir['extremities']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Eyes</div>
                    <div class="dotted-line"><?= $h($pir['eyes']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Ears, nose throat</div>
                    <div class="dotted-line"><?= $h($pir['ent']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Summary</div>
                    <div class="dotted-line"><?= $h($pir['systems_summary']) ?></div>
                </div>
            </div>
            <div class="system-column">
                <div class="system-item">
                    <div class="system-label">Respiratory</div>
                    <div class="dotted-line"><?= $h($pir['respiratory']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Cardiovascular</div>
                    <div class="dotted-line"><?= $h($pir['cardiovascular']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Gastrointestinal</div>
                    <div class="dotted-line"><?= $h($pir['gastrointestinal']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Genitourinary</div>
                    <div class="dotted-line"><?= $h($pir['genitourinary']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
            </div>
            <div class="system-column">
                <div class="system-item">
                    <div class="system-label">Endocrine</div>
                    <div class="dotted-line"><?= $h($pir['endocrine']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Hematopoietic</div>
                    <div class="dotted-line"><?= $h($pir['hematopoietic']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Neurological</div>
                    <div class="dotted-line"><?= $h($pir['neurological']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Psychiatric</div>
                    <div class="dotted-line"><?= $h($pir['psychiatric']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
                <div class="system-item">
                    <div class="system-label">Growth or tumor</div>
                    <div class="dotted-line"><?= $h($pir['growth_or_tumor']) ?></div>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom section -->
    <div class="bottom-container">
        <div class="health-assessment">
            <strong>HEALTH ASSESSMENT:</strong> ASA (encircle)
            <div class="asa-section">
                <?php foreach (['I','II','III','IV'] as $v): ?>
                    <span style="<?= $h($pir['asa'])===$v ? 'border: 2px solid red; border-radius: 50%; padding: 5px 8px; margin: 0 5px;' : 'margin: 0 5px;' ?>"><?= $v ?></span>
                <?php endforeach; ?>
            </div>
            <div><strong>Notes:</strong></div>
            <div><?= $h($pir['asa_notes']) ?></div>
        </div>
        <div class="medical-alert-section">
            <div class="official-stamp">OFFICIAL<br>DOCUMENT</div>
            <div class="medical-alert">MEDICAL ALERT!</div>
        </div>
    </div>

    <!-- Signature -->
    <div class="signature-area-final">
      Patient's name and signature:
      <div class="signature-wrapper">
          <?php if (!empty($signaturePathPage5) && file_exists($signaturePathPage5)): ?>
              <img src="<?= $h($signaturePathPage5) ?>?t=<?= time() ?>" class="signature-img" style="max-width:120px;max-height:40px; background:none;background-color:transparent;border:none" alt="Patient Signature">
          <?php endif; ?>
          <span class="signature-name"><?= $h($signaturePrintedName) ?></span>
      </div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array('2', $pages)): ?>
<!-- =====================================================
     PAGE 2 – HEALTH QUESTIONNAIRE (LEGAL SIZE) – EXACT DATA
     ===================================================== -->
<div class="page-break"></div>

<style>
/* ---- LEGAL-SIZE PAGE 2 STYLES ---- */
@page { size: legal; margin: 0.5in; }

.page2-container {
    font-family: Arial, sans-serif;
    font-size: 8px;
    line-height: 1.1;
}
.page2-container .form-header {
    text-align: right;
    font-size: 10px;
    font-weight: bold;
    margin-bottom: 10px;
}
.page2-container .name-section {
    border: 2px solid #000;
    padding: 3px;
    margin-bottom: 8px;
    display: flex;
    font-size: 8px;
}
.page2-container .name-field {
    flex: 1;
    border-right: 1px solid #000;
    padding: 2px;
    text-align: center;
    font-weight: bold;
}
.page2-container .name-field:last-child { border-right: none; }
.page2-container .section-title {
    font-weight: bold;
    font-size: 10px;
    margin: 5px 0 3px 0;
}
.page2-container .questionnaire-note {
    font-size: 8px;
    margin-bottom: 5px;
}
.page2-container .questions-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    margin-bottom: 8px;
}
.page2-container .questions-table th,
.page2-container .questions-table td {
    border: 1px solid #000;
    padding: 2px;
    font-size: 7px;
    vertical-align: top;
}
.page2-container .questions-table th {
    background-color: #f0f0f0;
    text-align: center;
    font-weight: bold;
    font-size: 8px;
}
.page2-container .checkbox-col {
    width: 15px;
    text-align: center;
    font-weight: bold;
}
.page2-container .question-col { width: 48%; }
.page2-container .tall-row td { height: 25px; }
.page2-container .disclaimer {
    font-size: 7px;
    margin: 8px 0;
    text-align: justify;
}
.page2-container .signature-section {
    border-top: 1px solid #000;
    border-bottom: 1px solid #000;
    padding: 5px 0;
    margin: 8px 0;
    font-size: 8px;
    height: 30px;
}
.page2-container .objective-header {
    background-color: #808080;
    color: white;
    font-weight: bold;
    font-size: 10px;
    padding: 3px;
    margin: 8px 0 5px 0;
}
.page2-container .clinical-section {
    font-weight: bold;
    font-size: 9px;
    margin: 5px 0 3px 0;
}
.page2-container .examination-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    margin-bottom: 5px;
}
.page2-container .examination-table td {
    border: 1px solid #000;
    padding: 2px;
    font-size: 7px;
    vertical-align: top;
}
.page2-container .exam-header {
    background-color: #f0f0f0;
    font-weight: bold;
    font-size: 8px;
}
.page2-container .exam-row { height: 20px; }
.page2-container .dotted-line {
    border-bottom: 1px dotted #000;
    display: inline-block;
    min-width: 50px;
    font-weight: bold;
    padding: 0 3px;
}
.page2-container .final-signature {
    margin-top: 10px;
    font-size: 8px;
}
</style>

<?php
/* ---- Fetch patient health data ---- */
$stmt = $pdo->prepare("SELECT * FROM patient_health WHERE patient_id = ?");
$stmt->execute([$patientId]);
$health = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/* ---- Fetch patient basic info ---- */
$pt = $pdo->prepare("SELECT last_name, first_name, middle_initial, nickname, age, gender FROM patients WHERE id = ?");
$pt->execute([$patientId]);
$patient = $pt->fetch(PDO::FETCH_ASSOC);
$patientFullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
?>

<div class="page2-container">
    <!-- Header -->
    <div class="form-header">
        FM-LPU-DENT-01/09<br>
        Page 2 of 5
    </div>

    <!-- Patient banner -->
    <div class="name-section">
        <div class="name-field">Last name<br><?= htmlspecialchars($patient['last_name']) ?></div>
        <div class="name-field">First name<br><?= htmlspecialchars($patient['first_name']) ?></div>
        <div class="name-field">MI<br><?= htmlspecialchars($patient['middle_initial']) ?></div>
        <div class="name-field">Nickname<br><?= htmlspecialchars($patient['nickname']) ?></div>
        <div class="name-field">Age/Gender<br><?= htmlspecialchars($patient['age']) ?>/<?= htmlspecialchars($patient['gender']) ?></div>
    </div>

    <!-- Questionnaire -->
    <div class="section-title">HEALTH QUESTIONNAIRE</div>
    <div class="questionnaire-note">
        Check the box to answer all the questions. Answers to the following questions are for our records only and are confidential.
    </div>

    <table class="questions-table">
        <thead>
            <tr>
                <th class="checkbox-col">Yes</th>
                <th class="checkbox-col">No</th>
                <th class="question-col"></th>
                <th class="checkbox-col">Yes</th>
                <th class="checkbox-col">No</th>
                <th class="question-col"></th>
            </tr>
        </thead>
        <tbody>

            <!-- Row 1 -->
            <tr>
                <td class="checkbox-col"><?= ($health['last_medical_physical_yes'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['last_medical_physical_no'] ?? 0) ? '✓' : '□' ?></td>
                <td>My last medical physical evaluation was on (approximately): <?= htmlspecialchars($health['last_medical_physical'] ?? '') ?></td>
                <td class="checkbox-col"><?= ($health['abnormal_bleeding_yes'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['abnormal_bleeding_no'] ?? 0) ? '✓' : '□' ?></td>
                <td>Have you had abnormal bleeding associated with previous extractions, surgery or trauma</td>
            </tr>
            <tr>
                <td colspan="3">Why? The name and address of my personal physician is: <?= htmlspecialchars($health['physician_name_addr'] ?? '') ?></td>
                <td colspan="3"></td>
            </tr>

            <!-- Row 3 -->
            <tr>
                <td class="checkbox-col"><?= ($health['under_physician_care'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['under_physician_care'] ?? 0) ? '□' : '✓' ?></td>
                <td>Are you NOW under the care of a physician?<br>If so, what is the condition being treated? <?= htmlspecialchars($health['under_physician_care_note'] ?? '') ?></td>
                <td class="checkbox-col"><?= ($health['bruise_easily'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['bruise_easily'] ?? 0) ? '□' : '✓' ?></td>
                <td>Do you bruise easily</td>
            </tr>

            <!-- Row 4 -->
            <tr>
                <td class="checkbox-col"><?= ($health['serious_illness_operation'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['serious_illness_operation'] ?? 0) ? '□' : '✓' ?></td>
                <td>Have you ever had any serious illness or operation?<br>If so, what was the illness or operation? <?= htmlspecialchars($health['serious_illness_operation_note'] ?? '') ?></td>
                <td class="checkbox-col"><?= ($health['blood_transfusion_yes'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['blood_transfusion_no'] ?? 0) ? '✓' : '□' ?></td>
                <td>Have you ever required a blood transfusion<br>If so, under what circumstances: <?= htmlspecialchars($health['blood_transfusion_note'] ?? '') ?></td>
            </tr>

            <!-- Row 5 -->
            <tr>
                <td class="checkbox-col"><?= ($health['hospitalized'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['hospitalized'] ?? 0) ? '□' : '✓' ?></td>
                <td>Have you been hospitalized?<br>If so, when and what was the problem? <?= htmlspecialchars($health['hospitalized_note'] ?? '') ?></td>
                <td class="checkbox-col"><?= ($health['blood_disorder_yes'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['blood_disorder_no'] ?? 0) ? '✓' : '□' ?></td>
                <td>Do you have any blood disorder such as anemia, including sickle cell anemia</td>
            </tr>

            <!-- Diseases section -->
            <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">Diseases or problems</td>
            </tr>
            <?php
            $diseases = [
                ['rheumatic_fever','Rheumatic fever','heart_abnormalities','Heart abnormalities'],
                ['cardiovascular_disease','Cardiovascular disease','childhood_diseases','Childhood diseases?'],
                ['asthma_hayfever','Asthma / hay fever','hives_skin_rash','Hives / skin rash'],
                ['fainting_seizures','Fainting spells / seizures','diabetes','Diabetes'],
                ['urinate_more','Urinate >6×/day','thirsty','Thirsty often'],
                ['mouth_dry','Mouth dry','hepatitis','Hepatitis / liver'],
                ['arthritis','Arthritis','stomach_ulcers','Stomach ulcers'],
                ['kidney_trouble','Kidney trouble','tuberculosis','Tuberculosis'],
                ['venereal_disease','Venereal disease','other_conditions','Other conditions?']
            ];
            foreach ($diseases as [$k1,$l1,$k2,$l2]):
            ?>
                <tr>
                    <td class="checkbox-col"><?= ($health[$k1] ?? 0) ? '✓' : '□' ?></td>
                    <td class="checkbox-col"><?= ($health[$k1] ?? 0) ? '□' : '✓' ?></td>
                    <td><?= $l1 ?><?= $k1==='childhood_diseases' ? ' <span class="dotted-line">'.htmlspecialchars($health['childhood_diseases_note'] ?? '').'</span>' : '' ?></td>

                    <td class="checkbox-col"><?= ($health[$k2] ?? 0) ? '✓' : '□' ?></td>
                    <td class="checkbox-col"><?= ($health[$k2] ?? 0) ? '□' : '✓' ?></td>
                    <td><?= $l2 ?><?= $k2==='other_conditions' ? ' <span class="dotted-line">'.htmlspecialchars($health['other_conditions_note'] ?? '').'</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>

            <!-- Allergies -->
            <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">Allergies / adverse reactions</td>
            </tr>
            <?php
            $allergies = [
                ['anesthetic_allergy','Local anesthetics','penicillin_allergy','Penicillin / antibiotics'],
                ['aspirin_allergy','Aspirin','latex_allergy','Latex'],
                ['other_allergy','Other','','']
            ];
            foreach ($allergies as [$k1,$l1,$k2,$l2]):
            ?>
                <tr>
                    <td class="checkbox-col"><?= ($health[$k1] ?? 0) ? '✓' : '□' ?></td>
                    <td class="checkbox-col"><?= ($health[$k1] ?? 0) ? '□' : '✓' ?></td>
                    <td><?= $l1 ?><?= $k1==='other_allergy' ? ' <span class="dotted-line">'.htmlspecialchars($health['other_allergy_note'] ?? '').'</span>' : '' ?></td>

                    <td class="checkbox-col"><?= ($health[$k2] ?? 0) ? '✓' : '□' ?></td>
                    <td class="checkbox-col"><?= ($health[$k2] ?? 0) ? '□' : '✓' ?></td>
                    <td><?= $l2 ?></td>
                </tr>
            <?php endforeach; ?>

            <!-- Free-text rows -->
            <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                    Taking any drugs/medicines: <span class="dotted-line"><?= htmlspecialchars($health['taking_drugs'] ?? '') ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                    Previous dental trouble: <span class="dotted-line"><?= htmlspecialchars($health['previous_dental_trouble'] ?? '') ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                    Other conditions / notes: <span class="dotted-line"><?= htmlspecialchars($health['other_problem'] ?? '') ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                    X-ray exposure: <span class="dotted-line"><?= htmlspecialchars($health['xray_exposure'] ?? '') ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                    Eyeglasses / contacts: <span class="dotted-line"><?= htmlspecialchars($health['eyeglasses'] ?? '') ?></span>
                </td>
            </tr>

            <!-- WOMEN -->
            <tr>
                <td colspan="3" class="border px-2 py-1 font-semibold">WOMEN</td>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td class="checkbox-col"><?= ($health['pregnant'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['pregnant'] ?? 0) ? '□' : '✓' ?></td>
                <td>Pregnant / missed period</td>
                <td class="checkbox-col"><?= ($health['breast_feeding'] ?? 0) ? '✓' : '□' ?></td>
                <td class="checkbox-col"><?= ($health['breast_feeding'] ?? 0) ? '□' : '✓' ?></td>
                <td>Breast feeding</td>
            </tr>
        </tbody>
    </table>

    <!-- Disclaimer -->
    <div class="disclaimer">
        To the best of my knowledge, all of the preceding answers are true and correct. If I ever have any change in my health, or have any abnormal laboratory tests, or if my medicines change, I will inform the dentist at my next appointment without fail.
    </div>

    <!-- Signature -->
    <div class="signature-section">
        Signature of the Patient (if age >18) or Guardian / date<br>
        (verifying accuracy of historical information)
    </div>

    <!-- Clinical Examination section (unchanged) -->
    <div class="objective-header">OBJECTIVE</div>
    <div class="clinical-section">
        CLINICAL EXAMINATION (Do not leave any blanks) (When indicated, encircle findings, then describe on blank)
    </div>

    <table class="examination-table">
        <!-- GENERAL APPRAISAL -->
        <tr>
            <td class="exam-header">GENERAL APPRAISAL</td>
            <td>
                General health notes: <span class="dotted-line"><?= htmlspecialchars($health['general_health_notes'] ?? '') ?></span>
                Physical <span class="dotted-line"><?= htmlspecialchars($health['physical'] ?? '') ?></span>
                Mental <span class="dotted-line"><?= htmlspecialchars($health['mental'] ?? '') ?></span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td>
                Vital Signs: T° <span class="dotted-line"><?= htmlspecialchars($health['vital_signs'] ?? '') ?></span>
                BP <span class="dotted-line"></span>
                RR <span class="dotted-line"></span>
                PR <span class="dotted-line"></span>
            </td>
        </tr>
        <tr>
            <td>Other</td>
            <td><span class="dotted-line"><?= htmlspecialchars($health['general_other'] ?? '') ?></span></td>
        </tr>

        <!-- EXTRA-ORAL -->
        <tr><td class="exam-header">EXTRAORAL EXAMINATION</td><td></td></tr>
        <tr class="exam-row">
            <td>
                Head and Face <span class="dotted-line"><?= htmlspecialchars($health['extra_head_face'] ?? '') ?></span>
                Eyes <span class="dotted-line"><?= htmlspecialchars($health['extra_eyes'] ?? '') ?></span>
                Ears <span class="dotted-line"><?= htmlspecialchars($health['extra_ears'] ?? '') ?></span>
                Nose <span class="dotted-line"><?= htmlspecialchars($health['extra_nose'] ?? '') ?></span>
            </td>
            <td>
                Hair <span class="dotted-line"><?= htmlspecialchars($health['extra_hair'] ?? '') ?></span>
                Neck <span class="dotted-line"><?= htmlspecialchars($health['extra_neck'] ?? '') ?></span>
                Parotid region <span class="dotted-line"><?= htmlspecialchars($health['extra_paranasal'] ?? '') ?></span>
                Lymph nodes <span class="dotted-line"><?= htmlspecialchars($health['extra_lymph'] ?? '') ?></span>
            </td>
        </tr>
        <tr class="exam-row">
            <td>
                Salivary glands <span class="dotted-line"><?= htmlspecialchars($health['extra_salivary'] ?? '') ?></span>
                TMJ <span class="dotted-line"><?= htmlspecialchars($health['extra_tmj'] ?? '') ?></span>
                Muscles of mastication <span class="dotted-line"><?= htmlspecialchars($health['extra_muscles'] ?? '') ?></span>
            </td>
            <td>Other: <span class="dotted-line"><?= htmlspecialchars($health['extra_other'] ?? '') ?></span></td>
        </tr>

        <!-- INTRA-ORAL -->
        <tr><td class="exam-header">INTRAORAL EXAMINATION</td><td></td></tr>
        <tr><td colspan="2">Oral mucosa:</td></tr>
        <tr class="exam-row">
            <td>
                Lips <span class="dotted-line"><?= htmlspecialchars($health['intra_lips'] ?? '') ?></span>
                Buccal mucosa <span class="dotted-line"><?= htmlspecialchars($health['intra_buccal'] ?? '') ?></span>
                Alveolar mucosa <span class="dotted-line"><?= htmlspecialchars($health['intra_alveolar'] ?? '') ?></span>
                Floor of the mouth <span class="dotted-line"><?= htmlspecialchars($health['intra_floor'] ?? '') ?></span>
            </td>
            <td>
                Tongue <span class="dotted-line"><?= htmlspecialchars($health['intra_tongue'] ?? '') ?></span>
                Saliva <span class="dotted-line"><?= htmlspecialchars($health['intra_saliva'] ?? '') ?></span>
                Pillars of fauces <span class="dotted-line"><?= htmlspecialchars($health['intra_pillars'] ?? '') ?></span>
                Tonsils <span class="dotted-line"><?= htmlspecialchars($health['intra_tonsils'] ?? '') ?></span>
            </td>
        </tr>
        <tr class="exam-row">
            <td>
                Uvula <span class="dotted-line"><?= htmlspecialchars($health['intra_uvula'] ?? '') ?></span>
                Oropharynx <span class="dotted-line"><?= htmlspecialchars($health['intra_oropharynx'] ?? '') ?></span>
            </td>
            <td>Other: <span class="dotted-line"><?= htmlspecialchars($health['intra_other'] ?? '') ?></span></td>
        </tr>

        <!-- Periodontal -->
        <tr>
            <td class="exam-header">Periodontal Examination (encircle)</td>
            <td class="exam-header">Occlusion:</td>
        </tr>
        <tr class="exam-row">
            <td>
                Gingiva:
                <div style="display: inline-flex; gap: 8px; align-items: center; font-weight: bold; margin-left: 5px;">
                    <?php foreach (['Healthy','Inflamed'] as $v): ?>
                        <span style="<?= htmlspecialchars($health['perio_gingiva_status'] ?? '')===$v ? 'border: 2px solid red; border-radius: 50%; padding: 2px 6px; margin: 0 2px;' : 'margin: 0 2px;' ?>"><?= $v ?></span>
                    <?php endforeach; ?>
                </div><br>
                Degree of inflammation:
                <div style="display: inline-flex; gap: 8px; align-items: center; font-weight: bold; margin-left: 5px;">
                    <?php foreach (['Mild','Moderate','Severe'] as $v): ?>
                        <span style="<?= htmlspecialchars($health['perio_inflammation_degree'] ?? '')===$v ? 'border: 2px solid red; border-radius: 50%; padding: 2px 6px; margin: 0 2px;' : 'margin: 0 2px;' ?>"><?= $v ?></span>
                    <?php endforeach; ?>
                </div><br>
                Degree of deposits:
                <div style="display: inline-flex; gap: 8px; align-items: center; font-weight: bold; margin-left: 5px;">
                    <?php foreach (['Light','Moderate','Heavy'] as $v): ?>
                        <span style="<?= htmlspecialchars($health['perio_deposits_degree'] ?? '')===$v ? 'border: 2px solid red; border-radius: 50%; padding: 2px 6px; margin: 0 2px;' : 'margin: 0 2px;' ?>"><?= $v ?></span>
                    <?php endforeach; ?>
                </div>
            </td>
            <td>
                Molar Class: L <?= htmlspecialchars($health['occl_molar_l'] ?? '') ?> R <?= htmlspecialchars($health['occl_molar_r'] ?? '') ?><br>
                Canine Class: <?= htmlspecialchars($health['occl_canine'] ?? '') ?><br>
                Incisal Class: <?= htmlspecialchars($health['occl_incisal'] ?? '') ?><br>
                Overjet: <?= htmlspecialchars($health['occl_overjet'] ?? '') ?> Overbite: <?= htmlspecialchars($health['occl_overbite'] ?? '') ?><br>
                Midline Deviation: <?= htmlspecialchars($health['occl_midline'] ?? '') ?> Crossbite: <?= htmlspecialchars($health['occl_crossbite'] ?? '') ?>
            </td>
        </tr>
        <tr>
            <td>Other: <span class="dotted-line"><?= htmlspecialchars($health['perio_other'] ?? '') ?></span></td>
            <td class="exam-header">Appliances: <span class="dotted-line"><?= htmlspecialchars($health['occl_appliances'] ?? '') ?></span></td>
        </tr>
    </table>

    <!-- Signature -->
    <div class="signature-area-final">
      Patient's name and signature:
      <div class="signature-wrapper">
          <?php if (!empty($signaturePathPage5) && file_exists($signaturePathPage5)): ?>
              <img src="<?= $h($signaturePathPage5) ?>?t=<?= time() ?>" class="signature-img" style="max-width:120px;max-height:40px; background:none;background-color:transparent;border:none" alt="Patient Signature">
          <?php endif; ?>
          <span class="signature-name"><?= $h($signaturePrintedName) ?></span>
      </div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array('3', $pages)): ?>
<div class="page-break"></div>
<div class="page3">

<style>
    /* --- existing page-3 styles (unchanged) --- */
    .page3 .header-section {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 20px;
    }
    .page3 .title {
        font-weight: bold;
        font-size: 16px;
        white-space: nowrap;
        margin-right: 10px;
        padding-top: 8px;
    }
    .page3 .patient-info-box {
        border: 2px solid #000;
        flex: 1;
        padding: 8px;
        min-height: 30px;
    }
    .page3 .patient-fields {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .page3 .patient-fields .field-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        font-size: 11px;
    }
    .page3 .patient-fields .field-label {
        font-size: 10px;
        font-weight: normal;
    }
    .page3 .patient-fields .field-value {
        font-weight: bold;
    }
    .page3 .date-info-box {
        border: 2px solid #000;
        padding: 8px;
        width: 220px;
        min-height: 30px;
    }
    .page3 .date-fields {
        display: flex;
        flex-direction: column;
        gap: 5px;
        font-size: 11px;
    }
    .page3 .date-field {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        border-bottom: 1px dotted #000;
    }
    .page3 .date-field span:first-child {
        font-weight: normal;
        margin-right: 5px;
    }
    .page3 .date-field span:last-child {
        font-weight: bold;
    }

    /* --- NEW CASE-HISTORY styles (inlined) --- */
 /* --- compact right-aligned CASE HISTORY (two-row) --- */
 .case-history-box {
    border: 1px solid #000;
    padding: 4px 6px;
    width: 300px;             /* fixed width */
    margin-left: auto; 
    margin-top: 10px;       /* push to right */
    margin-bottom: 15px;
    font-size: 10px;
}
.case-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 2px;
}
.case-label {
    font-weight: bold;
    margin-right: 3px;
    white-space: nowrap;
}
.case-dots {
    flex: 1;
    border-bottom: 1px dotted #000;
    min-width: 70px;
    margin-right: 8px;
}
.case-date-dots {
    border-bottom: 1px dotted #000;
    width: 65px;
}

</style>

    <div class="header-section">
        <div class="title">Dental Examination:</div>
        
        <div class="patient-info-box">
            <div class="patient-fields">
                <div class="field-item">
                    <span class="field-value"><?= $h($patient['last_name']) ?></span>
                    <span class="field-label">Last name</span>
                </div>
                <div class="field-item">
                    <span class="field-value"><?= $h($patient['first_name']) ?></span>
                    <span class="field-label">First name</span>
                </div>
                <div class="field-item">
                    <span class="field-value"><?= $h($patient['middle_initial']) ?></span>
                    <span class="field-label">MI</span>
                </div>
                <div class="field-item">
                    <span class="field-value"><?= $h($patient['age']) ?> / <?= $h($patient['gender']) ?></span>
                    <span class="field-label">Age/Gender</span>
                </div>
            </div>
        </div>
        
        <div class="date-info-box">
            <div class="date-fields">
                <div class="date-field">
                    <span>Date examined</span>
                    <span><?= $h($exam['date_examined'] ?? '') ?></span>
                </div>
                <div class="date-field">
                    <span>Clinician</span>
                    <span><?= $h($currentUserName ?: ($exam['clinician'] ?? '')) ?></span>
                </div>
                <div class="date-field">
                    <span>Checked by</span>
                    <span><?= $h($ciName) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="section-title3">Diagnostic Tests</div>
    <div style="white-space: pre-wrap;"><?= $h($exam['diagnostic_tests'] ?? '') ?></div>

    <div class="section-title3">Tooth Chart</div>
    <div class="tooth-chart-container">
        <?php if (!empty($exam['tooth_chart_drawing_path'])): ?>
        <img src="<?= $h($exam['tooth_chart_drawing_path']) ?>" class="tooth-chart-img" alt="Tooth Chart">
        <?php else: ?>
        <div class="photo-frame" style="width:100%;height:300px;">No chart uploaded</div>
        <?php endif; ?>
    </div>

    <div class="section-title3">Notes (other clinical findings)</div>
    <div style="white-space: pre-wrap;"><?= $h($exam['other_notes'] ?? '') ?></div>

    <!-- ===== NEW Assessment & Plan Table ===== -->
<div class="section-title3">Assessment & Plan</div>

<style>
    /* inline styles that match the supplied snippet */
    .assessment-table {
        width: 100%;
        border-collapse: collapse;
        border: 2px solid #000;
    }
    .assessment-table thead tr.header-row {
        background-color: #d3d3d3;
        font-weight: bold;
    }
    .assessment-table th.header-cell {
        border: 1px solid #000;
        padding: 1px;
        text-align: center;
        font-weight: bold;
    }
    .assessment-table td.sequence-cell {
        border: 1px solid #000;
        padding: 1px;
        width: 80px;
        font-weight: bold;
        text-align: center;
        vertical-align: top;
        background-color: #f0f0f0;
    }
    .assessment-table td.tooth-cell {
        border: 1px solid #000;
        padding: 1px;
        width: 40px;
        text-align: center;
        vertical-align: top;
    }
    .assessment-table td.content-cell {
        border: 1px solid #000;
        padding: 1px;
        vertical-align: top;
        text-align: center;
        min-height: 40px;
    }
    .assessment-table td.problems-cell   { width: 120px; }
    .assessment-table td.treatment-cell  { width: 120px; }
    .assessment-table td.prognosis-cell  { width: 80px; }
    .assessment-table tr.disease-control-row td {
        height: 50px;
    }
</style>

<div class="table-container" style="margin:0;padding:0;background:none;">
    <table class="assessment-table">
        <thead>
            <tr class="header-row">
                <th class="header-cell" style="font-size:18px" colspan="1">ASSESSMENT</th>
                <th class="header-cell" style="font-size:18px" colspan="4">PLAN</th>
            </tr>
            <tr class="header-row">
                <th class="header-cell">SEQUENCE</th>
                <th class="header-cell">TOOTH&nbsp;#</th>
                <th class="header-cell">PROBLEMS/DIAGNOSES</th>
                <th class="header-cell">TREATMENT&nbsp;PLAN</th>
                <th class="header-cell">PROGNOSIS</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rows = json_decode($exam['assessment_plan_json'] ?? '[]', true);
            $defaults = [
                'MAIN CONCERN (PRIORITY)',
                'I. SYSTEMIC PHASE',
                'II. ACUTE PHASE',
                'III. DISEASE CONTROL PHASE',
                'IV. DEFINITIVE PHASE',
                'V. MAINTENANCE PHASE'
            ];
            foreach ($defaults as $i => $seq):
                $isDiseaseControl = ($seq === 'III. DISEASE CONTROL PHASE');
            ?>
            <tr <?= $isDiseaseControl ? 'class="disease-control-row"' : '' ?>>
                <td class="sequence-cell <?= $i === 0 ? 'main-concern' : 'phase-text' ?>">
                    <?= $h($seq) ?>
                </td>
                <td class="tooth-cell"><?= $h($rows[$i]['tooth'] ?? '') ?></td>
                <td class="content-cell problems-cell"><?= $h($rows[$i]['diagnosis'] ?? '') ?></td>
                <td class="content-cell treatment-cell"><?= $h($rows[$i]['plan'] ?? '') ?></td>
                <td class="content-cell prognosis-cell"><?= $h($rows[$i]['prognosis'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

    <!-- ===== CASE HISTORY ===== -->
<!-- ===== CASE HISTORY (center-start, right-end) ===== -->
<div class="case-history-box">
  <div style="text-align: center; font-weight: bold; margin-bottom: 0.5em;">CASE HISTORY</div>

  <div class="case-row">
      <span class="case-label">Performed by</span>
      <span class="case-dots"><?= $h($exam['history_performed_by'] ?? '') ?></span>
      <span class="case-label">Date</span>
      <span class="case-date-dots"><?= $h($exam['history_performed_date'] ?? '') ?></span>
  </div>
  <div class="case-row">
      <span class="case-label">Checked by</span>
      <span class="case-dots"><?= $h($ciName) ?></span>
      <span class="case-label">Date</span>
      <span class="case-date-dots"><?= $h($exam['history_checked_date'] ?? '') ?></span>
  </div>
</div>



    <div class="signature-area-final">
      Patient's name and signature:
      <div class="signature-wrapper">
          <?php if (!empty($signaturePathPage5) && file_exists($signaturePathPage5)): ?>
              <img src="<?= $h($signaturePathPage5) ?>?t=<?= time() ?>" class="signature-img" style="max-width:120px;max-height:40px; background:none;background-color:transparent;border:none" alt="Patient Signature">
          <?php endif; ?>
          <span class="signature-name"><?= $h($signaturePrintedName) ?></span>
      </div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array('4', $pages)): ?>
<div class="page-break"></div>
<div class="page4">

  <style>
    /* Ensures all text is small and compact to fit the page */
    .page4 * {
      font-size: 8pt !important;
    }
    .consent-text p,
    .consent-text li,
    .authorization-text,
    .page4-title,
    .section4-title,
    .sig-label,
    .banner-2 label, .banner-2 span {
      font-size: 8pt !important;
      margin: 0;
    }
    .consent-text p {
      margin-bottom: 2px; /* A small gap between paragraphs for readability */
    }
    .data-privacy-signature-group {
    display: flex;
    align-items: flex-end;
    gap: 20px;
    margin: 20px 0;
}

.signature-part {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.signature-line {
    height: 40px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 5px;
    margin-bottom: 5px;
}

.signature-line img {
    max-height: 30px;
    max-width: 200px;
}

.name-line {
    border-bottom: 2px solid #000;
    padding: 5px 0;
    margin-bottom: 10px;
    text-align: center;
}

.name-line span {
    font-weight: normal;
}

.separator {
    font-size: 24px;
    font-weight: bold;
    margin: 0 10px;
    align-self: center;
}

.date-part {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}

.date-line {
    border-bottom: 2px solid #000;
    padding: 5px 0;
    margin-bottom: 10px;
    text-align: center;
}

.label-text {
    font-size: 12px;
    color: #666;
    text-align: center;
    margin-top: 5px;
}
  </style>

<h2 class="page4-title" style="text-align:left;margin-bottom:4px;">INFORMED CONSENT:</h2>

  <div class="consent-text">
    <p><b><u>TREATMENT TO BE DONE.</u></b> I understand and consent to have any treatment done by the dentist after the procedure, the risk & benefits and cost have been fully explained. These treatments include, but are not limited to, x-rays, cleanings, periodontal treatments, fillings, crowns, bridges, all prosthodontic, root canal treatments and orthodontic treatments, and all minor and major dental procedures. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_treatment'] ?? '') ?></span></span></p>
    <p><b><u>DRUGS & MEDICATIONS.</u></b> I understand that antibiotics, analgesics and other medications can cause allergic reactions causing redness and swelling of tissues, pain, itching, vomiting, and/or anaphylactic shock). <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_drugs'] ?? '') ?></span></span></p>
    <p><b><u>CHANGES IN TREATMENT PLAN.</u></b> I understand that during treatment it may be necessary to change/add procedures because of conditions found while working on the teeth that were not discovered during examination. For instance, root canal therapy following a crown or a filling procedures. I give my permission to the dentist to make any/all changes and additions as necessary with my responsibility to pay all the costs agreed. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_changes'] ?? '') ?></span></span></p>
    <p><b><u>RADIOGRAPH.</u></b> I understand that x-ray shot or a radiograph maybe necessary as part of diagnosis and to come up with tentative diagnosis of my dental problem and to make a better plan management of my dental treatment. 100% assurance for the success of the treatment since all dental treatments are subject to unpredictable complications that later on may lead to sudden change of treatment plan and subject to new charges. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_radiographs'] ?? '') ?></span></span></p>
    <p><b><u>REMOVAL OF TEETH.</u></b> I understand that alternatives to tooth removal exist (root canal therapy, crowns & periodontal surgery, etc.) and I agree to their removal. With alternatives, including their risk & benefits prior to authorizing the dentist to remove teeth and any others necessary for reasons above. I understand the risk involved in having teeth removed, some of which are pain, swelling, spread of infection, dry socket, fractured jaw, post-operative bleeding and numbness of the lip, tongue, teeth, chin, or gums that may persist for a few weeks or permanently. I understand the need of specialist if complications arise during or following treatment. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_removal_teeth'] ?? '') ?></span></span></p>
    <p><b><u>CROWNS, CAPS, BRIDGES.</u></b> I understand that sometimes it is not possible to match the color of natural teeth exactly with artificial teeth. I further understand that I may be wearing temporary crowns, which may come off easily and that I must be careful to ensure that they are kept on until the permanent crowns are delivered. I realize the final opportunity to make changes in my new crown, bridge, or cap (including shape, fit, size, and color) will be before cementation. It is also my responsibility to return for permanent cementation within 20 days from tooth preparation. Excessive delay may allow for decay, tooth movement, gum disease, or other problems. If I fail to return within this time, the dentist is not responsible for any problems resulting from my failure to return. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_crowns'] ?? '') ?></span></span></p>
    <!-- NEW ENDODONTICS -->
    <p><b><u>ENDODONTICS (ROOT CANAL).</u></b> I understand there is no guarantee that root canal treatment will save a tooth and that complications can occur from the treatment and that occasionally root canal filling materials may extend through that tooth which does not necessarily affect the success of the treatment. I understand that endodontic files and drills are very fine instruments and stresses vented in their manufacture & clarifications present in teeth can cause them to break during use. I understand that referral to the endodontist for additional treatments may be necessary following any root canal treatment and I agree that I am responsible for any additional cost for treatment performed by the endodontist. I understand that a tooth may require extraction in spite of all efforts to save it. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_endodontics'] ?? '') ?></span></span></p>

    <!-- NEW PERIODONTAL -->
    <p><b><u>PERIODONTAL DISEASE.</u></b> I understand that periodontal disease is a serious condition causing gums & bone inflammation and/or loss and that can lead to the loss of my teeth. I understand that alternative treatment plans to correct periodontal disease, including gum surgery tooth extractions with or without replacement. I understand that undertaking these treatments does not guarantee the elimination of the disease. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_periodontal'] ?? '') ?></span></span></p>
    <p><b><u>DENTURES.</u></b> I understand the wearing of dentures is difficult. Sore spots, altered speech, and difficulty in eating are common problems. Immediate dentures (placement of dentures immediately after extractions) may be uncomfortable. I realize that dentures may require adjustments and relining (at additional cost) to fit properly. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_dentures'] ?? '') ?></span></span></p>
    <p><b><u>FILLINGS.</u></b> I understand that care must be exercised in chewing on fillings during the first 24 hours to avoid breakage. I understand that a more extensive filling than originally diagnosed may be required due to additional decay found during preparation. I understand that significant sensitivity is a common but usually temporary after effect of a newly placed filling. <span class="initials-field">(Initial) <span class="line-for-initial"><?= $h($consent['consent_fillings'] ?? '') ?></span></span></p>
    <p><b>I understand that dentistry is not an exact science and that no dentist can properly guarantee results.</b></p>
  </div>

  <div class="authorization-text">
    I hereby authorize any of the dentists to proceed with and perform the dental restorations & treatments as explained to me. I understand that this is subject to modification depending on my diagnosed circumstances that may arise during the course of treatment. I understand that regardless of my dental insurance coverage I may have I am responsible for payment of dental fees. I agree to pay all attorney's fees, collection fee, or court costs that may be incurred to satisfy any obligation to this office. All treatment were properly explained to me and I was given a chance to ask questions and were properly answered. The attending dentist will not be held liable since it is my free will with full trust and confidence to undergo dental treatment under their care.
  </div>

  <!-- FIRST ROW -->
<div class="signature-area-main"
     style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:15px;align-items:end;text-align:center;">
  <div class="sig-group">
    <div class="sig-line" style="height:40px;display:flex;align-items:center;justify-content:center;">
      <!-- CACHE BUSTING: Added ?t=time() to force reload -->
      <?php if (!empty($consent['patient_signature']) && file_exists($consent['patient_signature'])): ?>
                  <img src="<?= $h($consent['patient_signature']) ?>?t=<?= time() ?>" style="height:100%;width:auto;max-width:100%;">
              <?php endif; ?>
    </div>
    <div class="sig-label" style="text-align:center;">Patient/Parent/Guardian's Signature</div>
  </div>
  <div class="sig-group">
    <div class="sig-line"><?= $h($consent['witness_signature']) ?></div>
    <div class="sig-label" style="text-align:center;">Witness</div>
  </div>
  <div class="sig-group">
    <div class="sig-line"><?= $h($consent['consent_date']) ?></div>
    <div class="sig-label" style="text-align:center;">Date</div>
  </div>
</div>

<!-- SECOND ROW (aligned under Witness & Date) -->
<div class="signature-area-main"
     style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:15px;align-items:end;text-align:center;margin-top:4px">
  <div></div> <!-- spacer -->
  <div class="sig-group">
    <div class="sig-line"><?= $h($currentUserName ?: $consent['clinician_signature']) ?></div>
    <div class="sig-label" style="text-align:center;">Clinician</div>
  </div>
  <div class="sig-group">
    <div class="sig-line"><?= $h($consent['clinician_date']) ?></div>
    <div class="sig-label" style="text-align:center;">Date</div>
  </div>
</div>

<hr style="border:none;border-top:1px dotted #000;margin:8px 0">
<h3 class="section4-title" style="text-align:center;margin-bottom:6px">DATA PRIVACY CONSENT</h3>
  <p class="consent-text">I hereby declare that by signing:</p>
  <ol class="consent-text">
    <li>I attest that the information I have written is true and correct to the best of my personal knowledge.</li>
    <li>I signify my consent to the collection use, recording, storing, organizing, consolidation, updating, processing access to transfer, disclosure of my personal and sensitive personal information to LPU-B, or any of its authorized representatives, agents, affiliates, external providers, local and foreign authorities regardless of their location and for registration for the purposes for which it was collected and other legal or regular purposes, based on our R.A. no. 10173, otherwise known as...</li>
    <li>I understand that upon my written request and subject to designated office hours of the LPU-B, I will be provided with the reasonable access to my personal information provided to LPU-B to verify the accuracy and completeness of my information and request for its amendment should it be found inaccurate.</li>
    <li>I am aware that my consent or permission I am giving in favor of LPU-B shall be effective immediately upon signing of this form and shall continue unless I revoke the same in writing. Revoking my consent will mean that I may no longer continue with or immediately cease from performing the acts mentioned under paragraph 2 herein concerning my personal and sensitive personal information.</li>
  </ol>

  <div style="display:flex;flex-direction:column;align-items:center;font-size:11px;margin:2px 0">
  <div style="display:flex;align-items:flex-end;gap:4px">
    <div style="text-align:center">
      <!-- CACHE BUSTING: Added ?t=time() to force reload -->
      <?php if (!empty($consent['data_privacy_signature_path']) && file_exists($consent['data_privacy_signature_path'])): ?>
                  <img src="<?= $h($consent['data_privacy_signature_path']) ?>?t=<?= time() ?>" style="max-width:120px;max-height:40px; background:none;background-color:transparent;border:none"alt="Signature">
              <?php endif; ?>
      <div style="border-bottom:1px solid #000;margin:0 2px"><?= $h($patient['first_name'].' '.$patient['last_name']) ?></div>
      <small>Signature over printed name</small>
    </div>
    <span style="font-weight:bold">/</span>
    <div style="text-align:center">
      <div style="border-bottom:1px solid #000;margin:0 2px"><?= $h($consent['data_privacy_date']) ?></div>
      <small>Date</small>
    </div>
  </div>
</div>

</div>
<?php endif; ?>

<?php if (in_array('5', $pages)): ?>
<!-- =====================================================
     PAGE 5 – PROGRESS NOTES (REVISED LAYOUT)
     ===================================================== -->
<div class="page-break"></div>
<div class="page5">

  <div class="form-code5">FM-LPU-DENT-01/09<br>Page 5 of 5</div>

  <div class="top-section">
    <div style="flex-grow: 1; display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start;">
        <div class="detail-field">
            <label>Patient's name</label>
            <span class="line"><?= $h($patient['first_name'].' '.$patient['last_name']) ?></span>
        </div>
        <div class="detail-field age-gender">
            <label>Age / Gender</label>
            <span class="line"><?= $h($patient['age'].' / '.$patient['gender']) ?></span>
        </div>
    </div>
    <div class="alert-box">MEDICAL ALERT!</div>
 </div>

  <table class="progress-table">
    <thead>
      <tr>
        <th class="col-date">Date</th>
        <th class="col-tooth">Tooth</th>
        <th class="col-notes">Progress Notes</th>
        <th class="col-clinician">Clinician</th>
        <th class="col-ci">CI</th>
        <th class="col-remarks">Remarks</th>
      </tr>
    </thead>
    <tbody>
      <?php
        // Fetch existing notes from the database
        $notes = $pdo->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id");
        $notes->execute([$patientId]);
        $rows = $notes->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row):
      ?>
        <tr>
          <td><?= $h($row['date']) ?></td>
          <td><?= $h($row['tooth']) ?></td>
          <td><?= nl2br($h($row['progress'])) ?></td>
          <td><?= $h($currentUserName ?: $row['clinician']) ?></td>
          <td><?= $h($row['ci']) ?></td>
          <td><?= nl2br($h($row['remarks'])) ?></td>
        </tr>
      <?php 
        endforeach; 

        // ADDED loop to print empty rows to fill the page, creating the form look
        $rowCount = count($rows);
        $totalRows = 25; // Adjusted row count to make space for signature
        for ($i = $rowCount; $i < $totalRows; $i++):
      ?>
        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>

  <!-- ===================================================================
       REVISED: Final Signature Area to match the image
       =================================================================== -->
  <div class="signature-area-final">
    Patient's name and signature:
    <div class="signature-wrapper">
        <!-- CACHE BUSTING: Added ?t=time() to force reload -->
        <?php if (!empty($signaturePathPage5) && file_exists($signaturePathPage5)): ?>
            <img src="<?= $h($signaturePathPage5) ?>?t=<?= time() ?>" class="signature-img" style="max-width:120px;max-height:40px; background:none;background-color:transparent;border:none" alt="Patient Signature">
        <?php endif; ?>
        <span class="signature-name"><?= $h($signaturePrintedName) ?></span>
    </div>
  </div>

</div>
<?php endif; ?>


</body>
</html>