<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
    <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-6 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                    <i class="ri-logout-box-line text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Confirm Logout</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Are you sure you want to log out of your account?</p>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900 border border-violet-200 dark:border-violet-800 rounded-md p-3 mb-4">
            <div class="flex items-center">
                <i class="ri-information-line text-violet-600 dark:text-violet-400 mr-2"></i>
                <span class="text-sm text-gray-700 dark:text-gray-300">You'll need to log in again to access your account.</span>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="hideLogoutModal()"
                    class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200">
                <i class="ri-close-line mr-1"></i>Cancel
            </button>
            <button type="button" onclick="confirmLogout()"
                    class="bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200 flex items-center">
                <i class="ri-logout-box-line mr-1"></i>Yes, Logout
            </button>
        </div>
    </div>
</div>

<script>
/* ---------- Logout Confirmation Modal Functions ---------- */
function showLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function hideLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout() {
    window.location.href = 'logout.php';
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                hideLogoutModal();
            }
        });
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const logoutModal = document.getElementById('logoutModal');
        if (logoutModal && logoutModal.style.display === 'flex') {
            hideLogoutModal();
        }
    }
});
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