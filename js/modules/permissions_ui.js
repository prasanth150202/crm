// js/modules/permissions_ui.js
// Dynamic UI behavior based on user permissions

const PermissionsUI = {
    userPermissions: [],

    /**
     * Initialize permissions UI - load user permissions and apply to UI
     */
    async init() {
        await this.loadUserPermissions();
        this.applyPermissionsToUI();
    },

    /**
     * Load current user's permissions from backend
     */
    async loadUserPermissions() {
        try {
            const user = window.AppData?.user;
            if (!user || !user.user_id) {
                console.warn('No user data available');
                return;
            }

            const response = await fetch(`${window.App.apiUrl}/users/get_permissions.php?user_id=${user.user_id}`, {
                credentials: 'include'
            });

            const data = await response.json();
            if (data.success && data.permissions) {
                // Store as array of enabled permission keys
                this.userPermissions = Object.keys(data.permissions).filter(key => data.permissions[key]);
                console.log('Loaded permissions:', this.userPermissions);
            }
        } catch (error) {
            console.error('Failed to load user permissions:', error);
        }
    },

    /**
     * Check if user has a specific permission
     */
    hasPermission(knobKey) {
        return this.userPermissions.includes(knobKey);
    },

    /**
     * Apply permissions to UI - hide/disable elements based on permissions
     */
    applyPermissionsToUI() {
        // Find all elements with data-feature attribute
        document.querySelectorAll('[data-feature]').forEach(element => {
            const requiredFeature = element.dataset.feature;

            if (!this.hasPermission(requiredFeature)) {
                // Hide element if user doesn't have permission
                element.style.display = 'none';
                element.classList.add('permission-hidden');
            } else {
                // Show element if user has permission
                element.style.display = '';
                element.classList.remove('permission-hidden');
            }
        });

        // Find all elements with data-feature-disable attribute (disable instead of hide)
        document.querySelectorAll('[data-feature-disable]').forEach(element => {
            const requiredFeature = element.dataset.featureDisable;

            if (!this.hasPermission(requiredFeature)) {
                element.disabled = true;
                element.classList.add('opacity-50', 'cursor-not-allowed');
                element.title = 'You do not have permission for this action';
            } else {
                element.disabled = false;
                element.classList.remove('opacity-50', 'cursor-not-allowed');
                element.title = '';
            }
        });
    },

    /**
     * Refresh UI permissions (call after permissions change)
     */
    async refresh() {
        await this.loadUserPermissions();
        this.applyPermissionsToUI();
    }
};

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => PermissionsUI.init());
} else {
    PermissionsUI.init();
}

// Make globally available
window.PermissionsUI = PermissionsUI;
