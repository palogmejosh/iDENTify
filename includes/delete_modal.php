<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-gradient-to-br from-white to-red-50 dark:from-gray-800 dark:to-red-900 p-6 rounded-lg shadow-xl border border-red-200 dark:border-red-700 max-w-sm mx-4 animate-modal-appear">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center mr-3">
                <i class="ri-delete-bin-line text-red-600 dark:text-red-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Delete</h3>
        </div>
        
        <div class="mb-6">
            <p class="text-gray-700 dark:text-gray-300" id="deleteModalMessage">
                Are you sure you want to delete this item? This action cannot be undone.
            </p>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button onclick="hideDeleteModal()" 
                    class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                <i class="ri-close-line mr-1"></i>Cancel
            </button>
            <button onclick="confirmDelete()" 
                    id="confirmDeleteBtn"
                    class="px-4 py-2 bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600 text-white rounded-md transition-all duration-200 shadow-lg">
                <i class="ri-delete-bin-line mr-1"></i>Delete
            </button>
        </div>
    </div>
</div>

<style>
@keyframes modal-appear {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.animate-modal-appear {
    animation: modal-appear 0.2s ease-out;
}
</style>

<script>
let deleteModalCallback = null;
let deleteModalData = null;

function showDeleteModal(message, callback, data = null) {
    const modal = document.getElementById('deleteModal');
    const messageElement = document.getElementById('deleteModalMessage');
    
    if (messageElement) {
        messageElement.innerHTML = message;
    }
    
    deleteModalCallback = callback;
    deleteModalData = data;
    
    if (modal) {
        modal.style.display = 'flex';
        
        // Focus management for accessibility
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (confirmBtn) {
            setTimeout(() => confirmBtn.focus(), 100);
        }
    }
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'none';
    }
    deleteModalCallback = null;
    deleteModalData = null;
}

function confirmDelete() {
    if (deleteModalCallback) {
        if (deleteModalData) {
            deleteModalCallback(deleteModalData);
        } else {
            deleteModalCallback();
        }
    }
    hideDeleteModal();
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('deleteModal');
        if (modal && modal.style.display === 'flex') {
            hideDeleteModal();
        }
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('deleteModal');
    if (e.target === modal) {
        hideDeleteModal();
    }
});
</script>