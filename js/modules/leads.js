// Helper for permission checks
window.hasPermission = function (key) {
    return window.userPermissions && window.userPermissions[key];
};
// UI gating for all major lead actions
document.addEventListener('DOMContentLoaded', () => {
    // Add Lead
    const addLeadBtn = document.getElementById('addLeadBtn');
    if (addLeadBtn && !window.hasPermission('create_leads')) {
        addLeadBtn.style.display = 'none';
    }
    // Import
    const importBtn = document.getElementById('importBtn');
    if (importBtn && !window.hasPermission('import_leads')) {
        importBtn.style.display = 'none';
    }
    // Export
    const exportBtns = document.querySelectorAll('[onclick*="exportSelectedToCSV"]');
    if (!window.hasPermission('export_leads')) {
        exportBtns.forEach(btn => btn.style.display = 'none');
    }
    // Bulk Delete
    const bulkDeleteBtns = document.querySelectorAll('[onclick*="bulkDeleteSelected"]');
    if (!window.hasPermission('delete_leads')) {
        bulkDeleteBtns.forEach(btn => btn.style.display = 'none');
    }
    // Per-lead Delete
    const perLeadDeleteBtns = document.querySelectorAll('button, a');
    perLeadDeleteBtns.forEach(btn => {
        if ((btn.textContent && btn.textContent.trim().toLowerCase() === 'delete') || (btn.title && btn.title.toLowerCase() === 'delete')) {
            if (!window.hasPermission('delete_leads')) {
                btn.style.display = 'none';
            }
        }
    });
    // Reports (if any report buttons/links have a data-feature or id)
    const reportBtns = document.querySelectorAll('[data-feature="view_reports"], #reportsBtn, .reports-btn');
    if (!window.hasPermission('view_reports')) {
        reportBtns.forEach(btn => btn.style.display = 'none');
    }
});
// Debug: Log user permissions on module load
if (window.userPermissions) {
    console.log('[PERMISSIONS]', window.userPermissions);
} else {
    console.warn('[PERMISSIONS] window.userPermissions is not set');
}
/**
 * Lead listing, detail, CRUD, table interactions.
 */

const App = window.App || {};

