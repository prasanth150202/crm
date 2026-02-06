// js/modules/feature_knobs.js
// Feature Knob Management Module (Admin Only)

export const FeatureKnobs = {
    currentRole: 'staff',
    allKnobs: [],
    changes: {},

    /**
     * Open the Feature Knob Panel modal
     */
    async openModal() {
        document.getElementById('featureKnobModal').classList.remove('hidden');
        this.currentRole = 'staff';
        this.changes = {};
        await this.loadKnobs();
    },

    /**
     * Close the modal
     */
    closeModal() {
        document.getElementById('featureKnobModal').classList.add('hidden');
        this.changes = {};
    },

    /**
     * Load all feature knobs from API
     */
    async loadKnobs() {
        try {
            const response = await fetch('/api/admin/feature_knobs/list.php');
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load feature knobs');
            }

            this.allKnobs = data.knobs;
            this.renderKnobs();

        } catch (error) {
            console.error('Error loading feature knobs:', error);
            this.showError('Failed to load feature permissions: ' + error.message);
        }
    },

    /**
     * Switch to a different role tab
     */
    switchRole(role) {
        this.currentRole = role;

        // Update tab styling
        document.querySelectorAll('.role-tab').forEach(tab => {
            if (tab.dataset.role === role) {
                tab.classList.remove('border-transparent', 'text-gray-500');
                tab.classList.add('border-blue-500', 'text-blue-600');
            } else {
                tab.classList.remove('border-blue-500', 'text-blue-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            }
        });

        this.renderKnobs();
    },

    /**
     * Render feature knobs grouped by category
     */
    renderKnobs() {
        const content = document.getElementById('featureKnobsContent');

        // Group knobs by category
        const grouped = {};
        this.allKnobs.forEach(knob => {
            if (!grouped[knob.category]) {
                grouped[knob.category] = [];
            }
            grouped[knob.category].push(knob);
        });

        // Category labels
        const categoryLabels = {
            'leads': 'Lead Management',
            'users': 'User Management',
            'reports': 'Reports & Analytics',
            'settings': 'Settings & Configuration',
            'system': 'System & Audit'
        };

        // Render HTML
        let html = '';

        for (const [category, knobs] of Object.entries(grouped)) {
            html += `
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-2">${categoryLabels[category] || category}</span>
                    </h4>
                    <div class="space-y-2">
            `;

            knobs.forEach(knob => {
                const isEnabled = this.getKnobState(knob.knob_key);
                const changeKey = `${this.currentRole}:${knob.knob_key}`;
                const hasChange = this.changes.hasOwnProperty(changeKey);

                html += `
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md ${hasChange ? 'ring-2 ring-blue-300' : ''}">
                        <div class="flex-1">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" 
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3"
                                    ${isEnabled ? 'checked' : ''}
                                    onchange="FeatureKnobs.toggleKnob('${knob.knob_key}', this.checked)"
                                    ${this.currentRole === 'owner' ? 'disabled' : ''}>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">${knob.knob_name}</div>
                                    <div class="text-xs text-gray-500">${knob.description}</div>
                                </div>
                            </label>
                        </div>
                        ${hasChange ? '<span class="text-xs text-blue-600 font-medium">Modified</span>' : ''}
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }

        if (this.currentRole === 'owner') {
            html = '<div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4"><p class="text-sm text-blue-800"><strong>Note:</strong> Owner role has all permissions enabled by default and cannot be modified.</p></div>' + html;
        }

        content.innerHTML = html;
    },

    /**
     * Get current state of a knob for the current role
     */
    getKnobState(knob_key) {
        const changeKey = `${this.currentRole}:${knob_key}`;

        // Check if there's a pending change
        if (this.changes.hasOwnProperty(changeKey)) {
            return this.changes[changeKey];
        }

        // Get from loaded data
        const knob = this.allKnobs.find(k => k.knob_key === knob_key);
        return knob ? knob.permissions[this.currentRole] : false;
    },

    /**
     * Toggle a feature knob
     */
    toggleKnob(knob_key, isEnabled) {
        const changeKey = `${this.currentRole}:${knob_key}`;
        const originalValue = this.allKnobs.find(k => k.knob_key === knob_key)?.permissions[this.currentRole];

        // If it's back to original, remove from changes
        if (originalValue === isEnabled) {
            delete this.changes[changeKey];
        } else {
            this.changes[changeKey] = isEnabled;
        }

        this.renderKnobs();
    },

    /**
     * Save all changes
     */
    async saveChanges() {
        if (Object.keys(this.changes).length === 0) {
            this.showMessage('No changes to save', 'info');
            return;
        }

        try {
            // Group changes by role
            const changesByRole = {};
            for (const [key, value] of Object.entries(this.changes)) {
                const [role, knob_key] = key.split(':');
                if (!changesByRole[role]) {
                    changesByRole[role] = {};
                }
                changesByRole[role][knob_key] = value;
            }

            // Save each role's changes
            for (const [role, permissions] of Object.entries(changesByRole)) {
                const response = await fetch('/api/admin/feature_knobs/bulk_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role, permissions })
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to save changes');
                }
            }

            this.showMessage('Feature permissions updated successfully!', 'success');
            this.changes = {};
            await this.loadKnobs(); // Reload to get fresh data

        } catch (error) {
            console.error('Error saving changes:', error);
            this.showError('Failed to save changes: ' + error.message);
        }
    },

    /**
     * Reset to default permissions
     */
    async resetToDefaults() {
        if (!confirm(`Reset ${this.currentRole} role to default permissions? This will discard any unsaved changes.`)) {
            return;
        }

        // Clear changes for this role
        for (const key in this.changes) {
            if (key.startsWith(this.currentRole + ':')) {
                delete this.changes[key];
            }
        }

        this.showMessage('Changes discarded. Click "Save Changes" after making new modifications.', 'info');
        this.renderKnobs();
    },

    /**
     * Show success/info message
     */
    showMessage(message, type = 'success') {
        const bgColor = type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-blue-50 border-blue-200 text-blue-800';
        const content = document.getElementById('featureKnobsContent');
        const alert = document.createElement('div');
        alert.className = `${bgColor} border rounded-md p-4 mb-4`;
        alert.innerHTML = `<p class="text-sm">${message}</p>`;
        content.insertBefore(alert, content.firstChild);

        setTimeout(() => alert.remove(), 5000);
    },

    /**
     * Show error message
     */
    showError(message) {
        const content = document.getElementById('featureKnobsContent');
        const alert = document.createElement('div');
        alert.className = 'bg-red-50 border border-red-200 text-red-800 rounded-md p-4 mb-4';
        alert.innerHTML = `<p class="text-sm"><strong>Error:</strong> ${message}</p>`;
        content.insertBefore(alert, content.firstChild);
    }
};

// Make globally available
window.FeatureKnobs = FeatureKnobs;
