/**
 * Partner Program module — backed by /api/partners/*.php
 */

const App = window.App || {};

Object.assign(App, {

    _partnersView: 'list',

    async loadPartners() {
        this._partnersView = this._partnersView || 'list';
        const appContent = document.getElementById('appContent');
        if (!appContent) return;

        appContent.innerHTML = `
            <div class="p-6">
                <div class="flex items-center justify-center py-20">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>
            </div>`;

        const result = await this.api('/partners/list.php');
        const partners = (result && result.success) ? result.partners : [];
        this._renderPartnersPage(partners);
    },

    _renderPartnersPage(partners) {
        const appContent = document.getElementById('appContent');
        if (!appContent) return;

        appContent.innerHTML = `
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Partners</h2>
                        <p class="text-sm text-gray-500 mt-0.5">${partners.length} partner${partners.length !== 1 ? 's' : ''}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center rounded-lg border border-gray-200 bg-white p-0.5 shadow-sm">
                            <button onclick="App._setPartnersView('list')"
                                class="p-1.5 rounded-md transition-colors ${this._partnersView === 'list' ? 'bg-gray-100 text-gray-900' : 'text-gray-400 hover:text-gray-600'}"
                                title="List view">
                                <i data-lucide="list" class="h-4 w-4"></i>
                            </button>
                            <button onclick="App._setPartnersView('grid')"
                                class="p-1.5 rounded-md transition-colors ${this._partnersView === 'grid' ? 'bg-gray-100 text-gray-900' : 'text-gray-400 hover:text-gray-600'}"
                                title="Grid view">
                                <i data-lucide="layout-grid" class="h-4 w-4"></i>
                            </button>
                        </div>
                        <button onclick="App.openAddPartnerModal()"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white shadow-sm transition-colors"
                            style="background: var(--color-primary);"
                            onmouseover="this.style.background='var(--color-primary-dark)'"
                            onmouseout="this.style.background='var(--color-primary)'">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            Add Partner
                        </button>
                    </div>
                </div>
                <div id="partnersContent">
                    ${this._buildPartnersContent(partners)}
                </div>
            </div>`;
        if (window.lucide) window.lucide.createIcons();
    },

    _setPartnersView(view) {
        this._partnersView = view;
        this.loadPartners();
    },

    _buildPartnersContent(partners) {
        if (!partners.length) {
            return `
                <div class="text-center py-20">
                    <div class="mx-auto w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mb-4">
                        <i data-lucide="handshake" class="h-8 w-8 text-indigo-400"></i>
                    </div>
                    <h3 class="text-base font-medium text-gray-900 mb-1">No partners yet</h3>
                    <p class="text-sm text-gray-500 mb-5">Add your first partner to start tracking referrals.</p>
                    <button onclick="App.openAddPartnerModal()"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white shadow-sm"
                        style="background: var(--color-primary);">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Add Partner
                    </button>
                </div>`;
        }
        return this._partnersView === 'list'
            ? this._buildListView(partners)
            : this._buildGridView(partners);
    },

    _buildListView(partners) {
        const rows = partners.map(p => {
            const refs = p.referrals || [];
            const refRow = refs.length > 0 ? `
                <tr class="bg-indigo-50/30">
                    <td colspan="7" class="px-6 py-2">
                        <div class="flex flex-wrap gap-2">
                            ${refs.map(r => `
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-white border border-indigo-100 text-gray-600">
                                    <i data-lucide="user" class="h-3 w-3 text-indigo-400"></i>
                                    ${this.escapeHtml(r.leadName || r.ref_name || '')}
                                </span>`).join('')}
                        </div>
                    </td>
                </tr>` : '';

            return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0"
                                style="background: var(--color-primary)">
                                ${this.escapeHtml((p.name || '?').charAt(0).toUpperCase())}
                            </div>
                            <span class="text-sm font-medium text-gray-900">${this.escapeHtml(p.name)}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${this.escapeHtml(p.company || '-')}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        ${p.email ? `<a href="mailto:${this.escapeHtml(p.email)}" class="text-indigo-600 hover:underline">${this.escapeHtml(p.email)}</a>` : '<span class="text-gray-400">-</span>'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${this.escapeHtml(p.phone || '-')}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        ${p.website
                            ? `<a href="${this.escapeHtml(p.website)}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">${this.escapeHtml(p.website.replace(/^https?:\/\//, ''))}</a>`
                            : '<span class="text-gray-400">-</span>'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                            ${refs.length}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="App.openAddReferralModal(${p.id})"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-indigo-200 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors">
                                <i data-lucide="git-branch" class="h-3.5 w-3.5"></i>
                                Add Referral
                            </button>
                            <button onclick="App._deletePartner(${p.id})"
                                class="p-1.5 text-gray-300 hover:text-red-500 rounded-md transition-colors" title="Remove partner">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                ${refRow}`;
        }).join('');

        return `
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Website</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referrals</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">${rows}</tbody>
                </table>
            </div>`;
    },

    _buildGridView(partners) {
        const cards = partners.map(p => {
            const refs = p.referrals || [];
            return `
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col gap-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-11 w-11 rounded-full flex items-center justify-center text-white text-base font-semibold flex-shrink-0"
                                style="background: var(--color-primary)">
                                ${this.escapeHtml((p.name || '?').charAt(0).toUpperCase())}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 leading-tight truncate">${this.escapeHtml(p.name)}</p>
                                <p class="text-xs text-gray-400 mt-0.5 truncate">${this.escapeHtml(p.company || '')}</p>
                            </div>
                        </div>
                        <button onclick="App._deletePartner(${p.id})"
                            class="p-1 text-gray-300 hover:text-red-400 rounded transition-colors flex-shrink-0" title="Remove">
                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                        </button>
                    </div>

                    <div class="space-y-1.5 text-xs">
                        ${p.email ? `
                        <div class="flex items-center gap-2 text-gray-500">
                            <i data-lucide="mail" class="h-3.5 w-3.5 text-gray-400 flex-shrink-0"></i>
                            <a href="mailto:${this.escapeHtml(p.email)}" class="text-indigo-600 hover:underline truncate">${this.escapeHtml(p.email)}</a>
                        </div>` : ''}
                        ${p.phone ? `
                        <div class="flex items-center gap-2 text-gray-500">
                            <i data-lucide="phone" class="h-3.5 w-3.5 text-gray-400 flex-shrink-0"></i>
                            <span>${this.escapeHtml(p.phone)}</span>
                        </div>` : ''}
                        ${p.website ? `
                        <div class="flex items-center gap-2 text-gray-500">
                            <i data-lucide="globe" class="h-3.5 w-3.5 text-gray-400 flex-shrink-0"></i>
                            <a href="${this.escapeHtml(p.website)}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline truncate">${this.escapeHtml(p.website.replace(/^https?:\/\//, ''))}</a>
                        </div>` : ''}
                    </div>

                    <div class="flex-1">
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Referrals</p>
                        ${refs.length === 0
                            ? `<p class="text-xs text-gray-400 italic">No referrals yet</p>`
                            : `<div class="flex flex-wrap gap-1.5">
                                ${refs.slice(0, 4).map(r => `
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        ${this.escapeHtml(r.leadName || r.ref_name || '')}
                                    </span>`).join('')}
                                ${refs.length > 4 ? `<span class="text-xs text-gray-400 self-center">+${refs.length - 4} more</span>` : ''}
                               </div>`}
                    </div>

                    <button onclick="App.openAddReferralModal(${p.id})"
                        class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-xs font-medium rounded-lg border border-indigo-200 text-indigo-600 hover:bg-indigo-50 transition-colors">
                        <i data-lucide="git-branch" class="h-3.5 w-3.5"></i>
                        Add Referral
                    </button>
                </div>`;
        }).join('');

        const addCard = `
            <button onclick="App.openAddPartnerModal()"
                class="rounded-xl border-2 border-dashed border-gray-200 p-5 flex flex-col items-center justify-center gap-2 text-gray-400 hover:border-indigo-300 hover:text-indigo-500 hover:bg-indigo-50/30 transition-all min-h-[220px] bg-gray-50">
                <i data-lucide="plus-circle" class="h-8 w-8"></i>
                <span class="text-sm font-medium">Add Partner</span>
            </button>`;

        return `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">${cards}${addCard}</div>`;
    },

    openAddPartnerModal() {
        const el = document.getElementById('partnerAddModal');
        if (el) el.remove();

        const modal = document.createElement('div');
        modal.id = 'partnerAddModal';
        modal.className = 'fixed inset-0 z-[2000] overflow-y-auto';
        modal.innerHTML = `
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" onclick="document.getElementById('partnerAddModal').remove()"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-gray-900">Add Partner</h3>
                        <button onclick="document.getElementById('partnerAddModal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>
                    <form id="partnerAddForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" id="partnerName" required placeholder="Full name"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                            <input type="text" id="partnerCompany" placeholder="Company name"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" id="partnerEmail" placeholder="email@example.com"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" id="partnerPhone" placeholder="+1 555-0100"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                            <input type="url" id="partnerWebsite" placeholder="https://example.com"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                        </div>
                        <div id="partnerAddError" class="hidden text-sm text-red-600"></div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" onclick="document.getElementById('partnerAddModal').remove()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" id="partnerAddSubmit"
                                class="px-4 py-2 text-sm font-medium text-white rounded-lg shadow-sm transition-colors"
                                style="background: var(--color-primary);">
                                Add Partner
                            </button>
                        </div>
                    </form>
                </div>
            </div>`;
        document.body.appendChild(modal);

        document.getElementById('partnerAddForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('partnerAddSubmit');
            const errEl = document.getElementById('partnerAddError');
            btn.disabled = true;
            btn.textContent = 'Saving…';
            errEl.classList.add('hidden');

            const result = await this.api('/partners/create.php', 'POST', {
                name:    document.getElementById('partnerName').value.trim(),
                company: document.getElementById('partnerCompany').value.trim(),
                email:   document.getElementById('partnerEmail').value.trim(),
                phone:   document.getElementById('partnerPhone').value.trim(),
                website: document.getElementById('partnerWebsite').value.trim(),
            });

            if (result && result.success) {
                document.getElementById('partnerAddModal').remove();
                this.showToast('Partner added!', 'success');
                this.loadPartners();
            } else {
                errEl.textContent = result?.error || 'Failed to add partner';
                errEl.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = 'Add Partner';
            }
        });

        if (window.lucide) window.lucide.createIcons();
        setTimeout(() => document.getElementById('partnerName')?.focus(), 50);
    },

    async _deletePartner(id) {
        if (!confirm('Remove this partner and all their referrals?')) return;
        const result = await this.api('/partners/delete.php', 'POST', { partner_id: id });
        if (result && result.success) {
            this.showToast('Partner removed', 'success');
            this.loadPartners();
        } else {
            this.showToast(result?.error || 'Failed to remove partner', 'error');
        }
    },

    async openAddReferralModal(partnerId) {
        const el = document.getElementById('partnerReferralModal');
        if (el) el.remove();

        // Fetch both in parallel
        const [leadsRes, partnerRes] = await Promise.all([
            this.api('/leads/list.php?limit=500'),
            this.api('/partners/list.php'),
        ]);
        let leads = (leadsRes && leadsRes.data) ? leadsRes.data : [];
        const partners = (partnerRes && partnerRes.success) ? partnerRes.partners : [];

        // Build exclusion sets: leads that are already partners (by email) or already referrals of this partner
        const partnerEmailSet = new Set(
            partners.filter(p => p.email).map(p => p.email.toLowerCase())
        );
        const thisPartner = partners.find(p => p.id === partnerId);
        const alreadyReferredIds = new Set(
            (thisPartner?.referrals || []).filter(r => r.leadId).map(r => Number(r.leadId))
        );

        leads = leads.filter(l =>
            !alreadyReferredIds.has(Number(l.id)) &&
            !(l.email && partnerEmailSet.has(l.email.toLowerCase()))
        );

        const leadRows = leads.length === 0
            ? `<p class="px-4 py-8 text-center text-sm text-gray-400">No available leads</p>`
            : leads.map(l => `
                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer ref-lead-row"
                    data-name="${this.escapeHtml((l.name || '').toLowerCase())}">
                    <input type="radio" name="refLeadId" value="${l.id}"
                        data-name="${this.escapeHtml(l.name || 'Unknown')}"
                        class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">${this.escapeHtml(l.name || 'Unknown')}</p>
                        <p class="text-xs text-gray-400 truncate">${this.escapeHtml(l.email || '')}${l.company ? ' · ' + this.escapeHtml(l.company) : ''}</p>
                    </div>
                </label>`).join('');

        const modal = document.createElement('div');
        modal.id = 'partnerReferralModal';
        modal.className = 'fixed inset-0 z-[2000] overflow-y-auto';
        modal.innerHTML = `
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" onclick="document.getElementById('partnerReferralModal').remove()"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-semibold text-gray-900">Add Referral</h3>
                        <button onclick="document.getElementById('partnerReferralModal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>

                    <div class="flex gap-1 p-1 bg-gray-100 rounded-lg mb-5">
                        <button id="refTabExisting" onclick="App._switchReferralTab('existing')"
                            class="flex-1 py-2 text-sm font-medium rounded-md transition-colors bg-white text-gray-900 shadow-sm">
                            <i data-lucide="users" class="h-4 w-4 inline mr-1.5 -mt-0.5"></i>Existing Lead
                        </button>
                        <button id="refTabNew" onclick="App._switchReferralTab('new')"
                            class="flex-1 py-2 text-sm font-medium rounded-md transition-colors text-gray-500 hover:text-gray-700">
                            <i data-lucide="user-plus" class="h-4 w-4 inline mr-1.5 -mt-0.5"></i>New Lead
                        </button>
                    </div>

                    <div id="refPanelExisting">
                        <input type="text" id="refLeadSearch" placeholder="Search leads…"
                            oninput="App._filterReferralLeads(this.value)"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 mb-3">
                        <div class="max-h-56 overflow-y-auto rounded-lg border border-gray-100 divide-y divide-gray-50">
                            ${leadRows}
                        </div>
                        <div id="refExistingError" class="hidden mt-2 text-sm text-red-600"></div>
                        <div class="flex justify-end gap-3 mt-4">
                            <button onclick="document.getElementById('partnerReferralModal').remove()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button id="refExistingSubmit" onclick="App._submitExistingReferral(${partnerId})"
                                class="px-4 py-2 text-sm font-medium text-white rounded-lg shadow-sm"
                                style="background: var(--color-primary);">
                                Add Referral
                            </button>
                        </div>
                    </div>

                    <div id="refPanelNew" class="hidden">
                        <div class="flex flex-col items-center justify-center py-8 gap-3 text-center">
                            <div class="w-14 h-14 rounded-full bg-indigo-50 flex items-center justify-center">
                                <i data-lucide="user-plus" class="h-7 w-7 text-indigo-500"></i>
                            </div>
                            <p class="text-sm text-gray-500 max-w-xs">
                                Fill in the full lead form — including all your custom fields — then the lead will be linked as a referral automatically.
                            </p>
                            <button onclick="App._openLeadFormForReferral(${partnerId})"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white rounded-lg shadow-sm"
                                style="background: var(--color-primary);">
                                <i data-lucide="plus" class="h-4 w-4"></i>
                                Open Lead Form
                            </button>
                        </div>
                        <div class="flex justify-end mt-2">
                            <button onclick="document.getElementById('partnerReferralModal').remove()"
                                class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);
        if (window.lucide) window.lucide.createIcons();
    },

    _openLeadFormForReferral(partnerId) {
        // Store pending referral so saveLead can auto-link after creation
        this._pendingReferralPartnerId = partnerId;
        // Close the referral picker and open the standard create lead modal
        const refModal = document.getElementById('partnerReferralModal');
        if (refModal) refModal.remove();
        if (typeof this.openCreateModal === 'function') {
            this.openCreateModal();
        }
    },

    _switchReferralTab(tab) {
        const tabEx  = document.getElementById('refTabExisting');
        const tabNew = document.getElementById('refTabNew');
        const panEx  = document.getElementById('refPanelExisting');
        const panNew = document.getElementById('refPanelNew');
        if (!tabEx) return;
        const active   = 'flex-1 py-2 text-sm font-medium rounded-md transition-colors bg-white text-gray-900 shadow-sm';
        const inactive = 'flex-1 py-2 text-sm font-medium rounded-md transition-colors text-gray-500 hover:text-gray-700';
        if (tab === 'existing') {
            tabEx.className = active;  tabNew.className = inactive;
            panEx.classList.remove('hidden'); panNew.classList.add('hidden');
        } else {
            tabNew.className = active; tabEx.className = inactive;
            panNew.classList.remove('hidden'); panEx.classList.add('hidden');
        }
    },

    _filterReferralLeads(q) {
        const lower = q.toLowerCase().trim();
        document.querySelectorAll('.ref-lead-row').forEach(row => {
            row.style.display = !lower || (row.dataset.name || '').includes(lower) ? '' : 'none';
        });
    },

    async _submitExistingReferral(partnerId) {
        const sel = document.querySelector('input[name="refLeadId"]:checked');
        const errEl = document.getElementById('refExistingError');
        if (!sel) { errEl.textContent = 'Please select a lead.'; errEl.classList.remove('hidden'); return; }

        const btn = document.getElementById('refExistingSubmit');
        btn.disabled = true; btn.textContent = 'Saving…';

        const result = await this.api('/partners/add_referral.php', 'POST', {
            partner_id: partnerId,
            lead_id: parseInt(sel.value),
            type: 'existing',
        });

        if (result && result.success) {
            document.getElementById('partnerReferralModal').remove();
            this.showToast('Referral added!', 'success');
            this.loadPartners();
        } else {
            errEl.textContent = result?.error || 'Failed to add referral';
            errEl.classList.remove('hidden');
            btn.disabled = false; btn.textContent = 'Add Referral';
        }
    },

    async openAddToPartnerModal(leadId, leadName) {
        const el = document.getElementById('addToPartnerModal');
        if (el) el.remove();

        // Fetch partners and lead details in parallel
        const [partnerRes, leadRes] = await Promise.all([
            this.api('/partners/list.php'),
            this.api(`/leads/get.php?id=${leadId}`),
        ]);
        const partners = (partnerRes && partnerRes.success) ? partnerRes.partners : [];
        const lead     = (leadRes && leadRes.id) ? leadRes : {};
        this._atpPartnersCache = partners;
        this._atpLeadId   = leadId;
        this._atpLeadName = leadName;

        const partnerRows = partners.length === 0
            ? `<p class="text-center text-sm text-gray-400 py-6">No partners yet. Add this lead as one above!</p>`
            : partners.map(p => `
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/40 cursor-pointer transition-colors">
                    <input type="radio" name="atpPartnerId" value="${p.id}"
                        class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <div>
                        <p class="text-sm font-medium text-gray-900">${this.escapeHtml(p.name)}</p>
                        ${p.company ? `<p class="text-xs text-gray-400">${this.escapeHtml(p.company)}</p>` : ''}
                    </div>
                </label>`).join('');

        const modal = document.createElement('div');
        modal.id = 'addToPartnerModal';
        modal.className = 'fixed inset-0 z-[2000] overflow-y-auto';
        modal.innerHTML = `
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" onclick="document.getElementById('addToPartnerModal').remove()"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="min-w-0 pr-4">
                            <h3 class="text-base font-semibold text-gray-900">${this.escapeHtml(leadName)}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Add to the partner program</p>
                        </div>
                        <button onclick="document.getElementById('addToPartnerModal').remove()" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>

                    <div class="flex gap-1 p-1 bg-gray-100 rounded-lg mb-5">
                        <button id="atpTabPartner" onclick="App._switchAtpTab('partner')"
                            class="flex-1 py-2 text-sm font-medium rounded-md transition-colors bg-white text-gray-900 shadow-sm">
                            <i data-lucide="handshake" class="h-4 w-4 inline mr-1.5 -mt-0.5"></i>Add as Partner
                        </button>
                        <button id="atpTabReferral" onclick="App._switchAtpTab('referral')"
                            class="flex-1 py-2 text-sm font-medium rounded-md transition-colors text-gray-500 hover:text-gray-700">
                            <i data-lucide="git-branch" class="h-4 w-4 inline mr-1.5 -mt-0.5"></i>Add as Referral
                        </button>
                    </div>

                    <!-- Add as Partner panel -->
                    <div id="atpPanelPartner">
                        <p class="text-xs text-gray-500 mb-3">Create <strong>${this.escapeHtml(leadName)}</strong> as a new partner. Fields are pre-filled from the lead.</p>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="atpPartnerName" value="${this.escapeHtml(lead.name || leadName)}"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Company</label>
                                    <input type="text" id="atpPartnerCompany" value="${this.escapeHtml(lead.company || '')}"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" id="atpPartnerEmail" value="${this.escapeHtml(lead.email || '')}"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Phone</label>
                                    <input type="text" id="atpPartnerPhone" value="${this.escapeHtml(lead.phone || '')}"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Website</label>
                                <input type="url" id="atpPartnerWebsite" value="${this.escapeHtml(lead.website || '')}"
                                    placeholder="https://example.com"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                            </div>
                        </div>
                        <div id="atpPartnerError" class="hidden mt-2 text-sm text-red-600"></div>
                        <div class="flex justify-end gap-3 mt-4">
                            <button onclick="document.getElementById('addToPartnerModal').remove()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button id="atpPartnerSubmit" onclick="App._submitAddAsPartner()"
                                class="px-4 py-2 text-sm font-medium text-white rounded-lg shadow-sm"
                                style="background: var(--color-primary);">
                                Create Partner
                            </button>
                        </div>
                    </div>

                    <!-- Add as Referral panel -->
                    <div id="atpPanelReferral" class="hidden">
                        <p class="text-xs text-gray-500 mb-3">Link <strong>${this.escapeHtml(leadName)}</strong> as a referral under an existing partner.</p>
                        <div class="space-y-2 max-h-56 overflow-y-auto rounded-lg border border-gray-100 divide-y divide-gray-50 mb-3">
                            ${partnerRows}
                        </div>
                        <div id="atpError" class="hidden mb-3 text-sm text-red-600"></div>
                        <div class="flex justify-end gap-3">
                            <button onclick="document.getElementById('addToPartnerModal').remove()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button id="atpSubmitBtn" onclick="App._submitAddToPartner()"
                                class="px-4 py-2 text-sm font-medium text-white rounded-lg shadow-sm"
                                style="background: var(--color-primary);">
                                Link as Referral
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);
        if (window.lucide) window.lucide.createIcons();
    },

    async ensurePartnerBadges() {
        const result = await this.api('/partners/list.php');
        if (!result || !result.success) return;
        this._partnerEmailSet = new Set(
            result.partners.filter(p => p.email).map(p => p.email.toLowerCase())
        );
        this._referralLeadIdSet = new Set();
        result.partners.forEach(p => {
            (p.referrals || []).forEach(r => {
                if (r.leadId) this._referralLeadIdSet.add(Number(r.leadId));
            });
        });
    },

    _switchAtpTab(tab) {
        const tabP = document.getElementById('atpTabPartner');
        const tabR = document.getElementById('atpTabReferral');
        const panP = document.getElementById('atpPanelPartner');
        const panR = document.getElementById('atpPanelReferral');
        if (!tabP) return;
        const active   = 'flex-1 py-2 text-sm font-medium rounded-md transition-colors bg-white text-gray-900 shadow-sm';
        const inactive = 'flex-1 py-2 text-sm font-medium rounded-md transition-colors text-gray-500 hover:text-gray-700';
        if (tab === 'partner') {
            tabP.className = active;  tabR.className = inactive;
            panP.classList.remove('hidden'); panR.classList.add('hidden');
        } else {
            tabR.className = active;  tabP.className = inactive;
            panR.classList.remove('hidden'); panP.classList.add('hidden');
        }
    },

    async _submitAddAsPartner() {
        const name  = (document.getElementById('atpPartnerName')?.value || '').trim();
        const email = (document.getElementById('atpPartnerEmail')?.value || '').trim().toLowerCase();
        const errEl = document.getElementById('atpPartnerError');
        if (!name) { errEl.textContent = 'Name is required.'; errEl.classList.remove('hidden'); return; }

        // Duplicate check: same email (strong) or same name (weak)
        const existing = (this._atpPartnersCache || []).find(p =>
            (email && p.email && p.email.toLowerCase() === email) ||
            (p.name && p.name.toLowerCase() === name.toLowerCase())
        );
        if (existing) {
            errEl.textContent = `"${existing.name}" is already a partner.`;
            errEl.classList.remove('hidden');
            return;
        }

        const btn = document.getElementById('atpPartnerSubmit');
        btn.disabled = true; btn.textContent = 'Saving…';

        const result = await this.api('/partners/create.php', 'POST', {
            name,
            company: document.getElementById('atpPartnerCompany')?.value.trim() || '',
            email:   document.getElementById('atpPartnerEmail')?.value.trim() || '',
            phone:   document.getElementById('atpPartnerPhone')?.value.trim() || '',
            website: document.getElementById('atpPartnerWebsite')?.value.trim() || '',
        });

        if (result && result.success) {
            document.getElementById('addToPartnerModal').remove();
            this.showToast(`${name} added as a partner!`, 'success');
        } else {
            errEl.textContent = result?.error || 'Failed to create partner';
            errEl.classList.remove('hidden');
            btn.disabled = false; btn.textContent = 'Create Partner';
        }
    },

    async _submitAddToPartner() {
        const leadId   = this._atpLeadId;
        const leadName = this._atpLeadName;
        const sel   = document.querySelector('input[name="atpPartnerId"]:checked');
        const errEl = document.getElementById('atpError');
        if (!sel) { errEl.textContent = 'Please select a partner.'; errEl.classList.remove('hidden'); return; }

        const btn = document.getElementById('atpSubmitBtn');
        btn.disabled = true; btn.textContent = 'Saving…';

        const result = await this.api('/partners/add_referral.php', 'POST', {
            partner_id: parseInt(sel.value),
            lead_id:    leadId,
            type:       'existing',
        });

        if (result && result.success) {
            document.getElementById('addToPartnerModal').remove();
            this.showToast(`${leadName} linked as referral`, 'success');
        } else {
            errEl.textContent = result?.error || 'Failed to link as referral';
            errEl.classList.remove('hidden');
            btn.disabled = false; btn.textContent = 'Link as Referral';
        }
    },

});

window.App = App;
