/**
 * Kanban board rendering and drag handling.
 */

const App = window.App || {};

Object.assign(App, {
    // Compute from/to dates for a given preset label
    _kanbanDateRange(preset) {
        const today = new Date();
        const pad = n => String(n).padStart(2, '0');
        const fmt = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

        const todayStr = fmt(today);

        if (preset === 'today') {
            return { from: todayStr, to: todayStr };
        }
        if (preset === 'yesterday') {
            const y = new Date(today); y.setDate(y.getDate() - 1);
            return { from: fmt(y), to: fmt(y) };
        }
        if (preset === '7days') {
            const d = new Date(today); d.setDate(d.getDate() - 6);
            return { from: fmt(d), to: todayStr };
        }
        if (preset === '30days') {
            const d = new Date(today); d.setDate(d.getDate() - 29);
            return { from: fmt(d), to: todayStr };
        }
        if (preset === 'month') {
            const d = new Date(today.getFullYear(), today.getMonth(), 1);
            return { from: fmt(d), to: todayStr };
        }
        return { from: null, to: null }; // 'all' or 'custom' handled separately
    },

    _applyKanbanPreset(preset) {
        if (!this._kanbanDateFilter) this._kanbanDateFilter = { preset: 'all', from: null, to: null };
        this._kanbanDateFilter.preset = preset;
        if (preset !== 'custom') {
            const range = this._kanbanDateRange(preset);
            this._kanbanDateFilter.from = range.from;
            this._kanbanDateFilter.to = range.to;
        }
        // Toggle custom inputs visibility
        const customRow = document.getElementById('kanbanCustomDateRow');
        if (customRow) customRow.classList.toggle('hidden', preset !== 'custom');
        // Update button styles
        document.querySelectorAll('.kanban-date-btn').forEach(btn => {
            const active = btn.dataset.preset === preset;
            btn.className = btn.className.replace(/bg-blue-600 text-white|bg-white text-gray-700/g, '').trim();
            btn.classList.add(active ? 'bg-blue-600' : 'bg-white', active ? 'text-white' : 'text-gray-700');
        });
        this.loadKanban();
    },

    _applyKanbanCustomDate() {
        const from = document.getElementById('kanbanDateFrom')?.value || null;
        const to = document.getElementById('kanbanDateTo')?.value || null;
        if (!this._kanbanDateFilter) this._kanbanDateFilter = { preset: 'custom', from: null, to: null };
        this._kanbanDateFilter.from = from;
        this._kanbanDateFilter.to = to;
        this.loadKanban();
    },

    async loadKanban() {
        document.getElementById('appContent').innerHTML = '<div class="text-center py-10"><p class="text-gray-500">Loading Pipeline...</p></div>';

        // Restore persisted filter bar config on first load (same as leads view)
        if (!this._filterBarLoaded && typeof this._loadFilterBarConfig === 'function') {
            this._filterBarLoaded = true;
            await this._loadFilterBarConfig();
        }

        // Init date filter state if not set
        if (!this._kanbanDateFilter) this._kanbanDateFilter = { preset: 'all', from: null, to: null };

        const user = this.getUser ? this.getUser() : null;
        const orgId = user ? user.org_id : '';
        const search = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        const source = document.getElementById('sourceFilter') ? document.getElementById('sourceFilter').value : '';
        const limit = 500;

        let url = `/leads/list.php?limit=${limit}&offset=0`;
        if (orgId) url += `&org_id=${orgId}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (source) url += `&source=${encodeURIComponent(source)}`;

        // Apply date filter
        const df = this._kanbanDateFilter;
        if (df.from) url += `&created_at_from=${encodeURIComponent(df.from)}`;
        if (df.to)   url += `&created_at_to=${encodeURIComponent(df.to)}`;

        // Apply facet filters (multi-select arrays)
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

        // Apply inline filters (from "+ Add Filter" chips)
        const stdTextFields = ['name','first_name','last_name','company','title','email','phone','city','state','country','zip_code','address','website'];
        Object.entries(this.inlineFilters || {}).forEach(([field, filter]) => {
            const op  = (filter && typeof filter === 'object' ? filter.op  : 'contains') || 'contains';
            const val = (filter && typeof filter === 'object' ? filter.value : String(filter || '')) || '';
            const noVal = op === 'is_empty' || op === 'is_not_empty';
            if (!noVal && !val) return;
            if (noVal) {
                url += `&inline_ops[${encodeURIComponent(field)}]=${encodeURIComponent(op)}`;
            } else if (stdTextFields.includes(field)) {
                url += `&${encodeURIComponent(field)}=${encodeURIComponent(val)}&${encodeURIComponent(field)}_op=${encodeURIComponent(op)}`;
            } else {
                url += `&custom_filters[${encodeURIComponent(field)}]=${encodeURIComponent(val)}&${encodeURIComponent(field)}_op=${encodeURIComponent(op)}`;
            }
        });

        try {
            const data = await this.api(url);
            if (data && data.data) {
                await this.renderKanbanBoard(data.data);
                // Load facets so the filter bar shows counts for current pipeline data
                if (typeof this.loadFacets === 'function') this.loadFacets();
            } else {
                document.getElementById('appContent').innerHTML = '<p class="p-4 text-red-500">Failed to load pipeline.</p>';
            }
        } catch (e) {
            console.error(e);
            document.getElementById('appContent').innerHTML = '<p class="p-4 text-red-500">Error loading pipeline.</p>';
        }
    },

    _kanbanDateFilterBar() {
        const df = this._kanbanDateFilter || { preset: 'all', from: null, to: null };
        const presets = [
            { key: 'all',       label: 'All' },
            { key: 'today',     label: 'Today' },
            { key: 'yesterday', label: 'Yesterday' },
            { key: '7days',     label: '7 Days' },
            { key: '30days',    label: '30 Days' },
            { key: 'month',     label: 'This Month' },
            { key: 'custom',    label: 'Custom' },
        ];

        const buttons = presets.map(p => {
            const active = df.preset === p.key;
            return `<button
                class="kanban-date-btn px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 transition-colors ${active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-50'}"
                data-preset="${p.key}"
                onclick="App._applyKanbanPreset('${p.key}')">${p.label}</button>`;
        }).join('');

        const customHidden = df.preset !== 'custom' ? 'hidden' : '';

        return `
            <div class="flex items-center gap-2 px-1 pb-3 flex-wrap">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide mr-1">Date:</span>
                ${buttons}
                <div id="kanbanCustomDateRow" class="flex items-center gap-2 ${customHidden}">
                    <input type="date" id="kanbanDateFrom" value="${df.from || ''}"
                        class="text-xs border border-gray-300 rounded-md px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500"
                        onchange="App._applyKanbanCustomDate()">
                    <span class="text-xs text-gray-500">to</span>
                    <input type="date" id="kanbanDateTo" value="${df.to || ''}"
                        class="text-xs border border-gray-300 rounded-md px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500"
                        onchange="App._applyKanbanCustomDate()">
                </div>
            </div>`;
    },

    async renderKanbanBoard(leads) {
        const container = document.getElementById('appContent');

        // Stage colors cycling list
        const colorPalette = [
            'border-blue-400', 'border-yellow-400', 'border-green-400',
            'border-purple-400', 'border-indigo-400', 'border-red-400',
            'border-pink-400', 'border-orange-400', 'border-teal-400', 'border-gray-400'
        ];

        // Load stages from settings — cached per session, cleared on settings save
        let stages = [];
        if (!this._cachedStageOptions) {
            try {
                const cfg = await this.api('/settings/get_field_config.php');
                if (cfg && cfg.success && cfg.fields && cfg.fields.stage_id && Array.isArray(cfg.fields.stage_id.options) && cfg.fields.stage_id.options.length > 0) {
                    this._cachedStageOptions = cfg.fields.stage_id.options;
                }
            } catch (e) {
                console.warn('Failed to load stage config for kanban:', e);
            }
        }
        if (this._cachedStageOptions) {
            stages = this._cachedStageOptions.map((opt, i) => ({
                id: opt.toLowerCase(),
                name: opt.charAt(0).toUpperCase() + opt.slice(1).replace(/_/g, ' '),
                color: colorPalette[i % colorPalette.length]
            }));
        }

        // Fallback to defaults if settings unavailable
        if (stages.length === 0) {
            stages = [
                { id: 'new',       name: 'New',       color: 'border-blue-400'   },
                { id: 'contacted', name: 'Contacted',  color: 'border-yellow-400' },
                { id: 'qualified', name: 'Qualified',  color: 'border-green-400'  },
                { id: 'proposal',  name: 'Proposal',   color: 'border-purple-400' },
                { id: 'won',       name: 'Won',        color: 'border-indigo-400' },
                { id: 'lost',      name: 'Lost',       color: 'border-red-400'    },
            ];
        }

        // Add any stages found in lead data that are not in settings (safety net)
        const knownIds = new Set(stages.map(s => s.id));
        leads.forEach(l => {
            const sid = (l.stage_id || 'new').toLowerCase();
            if (!knownIds.has(sid)) {
                stages.push({ id: sid, name: sid.charAt(0).toUpperCase() + sid.slice(1).replace(/_/g, ' '), color: 'border-gray-400' });
                knownIds.add(sid);
            }
        });

        // Group leads by stage — every configured stage always shown (even if empty)
        const grouped = {};
        stages.forEach(s => grouped[s.id] = []);
        leads.forEach(l => {
            const sid = (l.stage_id || 'new').toLowerCase();
            if (grouped[sid] !== undefined) {
                grouped[sid].push(l);
            } else {
                grouped[stages[0]?.id || 'new'].push(l);
            }
        });

        let html = `<div class="flex flex-col h-[calc(100vh-160px)]">`;
        html += this._kanbanDateFilterBar();
        html += `<div class="flex overflow-x-auto pb-4 space-x-4 flex-1 min-w-full">`;

        stages.forEach(stage => {
            const stageLeads = grouped[stage.id] || [];
            const totalValue = stageLeads.reduce((sum, l) => sum + parseFloat(l.lead_value || 0), 0);
            const formattedValue = App.formatCurrency(totalValue);

            html += `
                <div class="flex-shrink-0 w-80 bg-gray-100 rounded-lg flex flex-col border-t-4 ${stage.color}" 
                     oncontextmenu="return false;">
                    
                    <div class="p-3 bg-gray-100 rounded-t-lg sticky top-0 z-10 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-sm font-bold text-gray-700 uppercase">${stage.name}</h3>
                            <span class="text-xs font-semibold bg-white px-2 py-0.5 rounded text-gray-500 border border-gray-200">${stageLeads.length}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1 font-medium">${formattedValue}</div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-2 space-y-3" 
                         ondrop="App.handleDrop(event, '${stage.id}')" 
                         ondragover="App.allowDrop(event)">
                        
                        ${stageLeads.map(lead => this.renderKanbanCard(lead)).join('')}
                        
                        ${stageLeads.length === 0 ? '<div class="h-full flex items-center justify-center text-gray-400 text-xs italic">No leads</div>' : ''}
                    </div>
                </div>
            `;
        });

        html += `</div></div>`;
        container.innerHTML = html;
        lucide.createIcons();
    },

    renderKanbanCard(lead) {
        const value = lead.lead_value ? App.formatCurrency(lead.lead_value) : '';

        return `
            <div class="bg-white p-3 rounded shadow-sm border border-gray-200 cursor-move hover:shadow-md transition-shadow group relative"
                 draggable="true" 
                 ondragstart="App.handleDragStart(event, ${lead.id})">
                
                <div class="flex justify-between items-start mb-2">
                    <span class="text-xs font-semibold text-blue-600 truncate bg-blue-50 px-1 rounded">${lead.company || 'No Company'}</span>
                     <button onclick="App.openLeadDetail(${lead.id})" class="text-gray-400 hover:text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity">
                        <i data-lucide="eye" class="h-4 w-4"></i>
                    </button>
                </div>

                <h4 class="text-sm font-medium text-gray-900 mb-1 truncate">${lead.name}</h4>
                
                ${value ? `<div class="text-xs font-bold text-gray-700 mb-2">${value}</div>` : ''}

                <div class="flex items-center justify-between text-xs text-gray-500 mt-2 pt-2 border-t border-gray-100">
                    <span class="truncate max-w-[120px]" title="${lead.owner_email || 'Unassigned'}">
                         ${lead.owner_email ? lead.owner_email.split('@')[0] : 'Unassigned'}
                    </span>
                    <span>${new Date(lead.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}</span>
                </div>
            </div>
        `;
    },

    allowDrop(ev) {
        ev.preventDefault();
        ev.currentTarget.classList.add('bg-gray-200');
    },

    handleDragStart(ev, leadId) {
        ev.dataTransfer.setData('text/plain', leadId);
        ev.dataTransfer.effectAllowed = 'move';
    },

    async handleDrop(ev, newStage) {
        ev.preventDefault();
        ev.currentTarget.classList.remove('bg-gray-200');
        const leadId = ev.dataTransfer.getData('text/plain');

        if (!leadId) return;

        try {
            const res = await this.api('/update_stage.php', 'POST', {
                id: leadId,
                status: newStage
            });

            if (res.success) {
                await this.loadKanban();
                lucide.createIcons();
            } else {
                alert('Failed to move lead: ' + (res.error || 'Unknown error'));
            }
        } catch (e) {
            console.error(e);
            alert('Network error moving lead');
        }
    }
});

window.App = App;
