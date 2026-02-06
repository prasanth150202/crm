const App = window.App || {};
if (window.AppData) {
    Object.assign(App, window.AppData);
}

Object.assign(App, {
    // --- Modal Management ---
    openModal(modalId) {
        console.log(`[CORE] openModal: ${modalId}`);
        this.closeAllModals();
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'block';
            modal.style.zIndex = '2000';

            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('role', 'dialog');

            // Force a reflow to ensure transitions work (if any)
            void modal.offsetWidth;
        } else {
            console.warn('[CORE] Modal not found:', modalId);
        }
    },

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            modal.removeAttribute('aria-modal');
            modal.removeAttribute('role');
            modal.style.zIndex = '';
        }
    },

    closeAllModals() {
        // List all modal IDs here
        const modalIds = [
            'createLeadModal',
            'leadDetailPanel',
            'importModal',
            'manageColumnsModal',
            'deleteFieldModal',
            'chartConfigModal',
            'chartBuilderModal',
            'userModal',
            'changePasswordModal',
            'createMeetingModal'
        ];
        modalIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.classList.add('hidden');
                el.style.display = 'none';
                el.removeAttribute('aria-modal');
                el.removeAttribute('role');
                el.style.zIndex = '';
            }
        });
        // Remove any custom overlays
        document.querySelectorAll('.fixed.bg-black.bg-opacity-50.z-50').forEach(el => el.remove());
    },

    toggleSidebar() {
        const body = document.body;
        const isCollapsed = body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');

        const icon = document.getElementById('sidebarToggleIcon');
        if (icon) {
            icon.style.transition = 'transform 0.3s ease';
            icon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        }

        // Refresh icons if needed
        if (window.lucide) {
            window.lucide.createIcons();
        }
    },

    initSidebarState() {
        const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
        if (isCollapsed) {
            document.body.classList.add('sidebar-collapsed');
            const icon = document.getElementById('sidebarToggleIcon');
            if (icon) {
                icon.style.transform = 'rotate(180deg)';
            }
        }
    },
    apiUrl: (window.AppData && window.AppData.config && window.AppData.config.apiUrl)
        ? window.AppData.config.apiUrl
        : window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/api',
    selectedLeadIds: new Set(),
    currentLeadsPage: [],
    handleSearch: null,

    // --- Utils ---
    escapeHtml(unsafe) {
        if (!unsafe) return '';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    // --- Toast Notifications ---
    showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        // Securely build inner content
        const iconSpan = document.createElement('span');
        iconSpan.style.fontSize = '20px';
        iconSpan.textContent = icons[type];

        const messageSpan = document.createElement('span');
        messageSpan.style.flex = '1';
        messageSpan.textContent = message;

        toast.appendChild(iconSpan);
        toast.appendChild(messageSpan);
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    showConfirm(message, onConfirm, onCancel = null) {
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
        // Use textContent for message to prevent XSS
        const safeMessage = this.escapeHtml(message);

        overlay.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md mx-4 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Action</h3>
                <p class="text-gray-600 mb-6">${safeMessage}</p>
                <div class="flex justify-end space-x-3">
                    <button id="cancelBtn" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button id="confirmBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Confirm
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        document.getElementById('confirmBtn').onclick = () => {
            overlay.remove();
            if (onConfirm) onConfirm();
        };

        document.getElementById('cancelBtn').onclick = () => {
            overlay.remove();
            if (onCancel) onCancel();
        };

        overlay.onclick = (e) => {
            if (e.target === overlay) {
                overlay.remove();
                if (onCancel) onCancel();
            }
        };
    },

    // --- Authentication ---
    async login(email, password) {
        try {
            const response = await fetch(`${this.apiUrl}/auth/login.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Handle new response format (data.user) or old format (user)
                const user = data.data?.user || data.user;
                if (user) {
                    this.saveUserToStorage(user);
                    return { success: true, user: user };
                }
                return { success: false, error: 'Invalid response format' };
            }
            return { success: false, error: data.error || data.message || 'Login failed' };
        } catch (error) {
            console.error('Login error:', error);
            return { success: false, error: 'Network error' };
        }
    },

    async register(email, password, orgName, planId) {
        try {
            const response = await fetch(`${this.apiUrl}/auth/register.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password, org_name: orgName, plan_id: planId })
            });

            const data = await response.json();

            if (response.ok) {
                return { success: true };
            }
            return { success: false, error: data.error };
        } catch (error) {
            console.error('Registration error:', error);
            return { success: false, error: 'Network error' };
        }
    },

    async logout() {
        try {
            await fetch(`${this.apiUrl}/auth/logout.php`);
        } catch (e) {
            console.error('Logout API failed:', e);
        }
        localStorage.removeItem('crm_user');
        window.location.href = 'login.php';
    },

    saveUserToStorage(user) {
        localStorage.setItem('crm_user', JSON.stringify(user));
    },

    getUser() {
        // First priority: pre-injected data from PHP
        if (window.AppData && window.AppData.user) {
            return window.AppData.user;
        }

        try {
            const userStr = localStorage.getItem('crm_user');
            if (!userStr || userStr === 'null' || userStr === 'undefined' || userStr.trim() === '') {
                return null;
            }
            return JSON.parse(userStr);
        } catch (error) {
            console.error('Error parsing user from localStorage:', error);
            // Clear corrupted data
            localStorage.removeItem('crm_user');
            return null;
        }
    },

    requireAuth() {
        const user = this.getUser();
        if (!user) {
            // Clear any corrupted data
            localStorage.removeItem('crm_user');
            window.location.href = 'login.php';
            return null;
        }
        return user;
    },

    // Clear all stored data (logout helper)
    clearStorage() {
        localStorage.removeItem('crm_user');
    },

    // --- API Helpers ---
    async api(endpoint, method = 'GET', body = null) {
        const user = this.getUser();
        if (!user) return null;

        const headers = {
            'Content-Type': 'application/json'
        };

        // Add CSRF Token
        if (window.AppData && window.AppData.csrf_token) {
            headers['X-CSRF-Token'] = window.AppData.csrf_token;
        }

        const options = {
            method,
            headers
        };

        if (body) {
            options.body = JSON.stringify(body);
        }

        let url = `${this.apiUrl}${endpoint}`;

        if (method === 'GET') {
            if (!/[?&]org_id=/.test(url)) {
                const separator = url.includes('?') ? '&' : '?';
                url += `${separator}org_id=${user.org_id}`;
            }
        } else if (body) {
            if (body.org_id === undefined || body.org_id === null) {
                body.org_id = user.org_id;
            }
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        const text = await response.text();
        try {
            const json = text ? JSON.parse(text) : null;
            if (!response.ok) {
                return { error: json && json.error ? json.error : `Request failed (${response.status})` };
            }
            return json;
        } catch (err) {
            console.error('API parse error:', err, text);
            return { error: 'Invalid server response' };
        }
    },

    // --- View Controller ---
    router(view, pushState = true) {
        const appContent = document.getElementById('appContent');
        if (appContent) {
            appContent.innerHTML = ''; // Clear the content area
        }

        try {
            // Normalize view name (remove leading slash if present)
            view = view.replace(/^\//, '') || 'dashboard';

            if (pushState) {
                const projectRoot = (window.AppData && window.AppData.config && window.AppData.config.projectRoot) || '';
                const newPath = (view === 'dashboard') ? (projectRoot || '/') : projectRoot + '/' + view;
                window.history.pushState({ view }, '', newPath);
            }
            const navLinks = document.querySelectorAll('nav a');
            navLinks.forEach(link => {
                const icon = link.querySelector('i');
                const href = link.getAttribute('href');

                link.className = 'text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md';
                if (icon) icon.className = 'mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300';

                const linkView = (href.split('/').pop() || 'dashboard').split('.')[0];

                if (linkView === view) {
                    link.className = 'bg-gray-900 text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md';
                    if (icon) icon.className = 'mr-3 h-5 w-5 text-white';
                }
            });

            const leadActions = document.getElementById('leadActions');
            const dashboardActions = document.getElementById('dashboardActions');
            const leadFilters = document.getElementById('leadFilters');

            // Hide all action bars by default
            if (leadActions) leadActions.style.display = 'none';
            if (dashboardActions) dashboardActions.style.display = 'none';
            if (leadFilters) leadFilters.style.display = 'none';

            // Control visibility based on view
            if (view === 'dashboard') {
                if (dashboardActions) dashboardActions.style.display = 'flex';
            } else if (view === 'leads') {
                if (leadActions) leadActions.style.display = 'flex';
                if (leadFilters) leadFilters.style.display = 'block';
            }

            switch (view) {
                case 'dashboard':
                    document.getElementById('pageTitle').textContent = 'Dashboard';
                    if (typeof this.loadDashboard === 'function') {
                        this.loadDashboard();
                    }
                    break;
                case 'leads':
                    document.getElementById('pageTitle').textContent = 'Leads';
                    this.loadLeads();
                    break;
                case 'pipeline':
                    document.getElementById('pageTitle').textContent = 'Pipeline';
                    this.loadKanban();
                    break;
                case 'meetings':
                    document.getElementById('pageTitle').textContent = 'Meetings';
                    if (typeof Meetings !== 'undefined' && typeof Meetings.init === 'function') {
                        Meetings.init();
                    } else {
                        console.error('Meetings module not loaded');
                        appContent.innerHTML = '<div class="p-6 text-center text-gray-500">Meetings module not available</div>';
                    }
                    break;
                case 'invitations':
                    document.getElementById('pageTitle').textContent = 'User Invitations';
                    if (window.Invitations) {
                        window.Invitations.renderListView();
                    } else {
                        console.error('Invitations module not loaded');
                        appContent.innerHTML = '<div class="p-6 text-center text-gray-500">Invitations module not available</div>';
                    }
                    break;
                case 'audit':
                    document.getElementById('pageTitle').textContent = 'Audit Trail';
                    if (window.AuditLog) {
                        window.AuditLog.renderListView();
                    } else {
                        console.error('AuditLog module not loaded');
                        appContent.innerHTML = '<div class="p-6 text-center text-gray-500">Audit Trail module not available</div>';
                    }
                    break;
                case 'reports':
                    document.getElementById('pageTitle').textContent = 'Reports';
                    if (typeof this.loadReports === 'function') {
                        this.loadReports();
                    } else {
                        throw new Error('loadReports function not found');
                    }
                    break;
                case 'organizations':
                    document.getElementById('pageTitle').textContent = 'Organizations';
                    if (typeof this.loadOrganizations === 'function') {
                        this.loadOrganizations();
                    } else if (typeof this.loadOrgSelector === 'function') {
                        this.loadOrgSelector();
                    }
                    break;
                case 'settings':
                    document.getElementById('pageTitle').textContent = 'Settings';
                    if (typeof this.loadSettings === 'function') {
                        this.loadSettings();
                    }
                    break;
                case 'automations':
                    document.getElementById('pageTitle').textContent = 'Automations';
                    import('./automations.js?v=3').then(m => {
                        if (m.Automations) {
                            m.Automations.init();
                        } else {
                            console.error('Automations module not found');
                        }
                    }).catch(e => {
                        console.error('Failed to load automations module:', e);
                        appContent.innerHTML = '<div class="p-6 text-center text-gray-500">Automations module not available</div>';
                    });
                    break;
                default:
                    document.getElementById('pageTitle').textContent = 'Dashboard';
                    if (typeof this.loadDashboard === 'function') {
                        this.loadDashboard();
                    }
                    break;
            }
        } catch (e) {
            console.error('Router Error:', e);
            const appContent = document.getElementById('appContent');
            if (appContent) {
                appContent.innerHTML = `
                    <div class="p-6 bg-red-50 text-red-700 rounded-lg border border-red-200 m-4">
                        <h3 class="font-bold text-lg mb-2">⚠️ Application Error</h3>
                        <p class="mb-2">Failed to load view: <strong>${view}</strong></p>
                        <p class="font-mono text-sm bg-red-100 p-2 rounded">${e.message}</p>
                        <p class="text-xs text-gray-500 mt-4">Please check console for details.</p>
                    </div>`;
            }
        }
    },

    handleFilterChange() {
        const path = window.location.pathname;
        const currentView = path.substring(path.lastIndexOf('/') + 1) || 'leads';
        if (currentView === 'pipeline') {
            this.loadKanban();
        } else if (currentView === 'reports') {
            this.loadReports();
        } else {
            this.loadLeads(0);
        }
    },

    toggleAdvancedFilters() {
        const overlay = document.getElementById('advancedFiltersOverlay');
        const panel = document.getElementById('advancedFiltersPanel');
        const icon = document.getElementById('advancedFiltersIcon');

        if (!panel || !overlay) {
            console.error('Advanced filters elements not found');
            return;
        }

        const isHidden = overlay.classList.contains('hidden');

        if (isHidden) {
            // Show
            overlay.classList.remove('hidden');
            // Trigger reflow
            void overlay.offsetWidth;
            overlay.classList.remove('opacity-0');

            panel.classList.remove('translate-x-full');

            if (icon) icon.style.transform = 'rotate(180deg)';
            this.renderAdvancedFilters();
        } else {
            // Hide
            overlay.classList.add('opacity-0');
            panel.classList.add('translate-x-full');

            if (icon) icon.style.transform = 'rotate(0deg)';

            setTimeout(() => {
                overlay.classList.add('hidden');
            }, 300);
        }
    },

    async renderAdvancedFilters() {
        const container = document.getElementById('advancedFiltersContainer');
        if (!container) {
            console.error('Advanced filters container not found');
            return;
        }

        const customFields = await this.getCustomFields();
        const visibilitySettings = await this.getFieldVisibility();

        const standardFields = [
            { name: 'name', label: 'Name', type: 'text' },
            { name: 'title', label: 'Title', type: 'text' },
            { name: 'company', label: 'Company', type: 'text' },
            { name: 'email', label: 'Email', type: 'text' },
            { name: 'phone', label: 'Phone', type: 'text' },
            { name: 'lead_value', label: 'Value', type: 'number' },
            { name: 'source', label: 'Source', type: 'select' },
            { name: 'stage_id', label: 'Stage', type: 'select' },
            { name: 'created_at', label: 'Created Date', type: 'date' },
            { name: 'updated_at', label: 'Updated Date', type: 'date' }
        ];

        // For advanced filters, always show standard fields
        const visibleStandardFields = standardFields;
        const visibleCustomFields = customFields.filter(f => this.isFieldVisible(f.name, 'custom', visibilitySettings));

        const allFields = [...visibleStandardFields, ...visibleCustomFields.map(f => ({ ...f, label: f.name }))];

        container.innerHTML = ''; // Clear first

        for (const field of allFields) {
            try {
                if (field.type === 'select') {
                    // FIX: Check if options already exist (custom fields) or need fetching
                    let options = [];
                    if (field.options && field.options.length > 0) {
                        options = field.options;
                    } else {
                        options = await this.getFieldOptions(field.name);
                    }
                    container.innerHTML += this.renderFilterInput(field, options);
                } else {
                    container.innerHTML += this.renderFilterInput(field);
                }
            } catch (e) {
                console.error('Error rendering filter for', field.name, e);
            }
        }
    },

    renderFilterInput(field, options = []) {
        const fieldId = `filter_${field.name}`;
        const baseClasses = "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm";

        let inputHtml = '';

        switch (field.type) {
            case 'text':
                inputHtml = `
                    <input type="text" id="${fieldId}" name="${field.name}"
                        class="${baseClasses}" placeholder="Filter ${field.label}...">
                `;
                break;
            case 'number':
                inputHtml = `
                    <div class="flex space-x-2">
                        <input type="number" id="${fieldId}_min" name="${field.name}_min"
                            class="${baseClasses} flex-1" placeholder="Min ${field.label}">
                        <input type="number" id="${fieldId}_max" name="${field.name}_max"
                            class="${baseClasses} flex-1" placeholder="Max ${field.label}">
                    </div>
                `;
                break;
            case 'date':
                inputHtml = `
                    <div class="flex space-x-2">
                        <input type="date" id="${fieldId}_from" name="${field.name}_from"
                            class="${baseClasses} flex-1">
                        <input type="date" id="${fieldId}_to" name="${field.name}_to"
                            class="${baseClasses} flex-1">
                    </div>
                `;
                break;
            case 'select':
                // Use provided options, fallback to empty array
                inputHtml = `
                    <select id="${fieldId}" name="${field.name}" class="${baseClasses} bg-white">
                        <option value="">All ${field.label}${field.label.endsWith('s') ? '' : 's'}</option>
                        ${options.map(opt => `<option value="${opt}">${opt}</option>`).join('')}
                    </select>
                `;
                break;
            default:
                inputHtml = `
                    <input type="text" id="${fieldId}" name="${field.name}"
                        class="${baseClasses}" placeholder="Filter ${field.label}...">
                `;
        }

        return `
            <div class="space-y-2">
                <label for="${fieldId}" class="block text-sm font-medium text-gray-700">
                    ${field.label}
                </label>
                ${inputHtml}
            </div>
        `;
    },

    clearAdvancedFilters() {
        const container = document.getElementById('advancedFiltersContainer');
        if (container) {
            const inputs = container.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
            });
        }
        this.applyAdvancedFilters();
    },

    async applyAdvancedFilters() {
        this.toggleAdvancedFilters(); // Close the panel
        await window.App.loadLeads(0);
    },

    // Get field options from configuration
    async getFieldOptions(fieldName) {
        try {
            const fieldConfigRes = await this.api('/settings/get_field_config.php');
            if (fieldConfigRes && fieldConfigRes.success && fieldConfigRes.fields && fieldConfigRes.fields[fieldName]) {
                return fieldConfigRes.fields[fieldName].options || [];
            }
        } catch (e) {
            console.warn('Failed to load field config, using defaults:', e);
        }

        // Default options
        const defaults = {
            'source': ['Direct', 'Website', 'LinkedIn', 'Referral', 'Ads', 'Cold Call'],
            'stage_id': ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost']
        };

        return defaults[fieldName] || [];
    }
});

window.App = App;

// Initialize Application
document.addEventListener('DOMContentLoaded', () => {
    App.initSidebarState();

    // Initial routing based on URL
    const path = window.location.pathname;
    const projectRoot = (window.AppData && window.AppData.config && window.AppData.config.projectRoot) || '/leads2';

    let view = 'dashboard';
    if (path.startsWith(projectRoot)) {
        const relativePath = path.substring(projectRoot.length);
        const segments = relativePath.split('/').filter(Boolean);
        if (segments.length > 0) {
            view = segments[0];
        }
    } else {
        // Fallback for direct access if projectRoot extraction fails
        const segments = path.split('/').filter(Boolean);
        if (segments.length > 0) {
            view = segments[segments.length - 1];
        }
    }

    // Handle specific php files direct access (legacy)
    if (view.endsWith('.php')) {
        view = view.replace('.php', '');
    }

    if (view && view !== 'index') {
        App.router(view, false);
    } else {
        App.router('dashboard', false);
    }

    // Handle browser back/forward buttons
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.view) {
            App.router(event.state.view, false);
        } else {
            // Recalculate view from URL? Or default to dashboard?
            App.router('dashboard', false);
        }
    });
});