Object.assign(App, {
    // Utility: Close all modals (add more modal IDs as needed)
    closeAllModals() {
        const modalIds = [
            'createLeadModal',
            'leadDetailPanel',
            'importModal' // Add more modal IDs here if needed
        ];
        modalIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        });
    },
    // Default sorting state
    currentSort: { field: 'id', order: 'DESC' },

    // Selection state
    selectedLeadIds: new Set(),

    // Header filters state
    visibleFilterFields: {},
    activeHeaderFilters: {},

    // NEW: Method to clear ALL filters (basic + advanced)
    clearAllFilters() {
        // Reset basic filters
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';

        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) statusFilter.value = '';

        const sourceFilter = document.getElementById('sourceFilter');
        if (sourceFilter) sourceFilter.value = '';

        // Reset advanced filters container
        const advancedContainer = document.getElementById('advancedFiltersContainer');
        if (advancedContainer) {
            const inputs = advancedContainer.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.tagName.toLowerCase() === 'select') {
                    input.value = '';  // For dropdowns like source/stage in advanced
                } else {
                    input.value = '';
                }
            });
        }

        // Re-populate dropdowns to ensure "All" is selected and options are fresh
        this.updateFormDropdowns();

        // Trigger reload with no filters
        this.loadLeads(0);

        // Optional: Show a toast/feedback
        this.showToast('All filters cleared', 'success');
    },

    // MODIFIED: loadLeads - Add auto-reset for basic if advanced active (prevents conflicts)
    async loadLeads(offset = 0, triggerElementId = null) {
        const limit = 50;
        const user = this.requireAuth();
        if (!user) return;
        const orgId = user.org_id;

        // Save current scroll position to prevent "sliding/jumping"
        const scrollY = window.scrollY;
        const scrollX = window.scrollX;

        // Target the specific table container for horizontal scroll stability
        const tableContainer = document.querySelector('#leadsTableContainer .overflow-x-auto') || document.querySelector('.overflow-x-auto');
        const tableScrollLeft = tableContainer ? tableContainer.scrollLeft : 0;

        const search = document.getElementById('searchInput')?.value?.trim() || '';

        // BASIC FILTERS (clean state)
        let source = document.getElementById('sourceFilter')?.value || '';
        let stage = document.getElementById('statusFilter')?.value || '';

        // Treat "All" as empty
        if (source === '') source = null;
        if (stage === '') stage = null;

        let url = `/leads/list.php?org_id=${orgId}&limit=${limit}&offset=${offset}`;

        if (search) {
            url += `&search=${encodeURIComponent(search)}`;
        }

        // Add basic filters ONLY if selected
        if (source) {
            url += `&source=${encodeURIComponent(source)}`;
        }
        if (stage) {
            url += `&stage_id=${encodeURIComponent(stage)}`;
        }

        // ADVANCED FILTERS
        const { standard, custom } = this.collectAdvancedFilters();

        // SORTING
        const sortBy = this.currentSort?.field || 'created_at';
        const sortOrder = this.currentSort?.order || 'DESC';
        url += `&sort_by=${sortBy}&sort_order=${sortOrder}`;

        // HEADER FILTERS
        this.activeHeaderFilters = {};
        const headerFilters = document.querySelectorAll('.header-filter');
        headerFilters.forEach(el => {
            const val = el.value.trim();
            const name = el.getAttribute('data-name');
            if (val) {
                this.activeHeaderFilters[name] = val;
                const type = el.getAttribute('data-type');
                if (type === 'custom') {
                    url += `&custom_filters[${encodeURIComponent(name)}]=${encodeURIComponent(val)}`;
                } else {
                    url += `&${name}=${encodeURIComponent(val)}`;
                }
            }
        });

        Object.entries(custom).forEach(([key, val]) => {
            url += `&custom_filters[${encodeURIComponent(key)}]=${encodeURIComponent(val)}`;
        });

        console.log('FINAL FILTER URL:', url);

        try {
            const data = await this.api(url);

            if (data && data.data) {
                this.currentLeadsPage = data.data;
                // Store pagination meta
                if (data.meta) {
                    this.leadsMeta = {
                        total: parseInt(data.meta.total),
                        limit: parseInt(data.meta.limit),
                        offset: parseInt(data.meta.offset)
                    };
                }
                await this.renderLeadsTable(data.data);
                this.renderPagination();

                if (window.lucide) {
                    window.lucide.createIcons();
                }

                // Restore scroll position
                window.scrollTo(scrollX, scrollY);
                if (tableContainer) {
                    const newContainer = document.querySelector('#leadsTableContainer .overflow-x-auto') || document.querySelector('.overflow-x-auto');
                    if (newContainer) newContainer.scrollLeft = tableScrollLeft;
                }

                // Restore focus if triggered by a header filter
                if (triggerElementId) {
                    const el = document.getElementById(triggerElementId);
                    if (el) {
                        el.focus();
                        // Set cursor to end
                        if (el.setSelectionRange) {
                            const len = el.value.length;
                            el.setSelectionRange(len, len);
                        } else {
                            const val = el.value;
                            el.value = '';
                            el.value = val;
                        }
                    }
                }
            } else {
                const container = document.getElementById('leadsTableContainer') || document.getElementById('appContent');
                container.innerHTML = '<p class="p-4 text-red-500">No leads found.</p>';
            }
        } catch (e) {
            console.error(e);
            const container = document.getElementById('leadsTableContainer') || document.getElementById('appContent');
            container.innerHTML = '<p class="p-4 text-red-500">Error loading leads.</p>';
        }
    },

    renderPagination() {
        if (!this.leadsMeta) return;
        const { total, limit, offset } = this.leadsMeta;
        const currentPage = Math.floor(offset / limit) + 1;
        const totalPages = Math.ceil(total / limit);

        const createControls = () => {
            return `
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4 rounded shadow-sm">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button onclick="App.prevPage()" ${offset === 0 ? 'disabled' : ''} class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Previous</button>
                        <button onclick="App.nextPage()" ${currentPage >= totalPages ? 'disabled' : ''} class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium">${total === 0 ? 0 : offset + 1}</span>
                                to
                                <span class="font-medium">${Math.min(offset + limit, total)}</span>
                                of
                                <span class="font-medium">${total}</span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <button onclick="App.prevPage()" ${offset === 0 ? 'disabled' : ''} class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span class="sr-only">Previous</span>
                                    <i data-lucide="chevron-left" class="h-5 w-5"></i>
                                </button>
                                <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-offset-0">
                                    Page ${currentPage} of ${totalPages || 1}
                                </span>
                                <button onclick="App.nextPage()" ${currentPage >= totalPages ? 'disabled' : ''} class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span class="sr-only">Next</span>
                                    <i data-lucide="chevron-right" class="h-5 w-5"></i>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            `;
        };

        // Render Top Pagination
        const topContainer = document.getElementById('paginationTop');
        if (topContainer) {
            topContainer.innerHTML = createControls();
        } else {
            // Inject if missing (optional)
            const appContent = document.getElementById('appContent');
            if (appContent) {
                const div = document.createElement('div');
                div.id = 'paginationTop';
                div.className = 'mb-4';
                div.innerHTML = createControls();
                appContent.insertBefore(div, appContent.firstChild);
            }
        }

        // Render Bottom Pagination
        const bottomContainer = document.getElementById('paginationBottom');
        if (bottomContainer) {
            bottomContainer.innerHTML = createControls();
        } else {
            const appContent = document.getElementById('appContent');
            if (appContent) {
                const div = document.createElement('div');
                div.id = 'paginationBottom';
                div.innerHTML = createControls();
                appContent.appendChild(div);
            }
        }

        if (window.lucide) window.lucide.createIcons();
    },

    nextPage() {
        if (!this.leadsMeta) return;
        const { limit, offset, total } = this.leadsMeta;
        if (offset + limit < total) {
            this.loadLeads(offset + limit);
        }
    },

    prevPage() {
        if (!this.leadsMeta) return;
        const { limit, offset } = this.leadsMeta;
        if (offset - limit >= 0) {
            this.loadLeads(offset - limit);
        }
    },

    // Toggle Header Filters
    toggleHeaderFilters(fieldName) {
        if (fieldName) {
            // Toggle specific field
            this.visibleFilterFields[fieldName] = !this.visibleFilterFields[fieldName];

            // If we just turned it on, focus it
            if (this.visibleFilterFields[fieldName]) {
                setTimeout(() => {
                    const input = document.querySelector(`.header-filter[data-name="${fieldName}"]`);
                    if (input) input.focus();
                }, 0);
            }
        } else {
            // Global toggle: if any are visible, hide all. If none visible, show all.
            const anyVisible = Object.values(this.visibleFilterFields).some(v => v);
            if (anyVisible) {
                this.visibleFilterFields = {};
            } else {
                // Show all available fields (standard + custom)
                const filters = document.querySelectorAll('.header-filter');
                filters.forEach(el => {
                    const name = el.getAttribute('data-name');
                    if (name) this.visibleFilterFields[name] = true;
                });
            }
        }

        // Re-render to apply visibility changes
        // Instead of full loadLeads, we just re-render the table part if possible
        // but for simplicity and Lucide icon updates, we'll re-run renderLeadsTable with current data
        if (this.currentLeadsPage) {
            this.renderLeadsTable(this.currentLeadsPage);
        }
    },

    // Handle Header Sorting
    handleSort(field) {
        if (this.currentSort.field === field) {
            // Toggle order
            this.currentSort.order = this.currentSort.order === 'ASC' ? 'DESC' : 'ASC';
        } else {
            // New field, default to ASC
            this.currentSort = { field, order: 'ASC' };
        }
        this.loadLeads(0);
    },


    // MODIFIED: collectAdvancedFilters - Ensure empty/trimmed values are ignored
    collectAdvancedFilters() {
        const standard = {};
        const custom = {};

        const container = document.getElementById('advancedFiltersContainer');
        if (!container) return { standard, custom };

        const standardFields = [
            'name', 'title', 'company', 'email', 'phone',
            'lead_value_min', 'lead_value_max',
            'source', 'stage_id',
            'created_at_from', 'created_at_to',
            'updated_at_from', 'updated_at_to'
        ];

        container.querySelectorAll('input, select').forEach(el => {
            if (!el.name) return;

            const val = el.value?.trim();
            if (!val) return;

            if (standardFields.includes(el.name)) {
                standard[el.name] = val;
            } else {
                custom[el.name] = val;
            }
        });

        return { standard, custom };
    },

    async renderLeadsTable(leads) {
        const customFields = await this.getCustomFields();
        const container = document.getElementById('appContent');
        const visibilitySettings = await this.getFieldVisibility();

        const standardFieldsConfig = [
            { name: 'id', label: 'ID', sortable: true },
            { name: 'name', label: 'Name', sortable: true },
            { name: 'title', label: 'Title', sortable: true },
            { name: 'company', label: 'Company', sortable: true },
            { name: 'stage_id', label: 'Stage', sortable: true },
            { name: 'lead_value', label: 'Value', sortable: true },
            { name: 'source', label: 'Source', sortable: true },
            { name: 'email', label: 'Email', sortable: true },
            { name: 'phone', label: 'Phone', sortable: true },
            { name: 'assigned_to', label: 'Assigned To', sortable: false },
            { name: 'created_at', label: 'Entered On', sortable: true },
            { name: 'updated_at', label: 'Lastly Updated', sortable: true }
        ];

        // Apply saved column order
        const savedOrderObj = typeof this.getColumnOrder === 'function' ? this.getColumnOrder() : null;

        // Handle both old (array) and new (object) formats for backward compatibility during transition/cache
        let savedStandardOrder = null;
        let savedCustomOrder = null;

        if (savedOrderObj) {
            if (Array.isArray(savedOrderObj)) {
                savedStandardOrder = savedOrderObj;
            } else {
                savedStandardOrder = savedOrderObj.standard;
                savedCustomOrder = savedOrderObj.custom;
            }
        }

        if (savedStandardOrder && Array.isArray(savedStandardOrder)) {
            standardFieldsConfig.sort((a, b) => {
                const indexA = savedStandardOrder.indexOf(a.name);
                const indexB = savedStandardOrder.indexOf(b.name);
                if (indexA !== -1 && indexB !== -1) return indexA - indexB;
                if (indexA !== -1) return -1;
                if (indexB !== -1) return 1;
                return 0;
            });
        }

        if (savedCustomOrder && Array.isArray(savedCustomOrder)) {
            customFields.sort((a, b) => {
                const indexA = savedCustomOrder.indexOf(a.name);
                const indexB = savedCustomOrder.indexOf(b.name);
                if (indexA !== -1 && indexB !== -1) return indexA - indexB;
                // For custom fields, if new field added not in order yet, push to end
                if (indexA !== -1) return -1;
                if (indexB !== -1) return 1;
                return 0;
            });
        }

        // Filter visible standard fields, but EXCLUDE 'id' for now to force it to index 0 later
        let visibleStandardFields = standardFieldsConfig.filter(f => f.name !== 'id' && this.isFieldVisible(f.name, 'standard', visibilitySettings));

        // Force 'id' to be at the very front and always visible
        const idField = standardFieldsConfig.find(f => f.name === 'id');
        if (idField) {
            visibleStandardFields.unshift(idField);
        }

        const visibleCustomFields = customFields.filter(f => this.isFieldVisible(f.name, 'custom', visibilitySettings));

        let headers = visibleStandardFields.map(f => f.label);
        headers = [...headers, ...visibleCustomFields.map(f => f.name)];

        const allSelected = leads.length > 0 && leads.every(l => this.selectedLeadIds.has(l.id));
        const selectedCount = this.selectedLeadIds.size;

        // Selected Banner
        let html = '';
        if (selectedCount > 0) {
            html += `<div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-md mb-3 flex items-center justify-between">
                <div class="text-sm font-medium">${selectedCount} selected</div>
                <div class="space-x-2">
                    ${window.hasPermission('delete_leads') ? `<button class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700" onclick="App.bulkDeleteSelected()">Delete</button>` : ''}
                    ${window.hasPermission('export_leads') ? `<button class="px-3 py-1 text-sm bg-gray-800 text-white rounded hover:bg-gray-900" onclick="App.exportSelectedToCSV()">Export CSV</button>` : ''}
                </div>
            </div>`;
        }

        const tableHtml = `
            ${html}
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flow-root">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8 table-wrapper">
                        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead class="bg-gray-50 border-b border-gray-300 sticky-header">
                                        <!-- Header Labels & Sorting -->
                                        <tr>
                                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                                                <input type="checkbox" id="selectAll" onclick="App.toggleSelectAll(this.checked)" ${allSelected ? 'checked' : ''} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            </th>
                                            ${visibleStandardFields.map(f => `
                                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 group ${f.sortable ? 'cursor-pointer hover:bg-gray-100' : ''}" 
                                                    ${f.sortable ? `onclick="App.handleSort('${f.name}')"` : ''}>
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center space-x-1">
                                                            <span>${f.label}</span>
                                                            ${f.sortable ? `
                                                                <div class="flex flex-col opacity-20 group-hover:opacity-100 ${this.currentSort.field === f.name ? 'opacity-100 text-indigo-600' : ''}">
                                                                    <i data-lucide="${this.currentSort.field === f.name ? (this.currentSort.order === 'ASC' ? 'chevron-up' : 'chevron-down') : 'arrow-up-down'}" class="h-3 w-3"></i>
                                                                </div>
                                                            ` : ''}
                                                        </div>
                                                        ${f.name !== 'id' ? `
                                                            <button 
                                                                onclick="event.stopPropagation(); App.toggleHeaderFilters('${f.name}')"
                                                                class="p-1 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all ${this.visibleFilterFields[f.name] || (this.activeHeaderFilters && this.activeHeaderFilters[f.name]) ? 'text-indigo-600' : 'opacity-0 group-hover:opacity-100'}"
                                                                title="Filter ${f.label}"
                                                            >
                                                                <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                                            </button>
                                                        ` : ''}
                                                    </div>
                                                </th>
                                            `).join('')}
                                            ${visibleCustomFields.map(f => `
                                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer hover:bg-gray-100 group" 
                                                    onclick="App.handleSort('${f.name}')">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center space-x-1">
                                                            <span>${f.name}</span>
                                                            <div class="flex flex-col opacity-20 group-hover:opacity-100 ${this.currentSort.field === f.name ? 'opacity-100 text-indigo-600' : ''}">
                                                                <i data-lucide="${this.currentSort.field === f.name ? (this.currentSort.order === 'ASC' ? 'chevron-up' : 'chevron-down') : 'arrow-up-down'}" class="h-3 w-3"></i>
                                                            </div>
                                                        </div>
                                                        <button 
                                                            onclick="event.stopPropagation(); App.toggleHeaderFilters('${f.name}')"
                                                            class="p-1 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all ${this.visibleFilterFields[f.name] || (this.activeHeaderFilters && this.activeHeaderFilters[f.name]) ? 'text-indigo-600' : 'opacity-0 group-hover:opacity-100'}"
                                                            title="Filter ${f.name}"
                                                        >
                                                            <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                                        </button>
                                                    </div>
                                                </th>
                                            `).join('')}
                                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                                <div class="flex items-center justify-end">
                                                    <button 
                                                        onclick="App.toggleHeaderFilters()" 
                                                        class="p-1 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all"
                                                        title="Toggle filters"
                                                    >
                                                        <i data-lucide="filter" class="h-4 w-4"></i>
                                                    </button>
                                                </div>
                                            </th>
                                        </tr>
                                        <!-- Inline Header Filters -->
                                        <tr class="bg-gray-50/50 ${(Object.values(this.visibleFilterFields).some(v => v) || Object.keys(this.activeHeaderFilters || {}).length > 0) ? '' : 'hidden'}" id="headerFilterRow">
                                            <th class="py-2 pl-4 pr-3 sm:pl-6"></th>
                                            ${visibleStandardFields.map(f => {
            const isVisible = this.visibleFilterFields[f.name] || (this.activeHeaderFilters && this.activeHeaderFilters[f.name]);
            return `
                                                <th class="px-2 py-2">
                                                    ${f.name === 'id' ? '' : `
                                                        <input type="text" 
                                                            id="header_filter_${f.name}"
                                                            class="header-filter block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 ${isVisible ? '' : 'invisible'}" 
                                                            placeholder="Search ${f.label}..." 
                                                            data-name="${f.name}" 
                                                            data-type="standard"
                                                            value="${(this.activeHeaderFilters && this.activeHeaderFilters[f.name]) || ''}"
                                                            onkeyup="event.key === 'Enter' && App.loadLeads(0, 'header_filter_${f.name}')">
                                                    `}
                                                </th>
                                            `}).join('')}
                                            ${visibleCustomFields.map(f => {
                const isVisible = this.visibleFilterFields[f.name] || (this.activeHeaderFilters && this.activeHeaderFilters[f.name]);
                return `
                                                <th class="px-2 py-2">
                                                    <input type="text" 
                                                        id="header_filter_${f.name}"
                                                        class="header-filter block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 ${isVisible ? '' : 'invisible'}" 
                                                        placeholder="Search ${f.name}..." 
                                                        data-name="${f.name}" 
                                                        data-type="custom"
                                                        value="${(this.activeHeaderFilters && this.activeHeaderFilters[f.name]) || ''}"
                                                        onkeyup="event.key === 'Enter' && App.loadLeads(0, 'header_filter_${f.name}')">
                                                </th>
                                            `}).join('')}
                                            <th class="py-2 pl-3 pr-4 sm:pr-6"></th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-gray-200 bg-white" id="leadsTableBody">
                                        ${leads.map((lead, index) => this.renderLeadRow(lead, visibleStandardFields, visibleCustomFields, index)).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const leadsContainer = document.getElementById('leadsTableContainer') || document.getElementById('appContent');
        leadsContainer.innerHTML = tableHtml;

        if (window.lucide) {
            window.lucide.createIcons();
        }
    },

    renderLeadRow(lead, standardFields, customFields, index) {
        const rowNum = lead.seq_num || (index + 1);

        const normalizeStageKey = (raw) => {
            if (raw === null || raw === undefined) return '';
            const s = String(raw).trim();
            const num = Number(s);
            if (!Number.isNaN(num)) {
                switch (num) {
                    case 1: return 'new';
                    case 2: return 'contacted';
                    case 3: return 'qualified';
                    case 4: return 'proposal';
                    case 5: return 'won';
                    default: return `stage_${num}`;
                }
            }
            const upper = s.toUpperCase();
            if (upper === 'CLOSED WON') return 'won';
            if (upper === 'PROPOSAL') return 'proposal';
            return s.toLowerCase();
        };

        const getStatusBadge = (statusRaw, displayText) => {
            const key = normalizeStageKey(statusRaw);
            const colors = {
                'new': 'bg-blue-100 text-blue-800',
                'contacted': 'bg-yellow-100 text-yellow-800',
                'qualified': 'bg-green-100 text-green-800',
                'proposal': 'bg-purple-100 text-purple-800',
                'won': 'bg-indigo-100 text-indigo-800',
                'lost': 'bg-red-100 text-red-800'
            };
            const text = (statusRaw || '').toString().trim() || 'UNKNOWN';
            return `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${colors[key] || 'bg-gray-100 text-gray-800'}">${text.toUpperCase()}</span>`;
        };

        let customData = {};
        if (typeof lead.custom_data === 'string') {
            try {
                customData = JSON.parse(lead.custom_data);
            } catch (e) {
                customData = {};
            }
        } else if (lead.custom_data && typeof lead.custom_data === 'object') {
            customData = lead.custom_data;
        }

        const renderCustomField = (name) => customData[name] || '-';

        return `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-4 whitespace-nowrap text-sm">
                    <input type="checkbox" ${this.selectedLeadIds.has(lead.id) ? 'checked' : ''} onclick="App.toggleSelectLead(${lead.id}, this.checked)">
                </td>
                ${standardFields.map(fieldConfig => {
            const fieldName = fieldConfig.name;
            if (fieldName === 'id') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400 font-mono">${rowNum}</td>`;
            } else if (fieldName === 'name') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'name', '${lead.name.replace(/'/g, "\\'")}', 'text')">${lead.name}</td>`;
            } else if (fieldName === 'title') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'title', '${(lead.title || '').replace(/'/g, "\\'")}', 'text')">${lead.title || '-'}</td>`;
            } else if (fieldName === 'company') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'company', '${(lead.company || '').replace(/'/g, "\\'")}', 'text')">${lead.company || '-'}</td>`;
            } else if (fieldName === 'stage_id') {
                const stageDisplay = lead.stage_name || (function () {
                    const key = normalizeStageKey(lead.stage_id);
                    const map = { new: 'NEW', contacted: 'CONTACTED', qualified: 'QUALIFIED', proposal: 'PROPOSAL', won: 'CLOSED WON' };
                    if (map[key]) return map[key];
                    return String(lead.stage_id || '').toUpperCase() || 'UNKNOWN';
                })();
                return `<td class="px-6 py-4 whitespace-nowrap cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'stage_id', '${lead.stage_id}', 'select')">${getStatusBadge(lead.stage_id, stageDisplay)}</td>`;
            } else if (fieldName === 'lead_value') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'lead_value', '${lead.lead_value || ''}', 'text')">${lead.lead_value ? '$' + parseFloat(lead.lead_value).toLocaleString() : '-'}</td>`;
            } else if (fieldName === 'source') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'source', '${(lead.source || '').replace(/'/g, "\\'")}', 'select')">${lead.source || '-'}</td>`;
            } else if (fieldName === 'email') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'email', '${(lead.email || '').replace(/'/g, "\\'")}', 'text')">${lead.email || '-'}</td>`;
            } else if (fieldName === 'phone') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'phone', '${(lead.phone || '').replace(/'/g, "\\'")}', 'text')">${lead.phone || '-'}</td>`;
            } else if (fieldName === 'assigned_to') {
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, 'assigned_to', '${lead.assigned_to || ''}', 'user_select')">${lead.assigned_to_name || 'Unassigned'}</td>`;
            } else if (fieldName === 'created_at' || fieldName === 'updated_at') {
                const dateVal = lead[fieldName];
                if (!dateVal) return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>`;
                const date = new Date(dateVal);
                const formatted = isNaN(date.getTime()) ? dateVal : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                return `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatted}</td>`;
            }
            return '';
        }).join('')}

                
                ${customFields.map(field => {
            const isLongText = field.type === 'textarea' || field.type === 'long_text';
            const val = renderCustomField(field.name);

            if (isLongText) {
                return `
                            <td class="px-6 py-4 text-sm text-gray-500 cursor-pointer min-w-[200px] max-w-xs group" ondblclick="App.makeEditable(this, ${lead.id}, '${field.name}', '${(val + '').replace(/'/g, "\\'")}', '${field.type}', '${(field.options || []).join(',')}', true)">
                                <div class="p-2 bg-gray-50 border border-gray-200 rounded-md text-xs whitespace-normal break-words max-h-24 overflow-y-auto shadow-sm group-hover:border-blue-400 transition-colors custom-scrollbar relative">
                                    <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i data-lucide="edit-2" class="h-3 w-3 text-blue-500"></i>
                                    </div>
                                    ${val || '<span class="text-gray-400 italic">No notes...</span>'}
                                </div>
                            </td>
                        `;
            }
            return `
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" ondblclick="App.makeEditable(this, ${lead.id}, '${field.name}', '${(val + '').replace(/'/g, "\\'")}', '${field.type}', '${(field.options || []).join(',')}', true)">
                            ${val}
                        </td>
                    `;
        }).join('')}

                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    ${window.hasPermission('create_meetings') ? `
                        <button onclick="window.Meetings.openCreateModal(${lead.id}, '${lead.name.replace(/'/g, "\\'")}')" 
                                class="text-green-600 hover:text-green-900 mr-2 transition-colors" 
                                title="Schedule Meeting">
                            Schedule
                        </button>
                    ` : ''}
                    <button onclick="App.openLeadDetail(${lead.id})" class="text-indigo-600 hover:text-indigo-900 mr-2">View</button>
                    ${window.hasPermission('delete_leads') ? `<button onclick="App.confirmDeleteLead(${lead.id})" class="text-red-600 hover:text-red-800">Delete</button>` : ''}
                </td>
            </tr>
        `;
    },

    async openLeadDetail(id) {
        App.openModal('leadDetailPanel');
        document.getElementById('detailHeader').textContent = 'Fetching details...';

        try {
            const lead = await this.api(`/leads/get.php?id=${id}`);
            if (lead) {
                this.renderLeadDetail(lead);
            } else {
                document.getElementById('detailContent').innerHTML = '<p class="text-red-500">Lead not found</p>';
            }
        } catch (e) {
            console.error(e);
        }
    },

    closeLeadDetail() {
        App.closeModal('leadDetailPanel');
    },

    async renderLeadDetail(lead) {
        document.getElementById('detailHeader').textContent = `Created on ${new Date(lead.created_at).toLocaleDateString()}`;

        const container = document.getElementById('detailContent');

        const normalizeStageKey = (raw) => {
            if (raw === null || raw === undefined) return '';
            const s = String(raw).trim();
            const num = Number(s);
            if (!Number.isNaN(num)) {
                switch (num) {
                    case 1: return 'new';
                    case 2: return 'contacted';
                    case 3: return 'qualified';
                    case 4: return 'proposal';
                    case 5: return 'won';
                    default: return `stage_${num}`;
                }
            }
            const upper = s.toUpperCase();
            if (upper === 'CLOSED WON') return 'won';
            if (upper === 'PROPOSAL') return 'proposal';
            return s.toLowerCase();
        };

        const key = normalizeStageKey(lead.stage_id);
        let statusColor = 'bg-gray-100 text-gray-800';
        const colorMap = {
            'new': 'bg-blue-100 text-blue-800',
            'contacted': 'bg-yellow-100 text-yellow-800',
            'qualified': 'bg-green-100 text-green-800',
            'proposal': 'bg-purple-100 text-purple-800',
            'won': 'bg-indigo-100 text-indigo-800',
            'lost': 'bg-red-100 text-red-800'
        };
        if (colorMap[key]) statusColor = colorMap[key];

        const stageDisplay = lead.stage_name || (function () {
            const map = { new: 'NEW', contacted: 'CONTACTED', qualified: 'QUALIFIED', proposal: 'PROPOSAL', won: 'CLOSED WON' };
            return map[key] || (String(lead.stage_id || '').toUpperCase() || 'UNKNOWN');
        })();

        const leadValue = lead.lead_value ? parseFloat(lead.lead_value).toLocaleString('en-US', { style: 'currency', currency: 'USD' }) : '$0.00';

        container.innerHTML = `
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">${lead.name}</h3>
                        <p class="text-sm text-gray-500">${lead.title || 'No Title'} at ${lead.company || 'No Company'}</p>
                    </div>
                    <span class="px-3 py-1 text-sm font-semibold rounded-full ${statusColor}">
                        ${stageDisplay}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-xs text-gray-500 uppercase">Value</p>
                        <p class="text-lg font-bold text-gray-900">${leadValue}</p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-xs text-gray-500 uppercase">Source</p>
                        <p class="text-md font-medium text-gray-900">${lead.source}</p>
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Contact Information</h4>
                    <div class="bg-white border rounded-md divide-y">
                        <div class="flex justify-between p-3">
                            <span class="text-sm text-gray-500">Email</span>
                            <a href="mailto:${lead.email}" class="text-sm text-blue-600 hover:underline">${lead.email || '-'}</a>
                        </div>
                        <div class="flex justify-between p-3">
                            <span class="text-sm text-gray-500">Phone</span>
                            <a href="tel:${lead.phone}" class="text-sm text-blue-600 hover:underline">${lead.phone || '-'}</a>
                        </div>
                        <div class="flex justify-between p-3">
                            <span class="text-sm text-gray-500">Assigned To</span>
                            <span class="text-sm font-medium text-gray-900">${lead.assigned_to_name || 'Unassigned'}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Additional Info</h4>
                    <div class="bg-white border rounded-md divide-y" id="customFieldsContainer">
                        <p class="p-3 text-sm text-gray-500">Loading custom fields...</p>
                    </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-200">
                    <button class="w-full mb-3 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Log Activity (Coming Soon)
                    </button>
                    ${window.hasPermission('view_meetings') ? `
                        <button class="w-full mb-3 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-500 hover:bg-blue-600" onclick="window.Meetings.openCreateModal(${lead.id}, '${lead.name.replace(/'/g, "\\'")}')">
                            <i data-lucide="calendar" class="h-4 w-4 mr-2"></i> Schedule Meeting
                        </button>
                    ` : ''}
                    <div class="grid grid-cols-2 gap-2">
                        <button class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" onclick="App.openEditLeadModal(${lead.id})">
                            Edit Lead
                        </button>
                        <button class="w-full flex justify-center py-2 px-4 border border-red-200 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50" onclick="App.confirmDeleteLead(${lead.id})">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        `;

        const customFields = await this.getCustomFields();
        const customContainer = document.getElementById('customFieldsContainer');
        if (customFields.length === 0) {
            customContainer.innerHTML = '<p class="p-3 text-sm text-gray-500">No custom fields defined.</p>';
        } else {
            customContainer.innerHTML = customFields.map(def => {
                const val = (lead.custom_data && lead.custom_data[def.name]) ? lead.custom_data[def.name] : '-';
                const isLongText = def.type === 'textarea' || def.type === 'long_text';

                if (isLongText) {
                    return `
                        <div class="p-3 border-b">
                            <p class="text-xs text-gray-500 uppercase mb-1">${def.name}</p>
                            <div class="text-sm text-gray-900 bg-gray-50 p-2 rounded border border-gray-100 whitespace-pre-wrap">${val}</div>
                        </div>
                    `;
                }

                return `
                    <div class="flex justify-between p-3 border-b">
                        <span class="text-sm text-gray-500 capitalize">${def.name}</span>
                        <span class="text-sm text-gray-900 font-medium">${val}</span>
                    </div>
                `;
            }).join('');
        }
    },

    async openEditLeadModal(id) {
        try {
            const lead = await this.api(`/leads/get.php?id=${id}`);
            if (!lead) return;

            document.getElementById('modal-title').textContent = 'Edit Lead';
            const form = document.getElementById('createLeadForm');
            form.setAttribute('data-mode', 'edit');
            form.setAttribute('data-id', id);

            form.elements['name'].value = lead.name;
            form.elements['title'].value = lead.title || '';
            form.elements['company'].value = lead.company || '';
            form.elements['lead_value'].value = lead.lead_value || '';
            form.elements['email'].value = lead.email || '';
            form.elements['phone'].value = lead.phone || '';
            form.elements['source'].value = lead.source || 'Direct';
            form.elements['stage_id'].value = lead.stage_id || 'new';

            const assignedToSelect = form.elements['assigned_to'];
            if (assignedToSelect) {
                const users = await this.getOrgUsers();
                assignedToSelect.innerHTML = `<option value="">Unassigned</option>` +
                    users.map(u => `<option value="${u.id}" ${u.id == lead.assigned_to ? 'selected' : ''}>${u.full_name || u.email}</option>`).join('');
            }

            const container = document.getElementById('createLeadForm').querySelector('.grid');
            document.querySelectorAll('.custom-field-input').forEach(el => el.remove());

            const customFields = await this.getCustomFields();
            customFields.forEach(field => {
                const div = document.createElement('div');
                div.className = 'sm:col-span-3 custom-field-input';

                const label = document.createElement('label');
                label.className = 'block text-sm font-medium text-gray-700';
                label.textContent = field.name;

                let input;
                const value = (lead.custom_data && lead.custom_data[field.name]) ? lead.custom_data[field.name] : '';

                if (field.type === 'textarea' || field.type === 'long_text') {
                    input = document.createElement('textarea');
                    input.rows = 4;
                    input.value = value;
                } else if (field.type === 'select') {
                    input = document.createElement('select');
                    input.innerHTML = `<option value=""></option>` +
                        field.options.map(opt => `<option value="${opt}" ${opt == value ? 'selected' : ''}>${opt}</option>`).join('');
                } else if (field.type === 'date') {
                    input = document.createElement('input');
                    input.type = 'date';
                    input.value = value;
                } else {
                    input = document.createElement('input');
                    input.type = 'text';
                    input.value = value;
                }

                input.name = `custom_${field.name}`;
                input.className = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white';

                div.appendChild(label);
                div.appendChild(input);
                container.appendChild(div);
            });

            document.getElementById('createLeadModal').classList.remove('hidden');
        } catch (e) {
            console.error(e);
            alert('Failed to load lead for editing');
        }
    },

    async openCreateModal() {
        App.openModal('createLeadModal');
        try {
            const modal = document.getElementById('createLeadModal');
            if (!modal) {
                console.error('createLeadModal element not found');
                alert('Error: Form modal not found. Please refresh the page.');
                return;
            }

            const form = document.getElementById('createLeadForm');
            if (!form) {
                console.error('createLeadForm element not found');
                return;
            }

            form.reset();
            form.removeAttribute('data-mode');
            form.removeAttribute('data-id');

            const titleEl = document.getElementById('modal-title');
            if (titleEl) {
                titleEl.textContent = 'Add New Lead';
            }

            document.querySelectorAll('.custom-field-input').forEach(el => el.remove());

            // Update source and stage dropdowns with configurable options
            await this.updateFormDropdowns();

            // Get custom fields with error handling
            let customFields = [];
            try {
                if (typeof this.getCustomFields === 'function') {
                    customFields = await this.getCustomFields();
                } else {
                    console.warn('getCustomFields function not available');
                }
            } catch (error) {
                console.error('Error loading custom fields:', error);
                // Continue without custom fields
            }

            const container = form.querySelector('.grid');
            if (!container) {
                console.error('Form grid container not found');
                return;
            }

            customFields.forEach(field => {
                const div = document.createElement('div');
                div.className = 'sm:col-span-3 custom-field-input';

                const label = document.createElement('label');
                label.className = 'block text-sm font-medium text-gray-700';
                label.textContent = field.name;

                let input;
                if (field.type === 'textarea' || field.type === 'long_text') {
                    input = document.createElement('textarea');
                    input.rows = 4;
                    input.className = 'w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2 min-h-[100px] bg-white';
                } else if (field.type === 'select') {
                    input = document.createElement('select');
                    input.className = 'w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-white';
                    input.innerHTML = `<option value=""></option>` +
                        field.options.map(opt => `<option value="${opt}">${opt}</option>`).join('');
                } else if (field.type === 'date') {
                    input = document.createElement('input');
                    input.type = 'date';
                    input.className = 'w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2';
                } else {
                    input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2';
                }

                input.name = `custom_${field.name}`;
                input.className += ' mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2';

                div.appendChild(label);
                div.appendChild(input);
                container.appendChild(div);
            });
        } catch (error) {
            console.error('Error in openCreateModal:', error);
            alert('Error opening form: ' + error.message);
        }
    },

    async saveLead(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const mode = form.getAttribute('data-mode');
        const id = form.getAttribute('data-id');

        const customData = {};
        for (const key in data) {
            if (key.startsWith('custom_')) {
                const originalName = key.replace('custom_', '');
                customData[originalName] = data[key];
                delete data[key];
            }
        }
        data.custom_data = customData;

        if (mode === 'edit' && id) {
            data.id = id;
            data.org_id = this.getUser().org_id;

            const res = await this.api('/leads/update.php', 'POST', data);
            if (res.message) {
                this.closeCreateModal();
                this.loadLeads();
                this.closeLeadDetail();
            } else {
                alert('Error updating lead: ' + (res.error || 'Unknown'));
            }
        } else {
            data.org_id = this.getUser().org_id;
            const res = await this.api('/leads/create.php', 'POST', data);
            if (res.lead_id) {
                this.closeCreateModal();
                this.loadLeads();
            } else {
                alert('Error creating lead: ' + (res.error || 'Unknown'));
            }
        }
    },

    closeCreateModal() {
        App.closeModal('createLeadModal');
        const form = document.getElementById('createLeadForm');
        form.reset();
        document.querySelectorAll('.custom-field-input').forEach(el => el.remove());
        form.removeAttribute('data-mode');
        form.removeAttribute('data-id');
        document.getElementById('modal-title').textContent = 'Add New Lead';
    },

    async updateFormDropdowns() {
        try {
            // FORM DROPDOWNS
            const sourceSelect = document.querySelector('select[name="source"]');
            if (sourceSelect) {
                const opts = await this.getFieldOptions('source');
                const prev = sourceSelect.value;
                sourceSelect.innerHTML = `<option value="">All Sources</option>` +
                    opts.map(o => `<option value="${o}">${o}</option>`).join('');
                sourceSelect.value = prev || '';
            }

            const stageSelect = document.querySelector('select[name="stage_id"]');
            if (stageSelect) {
                const opts = await this.getFieldOptions('stage_id');
                const prev = stageSelect.value;
                stageSelect.innerHTML = `<option value="">All Stages</option>` +
                    opts.map(o => `<option value="${o}">${o.charAt(0).toUpperCase() + o.slice(1)}</option>`).join('');
                stageSelect.value = prev || '';
            }

            // FILTER DROPDOWNS (IMPORTANT)
            const sourceFilter = document.getElementById('sourceFilter');
            if (sourceFilter) {
                const opts = await this.getFieldOptions('source');
                const prev = sourceFilter.value;
                sourceFilter.innerHTML = `<option value="">All Sources</option>` +
                    opts.map(o => `<option value="${o}">${o}</option>`).join('');
                sourceFilter.value = prev || '';
            }

            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                const opts = await this.getFieldOptions('stage_id');
                const prev = statusFilter.value;
                statusFilter.innerHTML = `<option value="">All Stages</option>` +
                    opts.map(o => `<option value="${o}">${o.charAt(0).toUpperCase() + o.slice(1)}</option>`).join('');
                statusFilter.value = prev || '';
            }

            // ADVANCED FILTERS: update all <select> in #advancedFiltersContainer for source/stage_id
            const advancedContainer = document.getElementById('advancedFiltersContainer');
            if (advancedContainer) {
                // Source in advanced
                const advSource = advancedContainer.querySelector('select[name="source"]');
                if (advSource) {
                    const opts = await this.getFieldOptions('source');
                    const prev = advSource.value;
                    advSource.innerHTML = `<option value="">All Sources</option>` +
                        opts.map(o => `<option value="${o}">${o}</option>`).join('');
                    advSource.value = prev || '';
                }
            }
        } catch (e) {
            console.error('Dropdown update failed:', e);
        }
    },

    async getOrgUsers() {
        if (this._orgUsers) return this._orgUsers;
        try {
            const data = await this.api('/users/list.php');
            if (data && data.users) {
                this._orgUsers = data.users;
                return data.users;
            }
        } catch (e) {
            console.error('Failed to fetch org users:', e);
        }
        return [];
    },

    async makeEditable(cell, leadId, field, value, type = 'text', optionsStr = '') {
        if (cell.querySelector('input') || cell.querySelector('select') || cell.querySelector('textarea')) return;

        const originalContent = cell.innerHTML;
        cell.innerHTML = '';

        let input;
        if (type === 'select' || type === 'user_select') {
            input = document.createElement('select');
            input.className = 'w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-white';

            let options = [];
            if (type === 'user_select') {
                const users = await this.getOrgUsers();
                options = users.map(u => ({ value: u.id, text: u.full_name || u.email }));
            } else if (optionsStr) {
                options = optionsStr.split(',').map(o => ({ value: o.trim(), text: o.trim() })).filter(o => o.value);
            } else {
                try {
                    const rawOpts = await this.getFieldOptions(field);
                    options = rawOpts.map(o => ({ value: o, text: o.charAt(0).toUpperCase() + o.slice(1) }));
                } catch (e) {
                    const rawOpts = field === 'stage_id'
                        ? ['new', 'contacted', 'qualified', 'won', 'lost']
                        : (field === 'source' ? ['Direct', 'Website', 'LinkedIn', 'Referral', 'Ads', 'Cold Call'] : []);
                    options = rawOpts.map(o => ({ value: o, text: o.charAt(0).toUpperCase() + o.slice(1) }));
                }
            }

            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.text = 'Unassigned';
            input.appendChild(emptyOption);

            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.text = opt.text;
                if (String(value) === String(option.value)) option.selected = true;
                input.appendChild(option);
            });
        } else if (type === 'textarea' || type === 'long_text') {
            input = document.createElement('textarea');
            input.value = value;
            input.className = 'w-full text-sm border-blue-400 rounded-md shadow-lg focus:ring-2 focus:ring-blue-500 p-2 min-h-[100px] z-50 relative bg-white';
            input.rows = 4;
            // Prevent cell from collapsing or being too small
            cell.style.minWidth = '250px';
        } else {
            input = document.createElement('input');
            input.type = type;
            input.value = value;
            input.className = 'w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 p-1';
        }

        cell.appendChild(input);
        input.focus();

        const save = async () => {
            const newValue = input.value;
            const standardFields = ['name', 'title', 'company', 'lead_value', 'email', 'phone', 'source', 'stage_id', 'assigned_to'];

            if (standardFields.includes(field)) {
                if (newValue !== value) {
                    const updateData = { id: leadId, org_id: this.getUser().org_id };
                    updateData[field] = newValue;
                    const res = await this.api('/leads/update.php', 'POST', updateData);
                    // Only update cell content, do not reload table
                    if (res && res.success !== false) {
                        // Render new value in cell
                        if (field === 'lead_value') {
                            cell.innerHTML = newValue ? '$' + parseFloat(newValue).toLocaleString() : '-';
                        } else if (field === 'stage_id') {
                            // Render badge for stage
                            const normalizeStageKey = (raw) => {
                                if (raw === null || raw === undefined) return '';
                                const s = String(raw).trim();
                                const num = Number(s);
                                if (!Number.isNaN(num)) {
                                    switch (num) {
                                        case 1: return 'new';
                                        case 2: return 'contacted';
                                        case 3: return 'qualified';
                                        case 4: return 'proposal';
                                        case 5: return 'won';
                                        default: return `stage_${num}`;
                                    }
                                }
                                const upper = s.toUpperCase();
                                if (upper === 'CLOSED WON') return 'won';
                                if (upper === 'PROPOSAL') return 'proposal';
                                return s.toLowerCase();
                            };
                            const getStatusBadge = (statusRaw, displayText) => {
                                const key = normalizeStageKey(statusRaw);
                                const colors = {
                                    'new': 'bg-blue-100 text-blue-800',
                                    'contacted': 'bg-yellow-100 text-yellow-800',
                                    'qualified': 'bg-green-100 text-green-800',
                                    'proposal': 'bg-purple-100 text-purple-800',
                                    'won': 'bg-indigo-100 text-indigo-800',
                                    'lost': 'bg-red-100 text-red-800'
                                };
                                const text = (statusRaw || '').toString().trim() || 'UNKNOWN';
                                return `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${colors[key] || 'bg-gray-100 text-gray-800'}">${text.toUpperCase()}</span>`;
                            };
                            cell.innerHTML = getStatusBadge(newValue, newValue);
                        } else if (field === 'assigned_to') {
                            const users = await this.getOrgUsers();
                            const user = users.find(u => u.id == newValue);
                            cell.innerHTML = user ? (user.full_name || user.email) : 'Unassigned';
                        } else {
                            cell.innerHTML = newValue || '-';
                        }
                    } else {
                        // On error, restore original content and optionally show error
                        cell.innerHTML = originalContent;
                        this.showToast('Update failed', 'error');
                    }
                } else {
                    cell.innerHTML = originalContent;
                }
            } else {
                if (newValue !== value) {
                    const lead = await this.api(`/leads/get.php?id=${leadId}&org_id=${this.getUser().org_id}`);
                    let customData = lead.custom_data || {};
                    customData[field] = newValue;

                    const updateData = { id: leadId, org_id: this.getUser().org_id, custom_data: customData };
                    const res = await this.api('/leads/update.php', 'POST', updateData);
                    // Only update cell content, do not reload table
                    if (res && res.success !== false) {
                        cell.innerHTML = newValue || '-';
                    } else {
                        cell.innerHTML = originalContent;
                        this.showToast('Update failed', 'error');
                    }
                } else {
                    cell.innerHTML = originalContent;
                }
            }
        };

        input.addEventListener('blur', save);
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                if (type === 'textarea' || type === 'long_text') {
                    if (e.ctrlKey) {
                        input.blur(); // Save on Ctrl+Enter
                    }
                } else {
                    input.blur(); // Save on Enter for standard fields
                }
            }
        });
    },

    confirmDeleteLead(id) {
        this.showConfirm('Delete this lead? This cannot be undone.', () => this.deleteLead(id));
    },

    async deleteLead(id) {
        try {
            const res = await this.api('/leads/delete.php', 'POST', { id, org_id: this.getUser().org_id });
            if (res && res.success) {
                this.showToast('Lead deleted', 'success');
                this.closeLeadDetail();
                this.loadLeads();
            } else {
                this.showToast(res.error || 'Failed to delete lead', 'error');
            }
        } catch (e) {
            console.error(e);
            this.showToast('Failed to delete lead', 'error');
        }
    },

    toggleSelectLead(id, checked) {
        if (checked) {
            this.selectedLeadIds.add(id);
        } else {
            this.selectedLeadIds.delete(id);
        }
        this.renderLeadsTable(this.currentLeadsPage);
    },

    toggleSelectAll(checked) {
        if (checked) {
            this.currentLeadsPage.forEach(l => this.selectedLeadIds.add(l.id));
        } else {
            this.currentLeadsPage.forEach(l => this.selectedLeadIds.delete(l.id));
        }
        this.renderLeadsTable(this.currentLeadsPage);
    },

    clearSelection() {
        this.selectedLeadIds.clear();
    },

    async bulkDeleteSelected() {
        if (this.selectedLeadIds.size === 0) return;
        const ids = Array.from(this.selectedLeadIds);
        this.showConfirm(`Delete ${ids.length} lead(s)? This cannot be undone.`, async () => {
            try {
                const res = await this.api('/leads/bulk_delete.php', 'POST', { ids, org_id: this.getUser().org_id });
                if (res && res.success) {
                    this.showToast('Selected leads deleted', 'success');
                    this.clearSelection();
                    this.loadLeads();
                } else {
                    this.showToast(res.error || 'Failed to delete selected leads', 'error');
                }
            } catch (e) {
                console.error(e);
                this.showToast('Failed to delete selected leads', 'error');
            }
        });
    },

    async exportSelectedToCSV() {
        const ids = Array.from(this.selectedLeadIds);
        const customFields = await this.getCustomFields();
        const headers = ['id', 'name', 'title', 'company', 'stage_id', 'lead_value', 'source', 'email', 'phone', 'city', 'state', 'country', ...customFields.map(f => f.name)];

        const rowsSource = ids.length > 0
            ? this.currentLeadsPage.filter(l => ids.includes(l.id))
            : this.currentLeadsPage;

        const escape = (val) => {
            if (val === null || val === undefined) return '';
            const str = String(val).replace(/"/g, '""');
            return `"${str}"`;
        };

        const rows = rowsSource.map(lead => {
            let custom = lead.custom_data || {};
            if (typeof custom === 'string') {
                try { custom = JSON.parse(custom); } catch (_) { custom = {}; }
            }
            return [
                escape(lead.id),
                escape(lead.name || ''),
                escape(lead.title || ''),
                escape(lead.company || ''),
                escape(lead.stage_id || ''),
                escape(lead.lead_value || ''),
                escape(lead.source || ''),
                escape(lead.email || ''),
                escape(lead.phone || ''),
                escape(custom.city || ''),
                escape(custom.state || ''),
                escape(custom.country || ''),
                ...customFields.map(f => escape(custom[f.name] || ''))
            ].join(',');
        });

        const csv = [headers.join(','), ...rows].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `leads_export_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    }
});

window.App = App;

// Ensure dropdowns are populated on page load
document.addEventListener('DOMContentLoaded', () => {
    App.updateFormDropdowns();
});
