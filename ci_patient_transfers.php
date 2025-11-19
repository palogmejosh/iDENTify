<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Redirect if not Clinical Instructor
if ($role !== 'Clinical Instructor') {
    header('Location: dashboard.php');
    exit;
}

$ciId = $user['id'];

// Get transfer request counts
$transferCounts = getTransferRequestCounts($ciId);

// Get incoming and outgoing transfer requests
$incomingTransfers = getIncomingTransferRequests($ciId, 'pending');
$outgoingTransfers = getOutgoingTransferRequests($ciId, 'pending');

// Get all transfers for history
$allIncomingTransfers = getIncomingTransferRequests($ciId, 'all');
$allOutgoingTransfers = getOutgoingTransferRequests($ciId, 'all');

$fullName = $user ? $user['full_name'] : 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Patient Transfers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="dark-mode-override.css">
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
        .header-profile-pic {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        .dark .header-profile-pic {
            border-color: #4b5563;
        }
        .header-profile-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e5e7eb;
            color: #6b7280;
        }
        .dark .header-profile-placeholder {
            background-color: #374151;
            border-color: #4b5563;
            color: #9ca3af;
        }
        .tab-active {
            border-bottom: 2px solid #8b5cf6;
            color: #8b5cf6;
        }
        .history-tab-active {
            border-bottom: 2px solid #8b5cf6 !important;
            color: #8b5cf6 !important;
        }
        .dark .history-tab-active {
            color: #a78bfa !important;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-black transition-colors duration-200">

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="ml-64 mt-[64px] min-h-screen bg-gradient-to-br from-violet-50 to-purple-50 dark:bg-black p-6 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Patient Transfer Management</h2>
            </div>

            <!-- Notification/Alert Box -->
            <div id="alertBox" class="mb-6 hidden">
                <div id="alertContainer" class="px-4 py-3 rounded-lg shadow-lg relative">
                    <span id="alertMessage"></span>
                    <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="ri-information-line text-blue-600 dark:text-blue-400 text-xl mr-3 mt-1"></i>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-1">About Patient Transfers</h3>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            You can transfer your assigned patients to other Clinical Instructors regardless of their approval status. When you send a transfer request, the receiving instructor can accept or reject it. You can view incoming requests and respond to them here.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg mb-6 border border-violet-200 dark:border-violet-700">
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button onclick="switchTab('incoming')" id="incomingTab" class="tab-button tab-active px-6 py-3 text-sm font-medium focus:outline-none">
                        Incoming Requests 
                        <?php if ($transferCounts['incoming_pending'] > 0): ?>
                            <span class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-1"><?php echo $transferCounts['incoming_pending']; ?></span>
                        <?php endif; ?>
                    </button>
                    <button onclick="switchTab('outgoing')" id="outgoingTab" class="tab-button px-6 py-3 text-sm font-medium focus:outline-none">
                        Outgoing Requests
                        <?php if ($transferCounts['outgoing_pending'] > 0): ?>
                            <span class="ml-2 bg-yellow-500 text-white text-xs rounded-full px-2 py-1"><?php echo $transferCounts['outgoing_pending']; ?></span>
                        <?php endif; ?>
                    </button>
                    <button onclick="switchTab('history')" id="historyTab" class="tab-button px-6 py-3 text-sm font-medium focus:outline-none">
                        Transfer History
                    </button>
                </div>

                <!-- Incoming Requests Content -->
                <div id="incomingContent" class="tab-content p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Incoming Transfer Requests</h3>
                        <button onclick="toggleFilters('incoming')" class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 flex items-center">
                            <i class="ri-filter-3-line mr-1"></i>Filters
                        </button>
                    </div>
                    
                    <!-- Incoming Filters -->
                    <div id="incomingFilters" class="mb-4 hidden">
                        <div class="bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900/20 dark:to-purple-900/20 border border-violet-200 dark:border-violet-700 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search Patient</label>
                                    <input type="text" id="incomingSearchPatient" onkeyup="filterIncoming()" 
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white" 
                                           placeholder="Patient name or email">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">From CI</label>
                                    <input type="text" id="incomingSearchCI" onkeyup="filterIncoming()" 
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white" 
                                           placeholder="Clinical Instructor name">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sort By</label>
                                    <select id="incomingSortBy" onchange="filterIncoming()" 
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                        <option value="date-desc">Date (Newest First)</option>
                                        <option value="date-asc">Date (Oldest First)</option>
                                        <option value="patient-asc">Patient Name (A-Z)</option>
                                        <option value="patient-desc">Patient Name (Z-A)</option>
                                        <option value="ci-asc">CI Name (A-Z)</option>
                                        <option value="ci-desc">CI Name (Z-A)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button onclick="clearIncomingFilters()" class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                                    <i class="ri-refresh-line mr-1"></i>Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($incomingTransfers)): ?>
                        <div id="incomingTransfersList" class="space-y-4">
                            <?php foreach ($incomingTransfers as $transfer): ?>
                                <div class="incoming-transfer-item bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900/20 dark:to-purple-900/20 border border-violet-200 dark:border-violet-700 rounded-lg p-4" 
                                     data-patient-name="<?php echo htmlspecialchars(strtolower($transfer['first_name'] . ' ' . $transfer['last_name'])); ?>" 
                                     data-patient-email="<?php echo htmlspecialchars(strtolower($transfer['email'])); ?>" 
                                     data-ci-name="<?php echo htmlspecialchars(strtolower($transfer['from_ci_name'])); ?>" 
                                     data-treatment="<?php echo htmlspecialchars(strtolower($transfer['treatment_hint'] ?? '')); ?>" 
                                     data-date="<?php echo strtotime($transfer['requested_at']); ?>">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <i class="ri-user-line mr-1"></i>From: <?php echo htmlspecialchars($transfer['from_ci_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <i class="ri-mail-line mr-1"></i><?php echo htmlspecialchars($transfer['email']); ?>
                                            </p>
                                            <?php if (!empty($transfer['treatment_hint'])): ?>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <i class="ri-medicine-bottle-line mr-1"></i>Procedure Details: <?php echo htmlspecialchars($transfer['treatment_hint']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($transfer['transfer_reason'])): ?>
                                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">
                                                    <strong>Reason:</strong> <?php echo htmlspecialchars($transfer['transfer_reason']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                Requested: <?php echo date('M d, Y h:i A', strtotime($transfer['requested_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="flex flex-col space-y-2 ml-4">
                                            <button onclick="openIncomingDetailsModal(<?php echo htmlspecialchars(json_encode($transfer)); ?>)" 
                                                    class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                                                <i class="ri-eye-line mr-1"></i>View Details
                                            </button>
                                            <button onclick="openResponseModal(<?php echo htmlspecialchars(json_encode($transfer)); ?>, 'accept')" 
                                                    class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                                                <i class="ri-check-line mr-1"></i>Accept
                                            </button>
                                            <button onclick="openResponseModal(<?php echo htmlspecialchars(json_encode($transfer)); ?>, 'reject')" 
                                                    class="bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                                                <i class="ri-close-line mr-1"></i>Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="ri-inbox-line text-6xl text-gray-400 dark:text-gray-600 mb-3"></i>
                            <p class="text-gray-500 dark:text-gray-400">No pending incoming transfer requests</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Outgoing Requests Content -->
                <div id="outgoingContent" class="tab-content p-6 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Outgoing Transfer Requests</h3>
                        <button onclick="toggleFilters('outgoing')" class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 flex items-center">
                            <i class="ri-filter-3-line mr-1"></i>Filters
                        </button>
                    </div>
                    
                    <!-- Outgoing Filters -->
                    <div id="outgoingFilters" class="mb-4 hidden">
                        <div class="bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search Patient</label>
                                    <input type="text" id="outgoingSearchPatient" onkeyup="filterOutgoing()" 
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white" 
                                           placeholder="Patient name or email">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">To CI</label>
                                    <input type="text" id="outgoingSearchCI" onkeyup="filterOutgoing()" 
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white" 
                                           placeholder="Clinical Instructor name">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sort By</label>
                                    <select id="outgoingSortBy" onchange="filterOutgoing()" 
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                        <option value="date-desc">Date (Newest First)</option>
                                        <option value="date-asc">Date (Oldest First)</option>
                                        <option value="patient-asc">Patient Name (A-Z)</option>
                                        <option value="patient-desc">Patient Name (Z-A)</option>
                                        <option value="ci-asc">CI Name (A-Z)</option>
                                        <option value="ci-desc">CI Name (Z-A)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button onclick="clearOutgoingFilters()" class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                                    <i class="ri-refresh-line mr-1"></i>Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($outgoingTransfers)): ?>
                        <div id="outgoingTransfersList" class="space-y-4">
                            <?php foreach ($outgoingTransfers as $transfer): ?>
                                <div class="outgoing-transfer-item bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4" 
                                     data-patient-name="<?php echo htmlspecialchars(strtolower($transfer['first_name'] . ' ' . $transfer['last_name'])); ?>" 
                                     data-patient-email="<?php echo htmlspecialchars(strtolower($transfer['email'])); ?>" 
                                     data-ci-name="<?php echo htmlspecialchars(strtolower($transfer['to_ci_name'])); ?>" 
                                     data-treatment="<?php echo htmlspecialchars(strtolower($transfer['treatment_hint'] ?? '')); ?>" 
                                     data-date="<?php echo strtotime($transfer['requested_at']); ?>">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <i class="ri-user-line mr-1"></i>To: <?php echo htmlspecialchars($transfer['to_ci_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <i class="ri-mail-line mr-1"></i><?php echo htmlspecialchars($transfer['email']); ?>
                                            </p>
                                            <?php if (!empty($transfer['treatment_hint'])): ?>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <i class="ri-medicine-bottle-line mr-1"></i>Procedure Details: <?php echo htmlspecialchars($transfer['treatment_hint']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($transfer['transfer_reason'])): ?>
                                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">
                                                    <strong>Reason:</strong> <?php echo htmlspecialchars($transfer['transfer_reason']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                Requested: <?php echo date('M d, Y h:i A', strtotime($transfer['requested_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="flex flex-col space-y-2 ml-4">
                                            <button onclick="openOutgoingDetailsModal(<?php echo htmlspecialchars(json_encode($transfer)); ?>)" 
                                                    class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                                                <i class="ri-eye-line mr-1"></i>View Details
                                            </button>
                                            <button onclick="cancelTransfer(<?php echo $transfer['transfer_id']; ?>)" 
                                                    class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                                                <i class="ri-close-line mr-1"></i>Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="ri-inbox-line text-6xl text-gray-400 dark:text-gray-600 mb-3"></i>
                            <p class="text-gray-500 dark:text-gray-400">No pending outgoing transfer requests</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- History Content -->
                <div id="historyContent" class="tab-content p-6 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transfer History</h3>
                        <button onclick="toggleFilters('history')" class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 flex items-center">
                            <i class="ri-filter-3-line mr-1"></i>Filters
                        </button>
                    </div>
                    
                    <!-- History Filters -->
                    <div id="historyFilters" class="mb-4 hidden">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search Patient</label>
                                    <input type="text" id="historySearchPatient" onkeyup="filterHistory()" 
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white" 
                                           placeholder="Patient name or email">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search CI</label>
                                    <input type="text" id="historySearchCI" onkeyup="filterHistory()" 
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white" 
                                           placeholder="Clinical Instructor name">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                                    <input type="date" id="historyDateFrom" onchange="filterHistory()" 
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                                    <input type="date" id="historyDateTo" onchange="filterHistory()" 
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                    <select id="historyFilterStatus" onchange="filterHistory()" 
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                        <option value="all">All Status</option>
                                        <option value="accepted">Accepted</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sort By</label>
                                    <select id="historySortBy" onchange="filterHistory()" 
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                        <option value="date-desc">Date (Newest First)</option>
                                        <option value="date-asc">Date (Oldest First)</option>
                                        <option value="patient-asc">Patient Name (A-Z)</option>
                                        <option value="patient-desc">Patient Name (Z-A)</option>
                                        <option value="ci-asc">CI Name (A-Z)</option>
                                        <option value="ci-desc">CI Name (Z-A)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button onclick="clearHistoryFilters()" class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                                    <i class="ri-refresh-line mr-1"></i>Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sub-tabs for History -->
                    <div class="mb-4">
                        <div class="flex space-x-2 border-b border-gray-200 dark:border-gray-700">
                            <button onclick="switchHistoryTab('received')" id="receivedHistoryTab" 
                                    class="history-tab-button history-tab-active px-4 py-2 text-sm font-medium border-b-2 border-violet-500 text-violet-600 dark:text-violet-400 focus:outline-none">
                                <i class="ri-download-line mr-1"></i>Received Transfers
                            </button>
                            <button onclick="switchHistoryTab('sent')" id="sentHistoryTab" 
                                    class="history-tab-button px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 focus:outline-none">
                                <i class="ri-upload-line mr-1"></i>Sent Transfers
                            </button>
                        </div>
                    </div>
                    
                    <!-- Received Transfers Sub-tab Content -->
                    <div id="receivedHistoryContent" class="history-tab-content">
                        <div>
                            <?php 
                            $historyIncoming = array_filter($allIncomingTransfers, function($t) { 
                                return $t['transfer_status'] !== 'pending'; 
                            });
                            ?>
                            <?php if (!empty($historyIncoming)): ?>
                                <div id="receivedHistoryList" class="space-y-2">
                                    <?php foreach ($historyIncoming as $transfer): ?>
                                        <div onclick="openHistoryDetailsModal(<?php echo htmlspecialchars(json_encode($transfer)); ?>, 'incoming')" 
                                             class="history-transfer-item border border-gray-200 dark:border-gray-700 rounded-lg p-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" 
                                             data-patient-name="<?php echo htmlspecialchars(strtolower($transfer['first_name'] . ' ' . $transfer['last_name'])); ?>" 
                                             data-patient-email="<?php echo htmlspecialchars(strtolower($transfer['email'])); ?>" 
                                             data-ci-name="<?php echo htmlspecialchars(strtolower($transfer['from_ci_name'])); ?>" 
                                             data-status="<?php echo htmlspecialchars($transfer['transfer_status']); ?>" 
                                             data-date="<?php echo strtotime($transfer['responded_at']); ?>">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                                        From: <?php echo htmlspecialchars($transfer['from_ci_name']); ?>
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php 
                                                        echo $transfer['transfer_status'] === 'accepted' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                             'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; 
                                                    ?>">
                                                        <?php echo ucfirst($transfer['transfer_status']); ?>
                                                    </span>
                                                    <p class="text-xs text-gray-500 mt-1">
                                                        <?php echo date('M d, Y', strtotime($transfer['responded_at'])); ?>
                                                    </p>
                                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                        <i class="ri-information-line"></i> Click for details
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <i class="ri-inbox-line text-6xl text-gray-400 dark:text-gray-600 mb-3"></i>
                                    <p class="text-gray-500 dark:text-gray-400">No received transfer history</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sent Transfers Sub-tab Content -->
                    <div id="sentHistoryContent" class="history-tab-content hidden">
                        <div>
                            <?php 
                            $historyOutgoing = array_filter($allOutgoingTransfers, function($t) { 
                                return $t['transfer_status'] !== 'pending'; 
                            });
                            ?>
                            <?php if (!empty($historyOutgoing)): ?>
                                <div id="sentHistoryList" class="space-y-2">
                                    <?php foreach ($historyOutgoing as $transfer): ?>
                                        <div onclick="openHistoryDetailsModal(<?php echo htmlspecialchars(json_encode($transfer)); ?>, 'outgoing')" 
                                             class="history-transfer-item border border-gray-200 dark:border-gray-700 rounded-lg p-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" 
                                             data-patient-name="<?php echo htmlspecialchars(strtolower($transfer['first_name'] . ' ' . $transfer['last_name'])); ?>" 
                                             data-patient-email="<?php echo htmlspecialchars(strtolower($transfer['email'])); ?>" 
                                             data-ci-name="<?php echo htmlspecialchars(strtolower($transfer['to_ci_name'])); ?>" 
                                             data-status="<?php echo htmlspecialchars($transfer['transfer_status']); ?>" 
                                             data-date="<?php echo strtotime($transfer['responded_at'] ?? $transfer['requested_at']); ?>">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                                        To: <?php echo htmlspecialchars($transfer['to_ci_name']); ?>
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php 
                                                        echo $transfer['transfer_status'] === 'accepted' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                             ($transfer['transfer_status'] === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                                                              'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'); 
                                                    ?>">
                                                        <?php echo ucfirst($transfer['transfer_status']); ?>
                                                    </span>
                                                    <p class="text-xs text-gray-500 mt-1">
                                                        <?php echo date('M d, Y', strtotime($transfer['responded_at'] ?? $transfer['requested_at'])); ?>
                                                    </p>
                                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                        <i class="ri-information-line"></i> Click for details
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <i class="ri-inbox-line text-6xl text-gray-400 dark:text-gray-600 mb-3"></i>
                                    <p class="text-gray-500 dark:text-gray-400">No sent transfer history</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>

    <!-- Transfer Response Modal -->
    <div id="responseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-lg mx-auto modal-fade">
            <div class="flex justify-between items-center mb-4">
                <h3 id="responseModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Respond to Transfer Request</h3>
                <button onclick="closeResponseModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <div id="transferDetails" class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-md">
                <!-- Populated by JS -->
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Response Notes (Optional)
                </label>
                <textarea id="responseNotes" rows="3" 
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                          placeholder="Add any notes about your decision..."></textarea>
            </div>
            
            <input type="hidden" id="transferId">
            <input type="hidden" id="responseAction">
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeResponseModal()"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md">
                    Cancel
                </button>
                <button type="button" onclick="submitResponse()" id="submitResponseBtn"
                        class="px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                    <span id="submitBtnText">Submit</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Dark mode toggle
        (() => {
            const html = document.documentElement;
            const btn = document.getElementById('darkModeToggle');
            const key = 'darkMode';
            const isDark = localStorage.getItem(key) === 'true';

            if (isDark) html.classList.add('dark');

            btn.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.setItem(key, html.classList.contains('dark'));
            });
        })();

        // Alert functions
        function showAlert(message, type = 'success') {
            const alertBox = document.getElementById('alertBox');
            const alertContainer = document.getElementById('alertContainer');
            const alertMessage = document.getElementById('alertMessage');
            
            const colors = {
                success: 'bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200',
                error: 'bg-gradient-to-r from-red-100 to-pink-100 dark:from-red-900 dark:to-pink-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200',
                info: 'bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900 border border-blue-400 dark:border-blue-600 text-blue-700 dark:text-blue-200'
            };
            
            alertContainer.className = (colors[type] || colors.info) + ' px-4 py-3 rounded-lg shadow-lg relative';
            alertMessage.textContent = message;
            alertBox.classList.remove('hidden');
            
            setTimeout(hideAlert, 5000);
        }

        function hideAlert() {
            document.getElementById('alertBox')?.classList.add('hidden');
        }

        // Tab switching
        function switchTab(tab) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('tab-active'));
            
            // Show selected tab content
            document.getElementById(tab + 'Content').classList.remove('hidden');
            document.getElementById(tab + 'Tab').classList.add('tab-active');
        }
        
        // History sub-tab switching
        function switchHistoryTab(subtab) {
            // Hide all history sub-tab contents
            document.querySelectorAll('.history-tab-content').forEach(content => content.classList.add('hidden'));
            
            // Remove active class from all history sub-tabs
            document.querySelectorAll('.history-tab-button').forEach(btn => {
                btn.classList.remove('history-tab-active', 'border-b-2', 'border-violet-500', 'text-violet-600');
                btn.classList.add('text-gray-600');
            });
            
            // Show selected history sub-tab content
            document.getElementById(subtab + 'HistoryContent').classList.remove('hidden');
            
            // Activate selected history sub-tab button
            const activeBtn = document.getElementById(subtab + 'HistoryTab');
            activeBtn.classList.add('history-tab-active', 'border-b-2', 'border-violet-500', 'text-violet-600');
            activeBtn.classList.remove('text-gray-600');
        }

        // Transfer response modal
        function openResponseModal(transfer, action) {
            const modal = document.getElementById('responseModal');
            const title = document.getElementById('responseModalTitle');
            const details = document.getElementById('transferDetails');
            const submitBtn = document.getElementById('submitResponseBtn');
            const submitText = document.getElementById('submitBtnText');
            
            document.getElementById('transferId').value = transfer.transfer_id;
            document.getElementById('responseAction').value = action;
            
            if (action === 'accept') {
                title.textContent = 'Accept Transfer Request';
                submitBtn.className = 'bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200';
                submitText.textContent = 'Accept Transfer';
            } else {
                title.textContent = 'Reject Transfer Request';
                submitBtn.className = 'bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200';
                submitText.textContent = 'Reject Transfer';
            }
            
            details.innerHTML = `
                <h4 class="font-semibold mb-2">Patient Information:</h4>
                <div class="text-sm space-y-1">
                    <p><strong>Name:</strong> ${escapeHtml(transfer.first_name + ' ' + transfer.last_name)}</p>
                    <p><strong>Email:</strong> ${escapeHtml(transfer.email)}</p>
                    <p><strong>From CI:</strong> ${escapeHtml(transfer.from_ci_name)}</p>
                    ${transfer.treatment_hint ? `<p><strong>Procedure Details:</strong> ${escapeHtml(transfer.treatment_hint)}</p>` : ''}
                    ${transfer.transfer_reason ? `<p class="mt-2"><strong>Transfer Reason:</strong><br>${escapeHtml(transfer.transfer_reason)}</p>` : ''}
                </div>
            `;
            
            modal.style.display = 'flex';
        }

        function closeResponseModal() {
            document.getElementById('responseModal').style.display = 'none';
            document.getElementById('responseNotes').value = '';
        }

        function submitResponse() {
            const transferId = document.getElementById('transferId').value;
            const action = document.getElementById('responseAction').value;
            const notes = document.getElementById('responseNotes').value;
            const submitBtn = document.getElementById('submitResponseBtn');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Processing...';
            
            fetch('ajax_ci_patient_transfer.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=respond_transfer&transfer_id=${transferId}&response=${action}&response_notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeResponseModal();
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message || 'Error processing request', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = document.getElementById('submitBtnText').textContent;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Network error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = document.getElementById('submitBtnText').textContent;
            });
        }

        function cancelTransfer(transferId) {
            if (!confirm('Are you sure you want to cancel this transfer request?')) {
                return;
            }
            
            fetch('ajax_ci_patient_transfer.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=cancel_transfer&transfer_id=${transferId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message || 'Error cancelling transfer', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Network error occurred. Please try again.', 'error');
            });
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // History details modal
        function openHistoryDetailsModal(transfer, type) {
            const modal = document.getElementById('historyDetailsModal');
            const title = document.getElementById('historyModalTitle');
            const details = document.getElementById('historyDetails');
            
            // Set title based on type
            if (type === 'incoming') {
                title.innerHTML = '<i class="ri-download-line mr-2"></i>Received Transfer Details';
            } else {
                title.innerHTML = '<i class="ri-upload-line mr-2"></i>Sent Transfer Details';
            }
            
            // Build status badge
            let statusClass = '';
            if (transfer.transfer_status === 'accepted') {
                statusClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            } else if (transfer.transfer_status === 'rejected') {
                statusClass = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            } else if (transfer.transfer_status === 'cancelled') {
                statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
            }
            
            // Build details HTML
            let detailsHTML = `
                <div class="space-y-4">
                    <!-- Status Badge -->
                    <div class="flex justify-center mb-4">
                        <span class="inline-flex px-4 py-2 text-sm font-semibold rounded-full ${statusClass}">
                            ${transfer.transfer_status.toUpperCase()}
                        </span>
                    </div>
                    
                    <!-- Patient Information -->
                    <div class="bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Patient Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong class="text-gray-700 dark:text-gray-300">Name:</strong> ${escapeHtml(transfer.first_name + ' ' + transfer.last_name)}</p>
                            <p><strong class="text-gray-700 dark:text-gray-300">Email:</strong> ${escapeHtml(transfer.email)}</p>
                            ${transfer.phone ? `<p><strong class="text-gray-700 dark:text-gray-300">Phone:</strong> ${escapeHtml(transfer.phone)}</p>` : ''}
                            ${transfer.age ? `<p><strong class="text-gray-700 dark:text-gray-300">Age:</strong> ${escapeHtml(transfer.age)}</p>` : ''}
                            ${transfer.treatment_hint ? `<p><strong class="text-gray-700 dark:text-gray-300">Procedure Details:</strong> ${escapeHtml(transfer.treatment_hint)}</p>` : ''}
                        </div>
                    </div>
                    
                    <!-- Transfer Information -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Transfer Information</h4>
                        <div class="space-y-2 text-sm">
            `;
            
            if (type === 'incoming') {
                detailsHTML += `<p><strong class="text-gray-700 dark:text-gray-300">From:</strong> ${escapeHtml(transfer.from_ci_name)}</p>`;
                if (transfer.from_ci_email) {
                    detailsHTML += `<p><strong class="text-gray-700 dark:text-gray-300">From Email:</strong> ${escapeHtml(transfer.from_ci_email)}</p>`;
                }
            } else {
                detailsHTML += `<p><strong class="text-gray-700 dark:text-gray-300">To:</strong> ${escapeHtml(transfer.to_ci_name)}</p>`;
                if (transfer.to_ci_email) {
                    detailsHTML += `<p><strong class="text-gray-700 dark:text-gray-300">To Email:</strong> ${escapeHtml(transfer.to_ci_email)}</p>`;
                }
            }
            
            detailsHTML += `
                            <p><strong class="text-gray-700 dark:text-gray-300">Requested:</strong> ${new Date(transfer.requested_at).toLocaleString()}</p>
            `;
            
            if (transfer.responded_at) {
                detailsHTML += `<p><strong class="text-gray-700 dark:text-gray-300">Responded:</strong> ${new Date(transfer.responded_at).toLocaleString()}</p>`;
            }
            
            detailsHTML += `
                        </div>
                    </div>
            `;
            
            // Transfer Reason
            if (transfer.transfer_reason) {
                detailsHTML += `
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Transfer Reason</h4>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${escapeHtml(transfer.transfer_reason)}</p>
                    </div>
                `;
            }
            
            // Response Notes
            if (transfer.response_notes) {
                detailsHTML += `
                    <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Response Notes</h4>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${escapeHtml(transfer.response_notes)}</p>
                    </div>
                `;
            }
            
            detailsHTML += `</div>`;
            
            details.innerHTML = detailsHTML;
            modal.style.display = 'flex';
        }
        
        function closeHistoryDetailsModal() {
            document.getElementById('historyDetailsModal').style.display = 'none';
        }
        
        // Incoming request details modal
        function openIncomingDetailsModal(transfer) {
            const modal = document.getElementById('incomingDetailsModal');
            const details = document.getElementById('incomingDetails');
            
            // Build details HTML
            let detailsHTML = `
                <div class="space-y-4">
                    <!-- Status Badge -->
                    <div class="flex justify-center mb-4">
                        <span class="inline-flex px-4 py-2 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            PENDING REVIEW
                        </span>
                    </div>
                    
                    <!-- Patient Information -->
                    <div class="bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Patient Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong class="text-gray-700 dark:text-gray-300">Name:</strong> ${escapeHtml(transfer.first_name + ' ' + transfer.last_name)}</p>
                            <p><strong class="text-gray-700 dark:text-gray-300">Email:</strong> ${escapeHtml(transfer.email)}</p>
                            ${transfer.phone ? `<p><strong class="text-gray-700 dark:text-gray-300">Phone:</strong> ${escapeHtml(transfer.phone)}</p>` : ''}
                            ${transfer.age ? `<p><strong class="text-gray-700 dark:text-gray-300">Age:</strong> ${escapeHtml(transfer.age)}</p>` : ''}
                            ${transfer.treatment_hint ? `<p><strong class="text-gray-700 dark:text-gray-300">Procedure Details:</strong> ${escapeHtml(transfer.treatment_hint)}</p>` : ''}
                        </div>
                    </div>
                    
                    <!-- Transfer Information -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Transfer Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong class="text-gray-700 dark:text-gray-300">From:</strong> ${escapeHtml(transfer.from_ci_name)}</p>
                            ${transfer.from_ci_email ? `<p><strong class="text-gray-700 dark:text-gray-300">From Email:</strong> ${escapeHtml(transfer.from_ci_email)}</p>` : ''}
                            <p><strong class="text-gray-700 dark:text-gray-300">Requested:</strong> ${new Date(transfer.requested_at).toLocaleString()}</p>
                        </div>
                    </div>
            `;
            
            // Transfer Reason
            if (transfer.transfer_reason) {
                detailsHTML += `
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Transfer Reason</h4>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${escapeHtml(transfer.transfer_reason)}</p>
                    </div>
                `;
            }
            
            detailsHTML += `</div>`;
            
            details.innerHTML = detailsHTML;
            modal.style.display = 'flex';
        }
        
        function closeIncomingDetailsModal() {
            document.getElementById('incomingDetailsModal').style.display = 'none';
        }
        
        // Outgoing request details modal
        function openOutgoingDetailsModal(transfer) {
            const modal = document.getElementById('outgoingDetailsModal');
            const details = document.getElementById('outgoingDetails');
            
            // Build details HTML
            let detailsHTML = `
                <div class="space-y-4">
                    <!-- Status Badge -->
                    <div class="flex justify-center mb-4">
                        <span class="inline-flex px-4 py-2 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            PENDING RESPONSE
                        </span>
                    </div>
                    
                    <!-- Patient Information -->
                    <div class="bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Patient Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong class="text-gray-700 dark:text-gray-300">Name:</strong> ${escapeHtml(transfer.first_name + ' ' + transfer.last_name)}</p>
                            <p><strong class="text-gray-700 dark:text-gray-300">Email:</strong> ${escapeHtml(transfer.email)}</p>
                            ${transfer.phone ? `<p><strong class="text-gray-700 dark:text-gray-300">Phone:</strong> ${escapeHtml(transfer.phone)}</p>` : ''}
                            ${transfer.age ? `<p><strong class="text-gray-700 dark:text-gray-300">Age:</strong> ${escapeHtml(transfer.age)}</p>` : ''}
                            ${transfer.treatment_hint ? `<p><strong class="text-gray-700 dark:text-gray-300">Procedure Details:</strong> ${escapeHtml(transfer.treatment_hint)}</p>` : ''}
                        </div>
                    </div>
                    
                    <!-- Transfer Information -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Transfer Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong class="text-gray-700 dark:text-gray-300">To:</strong> ${escapeHtml(transfer.to_ci_name)}</p>
                            ${transfer.to_ci_email ? `<p><strong class="text-gray-700 dark:text-gray-300">To Email:</strong> ${escapeHtml(transfer.to_ci_email)}</p>` : ''}
                            <p><strong class="text-gray-700 dark:text-gray-300">Requested:</strong> ${new Date(transfer.requested_at).toLocaleString()}</p>
                        </div>
                    </div>
            `;
            
            // Transfer Reason
            if (transfer.transfer_reason) {
                detailsHTML += `
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Transfer Reason</h4>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${escapeHtml(transfer.transfer_reason)}</p>
                    </div>
                `;
            }
            
            detailsHTML += `</div>`;
            
            details.innerHTML = detailsHTML;
            modal.style.display = 'flex';
        }
        
        function closeOutgoingDetailsModal() {
            document.getElementById('outgoingDetailsModal').style.display = 'none';
        }
        
        // ============= FILTERING FUNCTIONS =============
        
        // Toggle filter visibility
        function toggleFilters(section) {
            const filtersDiv = document.getElementById(section + 'Filters');
            filtersDiv.classList.toggle('hidden');
        }
        
        // Incoming transfers filtering
        function filterIncoming() {
            const searchPatient = document.getElementById('incomingSearchPatient').value.toLowerCase();
            const searchCI = document.getElementById('incomingSearchCI').value.toLowerCase();
            const sortBy = document.getElementById('incomingSortBy').value;
            
            const container = document.getElementById('incomingTransfersList');
            if (!container) return;
            
            const items = Array.from(container.getElementsByClassName('incoming-transfer-item'));
            
            // Filter items
            let visibleCount = 0;
            items.forEach(item => {
                const patientName = item.dataset.patientName || '';
                const patientEmail = item.dataset.patientEmail || '';
                const ciName = item.dataset.ciName || '';
                
                const matchesPatient = !searchPatient || patientName.includes(searchPatient) || patientEmail.includes(searchPatient);
                const matchesCI = !searchCI || ciName.includes(searchCI);
                
                if (matchesPatient && matchesCI) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Sort visible items
            const visibleItems = items.filter(item => item.style.display !== 'none');
            sortItems(visibleItems, sortBy);
            
            // Re-append sorted items
            visibleItems.forEach(item => container.appendChild(item));
        }
        
        function clearIncomingFilters() {
            document.getElementById('incomingSearchPatient').value = '';
            document.getElementById('incomingSearchCI').value = '';
            document.getElementById('incomingSortBy').value = 'date-desc';
            filterIncoming();
        }
        
        // Outgoing transfers filtering
        function filterOutgoing() {
            const searchPatient = document.getElementById('outgoingSearchPatient').value.toLowerCase();
            const searchCI = document.getElementById('outgoingSearchCI').value.toLowerCase();
            const sortBy = document.getElementById('outgoingSortBy').value;
            
            const container = document.getElementById('outgoingTransfersList');
            if (!container) return;
            
            const items = Array.from(container.getElementsByClassName('outgoing-transfer-item'));
            
            // Filter items
            let visibleCount = 0;
            items.forEach(item => {
                const patientName = item.dataset.patientName || '';
                const patientEmail = item.dataset.patientEmail || '';
                const ciName = item.dataset.ciName || '';
                
                const matchesPatient = !searchPatient || patientName.includes(searchPatient) || patientEmail.includes(searchPatient);
                const matchesCI = !searchCI || ciName.includes(searchCI);
                
                if (matchesPatient && matchesCI) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Sort visible items
            const visibleItems = items.filter(item => item.style.display !== 'none');
            sortItems(visibleItems, sortBy);
            
            // Re-append sorted items
            visibleItems.forEach(item => container.appendChild(item));
        }
        
        function clearOutgoingFilters() {
            document.getElementById('outgoingSearchPatient').value = '';
            document.getElementById('outgoingSearchCI').value = '';
            document.getElementById('outgoingSortBy').value = 'date-desc';
            filterOutgoing();
        }
        
        // History transfers filtering (works across all history sub-tabs)
        function filterHistory() {
            const searchPatient = document.getElementById('historySearchPatient').value.toLowerCase();
            const searchCI = document.getElementById('historySearchCI').value.toLowerCase();
            const filterStatus = document.getElementById('historyFilterStatus').value;
            const sortBy = document.getElementById('historySortBy').value;
            const dateFrom = document.getElementById('historyDateFrom').value;
            const dateTo = document.getElementById('historyDateTo').value;
            
            // Convert date strings to timestamps for comparison
            const fromTimestamp = dateFrom ? new Date(dateFrom).getTime() / 1000 : null;
            const toTimestamp = dateTo ? new Date(dateTo + ' 23:59:59').getTime() / 1000 : null;
            
            // Filter all history lists
            const lists = ['receivedHistoryList', 'sentHistoryList'];
            
            lists.forEach(listId => {
                const container = document.getElementById(listId);
                if (!container) return;
                
                const items = Array.from(container.getElementsByClassName('history-transfer-item'));
                
                // Filter items
                items.forEach(item => {
                    const patientName = item.dataset.patientName || '';
                    const patientEmail = item.dataset.patientEmail || '';
                    const ciName = item.dataset.ciName || '';
                    const status = item.dataset.status || '';
                    const itemDate = parseInt(item.dataset.date) || 0;
                    
                    const matchesPatient = !searchPatient || patientName.includes(searchPatient) || patientEmail.includes(searchPatient);
                    const matchesCI = !searchCI || ciName.includes(searchCI);
                    const matchesStatus = filterStatus === 'all' || status === filterStatus;
                    const matchesDateFrom = !fromTimestamp || itemDate >= fromTimestamp;
                    const matchesDateTo = !toTimestamp || itemDate <= toTimestamp;
                    
                    if (matchesPatient && matchesCI && matchesStatus && matchesDateFrom && matchesDateTo) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Sort visible items
                const visibleItems = items.filter(item => item.style.display !== 'none');
                sortItems(visibleItems, sortBy);
                
                // Re-append sorted items
                visibleItems.forEach(item => container.appendChild(item));
            });
        }
        
        function clearHistoryFilters() {
            document.getElementById('historySearchPatient').value = '';
            document.getElementById('historySearchCI').value = '';
            document.getElementById('historyDateFrom').value = '';
            document.getElementById('historyDateTo').value = '';
            document.getElementById('historyFilterStatus').value = 'all';
            document.getElementById('historySortBy').value = 'date-desc';
            filterHistory();
        }
        
        // Generic sort function
        function sortItems(items, sortBy) {
            items.sort((a, b) => {
                const [field, order] = sortBy.split('-');
                let comparison = 0;
                
                switch(field) {
                    case 'date':
                        const dateA = parseInt(a.dataset.date) || 0;
                        const dateB = parseInt(b.dataset.date) || 0;
                        comparison = dateA - dateB;
                        break;
                    case 'patient':
                        const patientA = a.dataset.patientName || '';
                        const patientB = b.dataset.patientName || '';
                        comparison = patientA.localeCompare(patientB);
                        break;
                    case 'ci':
                        const ciA = a.dataset.ciName || '';
                        const ciB = b.dataset.ciName || '';
                        comparison = ciA.localeCompare(ciB);
                        break;
                }
                
                return order === 'asc' ? comparison : -comparison;
            });
        }
    </script>

    <!-- Incoming Request Details Modal -->
    <div id="incomingDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-2xl mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <i class="ri-download-line mr-2"></i>Incoming Transfer Request Details
                </h3>
                <button onclick="closeIncomingDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <div id="incomingDetails">
                <!-- Populated by JS -->
            </div>
            
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeIncomingDetailsModal()"
                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-6 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                    <i class="ri-close-line mr-1"></i>Close
                </button>
            </div>
        </div>
    </div>

    <!-- Outgoing Request Details Modal -->
    <div id="outgoingDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-2xl mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <i class="ri-upload-line mr-2"></i>Outgoing Transfer Request Details
                </h3>
                <button onclick="closeOutgoingDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <div id="outgoingDetails">
                <!-- Populated by JS -->
            </div>
            
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeOutgoingDetailsModal()"
                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-6 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                    <i class="ri-close-line mr-1"></i>Close
                </button>
            </div>
        </div>
    </div>

    <!-- Transfer History Details Modal -->
    <div id="historyDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-2xl mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 id="historyModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Transfer Details</h3>
                <button onclick="closeHistoryDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <div id="historyDetails">
                <!-- Populated by JS -->
            </div>
            
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeHistoryDetailsModal()"
                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-6 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                    <i class="ri-close-line mr-1"></i>Close
                </button>
            </div>
        </div>
    </div>

    <?php include 'includes/logout_modal.php'; ?>
</body>
</html>
