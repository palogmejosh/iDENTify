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

// Get all clinicians and admins for dropdown
$cliniciansStmt = $pdo->prepare("
    SELECT id, full_name
    FROM users
    WHERE (role = 'Clinician' OR role = 'Admin') AND account_status = 'active'
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
                            <input type="hidden" id="patientIdInput" name="patient_id">
                            <div class="relative" data-component="patient-search">
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <input
                                            type="text"
                                            id="patientSearchInput"
                                            autocomplete="off"
                                            placeholder="Type to search patients"
                                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                                            aria-describedby="patientSearchHelp"
                                        >
                                        <div id="patientSearchResults" class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-64 overflow-y-auto hidden">
                                            <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                Start typing to find a patient
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" id="clearPatientSelection" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white" title="Clear selection">
                                        <i class="ri-close-circle-line text-xl"></i>
                                    </button>
                                </div>
                                <p id="patientSearchHelp" class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                    <i class="ri-search-line"></i> Search by first name, last name, middle initial, or treatment hints.
                                </p>
                            </div>
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
                                            <?php echo (in_array($role, ['Clinician', 'Admin']) && $clinician['full_name'] === $fullName) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($clinician['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (in_array($role, ['Clinician', 'Admin'])): ?>
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

        const patientsData = <?php echo json_encode($patients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const patientSearchInput = document.getElementById('patientSearchInput');
        const patientSearchResults = document.getElementById('patientSearchResults');
        const patientIdInput = document.getElementById('patientIdInput');
        const procedureSelect = document.getElementById('procedureSelect');
        const clearPatientSelectionButton = document.getElementById('clearPatientSelection');

        let currentResults = [];
        let activeResultIndex = -1;

        function formatPatientDisplay(patient) {
            const displayName = `${patient.last_name}, ${patient.first_name}`;
            const age = patient.age ? ` • Age: ${patient.age}` : '';
            const hint = patient.treatment_hint ? ` • ${patient.treatment_hint}` : '';
            return `${displayName}${age}${hint}`;
        }

        function buildPatientFullName(patient) {
            return patient.middle_initial
                ? `${patient.first_name} ${patient.middle_initial}. ${patient.last_name}`
                : `${patient.first_name} ${patient.last_name}`;
        }

        function clearPatientDetails() {
            patientIdInput.value = '';
            document.getElementById('patientName').value = '';
            document.getElementById('patientAge').value = '';
            document.getElementById('patientGender').value = '';
            document.getElementById('procedureHint').value = '';
            procedureSelect.disabled = true;
            procedureSelect.innerHTML = '<option value="">-- Select a patient first --</option>';
        }

        function selectPatient(patient) {
            patientIdInput.value = patient.id;
            patientSearchInput.value = `${patient.last_name}, ${patient.first_name}`;
            document.getElementById('patientName').value = buildPatientFullName(patient);
            document.getElementById('patientAge').value = patient.age || '--';
            document.getElementById('patientGender').value = patient.gender || '--';
            document.getElementById('procedureHint').value = patient.treatment_hint || '--';
            hidePatientResults();
            loadTreatmentPlans(patient.id);
        }

        function hidePatientResults() {
            patientSearchResults.classList.add('hidden');
            patientSearchResults.setAttribute('aria-hidden', 'true');
            activeResultIndex = -1;
        }

        function renderPatientResults(results) {
            currentResults = results;
            patientSearchResults.innerHTML = '';

            if (!results.length) {
                const emptyState = document.createElement('div');
                emptyState.className = 'px-4 py-3 text-sm text-gray-500 dark:text-gray-400';
                emptyState.textContent = 'No patients found. Try a different search term.';
                patientSearchResults.appendChild(emptyState);
            } else {
                results.slice(0, 40).forEach((patient, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'w-full text-left px-4 py-3 hover:bg-violet-50 dark:hover:bg-gray-700 transition-colors focus:outline-none';
                    button.dataset.index = index.toString();
                    button.innerHTML = `
                        <p class="font-medium text-gray-800 dark:text-gray-100">${patient.last_name}, ${patient.first_name}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${patient.treatment_hint ? patient.treatment_hint : 'Patient ID: ' + patient.id}</p>
                    `;

                    button.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        selectPatient(patient);
                    });

                    patientSearchResults.appendChild(button);
                });
            }

            patientSearchResults.classList.remove('hidden');
            patientSearchResults.setAttribute('aria-hidden', 'false');
            activeResultIndex = -1;
        }

        function filterPatients(query) {
            const normalizedQuery = query.trim().toLowerCase();
            if (!normalizedQuery) {
                renderPatientResults(patientsData.slice(0, 40));
                return;
            }

            const results = patientsData.filter((patient) => {
                const fields = [
                    patient.first_name,
                    patient.last_name,
                    patient.middle_initial,
                    patient.treatment_hint
                ].filter(Boolean).map((field) => field.toLowerCase());

                return fields.some((field) => field.includes(normalizedQuery));
            });

            renderPatientResults(results);
        }

        function moveActiveSelection(direction) {
            if (currentResults.length === 0) {
                return;
            }

            const items = Array.from(patientSearchResults.querySelectorAll('button[data-index]'));
            if (!items.length) {
                return;
            }

            activeResultIndex += direction;
            if (activeResultIndex < 0) {
                activeResultIndex = items.length - 1;
            } else if (activeResultIndex >= items.length) {
                activeResultIndex = 0;
            }

            items.forEach((item, index) => {
                if (index === activeResultIndex) {
                    item.classList.add('bg-violet-100', 'dark:bg-gray-700');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('bg-violet-100', 'dark:bg-gray-700');
                }
            });
        }

        patientSearchInput.addEventListener('input', (event) => {
            const value = event.target.value;
            filterPatients(value);
            patientIdInput.value = '';
            if (!value.trim()) {
                clearPatientDetails();
            }
        });

        patientSearchInput.addEventListener('focus', () => {
            if (!patientSearchInput.value.trim()) {
                renderPatientResults(patientsData.slice(0, 40));
            } else {
                filterPatients(patientSearchInput.value);
            }
        });

        patientSearchInput.addEventListener('keydown', (event) => {
            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    moveActiveSelection(1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    moveActiveSelection(-1);
                    break;
                case 'Enter':
                    if (activeResultIndex >= 0 && currentResults[activeResultIndex]) {
                        event.preventDefault();
                        selectPatient(currentResults[activeResultIndex]);
                    }
                    break;
                case 'Escape':
                    hidePatientResults();
                    break;
                default:
                    break;
            }
        });

        patientSearchInput.addEventListener('blur', () => {
            setTimeout(() => hidePatientResults(), 150);
        });

        clearPatientSelectionButton.addEventListener('click', () => {
            patientSearchInput.value = '';
            clearPatientDetails();
            hidePatientResults();
            patientSearchInput.focus();
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-component="patient-search"]')) {
                hidePatientResults();
            }
        });

        document.getElementById('procedureLogForm').addEventListener('submit', (event) => {
            if (!patientIdInput.value) {
                event.preventDefault();
                patientSearchInput.focus();
                patientSearchInput.classList.add('ring-2', 'ring-red-500');
                setTimeout(() => {
                    patientSearchInput.classList.remove('ring-2', 'ring-red-500');
                }, 800);
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
