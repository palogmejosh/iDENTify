<?php 
// Get current user role if available
$role = isset($user) ? ($user['role'] ?? '') : (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '');
?>
<!-- Modal: Add Patient -->
<div id="addPatientModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-start pt-24 z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-96 modal-fade">
        <h3 class="text-lg font-semibold mb-4">Add New Patient</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
                <label class="block text-sm font-medium">First Name</label>
                <input type="text" name="firstName" required class="form-input w-full">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium">Last Name</label>
                <input type="text" name="lastName" required class="form-input w-full">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium">Age</label>
                <input type="number" name="age" required class="form-input w-full">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium">Phone</label>
                <input type="text" name="phone" required class="form-input w-full">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium">Email</label>
                <input type="email" name="email" required class="form-input w-full">
            </div>
            <?php if ($role !== 'Clinician'): ?>
            <div class="mb-4">
                <label class="block text-sm font-medium">Status</label>
                <select name="status" class="form-input w-full">
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Declined">Declined</option>
                </select>
            </div>
            <?php else: ?>
            <!-- Hidden input for Clinicians to ensure status defaults to Pending -->
            <input type="hidden" name="status" value="Pending">
            <?php endif; ?>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="document.getElementById('addPatientModal').style.display='none'" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>
