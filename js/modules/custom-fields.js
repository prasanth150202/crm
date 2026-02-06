/**
 * Custom field definitions, visibility, persistence helpers.
 */

const App = window.App || {};

Object.assign(App, {
    async renderManageColumnsList() {
        const fields = await this.getCustomFields();
        const customContainer = document.getElementById('existingFieldsList');
        const mandatoryContainer = document.getElementById('mandatoryFieldsList');
        const visibilitySettings = await this.getFieldVisibility();

        // Retrieve saved order
        const savedOrderObj = this.getColumnOrder();
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

        const mandatoryFields = [
            { name: 'name', label: 'Name', type: 'text' },
            { name: 'title', label: 'Title', type: 'text' },
            { name: 'company', label: 'Company', type: 'text' },
            { name: 'stage_id', label: 'Stage', type: 'select' },
            { name: 'lead_value', label: 'Lead Value', type: 'number' },
            { name: 'source', label: 'Source', type: 'select' },
            { name: 'email', label: 'Email', type: 'email' },
            { name: 'phone', label: 'Phone', type: 'text' },
            { name: 'created_at', label: 'Entered On', type: 'date' },
            { name: 'updated_at', label: 'Lastly Updated', type: 'date' }
        ];

        // Sort mandatory fields
        if (savedStandardOrder && Array.isArray(savedStandardOrder)) {
            mandatoryFields.sort((a, b) => {
                const indexA = savedStandardOrder.indexOf(a.name);
                const indexB = savedStandardOrder.indexOf(b.name);
                if (indexA !== -1 && indexB !== -1) return indexA - indexB;
                if (indexA !== -1) return -1;
                if (indexB !== -1) return 1;
                return 0;
            });
        }

        // Sort custom fields
        if (savedCustomOrder && Array.isArray(savedCustomOrder)) {
            fields.sort((a, b) => {
                const indexA = savedCustomOrder.indexOf(a.name);
                const indexB = savedCustomOrder.indexOf(b.name);
                if (indexA !== -1 && indexB !== -1) return indexA - indexB;
                if (indexA !== -1) return -1;
                if (indexB !== -1) return 1;
                return 0;
            });
        }

        mandatoryContainer.innerHTML = mandatoryFields.map((field, index) => {
            const isVisible = this.isFieldVisible(field.name, 'standard', visibilitySettings);
            return `
                <div class="flex items-center justify-between bg-gray-50 p-2 rounded draggable-item" draggable="true" data-name="${field.name}" data-type="standard">
                    <div class="flex items-center">
                        <i data-lucide="grip-vertical" class="h-4 w-4 text-gray-400 mr-2 cursor-move"></i>
                        <span class="text-sm font-medium text-gray-700 font-sans">${field.label}</span>
                        <span class="text-[10px] text-gray-400 ml-2 uppercase tracking-wide bg-gray-200 px-1.5 py-0.5 rounded leading-none">
                            ${field.type}
                        </span>
                    </div>
                    <label class="flex items-center cursor-pointer">
                        <span class="text-xs text-gray-600 mr-2">${isVisible ? 'Visible' : 'Hidden'}</span>
                        <div class="relative">
                            <input type="checkbox" ${isVisible ? 'checked' : ''} 
                                   onchange="App.toggleFieldVisibility('${field.name}', 'standard', this.checked)"
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </div>
                    </label>
                </div>
            `;
        }).join('');

        // Initialize Drag and Drop
        this.initDragAndDrop(mandatoryContainer);

        if (fields.length === 0) {
            customContainer.innerHTML = '<p class="text-xs text-gray-500">No custom fields added yet.</p>';
            return;
        }

        customContainer.innerHTML = fields.map(field => {
            const isVisible = this.isFieldVisible(field.name, 'custom', visibilitySettings);
            const typeLabel = (field.type || 'text').replace('_', ' ');

            return `
                <div class="flex items-center justify-between bg-gray-50 p-2 rounded draggable-item" draggable="true" data-name="${field.name}" data-type="custom">
                    <div class="flex items-center">
                        <i data-lucide="grip-vertical" class="h-4 w-4 text-gray-400 mr-2 cursor-move"></i>
                        <span class="text-sm font-medium text-gray-700 font-sans">${field.name}</span>
                        <span class="text-[10px] text-gray-400 ml-2 uppercase tracking-wide bg-gray-200 px-1.5 py-0.5 rounded leading-none">
                            ${typeLabel}
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <label class="flex items-center cursor-pointer">
                            <span class="text-xs text-gray-600 mr-2">${isVisible ? 'Visible' : 'Hidden'}</span>
                            <div class="relative">
                                <input type="checkbox" ${isVisible ? 'checked' : ''} 
                                       onchange="App.toggleFieldVisibility('${field.name}', 'custom', this.checked)"
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </div>
                        </label>
                        <button onclick="App.deleteCustomField('${field.name.replace(/'/g, "\\'")}')" 
                                class="text-red-600 hover:text-red-800 text-xs px-2 py-1 rounded hover:bg-red-50">Delete</button>
                    </div>
                </div>
            `;
        }).join('');

        // Initialize Drag and Drop for custom fields too
        this.initDragAndDrop(customContainer);
    },

    deleteCustomField(name) {
        this.fieldToDelete = name;
        document.getElementById('deleteFieldName').textContent = name;
        document.getElementById('deleteFieldModal').classList.remove('hidden');

        const btn = document.getElementById('confirmDeleteBtn');
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);

        newBtn.addEventListener('click', async () => {
            try {
                const response = await fetch(`${this.apiUrl}/leads/delete_custom_field.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        org_id: this.getUser().org_id,
                        field_name: this.fieldToDelete
                    })
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    let customFields = this.getCustomFields();
                    const updatedFields = (await customFields).filter(f => f.name !== this.fieldToDelete);
                    localStorage.setItem('crm_custom_fields_' + this.getUser().org_id, JSON.stringify(updatedFields));

                    this.showToast(`Field deleted successfully (${result.updated_leads} leads updated)`, 'success');
                    this.renderManageColumnsList();
                    this.loadLeads();
                } else {
                    this.showToast(result.error || 'Failed to delete field', 'error');
                }
            } catch (e) {
                this.showToast('Error deleting field: ' + e.message, 'error');
            }

            this.closeDeleteFieldModal();
        });
    },

    closeDeleteFieldModal() {
        document.getElementById('deleteFieldModal').classList.add('hidden');
        this.fieldToDelete = null;
    },

    async openManageColumns() {
        document.getElementById('manageColumnsModal').classList.remove('hidden');
        await this.renderManageColumnsList();
    },

    closeManageColumns() {
        document.getElementById('manageColumnsModal').classList.add('hidden');
        document.getElementById('addFieldForm').reset();
        this.toggleFieldOptions();
    },

    toggleFieldOptions() {
        const type = document.getElementById('fieldTypeSelect').value;
        const container = document.getElementById('fieldOptionsContainer');
        if (type === 'select') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    },

    initDragAndDrop(container) {
        let dragSrcEl = null;

        function handleDragStart(e) {
            this.style.opacity = '0.4';
            dragSrcEl = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDragEnter(e) {
            this.classList.add('border-2', 'border-blue-500');
        }

        function handleDragLeave(e) {
            this.classList.remove('border-2', 'border-blue-500');
        }

        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }

            if (dragSrcEl !== this) {
                // Swap DOM elements
                const parent = this.parentNode;
                const next = this.nextSibling === dragSrcEl ? this : this.nextSibling;
                parent.insertBefore(dragSrcEl, this);

                // Re-initialize icons
                if (window.lucide) window.lucide.createIcons();

                // Save Order
                App.saveColumnOrder();
            }
            return false;
        }

        function handleDragEnd(e) {
            this.style.opacity = '1';
            const items = container.querySelectorAll('.draggable-item');
            items.forEach(function (item) {
                item.classList.remove('border-2', 'border-blue-500');
            });
        }

        const items = container.querySelectorAll('.draggable-item');
        items.forEach(function (item) {
            item.addEventListener('dragstart', handleDragStart, false);
            item.addEventListener('dragenter', handleDragEnter, false);
            item.addEventListener('dragover', handleDragOver, false);
            item.addEventListener('dragleave', handleDragLeave, false);
            item.addEventListener('drop', handleDrop, false);
            item.addEventListener('dragend', handleDragEnd, false);
        });
    },

    async saveColumnOrder() {
        const mandatoryContainer = document.getElementById('mandatoryFieldsList');
        const mandatoryItems = mandatoryContainer.querySelectorAll('.draggable-item');
        const mandatoryOrder = Array.from(mandatoryItems).map(item => item.getAttribute('data-name'));

        const customContainer = document.getElementById('existingFieldsList');
        const customItems = customContainer.querySelectorAll('.draggable-item');
        const customOrder = Array.from(customItems).map(item => item.getAttribute('data-name'));

        localStorage.setItem('crm_column_order_' + this.getUser().org_id, JSON.stringify(mandatoryOrder));
        localStorage.setItem('crm_custom_column_order_' + this.getUser().org_id, JSON.stringify(customOrder));

        // Refresh leads table to reflect new order
        this.loadLeads();
        this.showToast('Column order saved', 'success');
    },

    getColumnOrder() {
        try {
            const standard = localStorage.getItem('crm_column_order_' + this.getUser().org_id);
            const custom = localStorage.getItem('crm_custom_column_order_' + this.getUser().org_id);
            return {
                standard: standard ? JSON.parse(standard) : null,
                custom: custom ? JSON.parse(custom) : null
            };
        } catch (e) {
            console.error('Error parsing column order', e);
            return { standard: null, custom: null };
        }
    },

    async saveNewField(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const fieldName = formData.get('fieldName').trim();
        const fieldType = formData.get('fieldType') || 'text';
        const fieldOptions = formData.get('fieldOptions') || '';

        if (!fieldName) return;

        const standardFields = ['name', 'title', 'company', 'lead_value', 'email', 'phone', 'source', 'stage_id', 'created_at', 'updated_at', 'id', 'org_id'];
        if (standardFields.includes(fieldName.toLowerCase())) {
            alert('This field name is reserved. Please choose another.');
            return;
        }

        let customFields = await this.getCustomFields();
        const exists = customFields.some(f => f.name.toLowerCase() === fieldName.toLowerCase());

        if (exists) {
            alert('A custom field with this name already exists.');
            return;
        }

        const newField = {
            name: fieldName,
            type: fieldType,
            options: fieldType === 'select' ? fieldOptions.split(',').map(o => o.trim()).filter(Boolean) : []
        };

        await this.saveCustomFieldToDatabase(fieldName, fieldType, fieldOptions);
        customFields.push(newField);

        e.target.reset();
        this.toggleFieldOptions();
        this.renderManageColumnsList();
        this.loadLeads();
    },

    normalizeCustomFieldList(rawList) {
        if (!Array.isArray(rawList)) return [];
        return rawList.map(f => {
            if (typeof f === 'string') {
                return { name: f, type: 'text', options: [] };
            }
            return {
                name: f.name,
                type: f.type || 'text',
                options: Array.isArray(f.options) ? f.options : []
            };
        });
    },

    ensureDefaultCustomFields(fields) {
        const defaults = [
            { name: 'city', type: 'text', options: [] },
            { name: 'state', type: 'text', options: [] },
            { name: 'country', type: 'text', options: [] }
        ];

        const existing = new Set(fields.map(f => f.name.toLowerCase()));
        defaults.forEach(def => {
            if (!existing.has(def.name.toLowerCase())) {
                fields.push(def);
            }
        });
        return fields;
    },

    async refreshCustomFieldsFromServer(force = false) {
        const user = this.getUser();
        if (!user) return [];

        const storageKey = 'crm_custom_fields_' + user.org_id;
        const cached = localStorage.getItem(storageKey);

        if (cached && !force) {
            const parsed = this.normalizeCustomFieldList(JSON.parse(cached));
            this.cachedCustomFields = parsed;
            return parsed;
        }

        try {
            const res = await this.api('/leads/custom_fields.php');
            if (res && res.success && Array.isArray(res.fields)) {
                let normalized = this.normalizeCustomFieldList(res.fields);
                normalized = this.ensureDefaultCustomFields(normalized);
                localStorage.setItem(storageKey, JSON.stringify(normalized));
                this.cachedCustomFields = normalized;
                return normalized;
            }
        } catch (err) {
            console.error('Failed to refresh custom fields from server:', err);
        }

        return this.getCustomFields();
    },

    async getCustomFields() {
        const user = this.getUser();
        if (!user) return [];

        try {
            const response = await fetch(`${this.apiUrl}/leads/custom_fields_simple.php?org_id=${user.org_id}`);
            const data = await response.json();
            if (data && data.success && Array.isArray(data.fields)) {
                return this.normalizeCustomFieldList(data.fields);
            }
        } catch (e) {
            console.error('Failed to get custom fields:', e);
        }

        return [];
    },

    async getFieldVisibility() {
        try {
            const result = await this.api('/leads/field_visibility.php');
            if (result && result.success) {
                return result.settings || [];
            }
        } catch (e) {
            console.error('Failed to get field visibility:', e);
        }
        return [];
    },

    isFieldVisible(fieldName, fieldType, visibilitySettings) {
        const setting = visibilitySettings.find(s => s.field_name === fieldName && s.field_type === fieldType);
        return setting ? setting.is_visible : true;
    },

    async toggleFieldVisibility(fieldName, fieldType, isVisible) {
        try {
            const result = await this.api('/leads/field_visibility.php', 'POST', {
                field_name: fieldName,
                field_type: fieldType,
                is_visible: isVisible
            });

            if (result && result.success) {
                this.showToast(`Field ${isVisible ? 'shown' : 'hidden'} successfully`, 'success');
                await this.renderManageColumnsList();
                this.loadLeads();
            } else {
                this.showToast('Failed to update field visibility', 'error');
            }
        } catch (e) {
            console.error('Failed to toggle field visibility:', e);
            this.showToast('Failed to update field visibility', 'error');
        }
    },

    async saveCustomFieldToDatabase(fieldName, fieldType, fieldOptions) {
        try {
            const response = await fetch(`${this.apiUrl}/leads/create_custom_field.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    org_id: this.getUser().org_id,
                    field_name: fieldName,
                    field_type: fieldType,
                    field_options: fieldOptions || ''
                })
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Failed to save field');
            }
        } catch (e) {
            console.error('Failed to save custom field:', e);
            throw e;
        }
    }
});

window.App = App;
