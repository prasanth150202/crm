// js/modules/field_management.js
// Field Management Page for managing all standard and custom fields

const FieldManager = {
    async init() {
        document.getElementById('appContent').innerHTML = '<div class="text-center py-10"><p class="text-gray-500">Loading Fields...</p></div>';
        await this.renderFieldManagement();
    },

    async renderFieldManagement() {
        // Fetch all fields (standard + custom)
        const [standardFields, customFields] = await Promise.all([
            this.getStandardFields(),
            this.getCustomFields()
        ]);
        const container = document.getElementById('appContent');
        let html = `<div class="max-w-3xl mx-auto mt-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Field Management</h2>
                <button onclick="FieldManager.openAddCustomFieldModal()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Add Custom Field</button>
            </div>
            <div class="bg-white shadow rounded-lg p-6 mb-8">
                <h3 class="text-lg font-semibold mb-4">Standard Fields</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Field</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${standardFields.map(f => `
                            <tr>
                                <td class="px-4 py-2">${f.label}</td>
                                <td class="px-4 py-2">${f.type}</td>
                                <td class="px-4 py-2">${f.type === 'select' ? `<button onclick=\"FieldManager.openEditOptionsModal('${f.name}')\" class=\"text-blue-600 hover:underline\">Edit Options</button>` : ''}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Custom Fields</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Field</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${customFields.map(f => `
                            <tr>
                                <td class="px-4 py-2">${f.label}</td>
                                <td class="px-4 py-2">${f.type}</td>
                                <td class="px-4 py-2">
                                    ${f.type === 'select' ? `<button onclick=\"FieldManager.openEditOptionsModal('${f.name}', true)\" class=\"text-blue-600 hover:underline\">Edit Options</button>` : ''}
                                    <button onclick=\"FieldManager.deleteCustomField('${f.name}')\" class=\"text-red-600 hover:underline ml-2\">Delete</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>`;
        container.innerHTML = html;
    },

    async getStandardFields() {
        // Example: fetch from API or static config
        return [
            { name: 'name', label: 'Name', type: 'text' },
            { name: 'email', label: 'Email', type: 'text' },
            { name: 'source', label: 'Source', type: 'select' },
            { name: 'stage_id', label: 'Stage', type: 'select' },
            // ...add more as needed
        ];
    },

    async getCustomFields() {
        // Example: fetch from API
        const res = await fetch('/fields/list_custom.php');
        const data = await res.json();
        return data.fields || [];
    },

    openEditOptionsModal(fieldName, isCustom = false) {
        // Open modal to edit options for select fields
        alert('Edit options for ' + fieldName + (isCustom ? ' (custom)' : ''));
    },

    openAddCustomFieldModal() {
        // Open modal to add a new custom field
        alert('Add new custom field');
    },

    async deleteCustomField(fieldName) {
        if (!confirm('Are you sure you want to delete this custom field?')) return;
        // Call API to delete
        const res = await fetch('/fields/delete_custom_field.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ field_name: fieldName })
        });
        const data = await res.json();
        if (data.success) {
            this.renderFieldManagement();
        } else {
            alert('Failed to delete field: ' + (data.error || 'Unknown error'));
        }
    }
};

window.FieldManager = FieldManager;
