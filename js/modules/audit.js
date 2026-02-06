/**
 * Audit Log Module
 * Handles system activity tracking and audit trail rendering
 */

const AuditLog = {
    currentPage: 1,
    perPage: 50,
    filters: {
        action_type: '',
        user_id: ''
    },

    /**
     * Initialize the module
     */
    init() {
        console.log('Audit Log Module Initialized');
    },

    /**
     * Render the audit trail view
     */
    async renderListView() {
        const container = document.getElementById('appContent');
        container.innerHTML = `
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Audit Trail</h1>
                        <p class="text-sm text-gray-500 mt-1">Track all system activities and user actions</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white p-4 shadow rounded-lg flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 uppercase tracking-wider mb-1">Action Type</label>
                        <select id="auditFilterAction" onchange="AuditLog.handleFilterChange()" class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white min-w-[200px]">
                            <option value="">All Actions</option>
                            <option value="login">Login</option>
                            <option value="lead_created">Lead Created</option>
                            <option value="lead_updated">Lead Updated</option>
                            <option value="lead_deleted">Lead Deleted</option>
                            <option value="meeting_scheduled">Meeting Scheduled</option>
                            <option value="invitation_created">Invitation Created</option>
                            <option value="invitation_activated">Invitation Activated</option>
                            <option value="invitation_revoked">Invitation Revoked</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 uppercase tracking-wider mb-1">User</label>
                        <select id="auditFilterUser" onchange="AuditLog.handleFilterChange()" class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white min-w-[200px]">
                            <option value="">All Users</option>
                            <!-- Users will be loaded here -->
                        </select>
                    </div>
                    <button onclick="AuditLog.resetFilters()" class="text-sm text-blue-600 hover:text-blue-500 font-medium pb-2 whitespace-nowrap">Reset Filters</button>
                </div>

                <!-- Table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="auditLogBody" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    Loading activity logs...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="auditPagination" class="flex justify-between items-center bg-white px-6 py-4 shadow rounded-lg">
                    <!-- Pagination content -->
                </div>
            </div>
        `;

        lucide.createIcons();
        await this.loadUsers();
        await this.loadLogs();
    },

    /**
     * Load logs from API
     */
    async loadLogs(page = 1) {
        this.currentPage = page;
        const body = document.getElementById('auditLogBody');
        if (body) {
            body.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        Loading activity logs...
                    </td>
                </tr>
            `;
        }

        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                ...this.filters
            });

            const response = await fetch(`${App.apiUrl}/audit/list.php?${params.toString()}`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.success) {
                this.renderLogsList(data.data);
                this.renderPagination(data.pagination);
            } else {
                throw new Error(data.error || 'Failed to load logs');
            }
        } catch (error) {
            console.error('Error loading logs:', error);
            if (body) {
                body.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-red-500">
                            Error: ${error.message}
                        </td>
                    </tr>
                `;
            }
        }
    },

    /**
     * Render logs in the table
     */
    renderLogsList(logs) {
        const body = document.getElementById('auditLogBody');
        if (!body) return;

        if (logs.length === 0) {
            body.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        No activity logs found matching the filters
                    </td>
                </tr>
            `;
            return;
        }

        body.innerHTML = logs.map(log => `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div>${App.formatDate(log.created_at)}</div>
                    <div class="text-xs text-gray-400">${new Date(log.created_at).toLocaleTimeString()}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${log.user_name || 'System'}</div>
                    <div class="text-xs text-gray-500">UID: ${log.user_id}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${this.getActionBadge(log.action_type)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${log.lead_name ? `<span class="inline-flex items-center text-blue-600 hover:text-blue-800 cursor-pointer" onclick="App.openLeadDetail(${log.lead_id})"><i data-lucide="user" class="h-3 w-3 mr-1"></i> ${log.lead_name}</span>` : '-'}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500">
                    <div class="max-w-xs overflow-hidden text-ellipsis" title="${log.description || ''}">
                        ${log.description || '-'}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${log.ip_address || '-'}
                </td>
            </tr>
        `).join('');

        lucide.createIcons();
    },

    /**
     * Render pagination controls
     */
    renderPagination(pagination) {
        const container = document.getElementById('auditPagination');
        if (!container) return;

        const { total, page, per_page, total_pages } = pagination;
        const start = (page - 1) * per_page + 1;
        const end = Math.min(page * per_page, total);

        container.innerHTML = `
            <div class="text-sm text-gray-700">
                Showing <span class="font-medium">${start}</span> to <span class="font-medium">${end}</span> of <span class="font-medium">${total}</span> logs
            </div>
            <div class="flex space-x-2">
                <button onclick="AuditLog.loadLogs(${page - 1})" ${page <= 1 ? 'disabled' : ''} 
                    class="px-3 py-1 border rounded-md text-sm font-medium ${page <= 1 ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}">
                    Previous
                </button>
                <div class="flex items-center px-4 text-sm font-medium text-gray-700">
                    Page ${page} of ${total_pages}
                </div>
                <button onclick="AuditLog.loadLogs(${page + 1})" ${page >= total_pages ? 'disabled' : ''} 
                    class="px-3 py-1 border rounded-md text-sm font-medium ${page >= total_pages ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}">
                    Next
                </button>
            </div>
        `;
    },

    /**
     * Get HTML badge for action type
     */
    getActionBadge(action) {
        let colors = 'bg-gray-100 text-gray-800';
        if (action.includes('created')) colors = 'bg-green-100 text-green-800';
        if (action.includes('updated')) colors = 'bg-blue-100 text-blue-800';
        if (action.includes('deleted')) colors = 'bg-red-100 text-red-800';
        if (action.includes('activated')) colors = 'bg-indigo-100 text-indigo-800';
        if (action.includes('revoked')) colors = 'bg-orange-100 text-orange-800';

        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${colors} capitalize">${action.replace(/_/g, ' ')}</span>`;
    },

    /**
     * Load users for filter dropdown
     */
    async loadUsers() {
        try {
            const response = await fetch(`${App.apiUrl}/users/list.php`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.success) {
                const select = document.getElementById('auditFilterUser');
                if (select) {
                    const currentOptions = select.innerHTML;
                    select.innerHTML = currentOptions + data.users.map(u => `
                        <option value="${u.id}">${u.full_name || u.email}</option>
                    `).join('');
                }
            }
        } catch (error) {
            console.error('Error loading users for filter:', error);
        }
    },

    /**
     * Handle filter changes
     */
    handleFilterChange() {
        this.filters.action_type = document.getElementById('auditFilterAction').value;
        this.filters.user_id = document.getElementById('auditFilterUser').value;
        this.loadLogs(1);
    },

    /**
     * Reset all filters
     */
    resetFilters() {
        this.filters = { action_type: '', user_id: '' };
        const actionSelect = document.getElementById('auditFilterAction');
        const userSelect = document.getElementById('auditFilterUser');
        if (actionSelect) actionSelect.value = '';
        if (userSelect) userSelect.value = '';
        this.loadLogs(1);
    }
};

window.AuditLog = AuditLog;
