
class RealtimeManager {
    constructor() {
        this.updateIntervals = new Map();
        this.lastUpdates = new Map();
        this.callbacks = new Map();
    }

    // Start polling for updates
    startPolling(endpoint, interval = 5000, callback = null) {
        this.stopPolling(endpoint);
        
        if (callback) {
            this.callbacks.set(endpoint, callback);
        }
        
        const poll = async () => {
            try {
                const response = await this.checkForUpdates(endpoint);
                if (response.has_updates && this.callbacks.has(endpoint)) {
                    this.callbacks.get(endpoint)(response.data);
                    this.lastUpdates.set(endpoint, response.last_update);
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        };
        
        // Initial load
        poll();
        
        // Set up interval
        const intervalId = setInterval(poll, interval);
        this.updateIntervals.set(endpoint, intervalId);
    }

    // Stop polling for specific endpoint
    stopPolling(endpoint) {
        if (this.updateIntervals.has(endpoint)) {
            clearInterval(this.updateIntervals.get(endpoint));
            this.updateIntervals.delete(endpoint);
        }
    }

    // Check for updates
    async checkForUpdates(endpoint) {
        const lastUpdate = this.lastUpdates.get(endpoint) || '';
        const url = new URL(`api.php`, window.location.origin);
        
        // Add endpoint-specific parameters
        if (endpoint === 'patients') {
            url.searchParams.append('action', 'get_patients');
            const searchTerm = document.getElementById('patientSearch')?.value || '';
            if (searchTerm) url.searchParams.append('search', searchTerm);
        } else if (endpoint === 'users') {
            url.searchParams.append('action', 'get_users');
            const roleFilter = new URLSearchParams(window.location.search).get('role') || 'all';
            const searchTerm = new URLSearchParams(window.location.search).get('search') || '';
            url.searchParams.append('role', roleFilter);
            if (searchTerm) url.searchParams.append('search', searchTerm);
        }
        
        if (lastUpdate) {
            url.searchParams.append('last_update', lastUpdate);
        }
        
        const response = await fetch(url);
        return await response.json();
    }

    // Add new patient via API
    async addPatient(patientData) {
        try {
            const response = await fetch('api.php?action=add_patient', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(patientData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.lastUpdates.set('patients', result.last_update);
                // Trigger immediate update
                if (this.callbacks.has('patients')) {
                    const patientsData = await this.checkForUpdates('patients');
                    this.callbacks.get('patients')(patientsData.data);
                }
            }
            
            return result;
        } catch (error) {
            console.error('Error adding patient:', error);
            return { success: false, message: 'Network error' };
        }
    }

    // Add new user via API
    async addUser(userData) {
        try {
            const response = await fetch('api.php?action=add_user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.lastUpdates.set('users', result.last_update);
                // Trigger immediate update
                if (this.callbacks.has('users')) {
                    const usersData = await this.checkForUpdates('users');
                    this.callbacks.get('users')(usersData.data);
                }
            }
            
            return result;
        } catch (error) {
            console.error('Error adding user:', error);
            return { success: false, message: 'Network error' };
        }
    }

    // Clean up all intervals
    cleanup() {
        this.updateIntervals.forEach(intervalId => clearInterval(intervalId));
        this.updateIntervals.clear();
        this.callbacks.clear();
        this.lastUpdates.clear();
    }
}

// Global realtime manager instance
window.realtimeManager = new RealtimeManager();

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    window.realtimeManager.cleanup();
});
