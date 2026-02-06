/**
 * User management and settings.
 */
/**
 * User management and settings.
 */

const App = window.App || {};

Object.assign(App, {

    goToFieldManagement() {
        import('./field_management.js').then(m => {
            if (m.FieldManager) {
                m.FieldManager.init();
            } else if (window.FieldManager) {
                window.FieldManager.init();
            }
        });
    },
    async loadSettings() {
        document.getElementById('appContent').innerHTML = '<div class="text-center py-10"><p class="text-gray-500">Loading Settings...</p></div>';
        try {
            const users = await this.api('/users/list.php');

            if (users && users.error === 'Unauthorized') {
                document.getElementById('appContent').innerHTML = '<div class="max-w-md mx-auto mt-10 p-6 bg-yellow-50 border border-yellow-200 rounded-lg"><h3 class="text-lg font-semibold text-yellow-800 mb-2">Session Expired</h3><p class="text-sm text-yellow-700 mb-4">Please log out and log back in.</p><button onclick="App.logout()" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">Log Out</button></div>';
                return;
            }

            if (users && users.error === 'Permission denied') {
                const user = this.getUser();
                document.getElementById('appContent').innerHTML = `
                    <div class="max-w-2xl mx-auto mt-10 p-6 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-800 mb-2">⚠️ Access Restricted</h3>
                        <p class="text-sm text-blue-700 mb-4">The Settings page is only accessible to <strong>Owners</strong> and <strong>Admins</strong>.</p>
                        <p class="text-sm text-blue-600 mb-4">Your current role: <strong>${user.role}</strong></p>
                        <div class="bg-white p-4 rounded border border-blue-100 mb-4">
                            <p class="text-sm font-semibold text-gray-700 mb-2">What you can do:</p>
                            <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
                                <li>View and manage your assigned leads</li>
                                <li>Change your password (click your name in sidebar)</li>
                                <li>Use the dashboard and reports</li>
                            </ul>
                        </div>
                        <button onclick="App.router('leads')" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Go to Leads
                        </button>
                    </div>
                `;
                return;
            }

            if (users && users.success) {
                this.renderSettings(users.users);
            } else {
                const errorMsg = users && users.error ? users.error : 'Unknown error';
                document.getElementById('appContent').innerHTML = `
                    <div class="max-w-md mx-auto mt-10 p-6 bg-red-50 border border-red-200 rounded-lg">
                        <h3 class="text-lg font-semibold text-red-800 mb-2">Failed to Load Settings</h3>
                        <p class="text-sm text-red-700 mb-4">Error: ${errorMsg}</p>
                        <button onclick="App.loadSettings()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                            Retry
                        </button>
                    </div>
                `;
            }
        } catch (e) {
            console.error('Settings error:', e);
            document.getElementById('appContent').innerHTML = `
                <div class="max-w-md mx-auto mt-10 p-6 bg-red-50 border border-red-200 rounded-lg">
                    <h3 class="text-lg font-semibold text-red-800 mb-2">Error Loading Settings</h3>
                    <p class="text-sm text-red-700 mb-4">${e.message}</p>
                    <button onclick="App.loadSettings()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Retry
                    </button>
                </div>
            `;
        }
    },

    async renderSettings(users) {
        this._currentUsers = users; // Store for lookup
        const container = document.getElementById('appContent');
        const currentUser = this.getUser();
        const isAdmin = currentUser && (currentUser.role === 'admin' || currentUser.role === 'owner');

        const roleLabels = {
            'owner': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Owner</span>',
            'admin': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Admin</span>',
            'manager': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Manager</span>',
            'sales_rep': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Staff</span>'
        };

        // Load field configurations
        let fieldConfig = {};
        try {
            const fieldConfigRes = await this.api('/settings/get_field_config.php');
            if (fieldConfigRes && fieldConfigRes.success) {
                fieldConfig = fieldConfigRes.fields || {};
            }
        } catch (e) {
            // Use default config if API fails
            fieldConfig = {};
        }

        const htmlContent = `
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Settings</h2>
                        <p class="text-sm text-gray-500 mt-1">Manage users and organization settings</p>
                    </div>
                    <div class="flex space-x-3">
                        ${isAdmin ? `
                            <button onclick="if(window.FeatureKnobs) { window.FeatureKnobs.openModal(); } else { import('./modules/feature_knobs.js').then(m => { window.FeatureKnobs = m.FeatureKnobs; m.FeatureKnobs.openModal(); }); }" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                                <i data-lucide="sliders" class="h-4 w-4 mr-2"></i>Feature Permissions
                            </button>
                        ` : ''}
                        <button onclick="if(window.Invitations) { window.Invitations.openModal(); } else { alert('Invitations module not loaded'); }" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                            <i data-lucide="user-plus" class="h-4 w-4 mr-2"></i>Invite User
                        </button>
                        <button onclick="App.goToFieldManagement()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="settings" class="h-4 w-4 mr-2"></i>Manage Fields
                        </button>
                    </div>
                </div>
                <!-- Field Management Section - ALWAYS VISIBLE -->
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden" id="fieldManagementSection">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Field Management</h3>
                        <p class="text-sm text-gray-500 mt-1">Manage dropdown options and field configurations</p>
                        ${!isAdmin ? `<p class="text-xs text-yellow-600 mt-2">⚠️ Admin/Owner access required. Your role: ${currentUser ? currentUser.role : 'unknown'}</p>` : ''}
                    </div>
                    <div class="p-6" id="fieldManagementContent">
                        <div class="text-center py-4">
                            <p class="text-gray-500">Loading field configurations...</p>
                        </div>
                    </div>
                </div>
                
                <!-- API Integration Section -->
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">API Integration</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">External API Access</h4>
                                <p class="text-sm text-gray-500 mt-1">Generate API key for your current organization.</p>
                            </div>
                            <button onclick="App.generateApiKey()" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i data-lucide="key" class="h-4 w-4 mr-2"></i>Generate API Key
                            </button>
                        </div>
                        <div id="apiKeyDisplay" class="mt-2">
                            <div class="bg-gray-50 rounded-md p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current API Key:</label>
                                <div class="flex">
                                    <input type="text" id="apiKeyValue" readonly class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-gray-300 bg-gray-50 text-sm font-mono" placeholder="No key yet">
                                    <button onclick="App.copyApiKey()" class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-500 hover:bg-gray-100">
                                        <i data-lucide="copy" class="h-4 w-4"></i>
                                    </button>
                                </div>
                                <p id="apiKeyOrgLabel" class="text-xs text-gray-500 mt-2">&nbsp;</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Zingbot Integration Section -->
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Zingbot Configuration</h3>
                        <p class="text-sm text-gray-500 mt-1">Configure connection to Zingbot automation platform</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">API Endpoint</label>
                            <input type="url" id="zingbotEndpoint" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="https://api.zingbot.com/v1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">API Key</label>
                            <div class="mt-1 flex rounded-md shadow-sm">
                                <input type="password" id="zingbotApiKey" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Enter API Key">
                                <button type="button" onclick="App.toggleZingbotKeyVisibility()" class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                    <i data-lucide="eye" id="zingbotKeyIcon" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="zingbotActive" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="zingbotActive" class="ml-2 block text-sm text-gray-900">Enable Zingbot Integration</label>
                        </div>
                        <div class="flex justify-end space-x-3 pt-2">
                            <button onclick="App.testZingbotConnection()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 text-sm font-medium">
                                Test Connection
                            </button>
                            <button onclick="App.saveZingbotSettings()" class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700 text-sm font-medium">
                                Save Settings
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Team Members</h3>
                        <button onclick="App.router('invitations')" class="text-sm text-blue-600 hover:text-blue-500 font-medium flex items-center">
                            <i data-lucide="mail" class="h-4 w-4 mr-1"></i>View Pending Invitations
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leads</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${users.map(user => `
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-gray-600">${user.full_name ? user.full_name.charAt(0).toUpperCase() : 'U'}</span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">${user.full_name || 'No Name'}</div>
                                                    <div class="text-sm text-gray-500">${user.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">${roleLabels[user.role] || roleLabels['sales_rep']}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${user.lead_count || 0}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            ${user.is_active ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>' : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>'}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            ${user.role !== 'owner' ? `
                                                ${isAdmin ? `
                                                    <button onclick="App.openUserModal(${user.id})" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                                    <button onclick="App.deleteUser(${user.id})" class="text-red-600 hover:text-red-900 mr-3">Delete</button>
                                                ` : ''}
                                                <button onclick="App.toggleUserStatus(${user.id}, ${!user.is_active})" class="text-gray-600 hover:text-gray-900">${user.is_active ? 'Deactivate' : 'Activate'}</button>
                                            ` : '<span class="text-gray-400">Owner</span>'}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = htmlContent;

        lucide.createIcons();
        this.loadApiKeyDisplay();
        this.loadZingbotSettings();

        // Load field management
        setTimeout(() => {
            const container = document.getElementById('fieldManagementContent');
            if (container) {
                try {
                    this.renderFieldManagement(fieldConfig, isAdmin);
                } catch (error) {
                    container.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <p class="text-sm text-red-800">Error loading field management. Please refresh the page.</p>
                        </div>
                    `;
                }
            }
        }, 200);
    },

    async renderFieldManagement(fieldConfig, isAdmin = true) {
        const container = document.getElementById('fieldManagementContent');
        if (!container) return;

        // If not admin, show message
        if (!isAdmin) {
            container.innerHTML = `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">
                        ⚠️ Field Management is only available for Admin and Owner roles. 
                        Please contact your administrator to manage field options.
                    </p>
                </div>
            `;
            return;
        }

        // Default configurations
        const defaultFields = {
            'source': {
                type: 'select',
                options: ['Direct', 'Website', 'LinkedIn', 'Referral', 'Ads', 'Cold Call'],
                label: 'Source'
            },
            'stage_id': {
                type: 'select',
                options: ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'],
                label: 'Stage'
            }
        };

        // Merge with saved config
        if (!fieldConfig || typeof fieldConfig !== 'object') {
            fieldConfig = {};
        }
        const fields = { ...defaultFields, ...fieldConfig };

        try {
            const fieldsHtml = Object.entries(fields).map(([fieldName, config]) => {
                if (!config || !config.options || !Array.isArray(config.options)) {
                    return '';
                }
                return `
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">${config.label || fieldName}</h4>
                                <p class="text-xs text-gray-500 mt-1">Field: ${fieldName}</p>
                            </div>
                            <button onclick="App.editFieldOptions('${fieldName}')" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-blue-600 bg-blue-50 hover:bg-blue-100">
                                <i data-lucide="edit" class="h-3 w-3 mr-1"></i>Edit Options
                            </button>
                        </div>
                        <div class="bg-gray-50 rounded-md p-3">
                            <p class="text-xs text-gray-600 mb-2">Current Options:</p>
                            <div class="flex flex-wrap gap-2">
                                ${config.options.map(opt => `
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white text-gray-800 border border-gray-300">
                                        ${opt}
                                    </span>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;
            }).filter(html => html.length > 0).join('');

            container.innerHTML = `
                <div class="space-y-6">
                    ${fieldsHtml}
                </div>
            `;

            lucide.createIcons();
        } catch (error) {
            container.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-sm text-red-800">Error loading field management. Please refresh the page.</p>
                </div>
            `;
        }
    },

    async editFieldOptions(fieldName) {
        // Check if user is admin
        const currentUser = this.getUser();
        const isAdmin = currentUser && (currentUser.role === 'admin' || currentUser.role === 'owner');

        if (!isAdmin) {
            alert('Only Admin and Owner roles can edit field options.');
            return;
        }

        try {
            // Get current field config
            const fieldConfigRes = await this.api('/settings/get_field_config.php');
            const fieldConfig = fieldConfigRes && fieldConfigRes.success ? fieldConfigRes.fields : {};
            const currentField = fieldConfig[fieldName] || {
                type: 'select',
                options: fieldName === 'source'
                    ? ['Direct', 'Website', 'LinkedIn', 'Referral', 'Ads', 'Cold Call']
                    : ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'],
                label: fieldName === 'source' ? 'Source' : 'Stage'
            };

            // Create modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 z-50 overflow-y-auto';
            modal.innerHTML = `
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="this.closest('.fixed').remove()"></div>
                    <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Edit ${currentField.label} Options</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-500">
                                <i data-lucide="x" class="h-6 w-6"></i>
                            </button>
                        </div>
                        <form id="editFieldOptionsForm" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Options (one per line)</label>
                                <textarea id="fieldOptionsTextarea" rows="8" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border" placeholder="Direct&#10;Website&#10;LinkedIn">${currentField.options.join('\n')}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Enter each option on a new line</p>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Save Options
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            lucide.createIcons();

            // Handle form submit
            modal.querySelector('#editFieldOptionsForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const optionsText = document.getElementById('fieldOptionsTextarea').value;
                const options = optionsText.split('\n').map(o => o.trim()).filter(o => o.length > 0);

                if (options.length === 0) {
                    alert('Please enter at least one option');
                    return;
                }

                // Update field config
                const updatedConfig = { ...fieldConfig };
                updatedConfig[fieldName] = {
                    ...currentField,
                    options: options
                };

                try {
                    const result = await this.api('/settings/save_field_config.php', 'POST', {
                        field_config: updatedConfig
                    });

                    if (result && result.success) {
                        this.showToast(`${currentField.label} options saved successfully`, 'success');
                        modal.remove();
                        this.loadSettings(); // Reload settings to show updated options
                    } else {
                        alert('Error: ' + (result.error || 'Failed to save options'));
                    }
                } catch (error) {
                    console.error('Error saving field config:', error);
                    alert('Failed to save options');
                }
            });

        } catch (error) {
            console.error('Error editing field options:', error);
            alert('Failed to load field configuration');
        }
    },

    async openUserModal(userIdOrData = null) {
        let userData = userIdOrData;
        if (userIdOrData && (typeof userIdOrData === 'number' || typeof userIdOrData === 'string')) {
            userData = this._currentUsers.find(u => u.id == userIdOrData);
        }

        const modal = document.getElementById('userModal');
        const form = document.getElementById('userForm');
        const title = document.getElementById('userModalTitle');
        const submitText = document.getElementById('userSubmitText');

        // Set up fields as text inputs
        if (userData) {
            title.textContent = 'Edit User';
            submitText.textContent = 'Update User';
            document.getElementById('userEditId').value = userData.id;
            document.getElementById('modalUserFullName').value = userData.full_name || '';
            document.getElementById('modalUserEmail').value = userData.email || '';
            // Fix: always set role, even if null
            document.getElementById('modalUserRole').value = (userData.role !== undefined && userData.role !== null) ? userData.role : '';
            document.getElementById('modalUserIsActive').checked = !!userData.is_active;
            document.getElementById('modalUserEmail').readOnly = true;
            document.getElementById('modalUserEmail').classList.add('bg-gray-50');
            // Fix: always set super admin checkbox
            const superAdminCheckbox = document.getElementById('modalUserSuperAdmin');
            if (superAdminCheckbox) {
                superAdminCheckbox.checked = userData.is_super_admin == 1 || userData.is_super_admin === true;
            }
        } else {
            title.textContent = 'Add User';
            submitText.textContent = 'Add User';
            form.reset();
            document.getElementById('userEditId').value = '';
            document.getElementById('modalUserEmail').readOnly = false;
            document.getElementById('modalUserEmail').classList.remove('bg-gray-50');
            document.getElementById('modalUserIsActive').checked = true;
            // Fix: always reset super admin checkbox
            const superAdminCheckbox = document.getElementById('modalUserSuperAdmin');
            if (superAdminCheckbox) {
                superAdminCheckbox.checked = false;
            }
        }

        // Super Admin checkbox logic
        const superAdminCheckbox = document.getElementById('modalUserSuperAdmin');
        const isSuperAdmin = superAdminCheckbox && superAdminCheckbox.checked;

        // Show/hide permissions based on super admin
        const permissionsContainer = document.getElementById('userPermissionsContainer');
        if (isSuperAdmin) {
            if (permissionsContainer) permissionsContainer.style.display = 'none';
        } else {
            if (permissionsContainer) permissionsContainer.style.display = '';
        }

        // Listen for super admin toggle
        if (superAdminCheckbox) {
            superAdminCheckbox.onchange = function () {
                if (this.checked) {
                    if (permissionsContainer) permissionsContainer.style.display = 'none';
                } else {
                    if (permissionsContainer) permissionsContainer.style.display = '';
                }
            };
        }

        // Load permissions only if not super admin
        if (!isSuperAdmin) {
            await this.loadUserPermissions(userData ? userData.id : null);
        } else {
            if (permissionsContainer) permissionsContainer.innerHTML = '';
        }

        modal.classList.remove('hidden');
    },

    async loadUserPermissions(userId = null) {
        const container = document.getElementById('userPermissionsContainer');
        try {
            // Fetch all feature knobs
            const knobsRes = await this.api('/admin/feature_knobs/list.php');

            // Fetch user's current permissions if editing
            let userPerms = {};
            if (userId) {
                const permsRes = await this.api(`/users/get_permissions.php?user_id=${userId}`);
                userPerms = permsRes.permissions || {};
            }

            // Render checkboxes grouped by category
            this.renderPermissionCheckboxes(knobsRes.grouped, userPerms);
        } catch (error) {
            console.error('Error loading permissions:', error);
            container.innerHTML = '<p class="text-sm text-red-600">Failed to load permissions</p>';
        }
    },

    renderPermissionCheckboxes(grouped, userPerms = {}) {
        const container = document.getElementById('userPermissionsContainer');
        const categoryLabels = {
            'leads': '📋 Lead Management',
            'users': '👥 User Management',
            'reports': '📊 Reports & Analytics',
            'settings': '⚙️ Settings & Configuration',
            'system': '🔧 System & Audit'
        };

        let html = '';
        for (const [category, knobs] of Object.entries(grouped)) {
            html += `
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-2">${categoryLabels[category] || category}</h5>
                    <div class="space-y-2">
            `;

            knobs.forEach(knob => {
                const isChecked = userPerms[knob.knob_key] || false;
                html += `
                    <label class="flex items-start cursor-pointer hover:bg-white rounded px-2 py-1">
                        <input type="checkbox" 
                            data-permission-knob="${knob.knob_key}"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mt-0.5"
                            ${isChecked ? 'checked' : ''}>
                        <div class="ml-2 flex-1">
                            <div class="text-xs font-medium text-gray-900">${knob.knob_name}</div>
                            <div class="text-xs text-gray-500">${knob.description}</div>
                        </div>
                    </label>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }

        container.innerHTML = html;
    },

    copyPermissionsFromRole() {
        const role = document.getElementById('modalUserRole').value;

        // Define default permissions per role
        const roleDefaults = {
            'staff': ['view_unassigned_leads', 'view_own_assigned_leads', 'update_lead_status', 'add_lead_notes', 'create_leads', 'edit_own_leads', 'view_reports'],
            'manager': ['view_unassigned_leads', 'view_all_assigned_leads', 'assign_leads', 'reassign_leads', 'update_lead_status', 'add_lead_notes', 'import_leads', 'export_leads', 'create_leads', 'edit_all_leads', 'view_reports', 'export_reports', 'view_user_list', 'view_activity_log'],
            'admin': ['view_unassigned_leads', 'view_all_assigned_leads', 'assign_leads', 'reassign_leads', 'update_lead_status', 'add_lead_notes', 'delete_leads', 'import_leads', 'export_leads', 'create_leads', 'edit_all_leads', 'view_reports', 'export_reports', 'create_custom_reports', 'manage_users', 'manage_roles', 'view_user_list', 'reset_user_passwords', 'access_settings', 'access_integrations', 'access_automations', 'manage_custom_fields', 'view_activity_log']
        };

        const defaults = roleDefaults[role] || [];

        // Update checkboxes
        document.querySelectorAll('[data-permission-knob]').forEach(checkbox => {
            checkbox.checked = defaults.includes(checkbox.dataset.permissionKnob);
        });

        this.showToast(`Copied ${defaults.length} permissions from ${role} role`, 'success');
    },

    closeUserModal() {
        document.getElementById('userModal').classList.add('hidden');
        document.getElementById('userForm').reset();
    },

    async saveUser(e) {
        e.preventDefault();
        const userId = document.getElementById('userEditId').value;
        const userData = {
            full_name: document.getElementById('modalUserFullName').value,
            email: document.getElementById('modalUserEmail').value,
            role: document.getElementById('modalUserRole').value,
            is_active: document.getElementById('modalUserIsActive').checked,
            is_super_admin: document.getElementById('modalUserSuperAdmin').checked ? 1 : 0
        };

        if (userId) {
            userData.id = userId;
        }

        // Collect selected permissions only if not super admin
        let permissions = {};
        if (!userData.is_super_admin) {
            document.querySelectorAll('[data-permission-knob]').forEach(checkbox => {
                permissions[checkbox.dataset.permissionKnob] = checkbox.checked;
            });
        }

        try {
            const endpoint = userId ? '/users/update.php' : '/users/create.php';
            const result = await this.api(endpoint, 'POST', userData);

            if (result.success) {
                // Save permissions if not super admin
                if (!userData.is_super_admin) {
                    const finalUserId = result.user_id || userId;
                    await this.api('/users/save_permissions.php', 'POST', {
                        user_id: finalUserId,
                        permissions: permissions
                    });
                }

                if (!userId) {
                    alert(`User created successfully! Temporary password: ${result.temp_password}`);
                } else {
                    this.showToast('User updated successfully', 'success');
                }
                this.closeUserModal();
                this.loadSettings();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (e) {
            console.error(e);
            alert('Failed to save user');
        }
    },

    async toggleUserStatus(userId, activate) {
        if (!confirm(`Are you sure you want to ${activate ? 'activate' : 'deactivate'} this user?`)) return;
        try {
            const result = await this.api('/users/update.php', 'POST', { id: userId, is_active: activate });
            if (result.success) {
                this.loadSettings();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (e) {
            console.error(e);
            alert('Failed to update user status');
        }
    },

    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This cannot be undone.')) return;
        try {
            const result = await this.api('/users/delete.php', 'POST', { user_id: userId });
            if (result.success) {
                this.showToast('User deleted successfully', 'success');
                this.loadSettings();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (e) {
            console.error(e);
            alert('Failed to delete user');
        }
    },

    openChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.remove('hidden');
        document.getElementById('changePasswordForm').reset();
    },

    closeChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.add('hidden');
        document.getElementById('changePasswordForm').reset();
    },

    async changePassword(e) {
        e.preventDefault();

        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (newPassword !== confirmPassword) {
            alert('New passwords do not match');
            return;
        }

        try {
            const result = await this.api('/users/change_password.php', 'POST', {
                current_password: currentPassword,
                new_password: newPassword
            });

            if (result.success) {
                alert('Password changed successfully!');
                this.closeChangePasswordModal();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (e) {
            console.error(e);
            alert('Failed to change password');
        }
    },

    async generateApiKey() {
        try {
            const user = this.getUser();
            const org_id = user && user.org_id ? parseInt(user.org_id, 10) : null;
            if (!org_id) {
                alert('No organization found for your session. Please re-login.');
                return;
            }

            const response = await fetch(`${this.apiUrl}/admin/generate_api_key.php`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ org_id })
            });
            const result = await response.json();

            if (result.success) {
                document.getElementById('apiKeyValue').value = result.api_key;
                const label = document.getElementById('apiKeyOrgLabel');
                if (label) label.textContent = `Org  ${result.organization}`;
                this.showToast(`API key generated for : ${result.organization}`, 'success');
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('API key generation error:', error);
            alert('Failed to generate API key');
        }
    },

    async loadApiKeyDisplay() {
        try {
            const user = this.getUser();
            const org_id = user && user.org_id ? parseInt(user.org_id, 10) : null;
            if (!org_id) return;

            const resp = await fetch(`${this.apiUrl}/admin/get_api_key.php?org_id=${org_id}`, {
                method: 'GET',
                credentials: 'include'
            });
            const result = await resp.json();
            const keyInput = document.getElementById('apiKeyValue');
            const label = document.getElementById('apiKeyOrgLabel');

            if (result && result.success) {
                if (keyInput) keyInput.value = result.api_key || '';
                if (label) label.textContent = `Org  ${result.organization}`;
            } else {
                if (keyInput) keyInput.value = '';
                if (label) label.textContent = result && result.error ? result.error : 'No key available';
            }
        } catch (e) {
            console.error('Failed to load API key:', e);
        }
    },

    copyApiKey() {
        const apiKeyInput = document.getElementById('apiKeyValue');
        apiKeyInput.select();
        document.execCommand('copy');
        this.showToast('API key copied to clipboard', 'success');
    },

    // --- Zingbot Settings ---
    async loadZingbotSettings() {
        try {
            const result = await this.api('/settings/zingbot.php');
            if (result && result.success && result.settings) {
                const s = result.settings;
                const endpointInput = document.getElementById('zingbotEndpoint');
                const keyInput = document.getElementById('zingbotApiKey');
                const activeCheck = document.getElementById('zingbotActive');

                if (endpointInput) endpointInput.value = s.api_endpoint || '';
                if (keyInput) keyInput.value = s.api_key || ''; // Will be masked
                if (activeCheck) activeCheck.checked = !!s.is_active;
            }
        } catch (e) {
            console.warn('Failed to load Zingbot settings:', e);
        }
    },

    async saveZingbotSettings() {
        const endpoint = document.getElementById('zingbotEndpoint').value.trim();
        const apiKey = document.getElementById('zingbotApiKey').value.trim();
        const isActive = document.getElementById('zingbotActive').checked;

        if (!endpoint || !apiKey) {
            alert('Please enter both API Endpoint and API Key');
            return;
        }

        try {
            const result = await this.api('/settings/zingbot.php', 'POST', {
                api_endpoint: endpoint,
                api_key: apiKey,
                is_active: isActive ? 1 : 0
            });

            if (result && result.success) {
                this.showToast('Zingbot settings saved successfully', 'success');
            } else {
                alert('Error: ' + (result.error || 'Failed to save settings'));
            }
        } catch (e) {
            console.error('Save error:', e);
            alert('Failed to save settings');
        }
    },

    async testZingbotConnection() {
        const endpoint = document.getElementById('zingbotEndpoint').value.trim();
        const apiKey = document.getElementById('zingbotApiKey').value.trim();

        if (!endpoint || !apiKey) {
            alert('Please enter settings before testing');
            return;
        }

        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = 'Testing...';
        btn.disabled = true;

        try {
            const result = await this.api('/settings/zingbot.php?action=test', 'POST', {
                api_endpoint: endpoint,
                api_key: apiKey
            });

            if (result && result.success) {
                this.showToast('Connection successful!', 'success');
            } else {
                alert('Connection failed: ' + (result.error || 'Unknown error'));
            }
        } catch (e) {
            console.error('Test error:', e);
            alert('Connection test failed');
        } finally {
            btn.textContent = originalText;
            btn.disabled = false;
        }
    },

    toggleZingbotKeyVisibility() {
        const input = document.getElementById('zingbotApiKey');
        const icon = document.getElementById('zingbotKeyIcon');

        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('class', 'h-4 w-4 text-blue-600');
        } else {
            input.type = 'password';
            icon.setAttribute('class', 'h-4 w-4');
        }
    }
});

window.App = App;
