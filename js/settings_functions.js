// SETTINGS PAGE & USER MANAGEMENT FUNCTIONS
// Add these functions to app.js after the chart builder functions

// ===== SETTINGS & USER MANAGEMENT =====

async loadSettings() {
    document.getElementById('appContent').innerHTML = '<div class="text-center py-10"><p class="text-gray-500">Loading Settings...</p></div>';

    try {
        const users = await this.api('/users/list.php');
        if (users && users.success) {
            this.renderSettings(users.users);
        } else {
            document.getElementById('appContent').innerHTML = '<p class="p-4 text-red-500">Failed to load settings.</p>';
        }
    } catch (e) {
        console.error(e);
        document.getElementById('appContent').innerHTML = '<p class="p-4 text-red-500">Error loading settings.</p>';
    }
},

renderSettings(users) {
    const container = document.getElementById('appContent');

    const roleLabels = {
        'owner': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Owner</span>',
        'admin': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Admin</span>',
        'manager': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Manager</span>',
        'sales_rep': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Sales Rep</span>'
    };

    container.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Settings</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage users and organization settings</p>
                </div>
                <button onclick="App.openUserModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                    <i data-lucide="user-plus" class="h-4 w-4 mr-2"></i>
                    Add User
                </button>
            </div>

            <!-- Users Table -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Team Members</h3>
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
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        ${roleLabels[user.role]}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ${user.lead_count || 0}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        ${user.is_active ?
            '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>' :
            '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>'
        }
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        ${user.role !== 'owner' ? `
                                            <button onclick="App.editUser(${user.id})" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                            <button onclick="App.toggleUserStatus(${user.id}, ${!user.is_active})" class="text-gray-600 hover:text-gray-900">
                                                ${user.is_active ? 'Deactivate' : 'Activate'}
                                            </button>
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

    lucide.createIcons();
},

openUserModal(userId = null) {
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const title = document.getElementById('userModalTitle');
    const submitText = document.getElementById('userSubmitText');

    if (userId) {
        // Edit mode - fetch user data
        title.textContent = 'Edit User';
        submitText.textContent = 'Update User';
        document.getElementById('userEditId').value = userId;

        // TODO: Fetch and populate user data
    } else {
        // Add mode
        title.textContent = 'Add User';
        submitText.textContent = 'Add User';
        form.reset();
        document.getElementById('userEditId').value = '';
    }

    modal.classList.remove('hidden');
},

closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
    document.getElementById('userForm').reset();
},

async saveUser(e) {
    e.preventDefault();

    const userId = document.getElementById('userEditId').value;
    const userData = {
        full_name: document.getElementById('userFullName').value,
        email: document.getElementById('userEmail').value,
        role: document.getElementById('userRole').value,
        is_active: document.getElementById('userIsActive').checked
    };

    try {
        const result = await this.api('/users/create.php', 'POST', userData);

        if (result.success) {
            alert(userId ? 'User updated successfully!' : `User created successfully! Temporary password: ${result.temp_password}`);
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
        const result = await this.api('/users/update.php', 'POST', {
            id: userId,
            is_active: activate
        });

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
