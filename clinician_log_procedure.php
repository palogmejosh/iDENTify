<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Redirect if not Clinician or Admin
if (!in_array($role, ['Clinician', 'Admin'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = 'success';

// Handle success/error messages
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = 'Procedure logged successfully!';
    $messageType = 'success';
} elseif (isset($_GET['error'])) {
    $message = 'Error logging procedure: ' . htmlspecialchars($_GET['error']);
    $messageType = 'error';
}

// Get all patients (Admin sees all, Clinician sees only their own)
if ($role === 'Admin') {
    // Admin sees all patients
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.first_name, 
            p.last_name,
            p.middle_initial,
            p.age, 
            p.gender,
            p.treatment_hint
        FROM patients p
        ORDER BY p.last_name, p.first_name
    ");
    $stmt->execute();
} else {
    // Clinician sees only patients they created
    $clinicianId = $user['id'];
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.first_name, 
            p.last_name,
            p.middle_initial,
            p.age, 
            p.gender,
            p.treatment_hint
        FROM patients p
        WHERE p.created_by = ?
        ORDER BY p.last_name, p.first_name
    ");
    $stmt->execute([$clinicianId]);
}
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all clinicians for dropdown
$cliniciansStmt = $pdo->prepare("
    SELECT id, full_name
    FROM users
    WHERE role = 'Clinician' AND account_status = 'active'
    ORDER BY full_name ASC
");
$cliniciansStmt->execute();
$clinicians = $cliniciansStmt->fetchAll(PDO::FETCH_ASSOC);

$fullName = $user ? $user['full_name'] : 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Log Procedure</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
    <link rel="stylesheet" href="styles.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        .modal-fade {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-gradient-to-br dark:from-violet-950 dark:via-gray-900 dark:to-purple-950 transition-colors duration-200">
    <?php include 'includes/header.php'; ?>

    <?php include 'includes/sidebar.php'; ?>

    <div class="flex ml-64 mt-16">

        <!-- Main Content -->
        <main class="flex-1 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6 main-content overflow-y-auto min-h-screen">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-6">
                    Log a Procedure
                </h2>

                <!-- Notification/Alert Box -->
                <?php if ($message): ?>
                    <div id="alertBox" class="mb-6">
                        <div class="<?php echo $messageType === 'success' ? 'bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900 border-green-400 dark:border-green-600 text-green-700 dark:text-green-200' : 'bg-gradient-to-r from-red-100 to-rose-100 dark:from-red-900 dark:to-rose-900 border-red-400 dark:border-red-600 text-red-700 dark:text-red-200'; ?> border px-4 py-3 rounded-lg shadow-lg relative">
                            <span><?php echo htmlspecialchars($message); ?></span>
                            <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3 hover:opacity-75">
                                <i class="ri-close-line"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Procedure Log Form -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-8">
                    <form id="procedureLogForm" method="POST" action="save_procedure_log.php" class="space-y-6">
                        <!-- Patient Selection -->
                        <div>
                            <label for="patientSelect" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                Select Patient <span class="text-red-500">*</span>
                            </label>
                            <select id="patientSelect" name="patient_id" required 
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select a patient --</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>"
                                            data-firstname="<?php echo htmlspecialchars($patient['first_name']); ?>"
                                            data-lastname="<?php echo htmlspecialchars($patient['last_name']); ?>"
                                            data-middleinitial="<?php echo htmlspecialchars($patient['middle_initial'] ?? ''); ?>"
                                            data-age="<?php echo htmlspecialchars($patient['age'] ?? ''); ?>"
                                            data-gender="<?php echo htmlspecialchars($patient['gender'] ?? ''); ?>"
                                            data-hint="<?php echo htmlspecialchars($patient['treatment_hint'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Patient Information (Auto-filled) -->
                        <div class="bg-violet-50 dark:bg-gray-700 rounded-lg p-6 space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Patient Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Patient's Name</label>
                                    <input type="text" id="patientName" readonly 
                                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-600 dark:text-white"
                                           placeholder="Select a patient first">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Age</label>
                                    <input type="text" id="patientAge" readonly 
                                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-600 dark:text-white"
                                           placeholder="--">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sex</label>
                                    <input type="text" id="patientGender" readonly 
                                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-600 dark:text-white"
                                           placeholder="--">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Procedure Details</label>
                                    <input type="text" id="procedureHint" readonly
                                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-600 dark:text-white"
                                           placeholder="--">
                                </div>
                            </div>
                        </div>

                        <!-- Procedure Selection -->
                        <div>
                            <label for="procedureSelect" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                Select Treatment Plan/Procedure <span class="text-red-500">*</span>
                            </label>
                            <select id="procedureSelect" name="procedure_selected" required 
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                                    disabled>
                                <option value="">-- Select a patient first --</option>
                            </select>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                <i class="ri-information-line"></i> Treatment plans are loaded from the patient's dental examination record.
                            </p>
                        </div>

                        <!-- Procedure Details and Chair Number -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Procedure Details -->
                            <div>
                                <label for="procedureDetails" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    Procedure Details
                                </label>
                                <input type="text" id="procedureDetails" name="procedure_details" 
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Additional procedure details (optional)">
                            </div>

                            <!-- Chair Number -->
                            <div>
                                <label for="chairNumber" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    Chair
                                </label>
                                <input type="text" id="chairNumber" name="chair_number" 
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Chair number (e.g., 1, 2A, C3)">
                            </div>
                        </div>

                        <!-- Remarks Selection -->
                        <div>
                            <label for="remarksSelect" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                Select Remarks (from Progress Notes)
                            </label>
                            <select id="remarksSelect" name="remarks" 
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                                    disabled>
                                <option value="">-- Select a patient first --</option>
                            </select>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                <i class="ri-information-line"></i> Remarks are loaded from the patient's progress notes (Step 5).
                            </p>
                        </div>

                        <!-- Clinician Name (Dropdown) -->
                        <div>
                            <label for="clinicianSelect" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                Clinician Name <span class="text-red-500">*</span>
                            </label>
                            <select id="clinicianSelect" name="clinician_name" required
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select Clinician --</option>
                                <?php foreach ($clinicians as $clinician): ?>
                                    <option value="<?php echo htmlspecialchars($clinician['full_name']); ?>"
                                            <?php echo ($role === 'Clinician' && $clinician['full_name'] === $fullName) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($clinician['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($role === 'Clinician'): ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                    <i class="ri-information-line"></i> Your name is pre-selected. You can change it if needed.
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-4 pt-4">
                            <button type="button" onclick="window.location.href='patients.php'" 
                                    class="px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-6 py-3 bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-lg hover:from-violet-700 hover:to-purple-700 transition-all shadow-md hover:shadow-lg">
                                <i class="ri-save-line mr-2"></i>Submit Log
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Logout Modal -->
    <?php include 'includes/logout_modal.php'; ?>

    <script>
        // Dark mode functionality
        const darkModeToggle = document.getElementById('darkModeToggle');
        const htmlElement = document.documentElement;

        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'enabled') {
            htmlElement.classList.add('dark');
        }

        darkModeToggle.addEventListener('click', () => {
            htmlElement.classList.toggle('dark');
            
            if (htmlElement.classList.contains('dark')) {
                localStorage.setItem('darkMode', 'enabled');
            } else {
                localStorage.setItem('darkMode', 'disabled');
            }
        });

        // Alert hide function
        function hideAlert() {
            const alertBox = document.getElementById('alertBox');
            if (alertBox) {
                alertBox.style.display = 'none';
            }
        }

        // Patient selection handler
        const patientSelect = document.getElementById('patientSelect');
        const procedureSelect = document.getElementById('procedureSelect');
        
        patientSelect.addEventListener('change', async function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (this.value) {
                // Fill patient information
                const firstName = selectedOption.dataset.firstname || '';
                const middleInitial = selectedOption.dataset.middleinitial || '';
                const lastName = selectedOption.dataset.lastname || '';
                const fullName = middleInitial 
                    ? `${firstName} ${middleInitial}. ${lastName}`
                    : `${firstName} ${lastName}`;
                
                document.getElementById('patientName').value = fullName;
                document.getElementById('patientAge').value = selectedOption.dataset.age || '--';
                document.getElementById('patientGender').value = selectedOption.dataset.gender || '--';
                document.getElementById('procedureHint').value = selectedOption.dataset.hint || '--';
                
                // Load treatment plans for selected patient
                await loadTreatmentPlans(this.value);
                
                // Load remarks for selected patient
                await loadRemarks(this.value);
            } else {
                // Clear fields
                document.getElementById('patientName').value = '';
                document.getElementById('patientAge').value = '';
                document.getElementById('patientGender').value = '';
                document.getElementById('procedureHint').value = '';
                procedureSelect.disabled = true;
                procedureSelect.innerHTML = '<option value="">-- Select a patient first --</option>';
                
                // Clear remarks dropdown
                const remarksSelect = document.getElementById('remarksSelect');
                remarksSelect.disabled = true;
                remarksSelect.innerHTML = '<option value="">-- Select a patient first --</option>';
            }
        });

        // Load treatment plans via AJAX
        async function loadTreatmentPlans(patientId) {
            try {
                const response = await fetch(`get_treatment_plans.php?patient_id=${patientId}`);
                const data = await response.json();
                
                procedureSelect.innerHTML = '<option value="">-- Select a treatment plan --</option>';
                
                if (data.success && data.plans && data.plans.length > 0) {
                    data.plans.forEach(plan => {
                        const option = document.createElement('option');
                        option.value = JSON.stringify(plan);
                        option.textContent = plan.display;
                        procedureSelect.appendChild(option);
                    });
                    procedureSelect.disabled = false;
                } else {
                    procedureSelect.innerHTML = '<option value="">No treatment plans found for this patient</option>';
                    procedureSelect.disabled = true;
                }
            } catch (error) {
                console.error('Error loading treatment plans:', error);
                procedureSelect.innerHTML = '<option value="">Error loading treatment plans</option>';
                procedureSelect.disabled = true;
            }
        }

        // Load remarks via AJAX
        async function loadRemarks(patientId) {
            const remarksSelect = document.getElementById('remarksSelect');
            
            try {
                const response = await fetch(`get_patient_remarks.php?patient_id=${patientId}`);
                const data = await response.json();
                
                remarksSelect.innerHTML = '<option value="">-- No remark (optional) --</option>';
                
                if (data.success && data.remarks && data.remarks.length > 0) {
                    data.remarks.forEach(remark => {
                        const option = document.createElement('option');
                        option.value = remark.remarks;
                        option.textContent = remark.display;
                        remarksSelect.appendChild(option);
                    });
                    remarksSelect.disabled = false;
                } else {
                    remarksSelect.innerHTML = '<option value="">No remarks found for this patient</option>';
                    remarksSelect.disabled = false; // Allow empty submission
                }
            } catch (error) {
                console.error('Error loading remarks:', error);
                remarksSelect.innerHTML = '<option value="">Error loading remarks</option>';
                remarksSelect.disabled = false; // Allow empty submission
            }
        }

        // Logout modal functions
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        function confirmLogout() {
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html>
