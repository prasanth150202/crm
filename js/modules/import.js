/**
 * CSV import workflow.
 */

const App = window.App || {};

Object.assign(App, {
    openImportModal() {
        App.openModal('importModal');
        document.getElementById('importStep1').classList.remove('hidden');
        document.getElementById('importStep2').classList.add('hidden');
        document.getElementById('importStep3').classList.add('hidden');
        this.importData = null;
    },

    closeImportModal() {
        App.closeModal('importModal');
        const fileInput = document.getElementById('importFileInput');
        if (fileInput) fileInput.value = '';
        this.importData = null;
    },

    async handleFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        if (!file.name.endsWith('.csv')) {
            this.showToast('Please upload a CSV file', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('org_id', this.getUser().org_id);

        try {
            document.getElementById('importStep1').innerHTML = '<div class="text-center py-10"><p class="text-gray-500">Analyzing file...</p></div>';

            const response = await fetch(`${this.apiUrl}/leads/import_preview.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.importData = { file, ...data };
                await this.showImportMapping(data);
            } else {
                this.showToast(data.error || 'Failed to process file', 'error');
                this.closeImportModal();
            }
        } catch (error) {
            console.error('Import error:', error);
            this.showToast('Failed to upload file', 'error');
            this.closeImportModal();
        }
    },

    async showImportMapping(data) {
        document.getElementById('importStep1').classList.add('hidden');
        document.getElementById('importStep2').classList.remove('hidden');

        const fieldsResponse = await this.api(`/leads/get_import_fields.php?org_id=${this.getUser().org_id}`);
        const standardFields = fieldsResponse?.standardFields || [];
        const customFields = fieldsResponse?.customFields || [];
        const allCRMFields = [...standardFields, ...customFields].filter(f => f.id !== 'create_new');

        this.importData.headers = data.headers;
        this._testData = data.preview[0] || []; // Store first row for preview

        let html = `
            <div class="space-y-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h5 class="text-sm font-bold text-blue-900 mb-2">Map CSV Columns to CRM Fields</h5>
                    <p class="text-xs text-blue-700">You can add multiple CSV columns to a single CRM field. Use separators like spaces or commas to join them.</p>
                </div>
                
                <div class="space-y-4">
        `;

        allCRMFields.forEach(f => {
            const suggestedCol = this.suggestFieldMapping(f.label || f.id);
            const initialBlocks = suggestedCol ? [`{{${suggestedCol}}}`] : [];

            html += `
                <div class="field-mapping-row p-4 bg-gray-50 rounded-lg border border-gray-200" data-field-id="${f.id}">
                    <div class="flex justify-between items-center mb-3">
                        <label class="text-sm font-semibold text-gray-700">
                            ${f.label} 
                            ${f.required ? '<span class="text-red-500 ml-1">*</span>' : ''}
                        </label>
                    </div>
                    
                    <div id="container_${f.id}" class="space-y-2">
                        ${this.renderMappingBlocks(f.id, initialBlocks)}
                    </div>
                    
                    <div class="flex gap-4 mt-3">
                        <button onclick="App.addMappingBlock('${f.id}', 'dynamic')" class="text-xs font-medium text-blue-600 hover:text-blue-800 flex items-center">
                            <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Add CSV Column
                        </button>
                        <button onclick="App.addMappingBlock('${f.id}', 'static')" class="text-xs font-medium text-gray-600 hover:text-gray-800 flex items-center">
                            <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Add Static Text
                        </button>
                    </div>
                </div>
            `;
        });

        html += `
                <div class="pt-4 border-t border-gray-200">
                    <button onclick="App.showCreateCustomFieldModal('', 0)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        + Create New Custom Field
                    </button>
                </div>
            </div>
        `;

        document.getElementById('columnMappingList').innerHTML = html;

        document.getElementById('importStats').innerHTML = `
            <div class="flex items-center justify-between bg-gray-50 rounded-md p-3 mb-4 text-sm border border-gray-200">
                <div>Total Rows: <span class="font-bold">${data.totalRows}</span></div>
                <div>Duplicates: <span class="font-bold">${data.duplicateCount}</span></div>
            </div>
        `;
    },

    renderMappingBlocks(fieldId, mappingArray) {
        let html = '';
        const headers = this.importData.headers;
        const previewRow = this._testData || [];

        for (let i = 0; i < mappingArray.length; i++) {
            const val = mappingArray[i];
            const isDynamic = val.startsWith('{{') && val.endsWith('}}');
            const blockValue = isDynamic ? val.slice(2, -2) : val;

            html += `
                <div class="mapping-block flex items-center gap-2 bg-white p-2 rounded border border-gray-200 shadow-sm" data-type="${isDynamic ? 'dynamic' : 'static'}">
                    <div class="flex-1">
                        ${isDynamic ? `
                            <select class="block-value w-full px-2 py-1.5 border border-gray-300 rounded text-sm bg-white focus:ring-1 focus:ring-blue-500">
                                <option value="">-- Select CSV Column --</option>
                                ${headers.map((h, idx) => `<option value="${h}" ${h === blockValue ? 'selected' : ''}>${h} ${previewRow[idx] ? `(${previewRow[idx]})` : ''}</option>`).join('')}
                            </select>
                        ` : `
                            <input type="text" class="block-value w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500" placeholder="Static text..." value="${blockValue}">
                        `}
                    </div>
                    <div class="w-16">
                        <input type="text" class="block-separator w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-center" placeholder="+" value=" " title="Separator / Joiner">
                    </div>
                    <button onclick="this.closest('.mapping-block').remove()" class="text-gray-400 hover:text-red-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            `;
        }
        return html;
    },

    addMappingBlock(fieldId, type) {
        const container = document.getElementById(`container_${fieldId}`);
        const headers = this.importData.headers;
        const previewRow = this._testData || [];

        const html = `
            <div class="mapping-block flex items-center gap-2 bg-white p-2 rounded border border-gray-200 shadow-sm animate-in fade-in slide-in-from-top-1" data-type="${type}">
                <div class="flex-1">
                    ${type === 'dynamic' ? `
                        <select class="block-value w-full px-2 py-1.5 border border-gray-300 rounded text-sm bg-white focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Select CSV Column --</option>
                            ${headers.map((h, idx) => `<option value="${h}">${h} ${previewRow[idx] ? `(${previewRow[idx]})` : ''}</option>`).join('')}
                        </select>
                    ` : `
                        <input type="text" class="block-value w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500" placeholder="Static text..." value="">
                    `}
                </div>
                <div class="w-16">
                    <input type="text" class="block-separator w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-center" placeholder="+" value=" ">
                </div>
                <button onclick="this.closest('.mapping-block').remove()" class="text-gray-400 hover:text-red-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', html);
    },

    suggestFieldMapping(fieldNameOrId) {
        const normalized = fieldNameOrId.toLowerCase().trim();
        const headers = this.importData.headers;

        const keywords = {
            'name': ['name', 'full name', 'fullname', 'contact name', 'lead name'],
            'first_name': ['first name', 'firstname', 'fname', 'given name'],
            'last_name': ['last name', 'lastname', 'lname', 'surname', 'family name'],
            'email': ['email', 'e-mail', 'email address', 'mail'],
            'phone': ['phone', 'telephone', 'mobile', 'phone number', 'contact number'],
            'company': ['company', 'organization', 'business', 'company name'],
            'title': ['title', 'job title', 'position', 'role'],
            'lead_value': ['value', 'lead value', 'deal value', 'amount', 'price'],
            'source': ['source', 'lead source', 'origin'],
            'stage_id': ['status', 'stage', 'stage_id', 'lead status'],
            'address': ['address', 'street', 'location'],
            'city': ['city', 'town'],
            'state': ['state', 'province', 'region'],
            'country': ['country', 'nation'],
            'zip_code': ['zip', 'postcode', 'postal', 'zip code'],
            'website': ['website', 'url', 'site', 'link'],
            'description': ['description', 'notes', 'comments', 'about', 'summary']
        };

        const targetKeywords = keywords[normalized] || [normalized];

        for (const header of headers) {
            const hNorm = header.toLowerCase().trim();
            if (targetKeywords.some(k => hNorm.includes(k) || k.includes(hNorm))) {
                return header;
            }
        }
        return null;
    },

    async handleFieldMapping(index, value) {
        // Obsolete with new UI structure, but kept for compatibility if called
    },

    async showCreateCustomFieldModal(suggestedName, selectIndex) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-[3000] overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center';
        modal.style.zIndex = '3000'; // Ensure it's above the import modal (which is 2000)
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md mx-4 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Custom Field</h3>
                <form id="quickCustomFieldForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Field Name</label>
                        <input type="text" id="quickFieldName" value="${suggestedName}" 
                               class="w-full border-gray-300 rounded-md p-2" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Field Type</label>
                        <select id="quickFieldType" class="w-full border-gray-300 rounded-md p-2 bg-white">
                            <option value="text">Short Text</option>
                            <option value="textarea">Long Text</option>
                            <option value="date">Date</option>
                            <option value="select">Dropdown</option>
                        </select>
                    </div>
                    <div class="mb-4 hidden" id="quickFieldOptionsContainer">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Options (comma separated)</label>
                        <textarea id="quickFieldOptions" rows="2" 
                                  class="w-full border-gray-300 rounded-md p-2" 
                                  placeholder="Option 1, Option 2, Option 3"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="this.closest('.fixed').remove()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Create Field
                        </button>
                    </div>
                </form>
            </div>
        `;

        document.body.appendChild(modal);

        document.getElementById('quickFieldType').onchange = function () {
            const container = document.getElementById('quickFieldOptionsContainer');
            if (this.value === 'select') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        };

        document.getElementById('quickCustomFieldForm').onsubmit = async (e) => {
            e.preventDefault();

            const fieldName = document.getElementById('quickFieldName').value.trim();
            const fieldType = document.getElementById('quickFieldType').value;
            const fieldOptions = document.getElementById('quickFieldOptions').value;

            if (!fieldName) return;

            try {
                // Check if field already exists BEFORE creating it
                let existingFields = await this.getCustomFields();
                const alreadyExists = existingFields.some(f => f.name.toLowerCase() === fieldName.toLowerCase());

                if (alreadyExists) {
                    this.showToast('Field name already exists', 'error');
                    return;
                }

                // Create the field in the database
                const response = await fetch(`${this.apiUrl}/leads/create_custom_field.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        org_id: this.getUser().org_id,
                        field_name: fieldName,
                        field_type: fieldType,
                        field_options: fieldOptions
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.showToast('Custom field created successfully!', 'success');
                    modal.remove();
                    // Refresh the mapping UI to show the new field
                    this.showImportMapping(this.importData);
                } else {
                    this.showToast(result.error || 'Failed to create field', 'error');
                }
            } catch (error) {
                console.error('Failed to create custom field:', error);
                this.showToast('Failed to create custom field', 'error');
            }
        };
    },

    proceedToImportOptions() {
        const columnMapping = {};
        let hasMapping = false;
        let missingName = true;

        document.querySelectorAll('.field-mapping-row').forEach(row => {
            const fieldId = row.dataset.fieldId;
            const blocks = row.querySelectorAll('.mapping-block');
            const sources = [];

            blocks.forEach(block => {
                const type = block.dataset.type;
                const value = block.querySelector('.block-value').value;
                const sep = block.querySelector('.block-separator').value;

                if (value !== '') {
                    sources.push(type === 'dynamic' ? `{{${value}}}` : value);
                    if (sep !== '') sources.push(sep);
                }
            });

            if (sources.length > 0) {
                columnMapping[fieldId] = sources;
                hasMapping = true;
                if (fieldId === 'name') missingName = false;
            }
        });

        if (!hasMapping) {
            this.showToast('Please map at least one field', 'warning');
            return;
        }

        if (missingName) {
            this.showToast('Name field is required', 'error');
            return;
        }

        this.importData.columnMapping = columnMapping;
        this.renderImportOptions();
        document.getElementById('importStep2').classList.add('hidden');
        document.getElementById('importStep3').classList.remove('hidden');
    },

    renderImportOptions() {
        const step3 = document.getElementById('importStep3');
        if (!step3) return;

        step3.innerHTML = `
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Import Options</h3>
                <button onclick="App.closeImportModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <div class="mb-6">
                <p class="text-sm text-gray-700 font-medium mb-4">How should we handle duplicate leads (matched by email)?</p>
                <div class="space-y-4">
                    <label class="flex items-start cursor-pointer">
                        <div class="flex items-center h-5">
                            <input name="importMode" type="radio" value="skip" checked class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        </div>
                        <div class="ml-3 text-sm">
                            <span class="font-medium text-gray-900">Skip Duplicates</span>
                            <p class="text-gray-500">Only import leads that don't already exist. Recommended for new lists.</p>
                        </div>
                    </label>
                    <label class="flex items-start cursor-pointer">
                        <div class="flex items-center h-5">
                            <input name="importMode" type="radio" value="update" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        </div>
                        <div class="ml-3 text-sm">
                            <span class="font-medium text-gray-900">Update Existing (Merge)</span>
                            <p class="text-gray-500">Update existing leads only with non-empty fields from the CSV. Preserves existing data.</p>
                        </div>
                    </label>
                    <label class="flex items-start cursor-pointer">
                        <div class="flex items-center h-5">
                            <input name="importMode" type="radio" value="overwrite" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        </div>
                        <div class="ml-3 text-sm">
                            <span class="font-medium text-gray-900">Overwrite Existing</span>
                            <p class="text-gray-500">Replace all existing lead data with the CSV data. Use with caution.</p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="flex justify-end space-x-3 border-t pt-4">
                <button onclick="document.getElementById('importStep3').classList.add('hidden'); document.getElementById('importStep2').classList.remove('hidden');"
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Back</button>
                <button onclick="App.executeImport()"
                    class="px-6 py-2 bg-green-600 text-white font-bold rounded-md hover:bg-green-700 shadow-sm">Start Import</button>
            </div>
        `;
    },

    async executeImport() {
        const importMode = document.querySelector('input[name="importMode"]:checked').value;
        const formData = new FormData();
        formData.append('file', this.importData.file);
        formData.append('org_id', this.getUser().org_id);
        formData.append('user_id', this.getUser().id);
        formData.append('import_mode', importMode);
        formData.append('column_mapping', JSON.stringify(this.importData.columnMapping));

        try {
            document.getElementById('importStep3').innerHTML = '<div class="text-center py-10"><div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div><p class="text-gray-500 mt-4">Importing leads...</p></div>';

            const response = await fetch(`${this.apiUrl}/leads/import.php`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showImportResults(result);
            } else {
                this.showToast(result.error || 'Import failed', 'error');
                this.closeImportModal();
            }
        } catch (error) {
            console.error('Import error:', error);
            this.showToast('Import failed', 'error');
            this.closeImportModal();
        }
    },

    showImportResults(result) {
        const hasErrors = result.error_count > 0;
        document.getElementById('importStep3').innerHTML = `
            <div class="text-center py-6">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full ${hasErrors ? 'bg-yellow-100' : 'bg-green-100'} mb-4">
                    <svg class="h-10 w-10 ${hasErrors ? 'text-yellow-600' : 'text-green-600'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Import Complete!</h3>
                
                <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Total Rows</p>
                            <p class="text-2xl font-bold text-gray-900">${result.total_rows}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Imported</p>
                            <p class="text-2xl font-bold text-green-600">${result.success_count}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Updated</p>
                            <p class="text-2xl font-bold text-blue-600">${result.updated_count}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Skipped</p>
                            <p class="text-2xl font-bold text-yellow-600">${result.skipped_count}</p>
                        </div>
                        ${result.error_count > 0 ? `
                        <div class="col-span-2">
                            <p class="text-sm text-gray-500">Errors</p>
                            <p class="text-2xl font-bold text-red-600">${result.error_count}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>

                ${result.skipped_examples && result.skipped_examples.length > 0 ? `
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4 text-left">
                    <h4 class="text-sm font-medium text-yellow-800 mb-2">Skipped Examples (Duplicates):</h4>
                    <ul class="text-xs text-yellow-700 space-y-1 max-h-40 overflow-y-auto">
                        ${result.skipped_examples.map(ex => `<li>• ${ex}</li>`).join('')}
                    </ul>
                </div>
                ` : ''}

                ${hasErrors && result.errors && result.errors.length > 0 ? `
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-4 text-left">
                    <h4 class="text-sm font-medium text-red-800 mb-2">Errors (first 10):</h4>
                    <ul class="text-xs text-red-700 space-y-1 max-h-40 overflow-y-auto">
                        ${result.errors.map(err => `<li>• ${err}</li>`).join('')}
                    </ul>
                </div>
                ` : ''}

                <button onclick="App.closeImportModal(); App.loadLeads();" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Done
                </button>
            </div>
        `;
    },

    async showImportHistory() {
        try {
            const result = await this.api('/leads/import_history.php');
            if (result && result.success) {
                this.renderImportHistory(result.jobs || []);
            } else {
                this.showToast(result?.error || 'Failed to load import history', 'error');
            }
        } catch (error) {
            console.error('Failed to load import history:', error);
            this.showToast('Failed to load import history', 'error');
        }
    },

    renderImportHistory(jobs) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-[3000] overflow-y-auto';
        modal.style.zIndex = '3000';
        modal.innerHTML = `
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" onclick="this.parentElement.parentElement.remove()"></div>
                <div class="relative bg-white rounded-lg max-w-4xl w-full p-6 max-h-[80vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Import History</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">File</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">User</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Mode</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Results</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${jobs.length === 0
                                    ? `<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No import history yet.</td></tr>`
                                    : jobs.map(job => `
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">${new Date(job.created_at).toLocaleString()}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">${job.filename || '-'}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">${job.user_email || 'N/A'}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full ${job.import_mode === 'skip' ? 'bg-gray-100 text-gray-800' : job.import_mode === 'update' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}">
                                                ${job.import_mode || '-'}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-600">
                                            <div>✓ ${job.success_count} | ↻ ${job.updated_count} | ⊘ ${job.skipped_count} | ✕ ${job.error_count}</div>
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full ${job.status === 'completed' ? 'bg-green-100 text-green-800' : job.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}">
                                                ${job.status || '-'}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
});

window.App = App;
