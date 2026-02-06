/**
 * Invitations Module
 * Handles user invitations and feature-based permissions
 */

const Invitations = {
    features: [],
    categories: {
        'leads': 'Lead Management',
        'users': 'User Management',
        'reports': 'Reports & Analytics',
        'settings': 'Settings & Config',
        'system': 'System & Audit'
    },

    /**
     * Initialize the module
     */
    init() {
        console.log('Invitations Module Initialized');
    },

    /**
     * Open the invitation modal
     */
    async openModal() {
        App.openModal('inviteUserModal');

        // Reset form
        document.getElementById('inviteUserForm').reset();
        document.getElementById('invitationLinkContainer').classList.add('hidden');
        document.getElementById('submitInviteBtn').disabled = false;
        document.getElementById('submitInviteBtn').textContent = 'Generate Invitation Link';

        // Load features if not already loaded
        if (this.features.length === 0) {
            await this.loadFeatures();
        } else {
            this.renderFeatures();
        }
    },

    /**
     * Close the invitation modal
     */
    closeModal() {
        App.closeModal('inviteUserModal');
    },

    /**
     * Load available features/permissions from API
     */
    async loadFeatures() {
        try {
            const container = document.getElementById('inviteFeaturesContainer');
            if (container) container.innerHTML = '<div class="text-center py-4 text-gray-400">Loading features...</div>';

            const data = await App.api('/admin/get_all_features.php');

            if (data && data.success) {
                this.features = data.features;
                this.renderFeatures();
            } else {
                throw new Error(data && data.error ? data.error : 'Failed to load features');
            }
        } catch (error) {
            console.error('Error loading features:', error);
            const container = document.getElementById('inviteFeaturesContainer');
            if (container) {
                container.innerHTML = `<div class="text-center py-4 text-red-500">Error: ${error.message}</div>`;
            }
        }
    },

    /**
     * Render features grouped by category
     */
    renderFeatures() {
        const container = document.getElementById('inviteFeaturesContainer');
        if (!container) return;

        const grouped = {};
        this.features.forEach(f => {
            if (!grouped[f.category]) grouped[f.category] = [];
            grouped[f.category].push(f);
        });

        let html = '';
        Object.keys(grouped).forEach(cat => {
            const catName = this.categories[cat] || cat;
            html += `
                <div class="feature-category">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 border-b pb-1">${catName}</h4>
                    <div class="grid grid-cols-1 gap-2">
                        ${grouped[cat].map(f => `
                            <label class="flex items-start p-2 rounded hover:bg-white transition-colors cursor-pointer border border-transparent hover:border-gray-200">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" name="features[]" value="${f.knob_key}" 
                                        class="feature-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                </div>
                                <div class="ml-3 text-sm">
                                    <span class="font-medium text-gray-700">${f.knob_name}</span>
                                    <p class="text-gray-500 text-xs">${f.description || ''}</p>
                                </div>
                            </label>
                        `).join('')}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    },

    /**
     * Toggle all feature checkboxes
     */
    toggleAllFeatures(checked) {
        document.querySelectorAll('.feature-checkbox').forEach(cb => cb.checked = checked);
    },

    /**
     * Submit invitation form
     */
    async submitInvitation(event) {
        if (event) event.preventDefault();

        const email = document.getElementById('inviteEmail').value;
        const role = document.getElementById('inviteRole').value;
        const selectedFeatures = Array.from(document.querySelectorAll('.feature-checkbox:checked'))
            .map(cb => cb.value);

        if (!email) {
            App.showToast('Please enter an email address', 'error');
            return;
        }

        if (selectedFeatures.length === 0) {
            App.showToast('Please select at least one feature permission', 'error');
            return;
        }

        const submitBtn = document.getElementById('submitInviteBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Generating...';

        try {
            const data = await App.api('/invitations/create.php', 'POST', {
                email,
                role,
                features: selectedFeatures,
                expiry_days: 7
            });

            if (data && data.success) {
                App.showToast('Invitation generated successfully', 'success');

                // Show link
                document.getElementById('invitationLinkContainer').classList.remove('hidden');
                document.getElementById('generatedInviteLink').value = data.invitation_url;

                // Keep modal open to copy link
                submitBtn.textContent = 'Invitation Generated';

                // Reload list if in list view
                if (document.getElementById('invitationsListView')) {
                    this.loadInvitations();
                }
            } else {
                throw new Error(data && data.error ? data.error : 'Failed to create invitation');
            }
        } catch (error) {
            console.error('Error creating invitation:', error);
            App.showToast(error.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Generate Invitation Link';
        }
    },

    /**
     * Copy invitation link to clipboard
     */
    copyLink() {
        const linkInput = document.getElementById('generatedInviteLink');
        linkInput.select();
        document.execCommand('copy');
        App.showToast('Invitation link copied to clipboard', 'success');
    },

    /**
     * Load and render invitations list view
     */
    async renderListView() {
        const container = document.getElementById('appContent');
        if (!container) return;

        container.innerHTML = `
            <div id="invitationsListView" class="space-y-6">
                <div class="flex justify-between items-center px-4 sm:px-0">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">User Invitations</h1>
                        <p class="text-sm text-gray-500 mt-1">Manage pending user invitations and access links</p>
                    </div>
                    <button onclick="Invitations.openModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                        <i data-lucide="user-plus" class="h-4 w-4 mr-2"></i>
                        Invite User
                    </button>
                </div>

                <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Features</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="invitationsListBody" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                        Loading invitations...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        if (window.lucide) {
            window.lucide.createIcons();
        }
        await this.loadInvitations();
    },

    /**
     * Load invitations from API
     */
    async loadInvitations() {
        try {
            const data = await App.api('/invitations/list.php');

            if (data && data.success) {
                this.renderInvitationsList(data.data.invitations);
            } else {
                throw new Error(data && data.error ? data.error : 'Failed to load invitations');
            }
        } catch (error) {
            console.error('Error loading invitations:', error);
            const body = document.getElementById('invitationsListBody');
            if (body) {
                body.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-red-500 font-medium">
                            Error: ${error.message}
                        </td>
                    </tr>
                `;
            }
        }
    },

    /**
     * Render invitations in the table
     */
    renderInvitationsList(invitations) {
        const body = document.getElementById('invitationsListBody');
        if (!body) return;

        if (!invitations || invitations.length === 0) {
            body.innerHTML = `
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i data-lucide="mail" class="h-12 w-12 mx-auto mb-4 text-gray-400 opacity-50"></i>
                        <p class="text-lg font-medium">No pending invitations</p>
                        <p class="text-sm text-gray-400">Invite a team member to see them here.</p>
                    </td>
                </tr>
            `;
            if (window.lucide) window.lucide.createIcons();
            return;
        }

        body.innerHTML = invitations.map(inv => `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${inv.email}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100 italic">
                        ${inv.role}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${this.getStatusBadge(inv.status)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                        ${inv.assigned_features ? (Array.isArray(inv.assigned_features) ? inv.assigned_features.length : 0) : 0} features
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${App.formatDate(inv.created_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex items-center">
                        <div class="h-6 w-6 rounded-full bg-gray-200 flex items-center justify-center mr-2 text-[10px] font-bold text-gray-600">
                            ${inv.created_by_name ? inv.created_by_name.charAt(0).toUpperCase() : 'S'}
                        </div>
                        ${inv.created_by_name || 'System'}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    ${inv.status === 'pending' || inv.status === 'expired' ? `
                        <button onclick="Invitations.revokeInvitation(${inv.id})" class="text-red-600 hover:text-red-900 font-semibold px-3 py-1 rounded hover:bg-red-50 transition-colors">Revoke</button>
                    ` : ''}
                </td>
            </tr>
        `).join('');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    },

    /**
     * Get HTML for status badge
     */
    getStatusBadge(status) {
        let colors = 'bg-gray-100 text-gray-800 border-gray-200';
        if (status === 'pending') colors = 'bg-yellow-50 text-yellow-700 border-yellow-200';
        if (status === 'used') colors = 'bg-green-50 text-green-700 border-green-200';
        if (status === 'expired') colors = 'bg-red-50 text-red-700 border-red-200';
        if (status === 'revoked') colors = 'bg-orange-50 text-orange-700 border-orange-200';

        return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${colors} capitalize shadow-sm">${status}</span>`;
    },

    /**
     * Revoke an invitation
     */
    async revokeInvitation(id) {
        if (!confirm('Are you sure you want to revoke this invitation? The link will stop working immediately.')) {
            return;
        }

        try {
            const data = await App.api('/invitations/revoke.php', 'POST', {
                invitation_id: id
            });

            if (data && data.success) {
                App.showToast('Invitation revoked successfully', 'success');
                this.loadInvitations();
            } else {
                throw new Error(data && data.error ? data.error : 'Failed to revoke invitation');
            }
        } catch (error) {
            console.error('Error revoking invitation:', error);
            App.showToast(error.message, 'error');
        }
    }
};

window.Invitations = Invitations;
