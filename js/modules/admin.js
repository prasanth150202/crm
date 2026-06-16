/**
 * Admin Module — Super-admin control panel.
 * Tabs: Subscription Plans | Organizations
 */

const Admin = {
    currentTab: 'plans',
    _featureKnobs: null,
    _plans: [],
    _orgs: [],
    _editingPlanId: null,
    _editingSubId: null,
    _editingOrgId: null,

    // ── Entry point ──────────────────────────────────────────────────────────

    init() {
        const isSuperAdmin = window.AppData &&
            (window.AppData.is_super_admin ||
             (window.AppData.user && window.AppData.user.is_super_admin));
        if (!isSuperAdmin) {
            document.getElementById('appContent').innerHTML =
                '<div class="p-10 text-center text-red-500 font-semibold">Access denied — super admin only.</div>';
            return;
        }
        this.renderShell();
        this.switchTab('plans');
    },

    renderShell() {
        document.getElementById('appContent').innerHTML = `
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
                        <p class="text-sm text-gray-500 mt-0.5">Manage subscriptions and organizations</p>
                    </div>
                </div>
                <div class="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
                    <button id="adminTab-plans" onclick="Admin.switchTab('plans')"
                        class="admin-tab px-4 py-2 text-sm font-medium rounded-md transition-all">
                        <i data-lucide="credit-card" class="inline w-4 h-4 mr-1.5 -mt-0.5"></i>Subscription Plans
                    </button>
                    <button id="adminTab-organizations" onclick="Admin.switchTab('organizations')"
                        class="admin-tab px-4 py-2 text-sm font-medium rounded-md transition-all">
                        <i data-lucide="building-2" class="inline w-4 h-4 mr-1.5 -mt-0.5"></i>Organizations
                    </button>
                </div>
                <div id="adminContent"></div>
            </div>`;
        if (window.lucide) lucide.createIcons();
    },

    switchTab(tab) {
        this.currentTab = tab;
        document.querySelectorAll('.admin-tab').forEach(btn => {
            const active = btn.id === `adminTab-${tab}`;
            btn.className = 'admin-tab px-4 py-2 text-sm font-medium rounded-md transition-all ' +
                (active ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600 hover:text-gray-900');
        });
        const content = document.getElementById('adminContent');
        if (content) content.innerHTML = '<div class="flex justify-center py-10"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
        if (tab === 'plans') this.loadPlansTab();
        else if (tab === 'organizations') this.loadOrgsTab();
    },

    // ── API helpers ──────────────────────────────────────────────────────────

    async adminGet(path) {
        const sep = path.includes('?') ? '&' : '?';
        return App.api(`${path}${sep}_admin=1`, 'GET');
    },

    async adminPost(path, body) {
        return App.api(path, 'POST', body);
    },

    // ══════════════════════════════════════════════════════════════════════════
    // TAB 1: Subscription Plans
    // ══════════════════════════════════════════════════════════════════════════

    async loadPlansTab() {
        const [plansRes, subsRes] = await Promise.all([
            this.adminGet('/admin/subscriptions/plans/list.php'),
            this.adminGet('/admin/subscriptions/list.php'),
        ]);
        this._plans = (plansRes && plansRes.data && plansRes.data.plans) || [];
        this._subs  = (subsRes && subsRes.data && subsRes.data.subscriptions) || [];
        this.renderPlansTab(this._plans, this._subs);
    },

    renderPlansTab(plans, subs) {
        const content = document.getElementById('adminContent');
        content.innerHTML = `
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">Subscription Plans</h2>
                    <button onclick="Admin.openPlanModal()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                        <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>New Plan
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="adminPlanCards">
                    ${plans.length ? plans.map(p => this._planCard(p)).join('') : '<p class="text-gray-500 col-span-3">No plans yet.</p>'}
                </div>

                <div>
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-lg font-semibold text-gray-800">Subscriptions</h2>
                        <div class="flex gap-2">
                            ${['', 'trialing', 'active', 'halted', 'cancelled'].map(s =>
                                `<button onclick="Admin.filterSubs('${s}')" class="sub-filter-btn px-3 py-1 text-xs rounded-full border ${s === '' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'}">${s === '' ? 'All' : s.charAt(0).toUpperCase() + s.slice(1)}</button>`
                            ).join('')}
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    ${['Organization','Plan','Status','Billing','Period End','Actions','Invoice'].map(h =>
                                        `<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">${h}</th>`
                                    ).join('')}
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" id="adminSubsTable">
                                ${subs.length ? subs.map(s => this._subRow(s)).join('') : '<tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No subscriptions found.</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>`;
        if (window.lucide) lucide.createIcons();
    },

    _taxLabel(p) {
        const t = p.tax_treatment || 'none';
        if (t === 'exclusive') return '<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full ml-1">+18% GST</span>';
        if (t === 'inclusive') return '<span class="text-xs bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full ml-1">GST incl.</span>';
        return '';
    },

    _planCard(p) {
        const badge = p.is_active
            ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Active</span>'
            : '<span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Inactive</span>';
        const customBadge = p.is_custom
            ? '<span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full ml-1">Custom</span>'
            : '';
        const taxBadge     = this._taxLabel(p);
        const featureCount = Array.isArray(p.feature_keys) ? p.feature_keys.length : 0;
        const mPrice = parseFloat(p.base_price_monthly || 0);
        const yPrice = parseFloat(p.base_price_yearly  || 0);
        const taxStr  = (p.tax_treatment === 'exclusive') ? ' + 18% GST' : (p.tax_treatment === 'inclusive') ? ' (GST incl.)' : '';
        return `
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-col gap-3">
                <div>
                    <div class="flex items-center gap-1.5 flex-wrap mb-1">${badge}${customBadge}${taxBadge}</div>
                    <h3 class="text-base font-semibold text-gray-900">${App.escapeHtml(p.name)}</h3>
                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">${App.escapeHtml(p.description || '')}</p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                    <div><span class="font-medium">Monthly:</span> ${p.currency} ${mPrice.toLocaleString()}${taxStr}</div>
                    <div><span class="font-medium">Yearly:</span> ${p.currency} ${yPrice.toLocaleString()}${taxStr}</div>
                    <div><span class="font-medium">Users:</span> ${p.included_users}</div>
                    <div><span class="font-medium">Trial:</span> ${p.trial_days} days</div>
                    <div class="col-span-2"><span class="font-medium">Features:</span> ${featureCount} enabled</div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button onclick="Admin.openPlanModal(${p.id})" class="flex-1 text-xs px-3 py-1.5 border border-gray-300 rounded-lg hover:border-blue-400 hover:text-blue-600">Edit</button>
                    <button onclick="Admin.openGenerateLinkModal(${p.id})" class="flex-1 text-xs px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 inline-flex items-center justify-center gap-1">
                        <i data-lucide="link" class="w-3 h-3"></i>Generate Link
                    </button>
                    <button onclick="Admin.deletePlan(${p.id})" class="text-xs px-3 py-1.5 border border-red-200 text-red-500 rounded-lg hover:bg-red-50">Delete</button>
                </div>
            </div>`;
    },

    _subRow(s) {
        const statusColors = {
            active:    'bg-green-100 text-green-700',
            trialing:  'bg-blue-100 text-blue-700',
            halted:    'bg-orange-100 text-orange-700',
            cancelled: 'bg-gray-100 text-gray-500',
            past_due:  'bg-red-100 text-red-700',
            completed: 'bg-gray-100 text-gray-500',
        };
        const badge = `<span class="text-xs px-2 py-0.5 rounded-full ${statusColors[s.status] || 'bg-gray-100 text-gray-500'}">${s.status}</span>`;
        const periodEnd = s.current_period_end
            ? new Date(s.current_period_end).toLocaleDateString()
            : (s.trial_ends_at ? new Date(s.trial_ends_at).toLocaleDateString() : '—');
        return `
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">${App.escapeHtml(s.org_name || '')}</td>
                <td class="px-4 py-3 text-gray-600">${App.escapeHtml(s.plan_name || '')}</td>
                <td class="px-4 py-3">${badge}</td>
                <td class="px-4 py-3 text-gray-600 capitalize">${s.billing_interval}</td>
                <td class="px-4 py-3 text-gray-600">${periodEnd}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-2">
                        ${(s.status === 'active' || s.status === 'trialing') ? `<button onclick="Admin.deactivateSub(${s.id})" class="text-xs text-orange-500 hover:underline">Deactivate</button>` : ''}
                        ${s.status === 'halted' ? `<button onclick="Admin.reactivateSub(${s.id})" class="text-xs text-green-600 hover:underline">Reactivate</button>` : ''}
                        ${s.status !== 'cancelled' ? `<button onclick="Admin.cancelSub(${s.id})" class="text-xs text-red-500 hover:underline">Cancel</button>` : ''}
                    </div>
                </td>
                <td class="px-4 py-3">
                    <button onclick="Admin.openSubInvoiceModal(${s.id})"
                        class="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg hover:bg-emerald-100">
                        <i data-lucide="file-text" class="w-3 h-3"></i>Invoice
                    </button>
                </td>
            </tr>`;
    },

    async filterSubs(status) {
        document.querySelectorAll('.sub-filter-btn').forEach(btn => {
            const isActive = btn.textContent.trim().toLowerCase() === (status || 'all');
            btn.className = 'sub-filter-btn px-3 py-1 text-xs rounded-full border ' +
                (isActive ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400');
        });
        const url = status ? `/admin/subscriptions/list.php?status=${status}` : '/admin/subscriptions/list.php';
        const res  = await this.adminGet(url);
        const subs = (res && res.data && res.data.subscriptions) || [];
        this._subs = subs;
        const tbody = document.getElementById('adminSubsTable');
        if (tbody) {
            tbody.innerHTML = subs.length
                ? subs.map(s => this._subRow(s)).join('')
                : '<tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No subscriptions found.</td></tr>';
            if (window.lucide) lucide.createIcons();
        }
    },

    // ── Plan modal ────────────────────────────────────────────────────────────

    _formatKnobLabel(knob) {
        if (knob.knob_name && knob.knob_name.trim()) return App.escapeHtml(knob.knob_name.trim());
        return App.escapeHtml(
            knob.knob_key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        );
    },

    async openPlanModal(planId = null) {
        this._editingPlanId = planId;

        if (!this._featureKnobs || Object.keys(this._featureKnobs).length === 0) {
            const res = await this.adminGet('/admin/subscriptions/plans/features.php');
            if (res && res.data && res.data.feature_knobs) {
                this._featureKnobs = res.data.feature_knobs;
            } else {
                this._featureKnobs = null;
                App.showToast('Could not load feature list — ' + ((res && res.error) || 'unknown error'), 'error');
            }
        }

        let plan = { feature_keys: [], is_active: 1 };
        if (planId) {
            plan = this._plans.find(p => p.id == planId) || plan;
        }

        const knobs = this._featureKnobs || {};
        const featureMatrix = Object.keys(knobs).length
            ? Object.entries(knobs).map(([category, items]) => `
                <div class="mb-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1.5">${App.escapeHtml(category || 'General')}</p>
                    <div class="grid grid-cols-2 gap-1">
                        ${items.map(k => `
                            <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                <input type="checkbox" name="feature_keys" value="${k.knob_key}"
                                    ${(plan.feature_keys || []).includes(k.knob_key) ? 'checked' : ''}
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                ${this._formatKnobLabel(k)}
                            </label>`).join('')}
                    </div>
                </div>`).join('')
            : '<p class="text-xs text-red-500">Feature list unavailable. Try closing and reopening.</p>';

        App.showModal({
            id: 'adminPlanModal',
            size: 'max-w-3xl',
            title: planId ? 'Edit Plan' : 'Create New Plan',
            content: `
                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Plan Name *</label>
                            <input id="planName" type="text" value="${App.escapeHtml(plan.name || '')}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g. Starter">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="planDesc" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">${App.escapeHtml(plan.description || '')}</textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Monthly Price</label>
                                <input id="planPriceM" type="number" min="0" step="0.01" value="${plan.base_price_monthly || 0}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Yearly Price</label>
                                <input id="planPriceY" type="number" min="0" step="0.01" value="${plan.base_price_yearly || 0}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Included Users</label>
                                <input id="planUsers" type="number" min="1" value="${plan.included_users || 1}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Trial Days</label>
                                <input id="planTrial" type="number" min="0" value="${plan.trial_days !== undefined ? plan.trial_days : 14}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Add'l User/Mo</label>
                                <input id="planAddlM" type="number" min="0" step="0.01" value="${plan.price_per_additional_user_monthly || 0}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Add'l User/Yr</label>
                                <input id="planAddlY" type="number" min="0" step="0.01" value="${plan.price_per_additional_user_yearly || 0}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
                                <input id="planCurrency" type="text" maxlength="3" value="${App.escapeHtml(plan.currency || 'INR')}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase">
                            </div>
                            <div class="flex flex-col gap-2 pt-2">
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input id="planIsCustom" type="checkbox" ${plan.is_custom ? 'checked' : ''}
                                        class="rounded border-gray-300 text-blue-600">
                                    Custom plan
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input id="planIsActive" type="checkbox" ${(plan.is_active == null || plan.is_active == 1) ? 'checked' : ''}
                                        class="rounded border-gray-300 text-blue-600">
                                    Active
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tax Treatment <span class="text-gray-400">(18% GST)</span></label>
                            <select id="planTaxTreatment" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="none"      ${(plan.tax_treatment||'none') === 'none'      ? 'selected' : ''}>No Tax</option>
                                <option value="exclusive" ${(plan.tax_treatment||'none') === 'exclusive' ? 'selected' : ''}>Tax Exclusive — add 18% GST on checkout</option>
                                <option value="inclusive" ${(plan.tax_treatment||'none') === 'inclusive' ? 'selected' : ''}>Tax Inclusive — price already includes 18% GST</option>
                            </select>
                        </div>
                    </div>
                    <div class="border-l pl-5 max-h-80 overflow-y-auto">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-700">Features</p>
                            <button type="button" id="selectAllFeaturesBtn" onclick="Admin._toggleAllFeatures()"
                                class="text-xs text-blue-600 hover:text-blue-800 hover:underline">Select All</button>
                        </div>
                        ${featureMatrix}
                    </div>
                </div>`,
            footer: `
                <button onclick="Admin.savePlan()" class="ml-2 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                    ${planId ? 'Update Plan' : 'Create Plan'}
                </button>
                <button onclick="App.closeModal('adminPlanModal')" class="px-4 py-2 border border-gray-300 text-sm rounded-lg hover:bg-gray-50">Cancel</button>`
        });
    },

    async savePlan() {
        const checked = [...document.querySelectorAll('input[name="feature_keys"]:checked')].map(el => el.value);
        const body = {
            name:        document.getElementById('planName').value.trim(),
            description: document.getElementById('planDesc').value.trim(),
            base_price_monthly: parseFloat(document.getElementById('planPriceM').value) || 0,
            base_price_yearly:  parseFloat(document.getElementById('planPriceY').value) || 0,
            included_users:     parseInt(document.getElementById('planUsers').value) || 1,
            trial_days:         parseInt(document.getElementById('planTrial').value) || 0,
            price_per_additional_user_monthly: parseFloat(document.getElementById('planAddlM').value) || 0,
            price_per_additional_user_yearly:  parseFloat(document.getElementById('planAddlY').value) || 0,
            currency:    document.getElementById('planCurrency').value.trim().toUpperCase() || 'INR',
            is_custom:   document.getElementById('planIsCustom').checked ? 1 : 0,
            is_active:      document.getElementById('planIsActive') ? (document.getElementById('planIsActive').checked ? 1 : 0) : 1,
            tax_treatment:  document.getElementById('planTaxTreatment')?.value || 'none',
            feature_keys: checked,
        };
        if (!body.name) { App.showToast('Plan name is required', 'error'); return; }

        const endpoint = this._editingPlanId
            ? '/admin/subscriptions/plans/update.php'
            : '/admin/subscriptions/plans/create.php';
        if (this._editingPlanId) body.id = this._editingPlanId;

        const res = await this.adminPost(endpoint, body);
        if (res && res.success) {
            App.closeModal('adminPlanModal');
            App.showToast(this._editingPlanId ? 'Plan updated' : 'Plan created', 'success');
            this._featureKnobs = null;
            this.loadPlansTab();
        } else {
            App.showToast((res && res.error) || 'Failed to save plan', 'error');
        }
    },

    _toggleAllFeatures() {
        const boxes     = [...document.querySelectorAll('input[name="feature_keys"]')];
        const allChecked = boxes.every(cb => cb.checked);
        boxes.forEach(cb => { cb.checked = !allChecked; });
        const btn = document.getElementById('selectAllFeaturesBtn');
        if (btn) btn.textContent = allChecked ? 'Select All' : 'Deselect All';
    },

    async deletePlan(planId) {
        App.showConfirm('Deactivate this plan? Organizations currently on it won\'t be affected.', async () => {
            const res = await this.adminPost('/admin/subscriptions/plans/delete.php', { plan_id: planId });
            if (res && res.success) {
                App.showToast('Plan deactivated', 'success');
                this.loadPlansTab();
            } else {
                App.showToast((res && res.error) || 'Could not deactivate plan', 'error');
            }
        });
    },

    // ── Generate Subscription Link ────────────────────────────────────────────

    openGenerateLinkModal(planId) {
        const plan = this._plans.find(p => p.id == planId);
        if (!plan) { App.showToast('Plan not found', 'error'); return; }

        App.showModal({
            id: 'adminLinkModal',
            size: 'max-w-lg',
            title: `Generate Subscription Link — ${App.escapeHtml(plan.name)}`,
            content: `
                <div class="space-y-4">
                    <div class="bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3 text-sm text-indigo-700">
                        Share this link with your client. They will fill in their details and complete payment via Razorpay.
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Billing Interval</label>
                            <select id="linkBilling" onchange="Admin._updateLinkPrice(${plan.id})"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
                            <input id="linkCurrency" type="text" maxlength="3" value="${App.escapeHtml(plan.currency || 'INR')}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Amount <span class="text-gray-400">(editable)</span></label>
                            <input id="linkAmount" type="number" min="0" step="0.01"
                                value="${parseFloat(plan.base_price_monthly || 0)}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Trial Days</label>
                            <input id="linkTrial" type="number" min="0" value="${plan.trial_days || 0}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Link Expires In (days)</label>
                            <input id="linkExpiry" type="number" min="1" max="30" value="7"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Max Users Allowed</label>
                            <input id="linkMaxUsers" type="number" min="1" value="${plan.included_users || 1}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tax Treatment <span class="text-gray-400">(18% GST)</span></label>
                        <select id="linkTaxTreatment" onchange="Admin._updateTaxPreview()"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="none"      ${(plan.tax_treatment||'none') === 'none'      ? 'selected' : ''}>No Tax</option>
                            <option value="exclusive" ${(plan.tax_treatment||'none') === 'exclusive' ? 'selected' : ''}>Tax Exclusive — add 18% GST on checkout</option>
                            <option value="inclusive" ${(plan.tax_treatment||'none') === 'inclusive' ? 'selected' : ''}>Tax Inclusive — price already includes 18% GST</option>
                        </select>
                        <div id="taxPreview" class="mt-1.5 text-xs text-blue-700 bg-blue-50 rounded-lg px-3 py-2 hidden"></div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Message to Client <span class="text-gray-400">(optional)</span></label>
                        <textarea id="linkMessage" rows="2" placeholder="e.g. Welcome! Use this link to set up your account."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <!-- Generated link output -->
                    <div id="linkOutput" class="hidden">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Subscription Link</label>
                        <div class="flex gap-2">
                            <input id="linkUrl" type="text" readonly
                                class="flex-1 border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-700 font-mono">
                            <button onclick="Admin._copyLink()" class="px-3 py-2 bg-gray-800 text-white text-xs rounded-lg hover:bg-gray-700 flex items-center gap-1">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i>Copy
                            </button>
                        </div>
                        <p id="linkExpText" class="text-xs text-gray-400 mt-1"></p>
                    </div>
                </div>`,
            footer: `
                <button onclick="Admin.generateLink(${planId})" id="generateLinkBtn"
                    class="ml-2 px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2">
                    <i data-lucide="link" class="w-4 h-4"></i>Generate Link
                </button>
                <button onclick="App.closeModal('adminLinkModal')" class="px-4 py-2 border border-gray-300 text-sm rounded-lg hover:bg-gray-50">Close</button>`
        });

        setTimeout(() => {
            this._planForLink = plan;
            this._updateTaxPreview();
            if (window.lucide) lucide.createIcons();
        }, 50);
    },

    _updateLinkPrice(planId) {
        const plan = this._plans.find(p => p.id == planId);
        if (!plan) return;
        const billing  = document.getElementById('linkBilling')?.value;
        const amountEl = document.getElementById('linkAmount');
        if (amountEl) {
            amountEl.value = billing === 'yearly'
                ? (parseFloat(plan.base_price_yearly  || 0))
                : (parseFloat(plan.base_price_monthly || 0));
        }
        this._updateTaxPreview();
    },

    _updateTaxPreview() {
        const treatment = document.getElementById('linkTaxTreatment')?.value;
        const amount    = parseFloat(document.getElementById('linkAmount')?.value) || 0;
        const currency  = document.getElementById('linkCurrency')?.value?.trim() || 'INR';
        const preview   = document.getElementById('taxPreview');
        if (!preview) return;
        if (!treatment || treatment === 'none' || amount <= 0) {
            preview.classList.add('hidden');
            preview.textContent = '';
            return;
        }
        preview.classList.remove('hidden');
        const fmt = n => `${currency} ${n.toFixed(2)}`;
        if (treatment === 'exclusive') {
            const tax   = amount * 0.18;
            const total = amount + tax;
            preview.innerHTML = `Base: <strong>${fmt(amount)}</strong> + 18% GST: <strong>${fmt(tax)}</strong> = Client pays: <strong>${fmt(total)}</strong>`;
        } else {
            const tax = amount - (amount / 1.18);
            preview.innerHTML = `Client pays: <strong>${fmt(amount)}</strong> — includes GST: <strong>${fmt(tax)}</strong>`;
        }
    },

    async generateLink(planId) {
        const btn = document.getElementById('generateLinkBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Generating…'; }

        const body = {
            plan_id:       planId,
            billing:       document.getElementById('linkBilling').value,
            amount:        parseFloat(document.getElementById('linkAmount').value) || 0,
            currency:      document.getElementById('linkCurrency').value.trim().toUpperCase() || 'INR',
            trial_days:    parseInt(document.getElementById('linkTrial').value) || 0,
            expires_days:  parseInt(document.getElementById('linkExpiry').value) || 7,
            max_users:     parseInt(document.getElementById('linkMaxUsers').value) || 1,
            message:       document.getElementById('linkMessage').value.trim(),
            tax_treatment: document.getElementById('linkTaxTreatment')?.value || 'none',
        };

        const res = await this.adminPost('/admin/subscriptions/generate-link.php', body);
        if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="link" class="w-4 h-4"></i>Regenerate'; if (window.lucide) lucide.createIcons(); }

        if (res && res.success && res.data && res.data.url) {
            const output = document.getElementById('linkOutput');
            const urlEl  = document.getElementById('linkUrl');
            const expEl  = document.getElementById('linkExpText');
            if (output) output.classList.remove('hidden');
            if (urlEl)  urlEl.value = res.data.url;
            if (expEl && res.data.expires_at) expEl.textContent = `Expires: ${new Date(res.data.expires_at).toLocaleString()}`;
            if (window.lucide) lucide.createIcons();
        } else {
            App.showToast((res && res.error) || 'Failed to generate link', 'error');
        }
    },

    _copyLink() {
        const urlEl = document.getElementById('linkUrl');
        if (!urlEl) return;
        navigator.clipboard.writeText(urlEl.value)
            .then(() => App.showToast('Link copied to clipboard', 'success'))
            .catch(() => {
                urlEl.select();
                document.execCommand('copy');
                App.showToast('Link copied', 'success');
            });
    },

    // ── Subscription invoice ─────────────────────────────────────────────────

    openSubInvoiceModal(subId) {
        const s = (this._subs || []).find(x => x.id == subId);
        if (!s) { App.showToast('Subscription not found', 'error'); return; }

        const billing  = s.billing_interval || 'monthly';
        const price    = billing === 'yearly'
            ? parseFloat(s.base_price_yearly  || 0)
            : parseFloat(s.base_price_monthly || 0);
        const currency = s.plan_currency || 'INR';
        const taxTrmt  = s.tax_treatment || 'none';
        const taxRate  = 18;
        const taxAmt   = taxTrmt === 'exclusive' ? Math.round(price * taxRate) / 100 : (taxTrmt === 'inclusive' ? Math.round((price - price / 1.18) * 100) / 100 : 0);
        const totalAmt = taxTrmt === 'exclusive' ? price + taxAmt : price;
        const fmt      = n => `${currency} ${parseFloat(n).toFixed(2)}`;

        const purchaseDate   = s.trial_starts_at || s.created_at || new Date().toISOString();
        const renewalStart   = s.current_period_start;
        const renewalEnd     = s.current_period_end;

        const fmtDate = iso => iso ? new Date(iso).toLocaleDateString('en-IN', { day:'2-digit', month:'long', year:'numeric' }) : '—';

        const taxLabel = taxTrmt === 'exclusive' ? 'Tax Exclusive (GST added separately)'
            : taxTrmt === 'inclusive' ? 'Tax Inclusive (GST included in price)' : 'No Tax';

        const priceRowsExcl = `
            <tr><td style="padding:7px 8px 7px 0;color:#374151;font-size:0.85rem;">Base Price</td><td style="padding:7px 0;text-align:right;font-size:0.85rem;">${fmt(price)}</td></tr>
            <tr><td style="padding:7px 8px 7px 0;color:#374151;font-size:0.85rem;">GST (${taxRate}%)</td><td style="padding:7px 0;text-align:right;font-size:0.85rem;">${fmt(taxAmt)}</td></tr>`;
        const priceRowsIncl = `
            <tr><td style="padding:7px 8px 7px 0;color:#374151;font-size:0.85rem;">Price (incl. GST)</td><td style="padding:7px 0;text-align:right;font-size:0.85rem;">${fmt(price)}</td></tr>
            <tr><td style="padding:7px 8px 7px 0;color:#6b7280;font-size:0.8rem;">GST portion (${taxRate}%)</td><td style="padding:7px 0;text-align:right;color:#6b7280;font-size:0.8rem;">${fmt(taxAmt)}</td></tr>`;
        const priceRowsNone = `<tr><td style="padding:7px 8px 7px 0;color:#374151;font-size:0.85rem;">Subscription Price</td><td style="padding:7px 0;text-align:right;font-size:0.85rem;">${fmt(price)}</td></tr>`;
        const priceRows = taxTrmt === 'exclusive' ? priceRowsExcl : taxTrmt === 'inclusive' ? priceRowsIncl : priceRowsNone;

        const gstNote = taxTrmt === 'exclusive'
            ? `GST @ ${taxRate}% (Tax Exclusive) charged separately. GSTIN: 33BUWPR7004L2ZZ`
            : taxTrmt === 'inclusive'
            ? `Price is tax inclusive. GST @ ${taxRate}% = ${fmt(taxAmt)} included in total. GSTIN: 33BUWPR7004L2ZZ`
            : 'No GST applicable on this transaction.';

        const invoiceHtml = (type, invoiceDate, periodText, invoiceNo) => `
            <div style="padding:36px 40px;font-family:Inter,sans-serif;font-size:0.9rem;line-height:1.6;color:#111827;">
                <div style="border-bottom:2px solid #1e3a8a;padding-bottom:16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <h1 style="font-size:1.45rem;font-weight:800;color:#1e3a8a;margin:0 0 2px;">TAX INVOICE</h1>
                        <p style="color:#6b7280;font-size:0.78rem;margin:0;">${type} · Original for Recipient</p>
                    </div>
                    <div style="text-align:right;">
                        <p style="font-size:0.8rem;color:#374151;margin:0 0 2px;"><strong>Invoice No:</strong> ${invoiceNo}</p>
                        <p style="font-size:0.8rem;color:#374151;margin:0 0 2px;"><strong>Date:</strong> ${invoiceDate}</p>
                        <p style="font-size:0.8rem;color:#374151;margin:0;"><strong>Tax Type:</strong> ${taxLabel}</p>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:18px;">
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                        <p style="font-size:0.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 7px;">Sold By</p>
                        <p style="font-weight:700;color:#111827;font-size:0.88rem;margin:0 0 2px;">Digifyce</p>
                        <p style="color:#374151;font-size:0.78rem;margin:0 0 1px;">No. 01A, New No. 200, Old No. 398</p>
                        <p style="color:#374151;font-size:0.78rem;margin:0 0 1px;">SLV DS Centre, Barathiyar Road</p>
                        <p style="color:#374151;font-size:0.78rem;margin:0 0 1px;">New Siddhapudur, Coimbatore – 641044</p>
                        <p style="color:#374151;font-size:0.78rem;margin:0 0 5px;">Tamil Nadu, India</p>
                        <p style="font-size:0.75rem;color:#374151;margin:0;"><strong>GSTIN:</strong> <span style="font-family:monospace;letter-spacing:0.03em;">33BUWPR7004L2ZZ</span></p>
                    </div>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                        <p style="font-size:0.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 7px;">Billed To</p>
                        <p style="font-weight:700;color:#111827;font-size:0.88rem;margin:0 0 2px;">${App.escapeHtml(s.org_name || '')}</p>
                        ${periodText ? `<p style="font-size:0.78rem;color:#374151;margin:0;"><strong>Period:</strong> ${periodText}</p>` : ''}
                    </div>
                </div>
                <table style="width:100%;border-collapse:collapse;margin-bottom:18px;font-size:0.83rem;">
                    <thead>
                        <tr style="background:#1e3a8a;">
                            <th style="padding:8px 12px;text-align:left;color:#fff;font-weight:600;font-size:0.75rem;text-transform:uppercase;">Description</th>
                            <th style="padding:8px 12px;text-align:center;color:#fff;font-weight:600;font-size:0.75rem;text-transform:uppercase;">Billing</th>
                            <th style="padding:8px 12px;text-align:right;color:#fff;font-weight:600;font-size:0.75rem;text-transform:uppercase;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                            <td style="padding:11px 12px;">
                                <p style="font-weight:600;color:#111827;margin:0 0 1px;">${App.escapeHtml(s.plan_name || '')} Subscription</p>
                                <p style="font-size:0.74rem;color:#6b7280;margin:0;">SaaS CRM Platform · ${type}</p>
                            </td>
                            <td style="padding:11px 12px;text-align:center;color:#374151;text-transform:capitalize;">${billing}</td>
                            <td style="padding:11px 12px;text-align:right;font-weight:500;">${fmt(price)}</td>
                        </tr>
                    </tbody>
                </table>
                <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                    <table style="width:260px;border-collapse:collapse;font-size:0.83rem;">
                        <tbody style="border-top:1px solid #e5e7eb;">${priceRows}</tbody>
                        <tfoot>
                            <tr style="border-top:2px solid #1e3a8a;">
                                <td style="padding:9px 8px 9px 0;font-weight:700;color:#111827;">Total</td>
                                <td style="padding:9px 0;text-align:right;font-weight:800;color:#1e3a8a;font-size:0.95rem;">${fmt(totalAmt)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:7px;padding:9px 13px;margin-bottom:13px;font-size:0.77rem;color:#0369a1;">
                    <strong>GST Note:</strong> ${gstNote}
                </div>
                <div style="border-top:1px solid #e5e7eb;padding-top:12px;text-align:center;">
                    <p style="font-size:0.72rem;color:#9ca3af;margin:0 0 2px;">This is a computer-generated invoice and does not require a signature.</p>
                    <p style="font-size:0.72rem;color:#9ca3af;margin:0;">Digifyce · GSTIN: 33BUWPR7004L2ZZ · Coimbatore, Tamil Nadu – 641044</p>
                </div>
            </div>`;

        const purchaseNo = 'INV-P-' + s.id;
        const renewalNo  = 'INV-R-' + s.id + '-' + Date.now();
        const periodStr  = (renewalStart && renewalEnd)
            ? `${fmtDate(renewalStart)} – ${fmtDate(renewalEnd)}`
            : '—';

        App.showModal({
            id: 'adminSubInvoiceModal',
            size: 'max-w-2xl',
            title: `Invoices — ${App.escapeHtml(s.org_name || '')}`,
            content: `
                <div class="overflow-hidden rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                ${['Type','Invoice #','Date / Period','Amount',''].map(h =>
                                    `<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">${h}</th>`
                                ).join('')}
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-1 rounded-full">
                                        <i data-lucide="file-text" class="w-3 h-3"></i>Purchase
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 font-mono text-xs">${purchaseNo}</td>
                                <td class="px-4 py-3 text-gray-600 text-xs">${fmtDate(purchaseDate)}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">${fmt(totalAmt)}${taxTrmt !== 'none' ? `<span class="text-xs text-gray-400 ml-1">(${taxTrmt === 'exclusive' ? '+GST' : 'incl.GST'})</span>` : ''}</td>
                                <td class="px-4 py-3 text-right">
                                    <button onclick="Admin._downloadSubInvoice('purchase',${s.id},this)"
                                        class="inline-flex items-center gap-1 text-xs px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        <i data-lucide="download" class="w-3 h-3"></i>Download
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-full">
                                        <i data-lucide="refresh-cw" class="w-3 h-3"></i>Renewal
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 font-mono text-xs">${renewalNo}</td>
                                <td class="px-4 py-3 text-gray-600 text-xs">${periodStr}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">${fmt(totalAmt)}${taxTrmt !== 'none' ? `<span class="text-xs text-gray-400 ml-1">(${taxTrmt === 'exclusive' ? '+GST' : 'incl.GST'})</span>` : ''}</td>
                                <td class="px-4 py-3 text-right">
                                    <button onclick="Admin._downloadSubInvoice('renewal',${s.id},this)"
                                        class="inline-flex items-center gap-1 text-xs px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                                        <i data-lucide="download" class="w-3 h-3"></i>Download
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>`,
        });

        // Stash for PDF generation
        this._invoiceCtx = { s, billing, price, currency, taxTrmt, taxRate, taxAmt, totalAmt, fmt,
            purchaseDate, renewalStart, renewalEnd, periodStr, taxLabel, priceRows, gstNote,
            purchaseNo, renewalNo, fmtDate, invoiceHtml };

        if (window.lucide) lucide.createIcons();
    },

    async _downloadSubInvoice(type, subId, btn) {
        const ctx = this._invoiceCtx;
        if (!ctx) return;
        const origText = btn.innerHTML;
        btn.innerHTML = '<span style="opacity:0.7">Generating…</span>';
        btn.disabled  = true;

        try {
            const isRenewal   = type === 'renewal';
            const invoiceDate = isRenewal
                ? ctx.fmtDate(ctx.renewalStart || new Date().toISOString())
                : ctx.fmtDate(ctx.purchaseDate);
            const periodText  = isRenewal ? ctx.periodStr : '';
            const invoiceNo   = isRenewal ? ctx.renewalNo : ctx.purchaseNo;
            const typeLabel   = isRenewal ? 'Monthly Renewal' : 'Initial Purchase';
            const htmlStr     = ctx.invoiceHtml(typeLabel, invoiceDate, periodText, invoiceNo);

            await html2pdf()
                .set({
                    margin:      [10, 10, 10, 10],
                    filename:    `${invoiceNo}.pdf`,
                    image:       { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, logging: false },
                    jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' }
                })
                .from(htmlStr, 'string')
                .save();
        } finally {
            btn.innerHTML = origText;
            btn.disabled  = false;
            if (window.lucide) lucide.createIcons();
        }
    },

    // ── Subscription modals ───────────────────────────────────────────────────

    openCreateSubModal() {
        this._editingSubId = null;
        this._openSubModal();
    },

    openEditSubModal(id, orgName, status, planId, billing) {
        this._editingSubId = id;
        this._openSubModal({ id, orgName, status, planId, billing });
    },

    _openSubModal(sub = {}) {
        const planOptions = this._plans.map(p =>
            `<option value="${p.id}" ${p.id == sub.planId ? 'selected' : ''}>${App.escapeHtml(p.name)}</option>`
        ).join('');

        App.showModal({
            id: 'adminSubModal',
            size: 'max-w-lg',
            title: sub.id ? 'Edit Subscription' : 'Create Subscription',
            content: `
                <div class="space-y-3">
                    ${!sub.id ? `
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Organization ID</label>
                        <input id="subOrgId" type="number" min="1" placeholder="Enter org ID"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>` : `<p class="text-sm text-gray-700 font-medium">${App.escapeHtml(sub.orgName || '')}</p>`}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Plan</label>
                        <select id="subPlanId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">${planOptions}</select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Billing Interval</label>
                            <select id="subBilling" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="monthly" ${sub.billing === 'monthly' ? 'selected' : ''}>Monthly</option>
                                <option value="yearly"  ${sub.billing === 'yearly'  ? 'selected' : ''}>Yearly</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                            <select id="subStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                ${['trialing','active','halted','cancelled','past_due','completed'].map(s =>
                                    `<option value="${s}" ${s === (sub.status || 'trialing') ? 'selected' : ''}>${s}</option>`
                                ).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Trial Start</label>
                            <input id="subTrialStart" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Trial End</label>
                            <input id="subTrialEnd" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="subNotes" rows="2" placeholder="Optional admin notes"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                    </div>
                </div>`,
            footer: `
                <button onclick="Admin.saveSub()" class="ml-2 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Save</button>
                <button onclick="App.closeModal('adminSubModal')" class="px-4 py-2 border border-gray-300 text-sm rounded-lg hover:bg-gray-50">Cancel</button>`
        });
    },

    async saveSub() {
        const isEdit = !!this._editingSubId;
        const body = {
            plan_id:          parseInt(document.getElementById('subPlanId').value),
            billing_interval: document.getElementById('subBilling').value,
            status:           document.getElementById('subStatus').value,
            trial_starts_at:  document.getElementById('subTrialStart').value || null,
            trial_ends_at:    document.getElementById('subTrialEnd').value || null,
            notes:            document.getElementById('subNotes').value.trim() || null,
        };

        if (isEdit) {
            body.id = this._editingSubId;
        } else {
            const orgEl = document.getElementById('subOrgId');
            body.organization_id = parseInt(orgEl ? orgEl.value : 0);
            if (!body.organization_id) { App.showToast('Organization ID is required', 'error'); return; }
        }

        const endpoint = isEdit ? '/admin/subscriptions/update.php' : '/admin/subscriptions/create.php';
        const res = await this.adminPost(endpoint, body);
        if (res && res.success) {
            App.closeModal('adminSubModal');
            App.showToast(isEdit ? 'Subscription updated' : 'Subscription created', 'success');
            this.loadPlansTab();
        } else {
            App.showToast((res && res.error) || 'Failed to save subscription', 'error');
        }
    },

    async cancelSub(subId) {
        App.showConfirm('Cancel this subscription? This cannot be undone.', async () => {
            const res = await this.adminPost('/admin/subscriptions/cancel.php', { subscription_id: subId });
            if (res && res.success) {
                App.showToast('Subscription cancelled', 'success');
                this.loadPlansTab();
            } else {
                App.showToast((res && res.error) || 'Failed to cancel', 'error');
            }
        });
    },

    async deactivateSub(subId) {
        App.showConfirm('Deactivate this subscription? The organization will lose access until reactivated.', async () => {
            const res = await this.adminPost('/admin/subscriptions/update.php', { id: subId, status: 'halted' });
            if (res && res.success) {
                App.showToast('Subscription deactivated', 'success');
                this.loadPlansTab();
            } else {
                App.showToast((res && res.error) || 'Failed to deactivate', 'error');
            }
        });
    },

    async reactivateSub(subId) {
        App.showConfirm('Reactivate this subscription?', async () => {
            const res = await this.adminPost('/admin/subscriptions/update.php', { id: subId, status: 'active' });
            if (res && res.success) {
                App.showToast('Subscription reactivated', 'success');
                this.loadPlansTab();
            } else {
                App.showToast((res && res.error) || 'Failed to reactivate', 'error');
            }
        });
    },

    // ══════════════════════════════════════════════════════════════════════════
    // TAB 2: Organizations
    // ══════════════════════════════════════════════════════════════════════════

    async loadOrgsTab(status = '', search = '') {
        let url = '/admin/organizations/list.php?org_id=';
        if (status) url += `&status=${encodeURIComponent(status)}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;

        const res  = await this.adminGet(url);
        const orgs = (res && res.data && res.data.organizations) || [];
        this._orgs = orgs;
        this.renderOrgsTab(orgs, status, search);
    },

    renderOrgsTab(orgs, status = '', search = '') {
        const statusOptions = ['', 'active', 'trial', 'suspended'];
        const content = document.getElementById('adminContent');
        content.innerHTML = `
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">Organizations</h2>
                    <button onclick="Admin.openCreateOrgModal()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                        <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>New Organization
                    </button>
                </div>
                <div class="flex gap-3 items-center flex-wrap">
                    <input id="orgSearch" type="text" value="${App.escapeHtml(search)}" placeholder="Search orgs or owner…"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 w-60">
                    <select id="orgStatusFilter" onchange="Admin.loadOrgsTab(this.value, document.getElementById('orgSearch').value)"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        ${statusOptions.map(s => `<option value="${s}" ${s === status ? 'selected' : ''}>${s === '' ? 'All Statuses' : s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                    </select>
                    <button onclick="Admin.loadOrgsTab(document.getElementById('orgStatusFilter').value, document.getElementById('orgSearch').value)"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Search</button>
                </div>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                ${['Organization','Owner','Plan','Subscription','Status','Users','Created','Actions'].map(h =>
                                    `<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">${h}</th>`
                                ).join('')}
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            ${orgs.length ? orgs.map(o => this._orgRow(o)).join('') : '<tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">No organizations found.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>`;

        document.getElementById('orgSearch').addEventListener('keydown', e => {
            if (e.key === 'Enter') this.loadOrgsTab(document.getElementById('orgStatusFilter').value, e.target.value);
        });
        if (window.lucide) lucide.createIcons();
    },

    _orgRow(o) {
        const statusColors = {
            active:    'bg-green-100 text-green-700',
            suspended: 'bg-red-100 text-red-700',
            trial:     'bg-blue-100 text-blue-700',
        };
        const subStatusColors = {
            active:    'bg-green-100 text-green-700',
            trialing:  'bg-blue-100 text-blue-700',
            halted:    'bg-orange-100 text-orange-700',
            cancelled: 'bg-gray-100 text-gray-500',
        };
        const badge    = `<span class="text-xs px-2 py-0.5 rounded-full ${statusColors[o.status] || 'bg-gray-100 text-gray-500'}">${o.status}</span>`;
        const subBadge = o.subscription_status
            ? `<span class="text-xs px-2 py-0.5 rounded-full ${subStatusColors[o.subscription_status] || 'bg-gray-100 text-gray-500'}">${o.subscription_status}</span>`
            : '<span class="text-xs text-gray-400">—</span>';
        const ownerName  = o.owner_name  ? App.escapeHtml(o.owner_name)  : '';
        const ownerEmail = o.owner_email ? App.escapeHtml(o.owner_email) : '—';
        const ownerDisplay = ownerName
            ? `<div class="font-medium text-gray-800 text-xs">${ownerName}</div><div class="text-gray-400 text-xs">${ownerEmail}</div>`
            : `<div class="text-gray-600 text-xs">${ownerEmail}</div>`;
        const created = new Date(o.created_at).toLocaleDateString();

        return `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900">${App.escapeHtml(o.name)}</td>
                <td class="px-4 py-3">${ownerDisplay}</td>
                <td class="px-4 py-3 text-gray-600">${App.escapeHtml(o.plan_name || '—')}</td>
                <td class="px-4 py-3">${subBadge}</td>
                <td class="px-4 py-3">${badge}</td>
                <td class="px-4 py-3 text-gray-600">${o.user_count}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">${created}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-2">
                        <button onclick="Admin.openOrgDetail(${o.id})" class="text-xs text-blue-600 hover:underline">View</button>
                        ${o.status !== 'suspended'
                            ? `<button onclick="Admin.updateOrgStatus(${o.id},'suspended')" class="text-xs text-orange-500 hover:underline">Suspend</button>`
                            : `<button onclick="Admin.updateOrgStatus(${o.id},'active')" class="text-xs text-green-600 hover:underline">Activate</button>`}
                    </div>
                </td>
            </tr>`;
    },

    openCreateOrgModal() {
        if (!this._plans || !this._plans.length) {
            App.showToast('Load the Plans tab first to select a plan', 'error');
        }
        const planOptions = (this._plans || []).filter(p => p.is_active)
            .map(p => `<option value="${p.id}">${App.escapeHtml(p.name)}</option>`).join('');

        App.showModal({
            id: 'adminOrgModal',
            size: 'max-w-lg',
            title: 'Create Organization',
            content: `
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Organization Name *</label>
                        <input id="orgName" type="text" placeholder="Acme Corp"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Owner Email *</label>
                            <input id="ownerEmail" type="email" placeholder="owner@example.com"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Owner Name *</label>
                            <input id="ownerName" type="text" placeholder="John Smith"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Temp Password *</label>
                        <input id="ownerPass" type="password" placeholder="Min 6 characters"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Plan *</label>
                            <select id="orgPlanId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">${planOptions}</select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Billing</label>
                            <select id="orgBilling" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                            <select id="orgStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="trial">Trial</option>
                                <option value="active">Active</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Trial Days</label>
                            <input id="orgTrial" type="number" min="0" value="14"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                </div>`,
            footer: `
                <button onclick="Admin.saveOrg()" class="ml-2 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Create</button>
                <button onclick="App.closeModal('adminOrgModal')" class="px-4 py-2 border border-gray-300 text-sm rounded-lg hover:bg-gray-50">Cancel</button>`
        });
    },

    async saveOrg() {
        const body = {
            org_name:         document.getElementById('orgName').value.trim(),
            owner_email:      document.getElementById('ownerEmail').value.trim(),
            owner_name:       document.getElementById('ownerName').value.trim(),
            owner_password:   document.getElementById('ownerPass').value,
            plan_id:          parseInt(document.getElementById('orgPlanId').value),
            billing_interval: document.getElementById('orgBilling').value,
            status:           document.getElementById('orgStatus').value,
            trial_days:       parseInt(document.getElementById('orgTrial').value) || 14,
        };
        if (!body.org_name)   { App.showToast('Organization name is required', 'error'); return; }
        if (!body.owner_email){ App.showToast('Owner email is required', 'error'); return; }
        if (!body.owner_name) { App.showToast('Owner name is required', 'error'); return; }
        if (body.owner_password.length < 6) { App.showToast('Password must be at least 6 characters', 'error'); return; }

        const res = await this.adminPost('/admin/organizations/create.php', body);
        if (res && res.success) {
            App.closeModal('adminOrgModal');
            App.showToast('Organization created successfully', 'success');
            this.loadOrgsTab();
        } else {
            App.showToast((res && res.error) || 'Failed to create organization', 'error');
        }
    },

    async updateOrgStatus(orgId, status) {
        const res = await this.adminPost('/admin/organizations/update.php', { id: orgId, status });
        if (res && res.success) {
            App.showToast(`Organization ${status}`, 'success');
            this.loadOrgsTab();
        } else {
            App.showToast((res && res.error) || 'Update failed', 'error');
        }
    },

    async openOrgDetail(orgId) {
        const res = await this.adminGet(`/admin/organizations/get.php?org_id=&id=${orgId}`);
        if (!res || !res.success) { App.showToast('Failed to load org details', 'error'); return; }
        const o   = res.data && res.data.organization;
        const sub = o.subscription;

        const subStatusColors = {
            active:    'bg-green-100 text-green-700',
            trialing:  'bg-blue-100 text-blue-700',
            halted:    'bg-orange-100 text-orange-700',
            cancelled: 'bg-gray-100 text-gray-500',
        };
        const planOptions = (this._plans || []).filter(p => p.is_active)
            .map(p => `<option value="${p.id}" ${p.id == o.current_plan_id ? 'selected' : ''}>${App.escapeHtml(p.name)}</option>`).join('');

        App.showModal({
            id: 'adminOrgDetailModal',
            size: 'max-w-2xl',
            title: App.escapeHtml(o.name),
            content: `
                <div class="space-y-5">
                    <div class="flex gap-4 flex-wrap">
                        <div class="flex-1 min-w-40">
                            <p class="text-xs text-gray-500 mb-1">Status</p>
                            <select id="odStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                ${['active','trial','suspended'].map(s => `<option value="${s}" ${s === o.status ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="flex-1 min-w-40">
                            <p class="text-xs text-gray-500 mb-1">Plan</p>
                            <select id="odPlan" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">${planOptions}</select>
                        </div>
                        <div class="flex items-end">
                            <button onclick="Admin._saveOrgDetail(${o.id})" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Save</button>
                        </div>
                    </div>
                    <!-- Owner info -->
                    ${(o.owner_email || o.owner_name) ? `
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Owner</p>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-sm">
                                ${(o.owner_name || o.owner_email || '?').charAt(0).toUpperCase()}
                            </div>
                            <div>
                                ${o.owner_name ? `<p class="text-sm font-medium text-gray-800">${App.escapeHtml(o.owner_name)}</p>` : ''}
                                <p class="text-xs text-gray-500">${App.escapeHtml(o.owner_email || '')}</p>
                            </div>
                        </div>
                    </div>` : ''}
                    <!-- Subscription card -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Subscription</p>
                        ${sub ? `
                            <div class="grid grid-cols-3 gap-3 text-xs text-gray-600">
                                <div><span class="font-medium">Plan:</span> ${App.escapeHtml(sub.plan_name || '—')}</div>
                                <div><span class="font-medium">Status:</span> <span class="px-1.5 py-0.5 rounded-full text-xs ${subStatusColors[sub.status] || ''}">${sub.status}</span></div>
                                <div><span class="font-medium">Billing:</span> ${sub.billing_interval}</div>
                                <div><span class="font-medium">Trial ends:</span> ${sub.trial_ends_at ? new Date(sub.trial_ends_at).toLocaleDateString() : '—'}</div>
                                <div><span class="font-medium">Period end:</span> ${sub.current_period_end ? new Date(sub.current_period_end).toLocaleDateString() : '—'}</div>
                                ${sub.notes ? `<div class="col-span-3"><span class="font-medium">Notes:</span> ${App.escapeHtml(sub.notes)}</div>` : ''}
                            </div>` : '<p class="text-xs text-gray-500">No active subscription.</p>'}
                    </div>
                    <!-- Usage stats -->
                    <div class="grid grid-cols-3 gap-4">
                        ${[['Users', o.user_count, 'users'], ['Leads', o.lead_count, 'contact'], ['Created', new Date(o.created_at).toLocaleDateString(), 'calendar']].map(([label, val, icon]) => `
                        <div class="bg-white border border-gray-100 rounded-lg p-3 text-center">
                            <i data-lucide="${icon}" class="w-5 h-5 mx-auto mb-1 text-blue-500"></i>
                            <p class="text-xl font-bold text-gray-900">${val}</p>
                            <p class="text-xs text-gray-500">${label}</p>
                        </div>`).join('')}
                    </div>
                    <!-- Members -->
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">Members</p>
                        <div class="space-y-1 max-h-40 overflow-y-auto">
                            ${(o.members || []).map(m => `
                            <div class="flex items-center justify-between text-xs py-1 border-b border-gray-100">
                                <span class="text-gray-800">${App.escapeHtml(m.full_name || '')} <span class="text-gray-400">${App.escapeHtml(m.email)}</span></span>
                                <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded">${m.role}</span>
                            </div>`).join('') || '<p class="text-xs text-gray-400">No members.</p>'}
                        </div>
                    </div>
                </div>`
        });
        if (window.lucide) lucide.createIcons();
    },

    async _saveOrgDetail(orgId) {
        const body = {
            id:              orgId,
            status:          document.getElementById('odStatus').value,
            current_plan_id: parseInt(document.getElementById('odPlan').value) || null,
        };
        const res = await this.adminPost('/admin/organizations/update.php', body);
        if (res && res.success) {
            App.closeModal('adminOrgDetailModal');
            App.showToast('Organization updated', 'success');
            this.loadOrgsTab();
        } else {
            App.showToast((res && res.error) || 'Update failed', 'error');
        }
    },
};

window.Admin = Admin;
export default Admin;
