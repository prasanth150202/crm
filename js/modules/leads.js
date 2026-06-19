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
    // Default sorting state
    currentSort: { field: 'id', order: 'DESC' },

    // Selection state
    selectedLeadIds: new Set(),
    selectAllMode: false,       // true = all leads across all pages selected

    // Header filters state
    visibleFilterFields: {},
    activeHeaderFilters: {},

    // Faceted navigation state
    facetFilters: {},
    inlineFilters: {},        // { fieldKey: value }  for "+ Add Filter" chips

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

        // Clear persisted advanced filters
        this.advancedFiltersState = {};
        localStorage.removeItem('advanced_filters');
        this.updateAdvancedFilterIndicator();

        // Re-populate dropdowns to ensure "All" is selected and options are fresh
        this.updateFormDropdowns();

        // Clear facet + inline filters
        this.facetFilters      = {};
        this.inlineFilters     = {};
        this._inlineFilterMeta = {};
        this.renderInlineFilterChips();
        this._saveFilterBar();

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

        // On first load, restore persisted filter bar config before building the query
        if (!this._filterBarLoaded) {
            this._filterBarLoaded = true;
            await this._loadFilterBarConfig();
        }

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

        console.log(`[LEADS] loadLeads: search="${search}", source="${source}", stage="${stage}"`);

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

        // Date Range Filtering - REMOVED per user request
        // (Leads should show all leads regardless of dashboard date setting)

        // ADVANCED FILTERS
        const { standard, custom, operators } = this.collectAdvancedFilters();

        // SORTING
        const sortBy = this.currentSort?.field || 'created_at';
        const sortOrder = this.currentSort?.order || 'DESC';
        url += `&sort_by=${sortBy}&sort_order=${sortOrder}`;

        // Append advanced STANDARD filters as top-level query params
        Object.entries(standard).forEach(([key, val]) => {
            url += `&${encodeURIComponent(key)}=${encodeURIComponent(val)}`;
        });

        // Append operator choices for advanced filters (e.g. name_op, email_op)
        Object.entries(operators).forEach(([key, val]) => {
            url += `&${encodeURIComponent(key)}=${encodeURIComponent(val)}`;
        });

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

        // FACET FILTERS (multi-select arrays)
        const facets = this.facetFilters || {};
        const facetStdFields = ['stage_id', 'source', 'assigned_to'];
        facetStdFields.forEach(field => {
            (facets[field] || []).forEach(v => { url += `&${field}[]=${encodeURIComponent(v)}`; });
        });
        Object.entries(facets).forEach(([field, vals]) => {
            if (!facetStdFields.includes(field)) {
                (vals || []).forEach(v => { url += `&custom_filters[${encodeURIComponent(field)}][]=${encodeURIComponent(v)}`; });
            }
        });

        // INLINE FILTERS (from "+ Add Filter" chips)
        const stdTextFields = ['name','first_name','last_name','company','title','email','phone','city','state','country','zip_code','address','website'];
        Object.entries(this.inlineFilters || {}).forEach(([field, filter]) => {
            let op  = (filter && typeof filter === 'object' ? filter.op  : 'contains') || 'contains';
            const val = (filter && typeof filter === 'object' ? filter.value : String(filter || '')) || '';
            const noVal = op === 'is_empty' || op === 'is_not_empty';
            // "pick_value" is a UI-only operator — backend treats it as equals
            if (op === 'pick_value') op = 'equals';
            if (!noVal && !val) return;

            if (noVal) {
                url += `&inline_ops[${encodeURIComponent(field)}]=${encodeURIComponent(op)}`;
            } else if (stdTextFields.includes(field)) {
                url += `&${encodeURIComponent(field)}=${encodeURIComponent(val)}&${encodeURIComponent(field)}_op=${encodeURIComponent(op)}`;
            } else {
                url += `&custom_filters[${encodeURIComponent(field)}]=${encodeURIComponent(val)}&${encodeURIComponent(field)}_op=${encodeURIComponent(op)}`;
            }
        });

        console.log('FINAL FILTER URL:', url);
        // Store so select-all operations can fetch all matching leads
        this._currentFilterUrl = url;

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
                this.loadFacets();

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
    // and capture operator selections like name_op / email_op
    collectAdvancedFilters() {
        // First, check if we have a rendered container to read from
        const container = document.getElementById('advancedFiltersContainer');
        const standardFields = [
            'name', 'first_name', 'last_name', 'title', 'company', 'email', 'phone',
            'city', 'state', 'country', 'zip_code', 'address', 'website',
            'lead_value_min', 'lead_value_max',
            'source', 'stage_id',
            'created_at_from', 'created_at_to',
            'updated_at_from', 'updated_at_to'
        ];

        if (container && container.children.length > 0) {
            const standard = {};
            const custom = {};
            const operators = {};

            container.querySelectorAll('input, select').forEach(el => {
                if (!el.name) return;

                const val = el.value?.trim();
                if (!val) return;

                // Operator controls (e.g. name_op, email_op, custom_myField_op)
                if (el.name.endsWith('_op')) {
                    operators[el.name] = val;
                    return;
                }

                if (standardFields.includes(el.name)) {
                    standard[el.name] = val;
                } else {
                    custom[el.name] = val;
                }
            });
            return { standard, custom, operators };
        }

        // Fallback or panel not rendered: use persisted state
        const saved = (typeof this.getSavedAdvancedFilters === 'function') ? this.getSavedAdvancedFilters() : {};
        const standard = {};
        const custom = {};
        const operators = {};

        Object.entries(saved).forEach(([key, val]) => {
            if (key.endsWith('_op')) {
                operators[key] = val;
            } else if (standardFields.includes(key) || key.endsWith('_min') || key.endsWith('_max') || key.endsWith('_from') || key.endsWith('_to')) {
                // Standard fields also include min/max/from/to variants
                standard[key] = val;
            } else {
                custom[key] = val;
            }
        });

        return { standard, custom, operators };
    },

    async renderLeadsTable(leads) {
        const customFields = await this.getCustomFields();
        const container = document.getElementById('appContent');
        const visibilitySettings = await this.getFieldVisibility();
        if (typeof this.ensurePartnerBadges === 'function') await this.ensurePartnerBadges();

        const standardFieldsConfig = [
            { name: 'id', label: 'ID', sortable: true },
            { name: 'name', label: 'Full Name', sortable: true },
            { name: 'first_name', label: 'First Name', sortable: true },
            { name: 'last_name', label: 'Last Name', sortable: true },
            { name: 'title', label: 'Title', sortable: true },
            { name: 'company', label: 'Company', sortable: true },
            { name: 'lead_value', label: 'Value', sortable: true },
            { name: 'stage_id', label: 'Stage', sortable: true },
            { name: 'source', label: 'Source', sortable: true },
            { name: 'email', label: 'Email', sortable: true },
            { name: 'phone', label: 'Phone', sortable: true },
            { name: 'assigned_to', label: 'Assigned To', sortable: false },
            { name: 'address', label: 'Address', sortable: true },
            { name: 'city', label: 'City', sortable: true },
            { name: 'state', label: 'State', sortable: true },
            { name: 'zip_code', label: 'Zip Code', sortable: true },
            { name: 'country', label: 'Country', sortable: true },
            { name: 'website', label: 'Website', sortable: true },
            { name: 'description', label: 'Description', sortable: true },
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

        // Selected Banner — always rendered (hidden when count=0) so in-place updates work
        const html = `<div id="selectionBanner" class="${selectedCount > 0 ? '' : 'hidden'} bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-md mb-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-3 flex-wrap text-sm">
                    <span class="font-medium" id="selectionCount">${selectedCount} selected</span>
                    <button id="selectAllLeadsBtn" class="hidden underline font-medium hover:text-blue-900 focus:outline-none"
                        onclick="App.selectAllLeads()">
                        Select all <span id="selectAllTotal">${this.leadsMeta ? this.leadsMeta.total : ''}</span> leads
                    </button>
                    <span id="selectAllModeMsg" class="hidden font-semibold">
                        · All <span id="selectAllTotalLabel"></span> leads selected.
                        <button onclick="App.cancelSelectAll()" class="underline font-normal hover:text-blue-900 focus:outline-none">Clear</button>
                    </span>
                </div>
                <div class="space-x-2">
                    ${window.hasPermission('delete_leads') ? `<button class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700" onclick="App.bulkDeleteSelected()">Delete</button>` : ''}
                    ${window.hasPermission('export_leads') ? `<button class="px-3 py-1 text-sm bg-gray-800 text-white rounded hover:bg-gray-900" onclick="App.exportSelectedToCSV()">Export CSV</button>` : ''}
                </div>
            </div>
        </div>`;

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
            if (f.name === 'id') return '<th class="px-2 py-2"></th>';

            // Use select dropdown for Stage and Source
            if (f.name === 'stage_id' || f.name === 'source') {
                const options = f.name === 'stage_id'
                    ? ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost']
                    : ['Direct', 'Website', 'LinkedIn', 'Referral', 'Ads', 'Cold Call'];
                return `
                        <th class="px-2 py-2">
                            <select 
                                id="header_filter_${f.name}"
                                class="header-filter block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 bg-white ${isVisible ? '' : 'invisible'}"
                                data-name="${f.name}"
                                data-type="standard"
                                onchange="App.loadLeads(0, 'header_filter_${f.name}')">
                                <option value="">All</option>
                                ${options.map(opt => `<option value="${opt}" ${(this.activeHeaderFilters && this.activeHeaderFilters[f.name] === opt) ? 'selected' : ''}>${opt.charAt(0).toUpperCase() + opt.slice(1)}</option>`).join('')}
                            </select>
                        </th>
                    `;
            }

            return `
                    <th class="px-2 py-2">
                        <input type="text" 
                            id="header_filter_${f.name}"
                            class="header-filter block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 ${isVisible ? '' : 'invisible'}" 
                            placeholder="Search ${f.label}..." 
                            data-name="${f.name}" 
                            data-type="standard"
                            value="${(this.activeHeaderFilters && this.activeHeaderFilters[f.name]) || ''}"
                            onkeyup="event.key === 'Enter' && App.loadLeads(0, 'header_filter_${f.name}')">
                    </th>
                `;
        }).join('')}
                                            ${visibleCustomFields.map(f => {
            const isVisible = this.visibleFilterFields[f.name] || (this.activeHeaderFilters && this.activeHeaderFilters[f.name]);
            if (f.type === 'select' && f.options) {
                return `
                        <th class="px-2 py-2">
                            <select 
                                id="header_filter_${f.name}"
                                class="header-filter block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 bg-white ${isVisible ? '' : 'invisible'}"
                                data-name="${f.name}"
                                data-type="custom"
                                onchange="App.loadLeads(0, 'header_filter_${f.name}')">
                                <option value="">All</option>
                                ${f.options.map(opt => `<option value="${opt}" ${(this.activeHeaderFilters && this.activeHeaderFilters[f.name] === opt) ? 'selected' : ''}>${opt}</option>`).join('')}
                            </select>
                        </th>
                    `;
            }
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
            ${html ? '' : ''}
        `;

        container.innerHTML = tableHtml;
    },

    renderLeadRow(lead, standardFields, customFields, index) {
        const isSelected = this.selectedLeadIds.has(lead.id);
        return `
            <tr data-lead-id="${lead.id}" class="hover:bg-gray-50 transition-colors ${isSelected ? 'bg-indigo-50' : ''}" onclick="App.openLeadDetail(${lead.id})">
                <td class="relative w-12 px-6 sm:w-16 sm:px-8">
                    <div class="sel-indicator absolute inset-y-0 left-0 w-0.5 bg-indigo-600 ${isSelected ? '' : 'hidden'}"></div>
                    <input type="checkbox"
                        class="absolute left-4 top-1/2 -mt-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 sm:left-6"
                        onclick="event.stopPropagation(); App.toggleSelectLead(${lead.id}, this.checked)"
                        ${isSelected ? 'checked' : ''}
                    >
                </td>
                ${standardFields.map(f => {
            let content = lead[f.name] || '-';
            if (f.name === 'id') {
                if (lead.seq_num !== undefined && lead.seq_num !== null) {
                    content = lead.seq_num;
                } else {
                    const total = (this.leadsMeta && this.leadsMeta.total) ? parseInt(this.leadsMeta.total) : 0;
                    const offset = (this.leadsMeta && this.leadsMeta.offset) ? parseInt(this.leadsMeta.offset) : 0;
                    const sortOrder = (this.currentSort && this.currentSort.order) ? this.currentSort.order.toUpperCase() : 'DESC';

                    if (sortOrder === 'DESC') {
                        content = total - (offset + index);
                    } else {
                        content = offset + index + 1;
                    }
                }
            }
            const isEditable = f.name !== 'id' && f.name !== 'created_at' && f.name !== 'updated_at' && f.name !== 'lead_value' && f.name !== 'stage_id' && f.name !== 'source' && f.name !== 'assigned_to';

            if (f.name === 'name') {
                const emailLower = (lead.email || '').toLowerCase();
                let badge = '';
                if (emailLower && this._partnerEmailSet?.has(emailLower)) {
                    badge = `<span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 leading-none" title="Partner">P</span>`;
                } else if (this._referralLeadIdSet?.has(Number(lead.id))) {
                    badge = `<span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700 leading-none" title="Referral">R</span>`;
                }
                content = `<span class="font-medium text-indigo-600">${content}</span>${badge}`;
            } else if (f.name === 'email') {
                if (content !== '-') content = `<a href="mailto:${content}" class="text-gray-500 hover:text-gray-900" onclick="event.stopPropagation()">${content}</a>`;
            } else if (f.name === 'website') {
                if (content !== '-') {
                    let url = content.startsWith('http') ? content : 'https://' + content;
                    content = `<a href="${url}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline" onclick="event.stopPropagation()">${lead[f.name]}</a>`;
                }
            } else if (f.name === 'stage_id') {
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
                const stageLabel = lead.stage_name || key.toUpperCase();

                const colors = {
                    'new': 'bg-blue-100 text-blue-800',
                    'contacted': 'bg-yellow-100 text-yellow-800',
                    'qualified': 'bg-green-100 text-green-800',
                    'proposal': 'bg-purple-100 text-purple-800',
                    'won': 'bg-indigo-100 text-indigo-800',
                    'lost': 'bg-red-100 text-red-800'
                };
                content = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${colors[key] || 'bg-gray-100 text-gray-800'}">${stageLabel}</span>`;
            } else if (f.name === 'source') {
                content = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">${content}</span>`;
            } else if (f.name === 'assigned_to') {
                content = lead.assigned_to_name || 'Unassigned';
            } else if (f.name === 'lead_value') {
                content = (lead.lead_value && parseFloat(lead.lead_value) !== 0) ? App.formatCurrency(lead.lead_value) : '-';
            } else if (f.name === 'created_at' || f.name === 'updated_at') {
                content = content !== '-' ? new Date(content).toLocaleDateString() : '-';
            } else if (f.name === 'description') {
                const fullDesc = lead.description || '';
                // Strip HTML tags for the plain-text preview; store raw HTML in data-full
                const textContent = fullDesc.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                const preview = textContent.length > 80 ? textContent.substring(0, 80) + '…' : (textContent || '-');
                const safeAttr = fullDesc.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/\n/g,'&#10;');
                const safeDisp = preview.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                return `<td class="px-3 py-4 text-sm text-gray-500 desc-col-cell" style="max-width:220px;min-width:120px"
                    data-full="${safeAttr}" data-lead-id="${lead.id}"
                    onclick="event.stopPropagation()"
                    ondblclick="event.stopPropagation(); App.makeEditable(this, ${lead.id}, 'description', this.getAttribute('data-full'), 'textarea')"
                    title="${safeAttr}">
                    <span class="desc-preview block overflow-hidden text-ellipsis whitespace-nowrap">${safeDisp}</span>
                </td>`;
            }

            // For editable cells
            const valStr = String(lead[f.name] || '');
            const editHandlers = isEditable ? `onclick="event.stopPropagation()" ondblclick="event.stopPropagation(); App.makeEditable(this, ${lead.id}, '${f.name}', '${valStr.replace(/'/g, "\\'")}')"` : '';
            if (f.name === 'stage_id') {
                return `<td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 cursor-pointer hover:bg-gray-100" onclick="event.stopPropagation()" ondblclick="event.stopPropagation(); App.makeEditable(this, ${lead.id}, 'stage_id', '${lead.stage_id}', 'select')">${content}</td>`;
            }
            if (f.name === 'source') {
                return `<td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 cursor-pointer hover:bg-gray-100" onclick="event.stopPropagation()" ondblclick="event.stopPropagation(); App.makeEditable(this, ${lead.id}, 'source', '${lead.source}', 'select')">${content}</td>`;
            }
            if (f.name === 'assigned_to') {
                return `<td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 cursor-pointer hover:bg-gray-100" onclick="event.stopPropagation()" ondblclick="event.stopPropagation(); App.makeEditable(this, ${lead.id}, 'assigned_to', '${lead.assigned_to}', 'user_select')">${content}</td>`;
            }
            if (f.name === 'lead_value') {
                return `<td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 cursor-pointer hover:bg-gray-100" onclick="event.stopPropagation()" ondblclick="event.stopPropagation(); App.makeEditable(this, ${lead.id}, 'lead_value', '${lead.lead_value || ''}', 'number')">${content}</td>`;
            }

            return `<td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 max-w-xs truncate ${isEditable ? 'cursor-pointer hover:bg-gray-100' : ''}" ${editHandlers} title="${valStr.replace(/"/g, '&quot;')}">${content}</td>`;
        }).join('')}
                ${customFields.map(f => {
            const val = (lead.custom_data && lead.custom_data[f.name]) || '-';

            let inputType = 'text';
            if (f.type === 'number') inputType = 'number';
            if (f.type === 'date') inputType = 'date';
            if (f.type === 'textarea' || f.type === 'long_text') inputType = 'textarea';
            if (f.type === 'select') inputType = 'select';

            let displayVal = val;
            if (f.type === 'textarea' && val.length > 50) displayVal = val.substring(0, 50) + '...';

            let options = '';
            if (f.type === 'select' && f.options) {
                options = f.options.join(',');
            }

            const valStr = String(val);
            return `<td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 cursor-pointer hover:bg-gray-100 max-w-xs truncate" 
                        onclick="event.stopPropagation()"
                        ondblclick="event.stopPropagation(); App.makeEditable(this, ${lead.id}, '${f.name}', '${valStr.replace(/'/g, "\\'")}', '${inputType}', '${options}')"
                        title="${valStr.replace(/"/g, '&quot;')}">
                        ${displayVal}
                    </td>`;
        }).join('')}
                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <div class="flex items-center justify-end gap-2">
                        ${window.hasPermission('view_meetings') ? `
                        <button
                            onclick="event.stopPropagation(); window.Meetings.openCreateModal(${lead.id}, '${lead.name.replace(/'/g, "\\'")}')"
                            class="inline-flex items-center gap-1 text-blue-500 hover:text-blue-700 hover:bg-blue-50 px-1.5 py-1 rounded transition-colors"
                            title="Schedule Meeting">
                            <i data-lucide="calendar-plus" class="h-4 w-4"></i>
                        </button>
                        ` : ''}
                        <button
                            onclick="event.stopPropagation(); App.openAddToPartnerModal(${lead.id}, '${lead.name.replace(/'/g, "\\'")}')"
                            class="inline-flex items-center gap-1 text-purple-500 hover:text-purple-700 hover:bg-purple-50 px-1.5 py-1 rounded transition-colors"
                            title="Add to Partner">
                            <i data-lucide="handshake" class="h-4 w-4"></i>
                        </button>
                        <button onclick="event.stopPropagation(); App.openLeadDetail(${lead.id})" class="text-indigo-600 hover:text-indigo-900">View</button>
                        ${window.hasPermission('delete_leads') ? `
                        <button onclick="event.stopPropagation(); App.confirmDeleteLead(${lead.id})" class="text-red-600 hover:text-red-900">Delete</button>
                        ` : ''}
                    </div>
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
        container.innerHTML = ''; // Clear previous content

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
        let statusColor = 'bg-slate-100 text-slate-600 border border-slate-200';
        const colorMap = {
            'new': 'bg-blue-50 text-blue-700 border border-blue-200',
            'contacted': 'bg-amber-50 text-amber-700 border border-amber-200',
            'qualified': 'bg-cyan-50 text-cyan-700 border border-cyan-200',
            'proposal': 'bg-violet-50 text-violet-700 border border-violet-200',
            'won': 'bg-green-50 text-green-700 border border-green-200',
            'lost': 'bg-red-50 text-red-700 border border-red-200'
        };
        if (colorMap[key]) statusColor = colorMap[key];

        // Format Description — decode any legacy HTML-escaped content, strip contenteditable attrs
        const _decodeDesc = (s) => {
            const t = document.createElement('textarea');
            t.innerHTML = s;
            return t.value;
        };
        const cleanDesc = lead.description
            ? _decodeDesc(lead.description).replace(/\s*contenteditable="[^"]*"/gi, '')
            : null;
        const descriptionHtml = cleanDesc
            ? `<div class="mt-4 p-3 bg-gray-50 rounded text-sm text-gray-700">${cleanDesc}</div>`
            : '<div class="mt-4 text-sm text-gray-400 italic">No description provided.</div>';

        // Address Block
        const addressParts = [lead.address, lead.city, lead.state, lead.zip_code, lead.country].filter(Boolean);
        const addressHtml = addressParts.length > 0
            ? addressParts.join(', ')
            : '<span class="text-gray-400 italic">No address provided</span>';

        // Website
        const websiteHtml = lead.website
            ? `< a href = "${lead.website.startsWith('http') ? lead.website : 'https://' + lead.website}" target = "_blank" class= "text-indigo-600 hover:underline" > ${lead.website}</a > `
            : '<span class="text-gray-400 italic">No website</span>';

        const html = `
            <div class="space-y-6">
                <!-- Header Info -->
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">${lead.name}</h3>
                        <p class="text-sm text-gray-500">${lead.title || 'No Title'} ${lead.company ? 'at ' + lead.company : ''}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                        ${lead.stage_name || key.toUpperCase()}
                    </span>
                </div>

                <!-- Contact Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Email</label>
                        <div class="mt-1 flex items-center">
                            <i data-lucide="mail" class="h-4 w-4 text-gray-400 mr-2"></i>
                            <a href="mailto:${lead.email}" class="text-indigo-600 hover:underline">${lead.email || '-'}</a>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</label>
                        <div class="mt-1 flex items-center">
                            <i data-lucide="phone" class="h-4 w-4 text-gray-400 mr-2"></i>
                            <a href="tel:${lead.phone}" class="text-indigo-600 hover:underline">${lead.phone || '-'}</a>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Source</label>
                        <div class="mt-1 text-gray-900">${lead.source || '-'}</div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Lead Value</label>
                        <div class="mt-1 text-gray-900 font-medium">${(lead.lead_value && parseFloat(lead.lead_value) !== 0) ? App.formatCurrency(lead.lead_value) : '-'}</div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Address</label>
                        <div class="mt-1 flex items-start">
                            <i data-lucide="map-pin" class="h-4 w-4 text-gray-400 mr-2 mt-0.5"></i>
                            <span class="text-gray-900">${addressHtml}</span>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Website</label>
                        <div class="mt-1 flex items-center">
                            <i data-lucide="globe" class="h-4 w-4 text-gray-400 mr-2"></i>
                            ${websiteHtml}
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</label>
                        <div class="mt-1 flex items-center">
                            <i data-lucide="user" class="h-4 w-4 text-gray-400 mr-2"></i>
                            <span class="text-gray-900">${lead.assigned_to_name || 'Unassigned'}</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</label>
                        <div class="mt-1 text-gray-500">${lead.owner_email || '-'}</div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Description</label>
                    ${descriptionHtml}
                </div>

                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Additional Info</h4>
                    <div class="bg-white border rounded-md divide-y" id="customFieldsContainer">
                        <p class="p-3 text-sm text-gray-500">Loading custom fields...</p>
                    </div>
                </div>

                <!-- Meetings Section -->
                ${window.hasPermission('view_meetings') ? `
                <div class="pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-1.5">
                            <i data-lucide="calendar" class="h-4 w-4 text-blue-500"></i>
                            Meetings
                        </h4>
                        <button onclick="window.Meetings.openCreateModal(${lead.id}, '${lead.name.replace(/'/g, "\\'")}').then(() => App.renderLeadMeetings(${lead.id}, '${lead.name.replace(/'/g, "\\'")}'))"
                            class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded-md transition-colors">
                            <i data-lucide="plus" class="h-3 w-3"></i> Schedule
                        </button>
                    </div>
                    <div id="leadMeetingsSection">
                        <!-- Meetings load here -->
                    </div>
                </div>
                ` : ''}

                <div class="pt-4 border-t border-gray-200">
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

        container.innerHTML = html;
        if (window.lucide) window.lucide.createIcons();

        const customFields = await this.getCustomFields();
        const customContainer = document.getElementById('customFieldsContainer');
        if (!customContainer) {
            console.warn('[LEADS] customFieldsContainer not found in DOM');
            return;
        }

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

        // Load meetings for this lead
        if (window.hasPermission('view_meetings') && window.Meetings) {
            this.renderLeadMeetings(lead.id, lead.name);
        }
    },

    /**
     * Render meetings section inside lead detail panel
     */
    async renderLeadMeetings(leadId, leadName) {
        const container = document.getElementById('leadMeetingsSection');
        if (!container) return;

        container.innerHTML = `
            <div class="flex items-center justify-center py-4">
                <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <span class="ml-2 text-sm text-gray-500">Loading meetings...</span>
            </div>
        `;

        try {
            const meetings = await window.Meetings.loadMeetings(leadId);

            if (!meetings || meetings.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5 text-gray-400">
                        <i data-lucide="calendar-x" class="h-7 w-7 mx-auto mb-2 text-gray-300"></i>
                        <p class="text-sm">No meetings scheduled yet.</p>
                    </div>
                `;
                if (window.lucide) window.lucide.createIcons();
                return;
            }

            const modeColors = {
                'in_person': 'bg-green-100 text-green-700',
                'phone': 'bg-blue-100 text-blue-700',
                'video': 'bg-purple-100 text-purple-700',
                'other': 'bg-gray-100 text-gray-600'
            };
            const modeLabels = {
                'in_person': 'In Person',
                'phone': 'Phone',
                'video': 'Video',
                'other': 'Other'
            };

            container.innerHTML = meetings.map(m => {
                const dt = new Date(m.meeting_date);
                const dateStr = dt.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
                const timeStr = dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const modeClass = modeColors[m.mode] || modeColors.other;
                const modeLabel = modeLabels[m.mode] || m.mode;
                const isPast = dt < new Date();
                const safeLeadName = leadName.replace(/'/g, "\\'");

                return `
                    <div class="flex items-start gap-3 p-3 rounded-lg border ${isPast ? 'border-gray-200 bg-gray-50' : 'border-blue-100 bg-blue-50'} mb-2">
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="h-8 w-8 rounded-full ${isPast ? 'bg-gray-200' : 'bg-blue-100'} flex items-center justify-center">
                                <i data-lucide="calendar" class="h-4 w-4 ${isPast ? 'text-gray-500' : 'text-blue-600'}"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900 truncate">${m.title}</p>
                                <span class="flex-shrink-0 text-xs px-2 py-0.5 rounded-full font-medium ${modeClass}">${modeLabel}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <i data-lucide="clock" class="h-3 w-3 inline mr-1"></i>${dateStr} at ${timeStr} &bull; ${m.duration} min
                            </p>
                            ${m.notes ? `<p class="text-xs text-gray-600 mt-1 line-clamp-2">${m.notes}</p>` : ''}
                            <div class="flex gap-2 mt-2">
                                <button onclick="window.Meetings.editMeeting(${m.id})" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                                <span class="text-gray-300">|</span>
                                <button onclick="window.Meetings.deleteMeeting(${m.id}, true).then(ok => ok && App.renderLeadMeetings(${leadId}, '${safeLeadName}'))" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            if (window.lucide) window.lucide.createIcons();

        } catch (err) {
            console.error('[LEADS] Failed to load meetings for lead:', err);
            container.innerHTML = `<p class="text-sm text-red-500 py-2">Failed to load meetings.</p>`;
        }
    },

    async openEditLeadModal(id) {
        try {
            const lead = await this.api(`/leads/get.php?id=${id}`);
            if (!lead || lead.error) {
                this.showToast(lead?.error || 'Lead not found', 'error');
                return;
            }

            document.getElementById('modal-title').textContent = 'Edit Lead';
            const form = document.getElementById('createLeadForm');
            if (!form) return;

            form.setAttribute('data-mode', 'edit');
            form.setAttribute('data-id', id);

            // Handle name splitting if necessary, or use what's in the lead object
            if (form.elements['first_name']) form.elements['first_name'].value = lead.first_name || '';
            if (form.elements['last_name']) form.elements['last_name'].value = lead.last_name || '';

            // Legacy 'name' support if the input exists
            if (form.elements['name']) form.elements['name'].value = lead.name || '';

            if (form.elements['title']) form.elements['title'].value = lead.title || '';
            if (form.elements['company']) form.elements['company'].value = lead.company || '';
            if (form.elements['lead_value']) form.elements['lead_value'].value = lead.lead_value || '';
            if (form.elements['email']) form.elements['email'].value = lead.email || '';
            if (form.elements['phone']) form.elements['phone'].value = lead.phone || '';
            if (form.elements['source']) form.elements['source'].value = lead.source || 'Direct';
            if (form.elements['stage_id']) form.elements['stage_id'].value = lead.stage_id || 'new';

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
                if (!container) return;
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
                        (field.options || []).map(opt => `<option value="${opt}" ${opt == value ? 'selected' : ''}>${opt}</option>`).join('');
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

            this.openModal('createLeadModal');
        } catch (e) {
            console.error('[LEADS] openEditLeadModal error:', e);
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

            // Update Lead Value label with org currency
            const leadValueLabel = document.getElementById('leadValueLabel');
            if (leadValueLabel) {
                const sym = this.getCurrencySymbol(this.getCurrency());
                leadValueLabel.textContent = `Lead Value (${sym})`;
            }

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
                    input.innerHTML = `< option value = "" ></option > ` +
                        field.options.map(opt => `< option value = "${opt}" > ${opt}</option > `).join('');
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
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Save Lead';

        // Disable button to prevent double submit
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        try {
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
                    this.showToast('Lead updated successfully', 'success');
                } else {
                    alert('Error updating lead: ' + (res.error || 'Unknown'));
                }
            } else {
                data.org_id = this.getUser().org_id;
                const res = await this.api('/leads/create.php', 'POST', data);
                if (res.lead_id) {
                    this.closeCreateModal();
                    this.loadLeads();
                    // Auto-link as partner referral if triggered from partner flow
                    if (this._pendingReferralPartnerId) {
                        const partnerId = this._pendingReferralPartnerId;
                        this._pendingReferralPartnerId = null;
                        await this.api('/partners/add_referral.php', 'POST', {
                            partner_id: partnerId,
                            lead_id:    res.lead_id,
                            type:       'existing',
                        });
                        this.showToast('Lead created and linked as referral!', 'success');
                        if (typeof this.loadPartners === 'function') this.loadPartners();
                    } else {
                        this.showToast('Lead created successfully', 'success');
                    }
                } else {
                    alert('Error creating lead: ' + (res.error || 'Unknown'));
                }
            }
        } catch (error) {
            console.error('Error in saveLead:', error);
            alert('An unexpected error occurred: ' + error.message);
        } finally {
            // Re-enable button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
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
            // FORM DROPDOWNS (Specifically targeting the creation/edit modal)
            const form = document.getElementById('createLeadForm');
            if (form) {
                const sourceSelect = form.elements['source'];
                if (sourceSelect) {
                    const opts = await this.getFieldOptions('source');
                    sourceSelect.innerHTML = opts.map(o => `<option value="${o}">${o}</option>`).join('');
                }

                const stageSelect = form.elements['stage_id'];
                if (stageSelect) {
                    const opts = await this.getFieldOptions('stage_id');
                    stageSelect.innerHTML = opts.map(o => `<option value="${o}">${o.charAt(0).toUpperCase() + o.slice(1)}</option>`).join('');
                }
            }

            // FILTER DROPDOWNS (Top bar filters)
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
            if (field === 'description') {
                // contenteditable div so HTML tables render visually while editing
                input = document.createElement('div');
                input.contentEditable = 'true';
                input.innerHTML = value || '';
                input.style.cssText = 'min-height:160px;max-height:340px;overflow-y:auto;padding:12px;outline:none;font-size:13px;line-height:1.6;word-break:break-word;white-space:pre-wrap;width:100%';
                cell.style.minWidth = '320px';
                cell.style.maxWidth = '440px';
                cell.removeAttribute('title');
            } else {
                input = document.createElement('textarea');
                input.value = value;
                input.rows = 4;
                input.className = 'w-full text-sm border-blue-400 rounded-md shadow-lg focus:ring-2 focus:ring-blue-500 p-2 min-h-[100px] z-50 relative bg-white';
                cell.style.minWidth = '250px';
            }
        } else {
            input = document.createElement('input');
            input.type = type;
            input.value = value;
            input.className = 'w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 p-1';
        }

        cell.appendChild(input);

        // For description: replace the bare textarea with a bordered wrapper that
        // contains the textarea + a thin toolbar strip with the "Add Table" button.
        // This avoids overflow/clipping issues with position:absolute.
        if (field === 'description') {
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'width:100%;border:2px solid #60a5fa;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.1);background:#fff';
            cell.replaceChild(wrapper, input);
            wrapper.appendChild(input);

            const bar = document.createElement('div');
            bar.style.cssText = 'display:flex;align-items:center;gap:6px;padding:5px 8px;background:#f8fafc;border-top:1px solid #e2e8f0';

            const label = document.createElement('span');
            label.style.cssText = 'font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;font-weight:500;flex:1';
            label.textContent = 'Insert';

            const tableBtn = document.createElement('button');
            tableBtn.type = 'button';
            tableBtn.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:3px 9px;font-size:11px;font-weight:500;color:#374151;background:#fff;border:1px solid #d1d5db;border-radius:5px;cursor:pointer;transition:all .15s';
            tableBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" style="width:12px;height:12px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M10 6v12M3 6h18v12H3z"/></svg>Table`;
            tableBtn.addEventListener('mousedown', e => e.preventDefault());
            tableBtn.addEventListener('mouseenter', () => { tableBtn.style.background='#eff6ff'; tableBtn.style.borderColor='#93c5fd'; tableBtn.style.color='#1d4ed8'; });
            tableBtn.addEventListener('mouseleave', () => { tableBtn.style.background='#fff'; tableBtn.style.borderColor='#d1d5db'; tableBtn.style.color='#374151'; });
            tableBtn.addEventListener('click', e => { e.stopPropagation(); App._descShowTablePicker(tableBtn, input); });

            bar.appendChild(label);
            bar.appendChild(tableBtn);
            wrapper.appendChild(bar);
        }

        input.focus();

        const save = async () => {
            let newValue = (input.contentEditable === 'true') ? input.innerHTML : input.value;
            // Strip browser-injected empty markup from contenteditable
            if (input.contentEditable === 'true') {
                const stripped = newValue.replace(/<br\s*\/?>/gi, '').replace(/<div>\s*<\/div>/gi, '').trim();
                if (!stripped) newValue = '';
                // Remove contenteditable attrs so they aren't stored in the DB
                newValue = newValue.replace(/\s*contenteditable="[^"]*"/gi, '');
            }
            const standardFields = ['name', 'title', 'company', 'lead_value', 'email', 'phone', 'source', 'stage_id', 'assigned_to', 'address', 'city', 'state', 'country', 'zip_code', 'website', 'description'];

            if (standardFields.includes(field)) {
                if (newValue !== value) {
                    const updateData = { id: leadId, org_id: this.getUser().org_id };
                    updateData[field] = newValue;
                    const res = await this.api('/leads/update.php', 'POST', updateData);
                    // Update cell content; reload if this field can affect filters
                    if (res && res.success !== false) {
                        // Render new value in cell
                        if (field === 'lead_value') {
                            cell.innerHTML = newValue ? App.formatCurrency(newValue) : '-';
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
                                    'new': 'bg-blue-50 text-blue-700 border border-blue-200',
                                    'contacted': 'bg-amber-50 text-amber-700 border border-amber-200',
                                    'qualified': 'bg-cyan-50 text-cyan-700 border border-cyan-200',
                                    'proposal': 'bg-violet-50 text-violet-700 border border-violet-200',
                                    'won': 'bg-green-50 text-green-700 border border-green-200',
                                    'lost': 'bg-red-50 text-red-700 border border-red-200'
                                };
                                return `<span class="px-2.5 py-0.5 inline-flex text-xs font-semibold rounded-full ${colors[key] || 'bg-slate-100 text-slate-600 border border-slate-200'}">${(displayText || statusRaw).toUpperCase()}</span>`;
                            };
                            cell.innerHTML = getStatusBadge(newValue, newValue);
                        } else if (field === 'website') {
                            if (newValue && newValue !== '-') {
                                let url = newValue.startsWith('http') ? newValue : 'https://' + newValue;
                                cell.innerHTML = `<a href="${url}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline" onclick="event.stopPropagation()">${newValue}</a>`;
                            } else {
                                cell.innerHTML = '-';
                            }
                        } else if (field === 'assigned_to') {
                            const users = await this.getOrgUsers();
                            const user = users.find(u => u.id == newValue);
                            cell.innerHTML = user ? (user.full_name || user.email) : 'Unassigned';
                        } else if (field === 'description') {
                            const text = (newValue || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                            const preview = text.length > 80 ? text.substring(0, 80) + '…' : (text || '-');
                            const safeDisp = preview.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                            cell.setAttribute('data-full', newValue || '');
                            cell.setAttribute('title', text.substring(0, 200));
                            cell.classList.remove('desc-expanded');
                            cell.style.maxWidth = '220px';
                            cell.style.minWidth = '';
                            cell.innerHTML = `<span class="desc-preview block overflow-hidden text-ellipsis whitespace-nowrap">${safeDisp}</span>`;
                        } else {
                            cell.innerHTML = newValue || '-';
                        }
                        // If there are active filters, reload so non‑matching leads disappear
                        const reloadFields = ['stage_id', 'source', 'lead_value', 'name', 'title', 'company', 'email', 'phone', 'address', 'city', 'state', 'zip_code', 'country', 'website'];
                        if (reloadFields.includes(field) && this.leadsMeta) {
                            this.loadLeads(this.leadsMeta.offset || 0);
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
                    // Update cell content and reload so filters are reapplied
                    if (res && res.success !== false) {
                        cell.innerHTML = newValue || '-';
                        if (this.leadsMeta) {
                            this.loadLeads(this.leadsMeta.offset || 0);
                        }
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
        if (checked) this.selectedLeadIds.add(id);
        else          this.selectedLeadIds.delete(id);
        this._applyRowSelection(id, checked);
        this._syncSelectionUI();
    },

    toggleSelectAll(checked) {
        if (checked) {
            this.currentLeadsPage.forEach(l => this.selectedLeadIds.add(l.id));
        } else {
            this.currentLeadsPage.forEach(l => this.selectedLeadIds.delete(l.id));
        }
        document.querySelectorAll('tr[data-lead-id]').forEach(row => {
            this._applyRowSelection(parseInt(row.dataset.leadId, 10), checked, row);
        });
        this._syncSelectionUI();
    },

    // Update one row's visual state in-place — no table re-render
    _applyRowSelection(id, selected, rowEl) {
        const row = rowEl || document.querySelector(`tr[data-lead-id="${id}"]`);
        if (!row) return;
        row.classList.toggle('bg-indigo-50', selected);
        const indicator = row.querySelector('.sel-indicator');
        if (indicator) indicator.classList.toggle('hidden', !selected);
        const cb = row.querySelector('input[type=checkbox]');
        if (cb) cb.checked = selected;
    },

    // Sync banner count + select-all checkbox without re-rendering the table
    _syncSelectionUI() {
        const count = this.selectedLeadIds.size;
        const page  = this.currentLeadsPage || [];
        const allPageSelected = page.length > 0 && page.every(l => this.selectedLeadIds.has(l.id));
        const total     = this.leadsMeta ? this.leadsMeta.total : 0;
        const hasMore   = total > page.length;

        const selectAllCb = document.getElementById('selectAll');
        if (selectAllCb) selectAllCb.checked = allPageSelected && !this.selectAllMode;

        const banner = document.getElementById('selectionBanner');
        if (banner) banner.classList.toggle('hidden', count === 0 && !this.selectAllMode);

        const countEl = document.getElementById('selectionCount');
        if (countEl) countEl.textContent = `${this.selectAllMode ? total : count} selected`;

        // "Select all X leads" button — visible when current page fully selected, more pages exist, not in selectAllMode
        const selectAllBtn = document.getElementById('selectAllLeadsBtn');
        if (selectAllBtn) {
            const show = allPageSelected && hasMore && !this.selectAllMode;
            selectAllBtn.classList.toggle('hidden', !show);
            const totalEl = document.getElementById('selectAllTotal');
            if (totalEl) totalEl.textContent = total;
        }

        // "All X leads selected" message — visible when in selectAllMode
        const selectAllMsg = document.getElementById('selectAllModeMsg');
        if (selectAllMsg) {
            selectAllMsg.classList.toggle('hidden', !this.selectAllMode);
            const labelEl = document.getElementById('selectAllTotalLabel');
            if (labelEl) labelEl.textContent = total;
        }
    },

    clearSelection() {
        this.selectedLeadIds.clear();
        this.selectAllMode = false;
    },

    selectAllLeads() {
        this.selectAllMode = true;
        this._syncSelectionUI();
    },

    cancelSelectAll() {
        this.selectAllMode = false;
        this.selectedLeadIds.clear();
        document.querySelectorAll('tr[data-lead-id]').forEach(row => {
            this._applyRowSelection(parseInt(row.dataset.leadId, 10), false, row);
        });
        this._syncSelectionUI();
    },

    // Fetch all IDs matching current filters (for select-all mode operations)
    async _fetchAllFilteredIds() {
        const baseUrl = (this._currentFilterUrl || '')
            .replace(/limit=\d+/, 'limit=99999')
            .replace(/offset=\d+/, 'offset=0');
        const url = baseUrl || `/leads/list.php?org_id=${this.getUser().org_id}&limit=99999&offset=0`;
        const data = await this.api(url);
        return (data && data.data) ? data.data.map(l => l.id) : [];
    },

    // Fetch all leads matching current filters (for select-all export)
    async _fetchAllFilteredLeads() {
        const baseUrl = (this._currentFilterUrl || '')
            .replace(/limit=\d+/, 'limit=99999')
            .replace(/offset=\d+/, 'offset=0');
        const url = baseUrl || `/leads/list.php?org_id=${this.getUser().org_id}&limit=99999&offset=0`;
        const data = await this.api(url);
        return (data && data.data) ? data.data : [];
    },

    async bulkDeleteSelected() {
        if (this.selectedLeadIds.size === 0 && !this.selectAllMode) return;

        let ids;
        if (this.selectAllMode) {
            this.showToast('Fetching all leads...', 'info');
            try {
                ids = await this._fetchAllFilteredIds();
            } catch (e) {
                this.showToast('Failed to fetch lead IDs', 'error');
                return;
            }
        } else {
            ids = Array.from(this.selectedLeadIds);
        }

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
        const customFields = await this.getCustomFields();
        const headers = ['id', 'name', 'title', 'company', 'stage_id', 'lead_value', 'source', 'email', 'phone', 'city', 'state', 'country', ...customFields.map(f => f.name)];

        let rowsSource;
        if (this.selectAllMode) {
            this.showToast('Preparing export...', 'info');
            try {
                rowsSource = await this._fetchAllFilteredLeads();
            } catch (e) {
                this.showToast('Failed to fetch leads for export', 'error');
                return;
            }
        } else {
            const ids = Array.from(this.selectedLeadIds);
            rowsSource = ids.length > 0
                ? this.currentLeadsPage.filter(l => ids.includes(l.id))
                : this.currentLeadsPage;
        }

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
    },

    // ── Faceted Navigation ──────────────────────────────────────────────────

    // Internal state for open dropdown
    _openFacetField: null,
    _facetData: null,
    _facetOutsideHandler: null,

    async loadFacets() {
        const user = this.getUser();
        if (!user) return;
        const orgId = user.org_id;

        // Wire up the static Add Filter button once
        if (!this._addFilterBtnWired) {
            this._addFilterBtnWired = true;
            const addBtn = document.getElementById('addFilterBtn');
            if (addBtn) {
                addBtn.addEventListener('click', () => this.showAddFilterMenu(addBtn));
            }
        }

        // Fetch custom fields so the Add Filter menu can list non-select custom fields
        try {
            const cf = await this.getCustomFields();
            this._lastCustomFields = cf || [];
        } catch (e) {
            this._lastCustomFields = [];
        }

        const search = document.getElementById('searchInput')?.value?.trim() || '';
        let url = `/leads/facets.php?org_id=${orgId}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;

        // Pass active facet filters (arrays → multiple params)
        const facets = this.facetFilters || {};
        const stdFields = ['stage_id', 'source', 'assigned_to'];
        stdFields.forEach(field => {
            const vals = facets[field] || [];
            vals.forEach(v => { url += `&${field}[]=${encodeURIComponent(v)}`; });
        });
        Object.entries(facets).forEach(([field, vals]) => {
            if (!stdFields.includes(field)) {
                (vals || []).forEach(v => { url += `&facet_custom[${encodeURIComponent(field)}][]=${encodeURIComponent(v)}`; });
            }
        });

        // Pass basic filter context so counts respect active dropdowns
        const stage = document.getElementById('statusFilter')?.value || '';
        const src   = document.getElementById('sourceFilter')?.value || '';
        if (stage && !(facets.stage_id || []).length) url += `&stage_id[]=${encodeURIComponent(stage)}`;
        if (src   && !(facets.source   || []).length) url += `&source[]=${encodeURIComponent(src)}`;

        try {
            const data = await this.api(url);
            this._facetData = data;
            this.renderFacetButtons(data);
            // Refresh dropdown content if one is open
            if (this._openFacetField) {
                this._renderDropdownContent(this._openFacetField);
            }
        } catch (e) {
            console.warn('[FACETS] Failed to load facets', e);
        }
    },

    renderFacetButtons(data) {
        const container = document.getElementById('facetButtons');
        if (!container) return;

        const facets    = this.facetFilters || {};
        const hasActive = Object.values(facets).some(arr => arr && arr.length > 0);

        const clearBtn = document.getElementById('facetClearAll');
        if (clearBtn) clearBtn.classList.toggle('hidden', !hasActive);

        const stageLabels = { new:'New', contacted:'Contacted', qualified:'Qualified', proposal:'Proposal', won:'Won', lost:'Lost' };

        const stdFacetFields = ['stage_id', 'source', 'assigned_to'];

        const buildBtn = (field, title, items, isStandard = false) => {
            const active   = facets[field] || [];
            const isActive = active.length > 0;

            // Standard fields always show; custom fields only show if they have data or an active filter
            if (!isStandard && (!items || items.length === 0) && !isActive) return '';

            let btnText = title;
            if (isActive) {
                // Use active values for label — fall back to raw value if not in items list
                const stageMap = { new:'New', contacted:'Contacted', qualified:'Qualified', proposal:'Proposal', won:'Won', lost:'Lost' };
                const labels = active.map(v => {
                    const item = (items || []).find(i => String(i.value) === String(v));
                    const lbl  = item ? (item.label || item.value) : v;
                    return field === 'stage_id' ? (stageMap[lbl.toLowerCase()] || stageMap[v] || lbl) : lbl;
                });
                const shown = labels.slice(0, 2).join(', ');
                btnText = `${title}: ${shown}${labels.length > 2 ? ` +${labels.length - 2}` : ''}`;
            }

            // Use data attributes — avoids any quote-escaping issues in onclick
            return `
            <button data-facet-btn data-facet-field="${this.escapeHtml(field)}"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-full border transition-all focus:outline-none
                    ${isActive
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-semibold shadow-sm'
                        : 'border-gray-300 bg-white text-gray-600 hover:border-indigo-400 hover:text-indigo-600'}">
                ${isActive ? '<i data-lucide="check" class="h-3 w-3 flex-shrink-0"></i>' : ''}
                <span>${this.escapeHtml(btnText)}</span>
                ${isActive
                    ? `<i data-lucide="x" data-clear-field="${this.escapeHtml(field)}" class="h-3 w-3 flex-shrink-0 ml-0.5 hover:text-red-500"></i>`
                    : '<i data-lucide="chevron-down" class="h-3 w-3 flex-shrink-0"></i>'}
            </button>`;
        };

        const isPipeline = (this.currentView === 'pipeline');

        let html = '';
        if (!isPipeline) html += buildBtn('stage_id', 'Stage', data.stage_id, true);
        html += buildBtn('source',      'Source',      data.source,      true);
        html += buildBtn('assigned_to', 'Assigned To', data.assigned_to, true);
        if (data.custom) {
            Object.entries(data.custom).forEach(([fieldName, items]) => {
                const title = fieldName.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                html += buildBtn(fieldName, title, items, false);
            });
        }

        container.innerHTML = html;
        if (window.lucide) window.lucide.createIcons();

        // Attach events after DOM is written
        container.querySelectorAll('[data-facet-btn]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const xBtn = e.target.closest('[data-clear-field]');
                if (xBtn) {
                    e.stopPropagation();
                    this.clearFacetField(xBtn.dataset.clearField);
                    return;
                }
                this.toggleFacetDropdown(btn.dataset.facetField, btn);
            });
        });
    },

    toggleFacetDropdown(field, btnEl) {
        const dropdown = document.getElementById('facetDropdown');
        if (!dropdown) return;

        // Toggle: if same field already open, close it
        if (this._openFacetField === field && !dropdown.classList.contains('hidden')) {
            this.closeFacetDropdown();
            return;
        }

        this._openFacetField = field;
        this._renderDropdownContent(field);

        // Align dropdown horizontally with the clicked button.
        // top is handled by CSS (calc(100% + 6px) relative to the bar).
        const bar     = document.getElementById('facetFilterBar');
        const barRect = bar ? bar.getBoundingClientRect() : { left: 0, width: 0 };
        const btnRect = btnEl.getBoundingClientRect();
        let left = btnRect.left - barRect.left;
        const ddW = 220;
        // Clamp so dropdown doesn't go past the right edge of the bar
        const maxLeft = barRect.width - ddW;
        if (left > maxLeft) left = Math.max(0, maxLeft);
        dropdown.style.left = left + 'px';
        dropdown.style.display = 'flex';
        dropdown.classList.remove('hidden');

        // Close on outside mousedown (fires before click, more reliable)
        if (this._facetOutsideHandler) {
            document.removeEventListener('mousedown', this._facetOutsideHandler);
        }
        this._facetOutsideHandler = (e) => {
            if (!dropdown.contains(e.target) && !e.target.closest('[data-facet-btn]')) {
                this.closeFacetDropdown();
            }
        };
        setTimeout(() => document.addEventListener('mousedown', this._facetOutsideHandler), 0);
        if (window.lucide) window.lucide.createIcons();
    },

    _renderDropdownContent(field) {
        const dropdown = document.getElementById('facetDropdown');
        if (!dropdown || !this._facetData) return;

        const stdFields = ['stage_id', 'source', 'assigned_to'];
        const items = stdFields.includes(field)
            ? (this._facetData[field] || [])
            : (this._facetData.custom && this._facetData.custom[field]) || [];

        const active = this.facetFilters[field] || [];

        const stageColors = {
            new:'bg-blue-50 text-blue-700 border border-blue-200',
            contacted:'bg-amber-50 text-amber-700 border border-amber-200',
            qualified:'bg-cyan-50 text-cyan-700 border border-cyan-200',
            proposal:'bg-violet-50 text-violet-700 border border-violet-200',
            won:'bg-green-50 text-green-700 border border-green-200',
            lost:'bg-red-50 text-red-700 border border-red-200',
        };

        // If items is empty but there are active values, show them so user can deselect
        const stageLabels = { new:'New', contacted:'Contacted', qualified:'Qualified', proposal:'Proposal', won:'Won', lost:'Lost' };
        let mergedItems = [...items];
        active.forEach(v => {
            if (!mergedItems.find(i => String(i.value) === String(v))) {
                const lbl = field === 'stage_id' ? (stageLabels[v] || v) : v;
                mergedItems.unshift({ value: v, label: lbl, count: 0 });
            }
        });

        // Deduplicate by label — e.g. NULL and 0 both appear as "Unassigned" from the API
        const labelSeen = {};
        mergedItems = mergedItems.filter(item => {
            const lbl = (item.label || item.value || '').toLowerCase();
            if (labelSeen[lbl]) {
                labelSeen[lbl].count += item.count || 0;
                return false;
            }
            labelSeen[lbl] = item;
            return true;
        });

        if (!mergedItems.length) {
            dropdown.innerHTML = '<p class="px-4 py-3 text-xs text-gray-400">No results with current filters</p>';
            return;
        }

        const buildRow = (item) => {
            const isChecked = active.includes(String(item.value));
            const label     = this.escapeHtml(item.label || item.value);
            const badge     = field === 'stage_id' ? (stageColors[item.value] || 'bg-gray-100 text-gray-600') : null;
            return `
            <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none facet-row">
                <input type="checkbox" ${isChecked ? 'checked' : ''}
                    data-facet-field="${this.escapeHtml(field)}"
                    data-facet-value="${this.escapeHtml(String(item.value))}"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                <span class="flex-1 text-sm text-gray-700 truncate">
                    ${badge
                        ? `<span class="px-1.5 py-0.5 rounded text-xs font-medium ${badge}">${label}</span>`
                        : label}
                </span>
                <span class="text-xs text-gray-400 flex-shrink-0">${item.count}</span>
            </label>`;
        };

        dropdown.innerHTML = `
            <div class="px-2 py-2 border-b border-gray-100 flex-shrink-0">
                <input type="text" placeholder="Search…" id="facetSearch"
                    class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-md outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300"
                    autocomplete="off">
            </div>
            <div id="facetSearchRows" class="overflow-y-auto flex-1">
                ${mergedItems.map(buildRow).join('')}
            </div>`;

        if (window.lucide) window.lucide.createIcons();

        // Search filter
        const searchInput = dropdown.querySelector('#facetSearch');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const q = searchInput.value.toLowerCase();
                dropdown.querySelectorAll('.facet-row').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(q) ? '' : 'none';
                });
            });
            setTimeout(() => searchInput.focus(), 50);
        }

        // Attach change events
        dropdown.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.addEventListener('change', () => {
                this.toggleFacetFilter(cb.dataset.facetField, cb.dataset.facetValue);
            });
        });
    },

    closeFacetDropdown() {
        const dropdown = document.getElementById('facetDropdown');
        if (dropdown) { dropdown.classList.add('hidden'); dropdown.style.display = ''; }
        this._openFacetField = null;
        if (this._facetOutsideHandler) {
            document.removeEventListener('mousedown', this._facetOutsideHandler);
            this._facetOutsideHandler = null;
        }
    },

    // Reload the correct view based on which is currently active
    _reloadCurrentView(offset = 0) {
        const view = this.currentView || window.location.pathname.split('/').filter(Boolean).pop() || 'leads';
        if (view === 'pipeline' && typeof this.loadKanban === 'function') {
            this.loadKanban();
        } else {
            this.loadLeads(offset);
        }
    },

    toggleFacetFilter(field, value) {
        if (!this.facetFilters) this.facetFilters = {};
        if (!this.facetFilters[field]) this.facetFilters[field] = [];

        const idx = this.facetFilters[field].indexOf(value);
        if (idx >= 0) {
            this.facetFilters[field].splice(idx, 1);
            if (this.facetFilters[field].length === 0) delete this.facetFilters[field];
        } else {
            this.facetFilters[field].push(value);
        }
        this._reloadCurrentView(0);
    },

    clearFacetField(field) {
        if (this.facetFilters) delete this.facetFilters[field];
        this.closeFacetDropdown();
        this._reloadCurrentView(0);
    },

    clearFacetFilters() {
        this.facetFilters       = {};
        this.inlineFilters      = {};
        this._inlineFilterMeta  = {};
        this.renderInlineFilterChips();
        this._saveFilterBar();
        this.closeFacetDropdown();
        this._reloadCurrentView(0);
    },

    // ── Persistent filter bar (org-level) ───────────────────────────────────

    _filterBarLsKey() {
        const user = this.getUser && this.getUser();
        return 'filter_bar_' + (user ? user.org_id : '0');
    },

    async _loadFilterBarConfig() {
        const isPlainObj = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);
        const applyConfig = (cfg) => {
            if (!isPlainObj(cfg)) return false;
            let applied = false;
            if (isPlainObj(cfg.inlineFilters)) {
                this.inlineFilters = cfg.inlineFilters;
                applied = true;
            }
            if (isPlainObj(cfg.inlineFilterMeta)) {
                this._inlineFilterMeta = cfg.inlineFilterMeta;
                applied = true;
            }
            return applied;
        };

        // 1. Restore from localStorage immediately (handles same-device refresh)
        try {
            const raw = localStorage.getItem(this._filterBarLsKey());
            if (raw) {
                const cached = JSON.parse(raw);
                if (applyConfig(cached)) this.renderInlineFilterChips();
            }
        } catch (e) { /* ignore */ }

        // 2. Verify/override with server (handles cross-device sync)
        try {
            const data = await this.api('/settings/get_filter_bar.php');
            if (data && data.success && data.filter_bar) {
                applyConfig(data.filter_bar);
                // Keep localStorage in sync with server
                try { localStorage.setItem(this._filterBarLsKey(), JSON.stringify(data.filter_bar)); } catch (e) { /* ignore */ }
                this.renderInlineFilterChips();
            }
        } catch (e) {
            console.warn('[FILTER BAR] Server load failed, using local cache', e);
        }
    },

    _saveFilterBar() {
        const payload = {
            inlineFilters:    this.inlineFilters    || {},
            inlineFilterMeta: this._inlineFilterMeta || {},
        };

        // Save to localStorage instantly — survives same-device refresh
        try { localStorage.setItem(this._filterBarLsKey(), JSON.stringify(payload)); } catch (e) { /* ignore */ }

        // Debounce the server write to avoid hammering on every keystroke
        clearTimeout(this._saveFilterBarTimer);
        this._saveFilterBarTimer = setTimeout(() => {
            this.api('/settings/save_filter_bar.php', 'POST', { filter_bar: payload })
                .catch(e => console.warn('[FILTER BAR] Server save failed', e));
        }, 800);
    },

    // ── Field value loader (for "= pick value" operator) ────────────────────

    _fieldValuesCache: {},

    async _loadFieldValues(fieldKey) {
        try {
            const data = await this.api(`/leads/field_values.php?field=${encodeURIComponent(fieldKey)}`);
            if (!data || !data.values) return;
            if (!this._fieldValuesCache) this._fieldValuesCache = {};
            this._fieldValuesCache[fieldKey] = data.values;
        } catch (e) {
            console.warn('[FIELD VALUES] Failed to load for', fieldKey, e);
        }
    },

    _showValuePickerPanel(anchorBtn, fieldKey, itemsOverride) {
        const panel = document.getElementById('valuePickerPanel');
        if (!panel) return;

        const items = itemsOverride || (this._fieldValuesCache && this._fieldValuesCache[fieldKey]) || [];
        const curVal = (this.inlineFilters[fieldKey] && this.inlineFilters[fieldKey].value) || '';

        const buildOptions = (list, q = '') => list
            .filter(item => !q || String(item.value).toLowerCase().includes(q))
            .map(item => `
                <button type="button" data-pick-val="${this.escapeHtml(String(item.value))}"
                    class="w-full text-left flex items-center justify-between px-3 py-2 text-xs hover:bg-indigo-50 cursor-pointer
                           ${curVal === String(item.value) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700'}">
                    <span class="truncate">${this.escapeHtml(String(item.value))}</span>
                    <span class="text-gray-400 ml-2 flex-shrink-0">${item.count}</span>
                </button>`).join('');

        panel.innerHTML = `
            <div class="px-2 py-2 border-b border-gray-100 flex-shrink-0">
                <input type="text" id="vPickerSearch" placeholder="Search…" autocomplete="off"
                    class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-md outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300">
            </div>
            <div id="vPickerList" class="overflow-y-auto flex-1">
                ${items.length ? buildOptions(items) : '<p class="px-3 py-3 text-xs text-gray-400">No values found</p>'}
            </div>`;

        // Position below the button (fixed — viewport coords, no scroll offset)
        const rect = anchorBtn.getBoundingClientRect();
        const panelW = 220;
        let left = rect.left;
        if (left + panelW > window.innerWidth - 8) left = Math.max(8, window.innerWidth - panelW - 8);
        panel.style.top  = (rect.bottom + 4) + 'px';
        panel.style.left = left + 'px';
        panel.style.display = 'flex';
        panel.classList.remove('hidden');

        // Search inside panel
        const searchEl = panel.querySelector('#vPickerSearch');
        const listEl   = panel.querySelector('#vPickerList');
        if (searchEl && listEl) {
            searchEl.addEventListener('input', () => {
                const q = searchEl.value.toLowerCase();
                listEl.innerHTML = buildOptions(items, q) ||
                    '<p class="px-3 py-3 text-xs text-gray-400">No match</p>';
                this._attachPickerOptionEvents(panel, fieldKey, anchorBtn);
            });
            setTimeout(() => searchEl.focus(), 50);
        }

        this._attachPickerOptionEvents(panel, fieldKey, anchorBtn);

        // Close on outside click
        const outside = (e) => {
            if (!panel.contains(e.target) && e.target !== anchorBtn) {
                panel.style.display = 'none';
                panel.classList.add('hidden');
                document.removeEventListener('mousedown', outside);
            }
        };
        // Store handler so we can remove it if panel is force-closed
        if (this._vPickerOutsideHandler) document.removeEventListener('mousedown', this._vPickerOutsideHandler);
        this._vPickerOutsideHandler = outside;
        setTimeout(() => document.addEventListener('mousedown', outside), 0);
    },

    _closeValuePickerPanel() {
        const panel = document.getElementById('valuePickerPanel');
        if (panel) { panel.style.display = 'none'; panel.classList.add('hidden'); }
        if (this._vPickerOutsideHandler) {
            document.removeEventListener('mousedown', this._vPickerOutsideHandler);
            this._vPickerOutsideHandler = null;
        }
    },

    _attachPickerOptionEvents(panel, fieldKey, anchorBtn) {
        panel.querySelectorAll('[data-pick-val]').forEach(btn => {
            btn.addEventListener('click', () => {
                const val = btn.dataset.pickVal;
                if (!this.inlineFilters[fieldKey] || typeof this.inlineFilters[fieldKey] !== 'object') {
                    this.inlineFilters[fieldKey] = { op: 'equals', value: val };
                } else {
                    this.inlineFilters[fieldKey].value = val;
                }
                // Update button label
                if (anchorBtn) anchorBtn.textContent = val || '— pick —';
                panel.style.display = 'none';
                panel.classList.add('hidden');
                this._saveFilterBar();
                this._reloadCurrentView(0);
            });
        });
    },

    // ── "+ Add Filter" inline chips ─────────────────────────────────────────

    // All fields available to add as inline filters
    _getAddableFields() {
        const stdFields = [
            { key: 'company',    label: 'Company',    type: 'text' },
            { key: 'name',       label: 'Full Name',  type: 'text' },
            { key: 'first_name', label: 'First Name', type: 'text' },
            { key: 'last_name',  label: 'Last Name',  type: 'text' },
            { key: 'email',      label: 'Email',      type: 'text' },
            { key: 'phone',      label: 'Phone',      type: 'text' },
            { key: 'title',      label: 'Title',      type: 'text' },
            { key: 'city',       label: 'City',       type: 'text' },
            { key: 'state',      label: 'State',      type: 'text' },
            { key: 'country',    label: 'Country',    type: 'text' },
            { key: 'zip_code',   label: 'Zip Code',   type: 'text' },
            { key: 'website',    label: 'Website',    type: 'text' },
        ];
        // Add custom non-select fields
        // getCustomFields() normalizes to { name, type, options } — use those keys
        const customFields = this._lastCustomFields || [];
        customFields.forEach(cf => {
            const fieldName = cf.name || cf.field_name;
            const fieldType = cf.type || cf.field_type || 'text';
            if (fieldName) {
                stdFields.push({ key: fieldName, label: fieldName.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()), type: fieldType, isCustom: true });
            }
        });
        return stdFields;
    },

    showAddFilterMenu(btnEl) {
        const dropdown = document.getElementById('facetDropdown');
        if (!dropdown) return;

        // Close if already open on __add__
        if (this._openFacetField === '__add__' && !dropdown.classList.contains('hidden')) {
            this.closeFacetDropdown();
            return;
        }

        this._openFacetField = '__add__';
        const fields  = this._getAddableFields();
        const active  = Object.keys(this.inlineFilters || {});

        const buildFieldRow = (f) => {
            const isAdded = active.includes(f.key);
            return `
            <label class="add-field-row flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none
                          ${isAdded ? 'opacity-50 pointer-events-none' : ''}">
                <input type="checkbox" ${isAdded ? 'checked disabled' : ''}
                    data-add-field="${this.escapeHtml(f.key)}"
                    data-add-label="${this.escapeHtml(f.label)}"
                    data-add-type="${this.escapeHtml(f.type)}"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 cursor-pointer">
                <span class="flex-1 text-sm text-gray-700">${this.escapeHtml(f.label)}</span>
            </label>`;
        };

        dropdown.innerHTML = `
            <div class="px-2 py-2 border-b border-gray-100 flex-shrink-0">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Add a filter</p>
                <input type="text" placeholder="Search fields…" id="addFilterSearch"
                    class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-md outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300"
                    autocomplete="off">
            </div>
            <div id="addFilterRows" class="overflow-y-auto flex-1">
                ${fields.map(buildFieldRow).join('')}
            </div>`;

        // Align below the button
        const bar     = document.getElementById('facetFilterBar');
        const barRect = bar ? bar.getBoundingClientRect() : { left: 0, width: 0 };
        const btnRect = btnEl.getBoundingClientRect();
        let left = btnRect.left - barRect.left;
        const ddW    = 220;
        const maxLeft = (barRect.width || window.innerWidth) - ddW;
        if (left > maxLeft) left = Math.max(0, maxLeft);
        dropdown.style.left = left + 'px';
        dropdown.style.display = 'flex';
        dropdown.classList.remove('hidden');

        // Search filter for field list
        const addSearch = dropdown.querySelector('#addFilterSearch');
        if (addSearch) {
            addSearch.addEventListener('input', () => {
                const q = addSearch.value.toLowerCase();
                dropdown.querySelectorAll('.add-field-row').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
            setTimeout(() => addSearch.focus(), 50);
        }

        // Event listeners on checkboxes
        dropdown.querySelectorAll('[data-add-field]').forEach(cb => {
            cb.addEventListener('change', () => {
                if (cb.checked) {
                    this.addInlineFilter(cb.dataset.addField, cb.dataset.addLabel, cb.dataset.addType);
                    this.closeFacetDropdown();
                }
            });
        });

        // Outside close
        if (this._facetOutsideHandler) document.removeEventListener('mousedown', this._facetOutsideHandler);
        this._facetOutsideHandler = (e) => {
            if (!dropdown.contains(e.target) && !e.target.closest('[data-facet-btn]')) {
                this.closeFacetDropdown();
            }
        };
        setTimeout(() => document.addEventListener('mousedown', this._facetOutsideHandler), 0);
        if (window.lucide) window.lucide.createIcons();
    },

    addInlineFilter(fieldKey, label, type) {
        if (!this.inlineFilters) this.inlineFilters = {};
        const defaultOp = type === 'select' ? 'equals' : 'contains';
        this.inlineFilters[fieldKey] = { op: defaultOp, value: '' };
        this._inlineFilterMeta = this._inlineFilterMeta || {};
        this._inlineFilterMeta[fieldKey] = { label, type: type || 'text' };
        this.renderInlineFilterChips();
        this._saveFilterBar();
        setTimeout(() => {
            const inp = document.querySelector(`[data-inline-field="${CSS.escape(fieldKey)}"]`);
            if (inp) inp.focus();
        }, 50);
    },

    removeInlineFilter(fieldKey) {
        delete (this.inlineFilters || {})[fieldKey];
        delete (this._inlineFilterMeta || {})[fieldKey];
        this.renderInlineFilterChips();
        this._saveFilterBar();
        this._reloadCurrentView(0);
    },

    renderInlineFilterChips() {
        const container = document.getElementById('inlineFilterChips');
        if (!container) return;

        // Always close the floating value picker when chips are redrawn
        this._closeValuePickerPanel();

        const filters = this.inlineFilters || {};
        const meta    = this._inlineFilterMeta || {};

        if (!Object.keys(filters).length) {
            container.innerHTML = '';
            return;
        }

        const getOps = (type) => {
            if (type === 'number') return [
                { v: 'equals',       l: 'is' },
                { v: 'not_equals',   l: 'is not' },
                { v: 'pick_value',   l: '= pick value' },
            ];
            if (type === 'date') return [
                { v: 'equals',       l: 'is' },
                { v: 'not_equals',   l: 'is not' },
                { v: 'is_empty',     l: 'is empty' },
                { v: 'is_not_empty', l: 'is not empty' },
            ];
            if (type === 'select') return [
                { v: 'equals',       l: 'is' },
                { v: 'not_equals',   l: 'is not' },
                { v: 'pick_value',   l: '= pick value' },
                { v: 'is_empty',     l: 'is empty' },
                { v: 'is_not_empty', l: 'is not empty' },
            ];
            return [
                { v: 'contains',     l: 'contains' },
                { v: 'not_contains', l: "doesn't contain" },
                { v: 'equals',       l: 'is' },
                { v: 'not_equals',   l: 'is not' },
                { v: 'starts_with',  l: 'starts with' },
                { v: 'ends_with',    l: 'ends with' },
                { v: 'pick_value',   l: '= pick value' },
                { v: 'is_empty',     l: 'is empty' },
                { v: 'is_not_empty', l: 'is not empty' },
            ];
        };

        const chips = Object.entries(filters).map(([key, filter]) => {
            const info   = meta[key] || { label: key, type: 'text' };
            const label  = this.escapeHtml(info.label);
            const ops    = getOps(info.type);
            const curOp  = (filter && filter.op)  || (info.type === 'select' ? 'equals' : 'contains');
            const curVal = (filter && filter.value) || '';
            const noVal  = curOp === 'is_empty' || curOp === 'is_not_empty';
            const pickVal = curOp === 'pick_value';

            const opOptions = ops.map(o =>
                `<option value="${this.escapeHtml(o.v)}"${curOp === o.v ? ' selected' : ''}>${this.escapeHtml(o.l)}</option>`
            ).join('');

            const inputType = info.type === 'date' ? 'date' : (info.type === 'number' ? 'number' : 'text');

            // Render value control based on operator
            let valueControl = '';
            if (!noVal) {
                if (pickVal || info.type === 'select') {
                    // Custom searchable picker button
                    const displayVal = curVal || '— pick —';
                    valueControl = `<button type="button"
                        data-vbtn-field="${this.escapeHtml(key)}"
                        data-vbtn-type="${pickVal ? 'pick' : 'select'}"
                        class="px-2 py-1.5 border-none outline-none bg-transparent text-xs text-gray-700 cursor-pointer border-l border-indigo-200 hover:bg-indigo-100 whitespace-nowrap max-w-[140px] truncate text-left">
                        ${this.escapeHtml(displayVal)}
                    </button>`;
                } else {
                    valueControl = `<input
                        type="${inputType}"
                        value="${this.escapeHtml(curVal)}"
                        placeholder="value…"
                        data-inline-field="${this.escapeHtml(key)}"
                        class="px-2 py-1.5 border-none outline-none bg-transparent text-xs w-24 focus:w-36 transition-all border-l border-indigo-200 min-w-0">`;
                }
            }

            return `
            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 text-xs overflow-hidden shadow-sm">
                <span class="px-2.5 py-1.5 font-semibold text-indigo-700 border-r border-indigo-200 bg-indigo-100 whitespace-nowrap">${label}</span>
                <select data-inline-op="${this.escapeHtml(key)}"
                    class="px-1.5 py-1.5 border-none outline-none bg-transparent text-xs text-gray-600 cursor-pointer focus:ring-0">
                    ${opOptions}
                </select>
                ${valueControl}
                <button data-inline-remove="${this.escapeHtml(key)}"
                    class="px-2 py-1.5 text-gray-400 hover:text-red-500 focus:outline-none border-l border-indigo-200 flex-shrink-0">
                    <i data-lucide="x" class="h-3 w-3"></i>
                </button>
            </span>`;
        }).join('');

        container.innerHTML = chips;
        if (window.lucide) window.lucide.createIcons();

        // Operator change — re-render chip, fetch values if pick_value selected
        container.querySelectorAll('[data-inline-op]').forEach(sel => {
            sel.addEventListener('change', () => {
                const key = sel.dataset.inlineOp;
                if (!this.inlineFilters[key] || typeof this.inlineFilters[key] !== 'object') {
                    this.inlineFilters[key] = { op: sel.value, value: '' };
                } else {
                    this.inlineFilters[key].op  = sel.value;
                    this.inlineFilters[key].value = '';
                }
                this.renderInlineFilterChips();
                this._saveFilterBar();
                const noVal = sel.value === 'is_empty' || sel.value === 'is_not_empty';
                if (noVal) this._reloadCurrentView(0);
                if (sel.value === 'pick_value') this._loadFieldValues(key);
            });
        });

        // Value input — debounced search + save
        container.querySelectorAll('[data-inline-field]').forEach(inp => {
            const key = inp.dataset.inlineField;
            const commit = () => {
                if (!this.inlineFilters[key] || typeof this.inlineFilters[key] !== 'object') {
                    this.inlineFilters[key] = { op: 'contains', value: inp.value };
                } else {
                    this.inlineFilters[key].value = inp.value;
                }
                this._saveFilterBar();
                this._reloadCurrentView(0);
            };
            const debounced = this.debounce ? this.debounce(commit, 400) : commit;
            inp.addEventListener('input', debounced);
            inp.addEventListener('change', commit);
        });

        // Value picker button — opens searchable panel
        container.querySelectorAll('[data-vbtn-field]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const key  = btn.dataset.vbtnField;
                const type = btn.dataset.vbtnType;
                if (type === 'pick') {
                    const cached = (this._fieldValuesCache || {})[key] || [];
                    if (!cached.length) {
                        this._loadFieldValues(key).then(() => this._showValuePickerPanel(btn, key));
                    } else {
                        this._showValuePickerPanel(btn, key);
                    }
                } else {
                    // select type — use facet data
                    const items = (this._facetData && this._facetData.custom && this._facetData.custom[key]) || [];
                    this._showValuePickerPanel(btn, key, items);
                }
            });
        });

        // Remove button
        container.querySelectorAll('[data-inline-remove]').forEach(btn => {
            btn.addEventListener('click', () => this.removeInlineFilter(btn.dataset.inlineRemove));
        });
    },
});

window.App = App;

// ── Description editor: table-insert picker ──────────────────────────────────
// btn = the "Add Table" button; textarea = the description textarea element
App._descShowTablePicker = function(btn, textarea) {
    // Attach picker to the td cell (not the bar) so overflow:hidden on the
    // wrapper doesn't clip it. The cell is the nearest position:relative ancestor.
    const cell = btn.closest('td') || btn.parentElement;

    // Toggle off if already open
    const existing = cell.querySelector('.desc-tbl-picker');
    if (existing) { existing.remove(); return; }

    const ROWS = 6, COLS = 5;
    const picker = document.createElement('div');
    picker.className = 'desc-tbl-picker';
    // Open above the button
    picker.style.cssText = 'position:absolute;bottom:calc(100% + 6px);left:0;z-index:9999;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.15);padding:12px';

    const label = document.createElement('p');
    label.style.cssText = 'font-size:11px;font-weight:500;color:#374151;text-align:center;margin-bottom:8px';
    label.textContent = 'Select rows × columns';

    const grid = document.createElement('div');
    grid.style.cssText = `display:grid;grid-template-columns:repeat(${COLS},1fr);gap:3px`;

    const gcells = [];
    for (let r = 1; r <= ROWS; r++) {
        for (let c = 1; c <= COLS; c++) {
            const gc = document.createElement('div');
            gc.style.cssText = 'width:16px;height:16px;border:1px solid #e5e7eb;border-radius:3px;cursor:pointer;background:#f9fafb';
            gc.dataset.r = r; gc.dataset.c = c;
            gc.addEventListener('mouseenter', () => {
                label.textContent = `${r} rows × ${c} cols`;
                gcells.forEach(el => {
                    const on = +el.dataset.r <= r && +el.dataset.c <= c;
                    el.style.background   = on ? '#dbeafe' : '#f9fafb';
                    el.style.borderColor  = on ? '#60a5fa' : '#e5e7eb';
                });
            });
            gc.addEventListener('mousedown', e => e.preventDefault());
            gc.addEventListener('click', () => {
                App._descInsertTable(textarea, r, c);
                picker.remove();
            });
            grid.appendChild(gc);
            gcells.push(gc);
        }
    }

    picker.appendChild(label);
    picker.appendChild(grid);
    cell.style.position = 'relative';
    cell.appendChild(picker);

    setTimeout(() => {
        const close = (e) => {
            if (!picker.contains(e.target) && e.target !== btn) {
                picker.remove();
                document.removeEventListener('mousedown', close);
            }
        };
        document.addEventListener('mousedown', close);
    }, 0);
};

// Inserts a plain HTML table into the contenteditable description div.
App._descInsertTable = function(editor, rows, cols) {
    const tbl = document.createElement('table');
    tbl.style.cssText = 'border-collapse:collapse;width:100%;margin:8px 0;font-size:13px;table-layout:fixed';

    const tbody = document.createElement('tbody');
    for (let r = 0; r < rows; r++) {
        const tr = document.createElement('tr');
        for (let c = 0; c < cols; c++) {
            const td = document.createElement('td');
            td.contentEditable = 'true';
            td.style.cssText = 'border:1px solid #d1d5db;padding:6px 10px;min-width:60px;background:#fff';
            td.innerHTML = '&#8203;';
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    tbl.appendChild(tbody);

    // Insert at cursor; fall back to appending at end
    editor.focus();
    const sel = window.getSelection();
    if (sel && sel.rangeCount && editor.contains(sel.anchorNode)) {
        const range = sel.getRangeAt(0);
        range.deleteContents();
        range.insertNode(tbl);
        // Move cursor after the table
        const after = document.createTextNode('\n');
        tbl.after(after);
        range.setStartAfter(after);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
    } else {
        editor.appendChild(tbl);
    }
};

// ── Description cell: eye-icon expand / collapse ─────────────────────────────
App.toggleDescriptionExpand = function(td) {
    if (!td || td.querySelector('textarea')) return;
    const preview = td.querySelector('.desc-preview');
    if (!preview) return;

    const full = td.getAttribute('data-full') || '';

    if (td.classList.contains('desc-expanded')) {
        const short = full.length > 80 ? full.substring(0, 80) + '…' : (full || '-');
        preview.textContent = short;
        preview.classList.add('overflow-hidden', 'text-ellipsis', 'whitespace-nowrap');
        preview.classList.remove('whitespace-pre-wrap', 'break-words');
        td.style.maxWidth = '220px';
        td.style.minWidth = '';
        td.classList.remove('desc-expanded');
    } else {
        preview.textContent = full || '-';
        preview.classList.remove('overflow-hidden', 'text-ellipsis', 'whitespace-nowrap');
        preview.classList.add('whitespace-pre-wrap', 'break-words');
        td.style.maxWidth = '380px';
        td.style.minWidth = '200px';
        td.classList.add('desc-expanded');
    }
};

// Ensure dropdowns are populated on page load
document.addEventListener('DOMContentLoaded', () => {
    App.updateFormDropdowns();
});
