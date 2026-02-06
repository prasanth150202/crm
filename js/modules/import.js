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

        const crmFields = ['name', 'email', 'phone', 'company', 'title', 'lead_value', 'source', 'stage_id'];
        const fieldsResponse = await this.api(`/leads/get_import_fields.php?org_id=${this.getUser().org_id}`);
        const customFields = fieldsResponse?.customFields || [];

        const mappingHtml = data.headers.map((header, index) => {
            const suggestedField = this.suggestFieldMapping(header);
            return `
                <div class="flex items-center space-x-4 py-2 border-b">
                    <div class="w-1/4 text-sm font-medium text-gray-700">${header}</div>
                    <div class="w-8 text-center text-gray-400">→</div>
                    <div class="w-1/3">
                        <select id="mapping_${index}" class="w-full text-sm border-gray-300 rounded-md p-2 bg-white" onchange="App.handleFieldMapping(${index}, this.value)">
                            <option value="">Skip this column</option>
                            <optgroup label="Standard Fields">
                                ${crmFields.map(field => `
                                    <option value="${field}" ${suggestedField === field ? 'selected' : ''}>
                                        ${field.charAt(0).toUpperCase() + field.slice(1).replace('_', ' ')}
                                    </option>
                                `).join('')}
                            </optgroup>
                            ${customFields.length > 0 ? `
                                <optgroup label="Custom Fields">
                                    ${customFields.filter(field => field.type === 'custom').map(field => `
                                        <option value="${field.id}">
                                            ${field.label}
                                        </option>
                                    `).join('')}
                                </optgroup>
                            ` : ''}
                            <optgroup label="Actions">
                                <option value="create_new">+ Create New Custom Field</option>
                            </optgroup>

                        </select>
                    </div>
                    <div class="w-1/4 text-xs text-gray-500">${data.preview[0] && data.preview[0][index] ? data.preview[0][index] : ''}</div>
                </div>
            `;
        }).join('');

        document.getElementById('columnMappingList').innerHTML = mappingHtml;
        document.getElementById('importStats').innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-blue-900">Total Rows:</span>
                    <span class="text-sm font-bold text-blue-900">${data.totalRows}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-blue-900">Duplicates Found:</span>
                    <span class="text-sm font-bold text-blue-900">${data.duplicateCount}</span>
                </div>
            </div>
        `;
    },

    suggestFieldMapping(header) {
        const normalized = header.toLowerCase().trim();
        const mappings = {
            'name': ['name', 'full name', 'fullname', 'contact name', 'lead name'],
            'email': ['email', 'e-mail', 'email address', 'mail'],
            'phone': ['phone', 'telephone', 'mobile', 'phone number', 'contact number'],
            'company': ['company', 'organization', 'business', 'company name'],
            'title': ['title', 'job title', 'position', 'role'],
            'lead_value': ['value', 'lead value', 'deal value', 'amount', 'price'],
            'source': ['source', 'lead source', 'origin'],
            'stage_id': ['status', 'stage', 'stage_id', 'lead status']
        };

        for (const [field, keywords] of Object.entries(mappings)) {
            if (keywords.some(keyword => normalized.includes(keyword))) {
                return field;
            }
        }

        const customFields = this.importData?.customFields || [];
        for (const customField of customFields) {
            if (normalized === customField.field_name.toLowerCase()) {
                return `custom_${customField.field_name}`;
            }
        }

        return '';
    },

    async handleFieldMapping(index, value) {
        if (value === 'create_new') {
            const header = this.importData.headers[index];
            await this.showCreateCustomFieldModal(header, index);
            return;
        }

        if (value && value !== '') {
            const allSelects = document.querySelectorAll('[id^="mapping_"]');
            let duplicateFound = false;

            allSelects.forEach((select, i) => {
                if (i !== index && select.value === value) {
                    duplicateFound = true;
                    select.value = '';
                }
            });

            if (duplicateFound) {
                this.showToast('Field can only be mapped once. Previous mapping cleared.', 'warning');
            }
        }
    },

    async showCreateCustomFieldModal(suggestedName, selectIndex) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center';
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
                    // Add to localStorage cache
                    existingFields.push({
                        name: fieldName,
                        type: fieldType,
                        options: fieldType === 'select' ? fieldOptions.split(',').map(o => o.trim()) : []
                    });
                    localStorage.setItem('crm_custom_fields_' + this.getUser().org_id, JSON.stringify(existingFields));

                    // Update the mapping dropdown
                    const select = document.getElementById(`mapping_${selectIndex}`);
                    const customGroup = select.querySelector('optgroup[label="Custom Fields"]');
                    const newOption = document.createElement('option');
                    newOption.value = `custom_${fieldName}`;
                    newOption.textContent = fieldName;
                    newOption.selected = true;

                    if (customGroup) {
                        customGroup.appendChild(newOption);
                    } else {
                        const newGroup = document.createElement('optgroup');
                        newGroup.label = 'Custom Fields';
                        newGroup.appendChild(newOption);
                        select.insertBefore(newGroup, select.querySelector('optgroup[label="Actions"]'));
                    }

                    this.showToast('Custom field created successfully!', 'success');
                    modal.remove();
                } else {
                    this.showToast(result.error || 'Failed to create field', 'error');
                }
            } catch (error) {
                this.showToast('Failed to create custom field', 'error');
            }
        };
    },

    proceedToImportOptions() {
        const columnMapping = {};
        const headers = this.importData.headers;

        headers.forEach((header, index) => {
            const select = document.getElementById(`mapping_${index}`);
            if (select && select.value && select.value !== 'create_new') {
                columnMapping[header] = select.value;
            }
        });

        if (Object.keys(columnMapping).length === 0) {
            this.showToast('Please map at least one column', 'warning');
            return;
        }

        if (!columnMapping[headers.find(h => columnMapping[h] === 'name')]) {
            const hasName = Object.values(columnMapping).includes('name');
            if (!hasName) {
                this.showToast('Name field is required', 'error');
                return;
            }
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
        formData.append('user_id', this.getUser().user_id);
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
            if (result.success) {
                this.renderImportHistory(result.jobs);
            }
        } catch (error) {
            console.error('Failed to load import history:', error);
        }
    },

    renderImportHistory(jobs) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 overflow-y-auto';
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
                                ${jobs.map(job => `
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">${new Date(job.created_at).toLocaleString()}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">${job.filename}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">${job.user_email || 'N/A'}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full ${job.import_mode === 'skip' ? 'bg-gray-100 text-gray-800' : job.import_mode === 'update' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}">
                                                ${job.import_mode}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-600">
                                            <div>✓ ${job.success_count} | ↻ ${job.updated_count} | ⊘ ${job.skipped_count} | ✕ ${job.error_count}</div>
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full ${job.status === 'completed' ? 'bg-green-100 text-green-800' : job.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}">
                                                ${job.status}
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
