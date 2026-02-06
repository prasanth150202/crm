/**
 * Kanban board rendering and drag handling.
 */

const App = window.App || {};

Object.assign(App, {
    async loadKanban() {
        document.getElementById('appContent').innerHTML = '<div class="text-center py-10"><p class="text-gray-500">Loading Pipeline...</p></div>';

        const search = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        const source = document.getElementById('sourceFilter') ? document.getElementById('sourceFilter').value : '';
        const limit = 500;

        let url = `/leads/list.php?limit=${limit}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (source) url += `&source=${encodeURIComponent(source)}`;

        try {
            const data = await this.api(url);
            if (data && data.data) {
                this.renderKanbanBoard(data.data);
            } else {
                document.getElementById('appContent').innerHTML = '<p class="p-4 text-red-500">Failed to load pipeline.</p>';
            }
        } catch (e) {
            console.error(e);
            document.getElementById('appContent').innerHTML = '<p class="p-4 text-red-500">Error loading pipeline.</p>';
        }
    },

    renderKanbanBoard(leads) {
        const container = document.getElementById('appContent');
        const stages = [
            { id: 'new', name: 'New', color: 'border-blue-400' },
            { id: 'contacted', name: 'Contacted', color: 'border-yellow-400' },
            { id: 'qualified', name: 'Qualified', color: 'border-green-400' },
            { id: 'won', name: 'Won', color: 'border-indigo-400' },
            { id: 'lost', name: 'Lost', color: 'border-red-400' }
        ];

        const grouped = {};
        stages.forEach(s => grouped[s.id] = []);
        leads.forEach(l => {
            const savedStage = l.stage_id ? l.stage_id.toLowerCase() : 'new';
            if (grouped[savedStage]) {
                grouped[savedStage].push(l);
            } else {
                if (!grouped['new']) grouped['new'] = [];
                grouped['new'].push(l);
            }
        });

        let html = `<div class="flex overflow-x-auto pb-4 space-x-4 h-[calc(100vh-200px)] min-w-full">`;

        stages.forEach(stage => {
            const stageLeads = grouped[stage.id] || [];
            const totalValue = stageLeads.reduce((sum, l) => sum + parseFloat(l.lead_value || 0), 0);
            const formattedValue = totalValue.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });

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

        html += `</div>`;
        container.innerHTML = html;
        lucide.createIcons();
    },

    renderKanbanCard(lead) {
        const value = lead.lead_value ? parseFloat(lead.lead_value).toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }) : '';

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
