/**
 * Organization management and switching.
 */

const App = window.App || {};

Object.assign(App, {
    async loadOrganizations() {
        document.getElementById('appContent').innerHTML = '<div class="text-center py-10"><p class="text-gray-500">Loading Organizations...</p></div>';
        try {
            const orgs = await this.api('/organizations/list.php');
            if (orgs && orgs.success) {
                this.renderOrganizations(orgs.organizations);
            } else {
                const errorMsg = orgs && orgs.error ? orgs.error : 'Unknown error';
                document.getElementById('appContent').innerHTML = `
                    <div class="max-w-md mx-auto mt-10 p-6 bg-red-50 border border-red-200 rounded-lg">
                        <h3 class="text-lg font-semibold text-red-800 mb-2">Failed to Load Organizations</h3>
                        <p class="text-sm text-red-700 mb-4">Error: ${errorMsg}</p>
                    </div>
                `;
            }
        } catch (e) {
            console.error('Organizations error:', e);
            document.getElementById('appContent').innerHTML = `
                <div class="max-w-md mx-auto mt-10 p-6 bg-red-50 border border-red-200 rounded-lg">
                    <h3 class="text-lg font-semibold text-red-800 mb-2">Error Loading Organizations</h3>
                    <p class="text-sm text-red-700 mb-4">${e.message}</p>
                </div>
            `;
        }
    },
    async toggleOrganizationStatus(orgId, activate) {
    const action = activate ? 'activate' : 'deactivate';
    if (!confirm(`Are you sure you want to ${action} this organization?`)) return;
    try {
        const result = await this.api('/organizations/update.php', 'POST', { org_id: orgId, is_active: activate });
        if (result && result.success) {
            this.showToast(`Organization ${action}d successfully!`, 'success');
            this.loadOrganizations();
        } else {
            this.showToast('Error: ' + (result && result.error ? result.error : `Failed to ${action} organization`), 'error');
            this.loadOrganizations();
        }
    } catch (e) {
        console.error(e);
        this.showToast(`Failed to ${action} organization`, 'error');
        this.loadOrganizations();
    }
},
    renderOrganizations(orgs) {
        console.log('renderOrganizations called, orgs:', orgs);
        const container = document.getElementById('appContent');
        container.innerHTML = `
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Organizations</h2>
                        <p class="text-sm text-gray-500 mt-1">Manage all organizations in the system</p>
                    </div>
                    <button onclick="App.openCreateOrgModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                        <i data-lucide="plus" class="h-4 w-4 mr-2"></i>
                        Add Organization
                    </button>
                </div>

                <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organization</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leads</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${orgs.map(org => `
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">${org.name}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${org.owner_email || 'N/A'}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${org.user_count}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${org.lead_count}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            ${org.is_active ?
                '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>' :
                '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>'}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(org.created_at).toLocaleDateString()}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
    <button onclick="App.switchOrganization(${org.id}, '${org.name}')" class="text-blue-600 hover:text-blue-900 mr-3">Switch</button>
    <button onclick="App.toggleOrganizationStatus(${org.id}, ${org.is_active ? 'false' : 'true'})" class="${org.is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'}">
        ${org.is_active ? 'Deactivate' : 'Activate'}
    </button>
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

    async deleteOrganization(orgId, orgName) {
        if (!confirm(`Are you sure you want to delete the organization: ${orgName}? This action cannot be undone.`)) {
            return;
        }
        try {
            const result = await this.api('/organizations/delete.php', 'POST', { org_id: orgId });
            if (result && result.success) {
                this.showToast('Organization deleted successfully!', 'success');
                this.loadOrganizations();
            } else {
                this.showToast('Error: ' + (result && result.error ? result.error : 'Failed to delete organization'), 'error');
            }
        } catch (e) {
            console.error(e);
            this.showToast('Failed to delete organization', 'error');
        }
    },

    async openCreateOrgModal() {
        // Fetch plans
        let plans = [];
        try {
            const res = await fetch('api/plans/list.php'); // relative to /leads2/
            const data = await res.json();
            if (data.success && data.data) {
                plans = data.data;
            }
        } catch (e) {
            this.showToast('Failed to load plans', 'error');
            return;
        }
        // Build modal HTML
        const modal = document.createElement('div');
        modal.id = 'createOrgModal';
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-bold mb-4">Create Organization</h2>
                <label class="block mb-2 text-sm font-medium">Organization Name</label>
                <input id="orgNameInput" type="text" class="w-full border rounded px-3 py-2 mb-4" placeholder="Enter organization name">
                <label class="block mb-2 text-sm font-medium">Select Plan</label>
                <select id="planSelect" class="w-full border rounded px-3 py-2 mb-4">
                    <option value="">-- Select a Plan --</option>
                    ${plans.map(plan => `<option value="${plan.id}">${plan.name} (${plan.currency} ${plan.base_price_monthly}/mo)</option>`).join('')}
                </select>
                <div class="flex justify-end space-x-2">
                    <button id="cancelCreateOrgBtn" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                    <button id="submitCreateOrgBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Create</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('cancelCreateOrgBtn').onclick = () => modal.remove();
        document.getElementById('submitCreateOrgBtn').onclick = async () => {
            const name = document.getElementById('orgNameInput').value.trim();
            const plan_id = document.getElementById('planSelect').value;
            if (!name) {
                this.showToast('Organization name is required', 'error');
                return;
            }
            if (!plan_id) {
                this.showToast('Please select a plan', 'error');
                return;
            }
            try {
                const result = await this.api('/organizations/create.php', 'POST', { name, plan_id });
                if (result.success) {
                    modal.remove();
                    // Razorpay Checkout redirection
                    if (result.razorpay_subscription_id) {
                        this.showToast('Redirecting to payment...', 'info');
                        // You may want to fetch your Razorpay key from config/env
                        const razorpayKey = (window.AppData && window.AppData.config && window.AppData.config.razorpayKeyId) || '';
                        if (!razorpayKey) {
                            alert('Razorpay key not configured.');
                            this.loadOrganizations();
                            return;
                        }
                        const options = {
                            key: razorpayKey,
                            subscription_id: result.razorpay_subscription_id,
                            name: name,
                            description: 'Organization Subscription',
                            handler: (response) => {
                                this.showToast('Payment successful!', 'success');
                                this.loadOrganizations();
                            },
                            theme: { color: '#2563eb' }
                        };
                        const rzp = new window.Razorpay(options);
                        rzp.open();
                    } else {
                        this.showToast('Organization created successfully!', 'success');
                        this.loadOrganizations();
                    }
                } else {
                    this.showToast('Error: ' + result.error, 'error');
                }
            } catch (e) {
                console.error(e);
                this.showToast('Failed to create organization', 'error');
            }
        };
    },

    async switchOrganization(orgId, orgName) {
        try {
            const result = await this.api('/organizations/switch.php', 'POST', { org_id: orgId });
            if (result.success) {
                const user = this.getUser();
                user.org_id = orgId;
                user.org_name = result.org_name || orgName;
                this.saveUserToStorage(user);

                this.showToast(`Switched to ${result.org_name}!`, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                this.showToast('Error: ' + result.error, 'error');
            }
        } catch (e) {
            console.error(e);
            this.showToast('Failed to switch organization', 'error');
        }
    },

    async loadUserOrganizations() {
        try {
            const result = await this.api('/organizations/my_orgs.php');
            if (result && result.success && result.organizations.length > 1) {
                document.getElementById('orgSwitcher').style.display = 'block';
                this.renderOrgDropdown(result.organizations, result.current_org_id);
            }
        } catch (e) {
            console.error('Failed to load user organizations:', e);
        }
    },

    renderOrgDropdown(orgs, currentOrgId) {
        const currentOrg = orgs.find(o => o.id == currentOrgId);
        if (currentOrg) {
            document.getElementById('currentOrgName').textContent = currentOrg.name;
        }

        const dropdown = document.getElementById('orgDropdownList');
        dropdown.innerHTML = orgs.map(org => `
            <button onclick="App.switchOrganization(${org.id}, '${org.name}')" 
                class="block w-full text-left px-4 py-2 text-sm ${org.id == currentOrgId ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-700 hover:bg-gray-50'}">
                ${org.name}
                ${org.id == currentOrgId ? '<span class="float-right text-blue-600">✓</span>' : ''}
            </button>
        `).join('');

        lucide.createIcons();
    },

    toggleOrgDropdown() {
        const dropdown = document.getElementById('orgDropdown');
        if (!dropdown) return;
        dropdown.classList.toggle('hidden');
    },

    async loadOrgSelector() {
        try {
            const result = await this.api('/organizations/my_orgs.php');
            if (result && result.success && result.organizations.length > 1) {
                const orgSelector = document.getElementById('orgSelector');
                if (orgSelector) {
                    orgSelector.style.display = 'block';
                    const dropdown = document.getElementById('orgSelectorDropdown');
                    dropdown.innerHTML = '';
                    const currentOrgId = result.current_org_id;
                    result.organizations.forEach(org => {
                        const option = document.createElement('option');
                        option.value = org.id;
                        option.textContent = org.name;
                        if (String(org.id) === String(currentOrgId)) {
                            option.selected = true;
                        }
                        dropdown.appendChild(option);
                    });
                }
            }
        } catch (e) {
            console.error('Failed to load org selector:', e);
        }
    },

    async handleOrgChange(value) {
        try {
            const stmt = await this.api('/organizations/switch.php', 'POST', { org_id: parseInt(value) });
            if (stmt && stmt.success) {
                const user = this.getUser();
                user.org_id = parseInt(value);
                this.saveUserToStorage(user);
                this.showToast('Switched organization', 'success');
                window.location.reload();
            } else {
                this.showToast('Failed to switch organization', 'error');
            }
        } catch (e) {
            console.error(e);
            this.showToast('Failed to switch organization', 'error');
        }
    },

    isGlobalView() {
        return localStorage.getItem('global_view') === 'true';
    }
});

window.App = App;
