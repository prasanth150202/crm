/**
 * Automations Module
 * Manages automation workflows, triggers, and actions
 */

export const Automations = {
    workflows: [],
    canEditAll: false,
    editingWorkflowId: null,

    async init() {
        console.log('[Automations] Initializing module');
        await this.loadWorkflows();
        this.render();
    },

    async loadWorkflows() {
        try {
            const result = await App.api('/automations/list.php');
            if (result && result.success) {
                this.workflows = result.workflows || [];
                this.canEditAll = result.canEditAll || false;
            } else {
                App.showToast(result.error || 'Failed to load workflows', 'error');
            }
        } catch (error) {
            console.error('[Automations] Load error:', error);
            App.showToast('Failed to load workflows', 'error');
        }
    },

    render() {
        const container = document.getElementById('appContent');
        if (!container) return;

        const user = App.getUser();
        const canCreate = window.userPermissions && window.userPermissions['create_automations'];
        const canCreateOrg = window.userPermissions && window.userPermissions['create_org_automations'];

        container.innerHTML = `
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Automations</h2>
                        <p class="text-sm text-gray-500 mt-1">Create and manage automation workflows</p>
                    </div>
                    <div class="flex space-x-3">
                        ${canCreate || canCreateOrg ? `
                            <button onclick="Automations.openWorkflowBuilder()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                <i data-lucide="plus" class="h-4 w-4 mr-2"></i>Create Workflow
                            </button>
                        ` : ''}
                    </div>
                </div>

                ${this.workflows.length === 0 ? `
                    <div class="text-center py-12 bg-white rounded-lg border border-gray-200">
                        <i data-lucide="zap" class="h-16 w-16 mx-auto text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Workflows Yet</h3>
                        <p class="text-gray-500 mb-4">Create your first automation workflow to get started</p>
                        ${canCreate || canCreateOrg ? `
                            <button onclick="Automations.openWorkflowBuilder()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i data-lucide="plus" class="h-4 w-4 mr-2"></i>Create Workflow
                            </button>
                        ` : ''}
                    </div>
                ` : `
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Workflow</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scope</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Triggers</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${this.workflows.map(wf => this.renderWorkflowRow(wf)).join('')}
                            </tbody>
                        </table>
                    </div>
                `}
            </div>
        `;

        if (window.lucide) {
            window.lucide.createIcons();
        }
    },

    renderWorkflowRow(workflow) {
        const user = App.getUser();
        const isOwner = workflow.created_by == user.id;
        const canEdit = this.canEditAll || isOwner;
        const canDelete = window.userPermissions && window.userPermissions['delete_automations'] && (this.canEditAll || isOwner);

        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">${App.escapeHtml(workflow.name)}</div>
                        ${workflow.description ? `<div class="text-sm text-gray-500">${App.escapeHtml(workflow.description)}</div>` : ''}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs font-semibold rounded-full ${workflow.scope === 'organization' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">
                        ${workflow.scope === 'organization' ? 'Organization' : 'Personal'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${workflow.trigger_count || 0}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${workflow.action_count || 0}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <button onclick="Automations.toggleStatus(${workflow.id}, ${workflow.is_active ? 0 : 1})" ${!canEdit ? 'disabled' : ''} class="${workflow.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'} px-2 py-1 text-xs font-semibold rounded-full ${canEdit ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed'}">
                        ${workflow.is_active ? 'Active' : 'Inactive'}
                    </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${App.escapeHtml(workflow.creator_name || 'Unknown')}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                    ${canEdit ? `<button onclick="Automations.editWorkflow(${workflow.id})" class="text-blue-600 hover:text-blue-900">Edit</button>` : ''}
                    ${canDelete ? `<button onclick="Automations.deleteWorkflow(${workflow.id})" class="text-red-600 hover:text-red-900">Delete</button>` : ''}
                </td>
            </tr>
        `;
    },

    async openWorkflowBuilder(workflowId = null) {
        this.editingWorkflowId = workflowId;
        const user = App.getUser();
        const canCreateOrg = window.userPermissions && window.userPermissions['create_org_automations'];
        const canCreate = window.userPermissions && window.userPermissions['create_automations'];

        let workflowData = null;
        if (workflowId) {
            try {
                const result = await App.api(`/automations/get.php?id=${workflowId}`);
                if (result && result.success) {
                    workflowData = result.workflow;
                } else {
                    App.showToast(result.error || 'Failed to load workflow details', 'error');
                    return;
                }
            } catch (error) {
                console.error('[Automations] Fetch error:', error);
                App.showToast('Failed to load workflow details', 'error');
                return;
            }
        }

        const modalHtml = `
            <div id="workflowBuilderModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" style="display: block;">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">
                            <i data-lucide="zap" class="inline h-5 w-5 mr-2"></i>
                            ${workflowId ? 'Edit' : 'Create'} Workflow
                        </h2>
                        <button onclick="Automations.closeWorkflowBuilder()" class="text-gray-400 hover:text-gray-600">
                            <i data-lucide="x" class="h-6 w-6"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Workflow Name & Description -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Workflow Name *</label>
                                <input type="text" id="workflowName" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" 
                                    placeholder="e.g., Send new leads to webhook"
                                    value="${workflowData ? App.escapeHtml(workflowData.name) : ''}">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea id="workflowDescription" rows="2" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" 
                                    placeholder="Optional description">${workflowData ? App.escapeHtml(workflowData.description) : ''}</textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Scope *</label>
                                <select id="workflowScope" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white">
                                    ${canCreateOrg ? `<option value="organization" ${workflowData?.scope === 'organization' ? 'selected' : ''}>Organization-wide (all users)</option>` : ''}
                                    ${canCreate ? `<option value="user" ${workflowData?.scope === 'user' ? 'selected' : ''}>Personal (only me)</option>` : ''}
                                </select>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" id="workflowShared" ${workflowData?.is_shared ? 'checked' : ''} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="workflowShared" class="ml-2 block text-sm text-gray-700">Share with other users (they can view but only I can trigger)</label>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" id="workflowActive" ${workflowData ? (workflowData.is_active ? 'checked' : '') : 'checked'} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="workflowActive" class="ml-2 block text-sm text-gray-700">Active (workflow will execute when triggered)</label>
                            </div>
                        </div>

                        <!-- Triggers Section -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Triggers</h3>
                                <button onclick="Automations.addTrigger()" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <i data-lucide="plus" class="h-4 w-4 mr-1"></i>Add Trigger
                                </button>
                            </div>
                            <div id="triggersContainer" class="space-y-3">
                                <p class="text-sm text-gray-500">No triggers yet. Click "Add Trigger" to add one.</p>
                            </div>
                        </div>

                        <!-- Actions Section -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Actions</h3>
                                <button onclick="Automations.addAction()" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <i data-lucide="plus" class="h-4 w-4 mr-1"></i>Add Action
                                </button>
                            </div>
                            <div id="actionsContainer" class="space-y-3">
                                <p class="text-sm text-gray-500">No actions yet. Click "Add Action" to add one.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex justify-end space-x-3">
                        <button onclick="Automations.closeWorkflowBuilder()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button onclick="Automations.saveWorkflow()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            ${workflowId ? 'Update' : 'Create'} Workflow
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        const existingModal = document.getElementById('workflowBuilderModal');
        if (existingModal) existingModal.remove();

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        if (window.lucide) {
            window.lucide.createIcons();
        }

        // Initialize triggers and actions
        this.triggers = [];
        this.actions = [];

        if (workflowData) {
            if (workflowData.triggers && workflowData.triggers.length > 0) {
                for (const t of workflowData.triggers) {
                    await this.addTrigger(t);
                }
            }
            if (workflowData.actions && workflowData.actions.length > 0) {
                for (const a of workflowData.actions) {
                    await this.addAction(a);
                }
            }
        } else {
            await this.addTrigger();
        }
    },

    closeWorkflowBuilder() {
        const modal = document.getElementById('workflowBuilderModal');
        if (modal) modal.remove();
        this.triggers = [];
        this.actions = [];
        this.editingWorkflowId = null;
    },

    triggers: [],
    actions: [],

    async addTrigger(initData = null) {
        const triggerId = this.triggers.length;
        const type = initData ? initData.trigger_type : 'lead_created';
        const config = (initData && initData.config) ? initData.config : {};
        this.triggers.push({ id: triggerId, type: type, config: config });

        const container = document.getElementById('triggersContainer');
        if (container.children[0]?.textContent.includes('No triggers')) {
            container.innerHTML = '';
        }

        const triggerHtml = `
            <div id="trigger-${triggerId}" class="flex items-start space-x-3 p-3 bg-gray-50 rounded-md border border-gray-200">
                <i data-lucide="play-circle" class="h-5 w-5 text-blue-600 mt-1"></i>
                <div class="flex-1">
                    <select id="trigger-type-${triggerId}" onchange="Automations.updateTriggerType(${triggerId}, this.value)" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white">
                        <option value="lead_created" ${type === 'lead_created' ? 'selected' : ''}>When a lead is created</option>
                        <option value="lead_stage_changed" ${type === 'lead_stage_changed' ? 'selected' : ''}>When lead stage changes</option>
                        <option value="lead_assigned" ${type === 'lead_assigned' ? 'selected' : ''}>When lead is assigned</option>
                        <option value="field_changed" ${type === 'field_changed' ? 'selected' : ''}>When a field changes</option>
                    </select>
                    <div id="trigger-config-${triggerId}" class="mt-2"></div>
                </div>
                <button onclick="Automations.removeTrigger(${triggerId})" class="text-gray-400 hover:text-red-600">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                </button>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', triggerHtml);
        await this.updateTriggerType(triggerId, type, !!initData);
        if (window.lucide) window.lucide.createIcons();
    },

    async updateTriggerType(triggerId, type, isInit = false) {
        const trigger = this.triggers.find(t => t.id === triggerId);
        if (trigger && !isInit) {
            trigger.type = type;
            trigger.config = {};
        }

        // Show configuration UI based on type
        const configContainer = document.getElementById(`trigger-config-${triggerId}`);
        if (configContainer) {
            configContainer.innerHTML = '<div class="text-xs text-gray-500 p-2">Loading configuration...</div>';
            configContainer.innerHTML = await this.renderTriggerConfig(triggerId, type);

            // If init, handle secondary renders (like field changed conditions)
            if (isInit && type === 'field_changed' && trigger.config.field_name) {
                await this.updateTriggerFieldConfig(triggerId, trigger.config.field_name, true);
            }
        }
    },

    async renderTriggerConfig(triggerId, type) {
        switch (type) {
            case 'lead_stage_changed':
                // Get available stages
                const stages = await this.getStages();
                const currentStage = this.triggers[triggerId].config.to_stage || '';
                return `
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-700">When stage changes to:</label>
                        <select id="trigger-stage-${triggerId}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                            oninput="Automations.updateTriggerConfig(${triggerId}, 'to_stage', this.value)">
                            <option value="" ${!currentStage ? 'selected' : ''}>Any stage</option>
                            ${stages.map(s => `<option value="${s.id}" ${currentStage === s.id ? 'selected' : ''}>${s.name}</option>`).join('')}
                        </select>
                        <p class="text-xs text-gray-500">Leave as "Any stage" to trigger on all stage changes</p>
                    </div>
                `;

            case 'field_changed':
                const customFields = await this.getCustomFields();
                const currentField = this.triggers[triggerId].config.field_name || '';
                return `
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Watch field:</label>
                            <select id="trigger-field-${triggerId}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                                onchange="Automations.updateTriggerFieldConfig(${triggerId}, this.value)">
                                <option value="" ${!currentField ? 'selected' : ''}>Select a field...</option>
                                <optgroup label="Standard Fields">
                                    <option value="name" ${currentField === 'name' ? 'selected' : ''}>Name</option>
                                    <option value="email" ${currentField === 'email' ? 'selected' : ''}>Email</option>
                                    <option value="phone" ${currentField === 'phone' ? 'selected' : ''}>Phone</option>
                                    <option value="company" ${currentField === 'company' ? 'selected' : ''}>Company</option>
                                    <option value="lead_value" ${currentField === 'lead_value' ? 'selected' : ''}>Lead Value</option>
                                    <option value="source" ${currentField === 'source' ? 'selected' : ''}>Source</option>
                                    <option value="stage_id" ${currentField === 'stage_id' ? 'selected' : ''}>Stage</option>
                                    <option value="owner_id" ${currentField === 'owner_id' ? 'selected' : ''}>Owner</option>
                                </optgroup>
                                ${customFields.length > 0 ? `
                                    <optgroup label="Custom Fields">
                                        ${customFields.map(f => `<option value="custom_${f.name}" ${currentField === 'custom_' + f.name ? 'selected' : ''}>${f.name}</option>`).join('')}
                                    </optgroup>
                                ` : ''}
                            </select>
                        </div>
                        <div id="trigger-field-conditions-${triggerId}"></div>
                    </div>
                `;


            case 'lead_assigned':
                const assignType = this.triggers[triggerId].config.assign_type || 'any';
                return `
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-700">Trigger when:</label>
                        <select id="trigger-assign-type-${triggerId}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                            oninput="Automations.updateTriggerConfig(${triggerId}, 'assign_type', this.value)">
                            <option value="any" ${assignType === 'any' ? 'selected' : ''}>Any assignment</option>
                            <option value="to_user" ${assignType === 'to_user' ? 'selected' : ''}>Assigned to specific user</option>
                            <option value="to_me" ${assignType === 'to_me' ? 'selected' : ''}>Assigned to me</option>
                        </select>
                    </div>
                `;

            case 'lead_created':
            default:
                return `<p class="text-xs text-gray-500 italic">No additional configuration needed</p>`;
        }
    },

    async getStages() {
        // Fetch stages from API or return default
        try {
            const result = await App.api('/settings/get_field_config.php');
            if (result && result.success && result.fields && result.fields.stage_id) {
                return result.fields.stage_id.options.map(s => ({
                    id: s.toLowerCase().replace(/\s+/g, '_'),
                    name: s
                }));
            }
        } catch (e) {
            console.warn('Failed to load stages, using defaults');
        }

        // Default stages
        return [
            { id: 'new', name: 'New' },
            { id: 'contacted', name: 'Contacted' },
            { id: 'qualified', name: 'Qualified' },
            { id: 'proposal', name: 'Proposal' },
            { id: 'won', name: 'Won' },
            { id: 'lost', name: 'Lost' }
        ];
    },

    async getCustomFields() {
        try {
            console.log('[Automations] Fetching custom fields...');
            const result = await App.api('/fields/list.php');
            console.log('[Automations] Custom fields result:', result);
            if (result && result.success && result.fields) {
                console.log('[Automations] Custom fields count:', result.fields.length);
                return result.fields;
            }
        } catch (e) {
            console.warn('Failed to load custom fields', e);
        }
        return [];
    },

    updateTriggerConfig(triggerId, key, value) {
        const trigger = this.triggers.find(t => t.id === triggerId);
        if (trigger) {
            trigger.config[key] = value;
        }
    },

    async updateTriggerFieldConfig(triggerId, fieldName, isInit = false) {
        const trigger = this.triggers.find(t => t.id === triggerId);
        if (trigger && !isInit) {
            trigger.config.field_name = fieldName;
            // Default operator if not set
            if (!trigger.config.operator) trigger.config.operator = 'equals';
            // Clear value if switching fields to avoid type mismatch confusion, or keep it?
            if (!trigger.config.field_value) trigger.config.field_value = '';
        }

        const operator = trigger.config.operator || 'equals';
        const container = document.getElementById(`trigger-field-conditions-${triggerId}`);
        if (container && fieldName) {
            // Render Operator and Value inputs
            container.innerHTML = `
                <div class="flex space-x-2">
                    <div class="w-1/3">
                        <label class="block text-xs font-medium text-gray-700">Condition:</label>
                        <select class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                            oninput="Automations.updateTriggerConfig(${triggerId}, 'operator', this.value); Automations.renderTriggerValueInput(${triggerId}, '${fieldName}', this.value)">
                            <option value="equals" ${operator === 'equals' ? 'selected' : ''}>Equals</option>
                            <option value="not_equals" ${operator === 'not_equals' ? 'selected' : ''}>Does not equal</option>
                            <option value="contains" ${operator === 'contains' ? 'selected' : ''}>Contains</option>
                            <option value="not_empty" ${operator === 'not_empty' ? 'selected' : ''}>Is not empty</option>
                            <option value="is_empty" ${operator === 'is_empty' ? 'selected' : ''}>Is empty</option>
                            <option value="changed" ${operator === 'changed' ? 'selected' : ''}>Changed (Any value)</option>
                        </select>
                    </div>
                    <div class="w-2/3" id="trigger-value-container-${triggerId}">
                        <!-- Value input rendered dynamically -->
                    </div>
                </div>
            `;

            // Initial render of value input based on current operator
            this.renderTriggerValueInput(triggerId, fieldName, operator);
        } else if (container) {
            container.innerHTML = '';
        }
    },

    renderTriggerValueInput(triggerId, fieldName, operator) {
        const container = document.getElementById(`trigger-value-container-${triggerId}`);
        if (!container) return;

        // hide logic
        if (['not_empty', 'is_empty', 'changed'].includes(operator)) {
            container.innerHTML = '';
            return;
        }

        const trigger = this.triggers.find(t => t.id === triggerId);
        const currentValue = trigger ? (trigger.config.field_value || '') : '';

        // Render input
        container.innerHTML = `
            <label class="block text-xs font-medium text-gray-700">Value:</label>
            <input type="text" 
                class="w-full px-2 py-1 border border-gray-300 rounded text-sm" 
                placeholder="Enter value"
                value="${App.escapeHtml(currentValue)}"
                oninput="Automations.updateTriggerConfig(${triggerId}, 'field_value', this.value)">
        `;
    },

    removeTrigger(triggerId) {
        this.triggers = this.triggers.filter(t => t.id !== triggerId);
        const element = document.getElementById(`trigger-${triggerId}`);
        if (element) element.remove();

        const container = document.getElementById('triggersContainer');
        if (container.children.length === 0) {
            container.innerHTML = '<p class="text-sm text-gray-500">No triggers yet. Click "Add Trigger" to add one.</p>';
        }
    },

    async addAction(initData = null) {
        const actionId = this.actions.length;
        const type = initData ? initData.action_type : 'webhook';
        const config = (initData && initData.config) ? initData.config : {};
        this.actions.push({ id: actionId, type: type, config: config });

        const container = document.getElementById('actionsContainer');
        if (container.children[0]?.textContent.includes('No actions')) {
            container.innerHTML = '';
        }

        const actionHtml = `
            <div id="action-${actionId}" class="flex items-start space-x-3 p-3 bg-gray-50 rounded-md border border-gray-200">
                <i data-lucide="zap" class="h-5 w-5 text-green-600 mt-1"></i>
                <div class="flex-1">
                    <select id="action-type-${actionId}" onchange="Automations.updateActionType(${actionId}, this.value)" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white">
                        <option value="webhook" ${type === 'webhook' ? 'selected' : ''}>Send to Webhook URL</option>
                        <option value="zingbot" ${type === 'zingbot' ? 'selected' : ''}>Trigger Zingbot Flow</option>
                        <option value="assign_user" ${type === 'assign_user' ? 'selected' : ''}>Assign to User</option>
                        <option value="update_field" ${type === 'update_field' ? 'selected' : ''}>Update Lead Field</option>
                        <option value="add_note" ${type === 'add_note' ? 'selected' : ''}>Add Note to Lead</option>
                    </select>
                    <div id="action-config-${actionId}" class="mt-2">
                        ${this.renderActionConfig(actionId, type)}
                    </div>
                </div>
                <button onclick="Automations.removeAction(${actionId})" class="text-gray-400 hover:text-red-600">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                </button>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', actionHtml);
        await this.updateActionType(actionId, type, true);
        if (window.lucide) window.lucide.createIcons();
    },

    async updateActionType(actionId, type, isInit = false) {
        const action = this.actions.find(a => a.id === actionId);
        if (action && !isInit) {
            action.type = type;
            action.config = {};
        }

        const configContainer = document.getElementById(`action-config-${actionId}`);
        if (configContainer) {
            configContainer.innerHTML = this.renderActionConfig(actionId, type);

            // Handle async rendering parts (like Zingbot flows)
            if (type === 'zingbot') {
                await this.renderZingbotFlowSelect(actionId);
            }
        }
    },

    renderActionConfig(actionId, type) {
        const action = this.actions.find(a => a.id === actionId);
        const config = action ? action.config : {};

        switch (type) {
            case 'webhook':
                return `
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-700">Webhook URL:</label>
                        <input type="url" id="action-webhook-url-${actionId}" placeholder="https://example.com/webhook" 
                            class="w-full px-2 py-2 border border-gray-300 rounded-md text-sm" 
                            value="${App.escapeHtml(config.url || '')}"
                            oninput="Automations.updateActionConfig(${actionId}, 'url', this.value)">
                        
                        <label class="block text-xs font-medium text-gray-700 mt-3">Payload Data:</label>
                        <select id="action-webhook-payload-${actionId}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                            oninput="Automations.updateActionConfig(${actionId}, 'payload_type', this.value); 
                                      document.getElementById('webhook-helper-text-${actionId}').innerHTML = 
                                      this.value === 'full' ? 'Sends complete lead object including all custom fields.' : 
                                      'Sends only standard contact fields (Name, Email, Phone, Company).';">
                            <option value="full" ${config.payload_type === 'full' ? 'selected' : ''}>Full lead data (all fields)</option>
                            <option value="basic" ${config.payload_type === 'basic' ? 'selected' : ''}>Basic info only (name, email, phone)</option>
                            <option value="custom" ${config.payload_type === 'custom' ? 'selected' : ''}>Custom fields only</option>
                        </select>
                        
                        <div class="mt-2 p-2 bg-blue-50 rounded text-xs text-blue-700">
                            <strong>Payload Info:</strong> 
                            <span id="webhook-helper-text-${actionId}">
                                ${config.payload_type === 'basic' ? 'Sends only standard contact fields (Name, Email, Phone, Company).' : 'Full lead data sends the entire lead object including all custom fields.'}
                            </span>
                        </div>
                    </div>
                `;
            case 'zingbot':
                return `
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-700">Zingbot Flow:</label>
                        <div id="zingbot-flow-container-${actionId}">
                            <div class="text-xs text-gray-400 p-2 italic animate-pulse">Loading flows from Zingbot...</div>
                        </div>
                        
                        <label class="block text-xs font-medium text-gray-700 mt-3">Send to:</label>
                        <select id="action-zingbot-target-${actionId}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                            oninput="Automations.updateActionConfig(${actionId}, 'target', this.value)">
                            <option value="lead_phone" ${config.target === 'lead_phone' ? 'selected' : ''}>Lead's phone number</option>
                            <option value="assigned_user" ${config.target === 'assigned_user' ? 'selected' : ''}>Assigned user's phone</option>
                        </select>
                        
                        <div class="mt-2 p-2 bg-yellow-50 rounded text-xs text-yellow-700">
                            <strong>Note:</strong> Connects to <code>app.zingbot.io</code> to trigger selected flow.
                        </div>
                    </div>
                `;
            case 'assign_user':
                return `
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-700">Assign to:</label>
                        <select id="action-assign-user-${actionId}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                            oninput="Automations.updateActionConfig(${actionId}, 'user_id', this.value)">
                            <option value="">Select user...</option>
                            <option value="round_robin" ${config.user_id === 'round_robin' ? 'selected' : ''}>Round Robin (distribute evenly)</option>
                            <option value="1" ${config.user_id === '1' ? 'selected' : ''}>User 1</option>
                            <option value="2" ${config.user_id === '2' ? 'selected' : ''}>User 2</option>
                        </select>
                        <p class="text-xs text-gray-500 italic">User list will load from your organization</p>
                    </div>
                `;
            case 'update_field':
                return `
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-700">Field to update:</label>
                        <select id="action-update-field-${actionId}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                            oninput="Automations.updateActionConfig(${actionId}, 'field_name', this.value); Automations.showFieldValueInput(${actionId}, this.value)">
                            <option value="" ${!config.field_name ? 'selected' : ''}>Select field...</option>
                            <option value="stage_id" ${config.field_name === 'stage_id' ? 'selected' : ''}>Stage</option>
                            <option value="source" ${config.field_name === 'source' ? 'selected' : ''}>Source</option>
                            <option value="lead_value" ${config.field_name === 'lead_value' ? 'selected' : ''}>Lead Value</option>
                        </select>
                        <div id="action-field-value-${actionId}" class="mt-2">
                            ${config.field_name ? `
                                <label class="block text-xs font-medium text-gray-700">New value:</label>
                                <input type="text" placeholder="Enter new value" 
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm mt-1"
                                    value="${App.escapeHtml(config.field_value || '')}"
                                    oninput="Automations.updateActionConfig(${actionId}, 'field_value', this.value)">
                            ` : ''}
                        </div>
                    </div>
                `;
            case 'add_note':
                return `
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-700">Note text:</label>
                        <textarea id="action-note-text-${actionId}" placeholder="Note to add to lead" rows="2"
                            class="w-full px-2 py-2 border border-gray-300 rounded-md text-sm"
                            oninput="Automations.updateActionConfig(${actionId}, 'note_text', this.value)">${App.escapeHtml(config.note_text || '')}</textarea>
                        
                        <div class="mt-2 p-2 bg-blue-50 rounded text-xs text-blue-700">
                            You can use variables: <code>{{lead.name}}</code>, <code>{{lead.stage}}</code>, etc.
                        </div>
                    </div>
                `;
            default:
                return '';
        }
    },

    showFieldValueInput(actionId, fieldName) {
        const container = document.getElementById(`action-field-value-${actionId}`);
        if (container && fieldName) {
            container.innerHTML = `
                <label class="block text-xs font-medium text-gray-700">New value:</label>
                <input type="text" placeholder="Enter new value" 
                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm mt-1"
                    oninput="Automations.updateActionConfig(${actionId}, 'field_value', this.value)">
            `;
        }
    },

    updateActionConfig(actionId, key, value) {
        const action = this.actions.find(a => a.id === actionId);
        if (action) {
            action.config[key] = value;
        }
    },

    removeAction(actionId) {
        this.actions = this.actions.filter(a => a.id !== actionId);
        const element = document.getElementById(`action-${actionId}`);
        if (element) element.remove();

        const container = document.getElementById('actionsContainer');
        if (container.children.length === 0) {
            container.innerHTML = '<p class="text-sm text-gray-500">No actions yet. Click "Add Action" to add one.</p>';
        }
    },

    async saveWorkflow() {
        const name = document.getElementById('workflowName').value.trim();
        const description = document.getElementById('workflowDescription').value.trim();
        const scope = document.getElementById('workflowScope').value;
        const isShared = document.getElementById('workflowShared').checked;
        const isActive = document.getElementById('workflowActive').checked;

        if (!name) {
            App.showToast('Please enter a workflow name', 'error');
            return;
        }

        if (this.triggers.length === 0) {
            App.showToast('Please add at least one trigger', 'error');
            return;
        }

        if (this.actions.length === 0) {
            App.showToast('Please add at least one action', 'error');
            return;
        }

        try {
            const payload = {
                workflow_id: this.editingWorkflowId,
                name,
                description,
                scope,
                is_shared: isShared,
                is_active: isActive,
                triggers: this.triggers.map(t => ({
                    trigger_type: t.type,
                    config: t.config
                })),
                actions: this.actions.map(a => ({
                    action_type: a.type,
                    config: a.config
                }))
            };

            const endpoint = this.editingWorkflowId ? '/automations/update.php' : '/automations/create.php';
            const result = await App.api(endpoint, 'POST', payload);

            if (result && result.success) {
                App.showToast(this.editingWorkflowId ? 'Workflow updated successfully!' : 'Workflow created successfully!', 'success');
                this.closeWorkflowBuilder();
                await this.loadWorkflows();
                this.render();
            } else {
                App.showToast(result.error || 'Failed to save workflow', 'error');
            }
        } catch (error) {
            console.error('[Automations] Save error:', error);
            App.showToast(this.editingWorkflowId ? 'Failed to update workflow' : 'Failed to create workflow', 'error');
        }
    },

    async toggleStatus(workflowId, isActive) {
        try {
            const result = await App.api('/automations/toggle_status.php', 'POST', {
                workflow_id: workflowId,
                is_active: isActive
            });

            if (result && result.success) {
                App.showToast('Workflow status updated', 'success');
                await this.loadWorkflows();
                this.render();
            } else {
                App.showToast(result.error || 'Failed to update status', 'error');
            }
        } catch (error) {
            console.error('[Automations] Toggle status error:', error);
            App.showToast('Failed to update status', 'error');
        }
    },

    async deleteWorkflow(workflowId) {
        App.showConfirm('Are you sure you want to delete this workflow? This action cannot be undone.', async () => {
            try {
                const result = await App.api('/automations/delete.php', 'POST', {
                    workflow_id: workflowId
                });

                if (result && result.success) {
                    App.showToast('Workflow deleted successfully', 'success');
                    await this.loadWorkflows();
                    this.render();
                } else {
                    App.showToast(result.error || 'Failed to delete workflow', 'error');
                }
            } catch (error) {
                console.error('[Automations] Delete error:', error);
                App.showToast('Failed to delete workflow', 'error');
            }
        });
    },

    async editWorkflow(workflowId) {
        this.editingWorkflowId = workflowId;
        await this.openWorkflowBuilder(workflowId);
    },

    async getZingbotFlows() {
        if (this._zingbotFlows) return this._zingbotFlows;
        try {
            const result = await App.api('/integrations/zingbot/flows.php');
            if (result && result.success && result.flows) {
                this._zingbotFlows = result.flows;
                return result.flows;
            }
        } catch (e) {
            console.error('[Automations] Failed to fetch Zingbot flows', e);
        }
        return [];
    },

    async renderZingbotFlowSelect(actionId) {
        const container = document.getElementById(`zingbot-flow-container-${actionId}`);
        if (!container) return;

        const flows = await this.getZingbotFlows();
        const action = this.actions.find(a => a.id === actionId);
        const currentFlowId = action?.config?.flow_id || '';

        if (flows && flows.length > 0) {
            container.innerHTML = `
                <select id="action-zingbot-flow-${actionId}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white"
                    oninput="Automations.updateActionConfig(${actionId}, 'flow_id', this.value)">
                    <option value="">Select a flow...</option>
                    ${flows.map(f => `<option value="${f.id}" ${currentFlowId == f.id ? 'selected' : ''}>${f.name}</option>`).join('')}
                </select>
            `;
        } else {
            container.innerHTML = `
                <div class="p-2 border border-red-200 bg-red-50 text-red-600 rounded text-xs">
                    <i data-lucide="alert-circle" class="h-4 w-4 inline mr-1 text-red-500"></i>
                    No flows found. <button onclick="App.router('settings')" class="underline font-medium hover:text-red-700">Check Zingbot settings</button> or API key.
                </div>
            `;
            if (window.lucide) window.lucide.createIcons();
        }
    }
};

// Expose to window for onclick handlers
window.Automations = Automations;
