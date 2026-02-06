<?php
/**
 * All Modals for the Application
 */
?>
<!-- Manage & Create Fields Modal -->
<div id="manageColumnsModal" class="fixed inset-0 z-10 overflow-y-auto hidden"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="App.closeManageColumns()"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Manage Fields</h3>
                <button onclick="App.closeManageColumns()" class="text-gray-400 hover:text-gray-500">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Create New Field Section -->
            <div class="bg-gray-50 p-4 rounded-md mb-6 border border-gray-200">
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Create New Field</h4>
                <form id="addFieldForm" onsubmit="App.saveNewField(event)">
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-700">Field Name</label>
                        <input type="text" name="fieldName" required
                            placeholder="e.g. Industry, Priority"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm border p-2 text-sm">
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-700">Field Type</label>
                        <select name="fieldType" id="fieldTypeSelect"
                            onchange="App.toggleFieldOptions()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm border p-2 bg-white text-sm">
                            <option value="text">Short Text</option>
                            <option value="textarea">Long Text</option>
                            <option value="date">Date</option>
                            <option value="select">Dropdown</option>
                        </select>
                    </div>

                    <div class="mb-3 hidden" id="fieldOptionsContainer">
                        <label class="block text-xs font-medium text-gray-700">Options (comma
                            separated)</label>
                        <textarea name="fieldOptions" rows="2"
                            placeholder="Option 1, Option 2, Option 3"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm border p-2 text-sm"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700">
                            Create Field
                        </button>
                    </div>
                </form>
            </div>

            <!-- Mandatory Fields Section -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Mandatory Fields</h4>
                <div id="mandatoryFieldsList" class="space-y-2 max-h-60 overflow-y-auto">
                    <!-- Dynamic mandatory fields will be loaded here -->
                </div>
            </div>

            <!-- Existing Custom Fields Section -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Custom Fields</h4>
                <div id="existingFieldsList" class="space-y-2 max-h-60 overflow-y-auto">
                    <!-- Dynamic fields will be loaded here -->
                    <p class="text-xs text-gray-500">No custom fields added yet.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Delete Confirmation Modal -->
<div id="deleteFieldModal" class="fixed inset-0 z-20 overflow-y-auto hidden"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true">
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm sm:w-full sm:p-6">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-5">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="deleteModalTitle">
                        Delete Field</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                            Are you sure you want to delete <span id="deleteFieldName"
                                class="font-bold"></span>?
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-6 flex justify-center space-x-3">
                <button type="button" onclick="App.closeDeleteFieldModal()"
                    class="inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:text-sm">
                    Cancel
                </button>
                <button type="button" id="confirmDeleteBtn"
                    class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:text-sm">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chart Configuration Modal -->
<div id="chartConfigModal" class="fixed inset-0 z-20 overflow-y-auto hidden" aria-labelledby="modal-title"
    role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="App.closeChartConfig()"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Configure Charts</h3>
                <button onclick="App.closeChartConfig()" class="text-gray-400 hover:text-gray-500">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <p class="text-sm text-gray-500">Select which charts to display on your Reports dashboard.</p>
                <!-- Standard Charts -->
                <div class="border-t pt-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Standard Charts</h4>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" id="chart_stage"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                            <span class="ml-2 text-sm text-gray-700">Leads by Stage</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" id="chart_source"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                            <span class="ml-2 text-sm text-gray-700">Leads by Source</span>
                        </label>
                    </div>
                </div>
                <!-- Custom Field Charts -->
                <div class="border-t pt-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Custom Field Charts</h4>
                    <div id="customFieldChartsList" class="space-y-2">
                        <!-- Dynamically populated -->
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="App.closeChartConfig()"
                    class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" onclick="App.saveChartConfig()"
                    class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chart Builder Modal -->
<div id="chartBuilderModal" class="fixed inset-0 z-20 overflow-y-auto hidden" aria-labelledby="modal-title"
    role="dialog" aria-modal="true">
    <!-- ... same as in dashboard.html ... -->
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="App.closeChartBuilder()"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="chartBuilderTitle">Create New Chart</h3>
                <button onclick="App.closeChartBuilder()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="chartBuilderForm" class="space-y-4">
                <input type="hidden" id="chartEditId" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chart Title</label>
                    <input type="text" id="chartTitle"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2"
                        placeholder="e.g., Leads by Source" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chart Type</label>
                    <select id="chartType"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 bg-white">
                        <option value="scorecard">📊 Score Card (KPI)</option>
                        <option value="table">📋 Table View</option>
                        <option value="combo">📊 Combo (Dual Axis)</option>
                        <option value="bar">📊 Bar Chart</option>
                        <option value="pie">🥧 Pie Chart</option>
                        <option value="doughnut">🍩 Doughnut Chart</option>
                        <option value="line">📈 Line Chart</option>
                        <option value="area">📉 Area Chart</option>
                        <option value="funnel">🔻 Funnel Chart</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">X-Axis (Dimension)</label>
                    <select id="chartXAxis"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 bg-white">
                        <option value="stage_id">Stage</option>
                        <option value="source">Source</option>
                        <option value="lead_name">Lead Name</option>
                        <option value="company">Company</option>
                        <option value="email">Email</option>
                        <option value="phone">Phone</option>
                        <option value="title">Title</option>
                        <option value="owner_email">Owner</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Y-Axis (Metric)</label>
                    <select id="chartYAxisMetric"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 bg-white">
                        <option value="count">Count of</option>
                        <option value="sum">Sum of</option>
                        <option value="avg">Average of</option>
                        <option value="show">Show the text</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Y-Axis Value</label>
                    <select id="chartYAxisValue"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 bg-white">
                        <option value="leads">Leads</option>
                        <option value="lead_value">Lead Value</option>
                        <option value="notes">Notes</option>
                        <option value="email">Email</option>
                        <option value="phone">Phone</option>
                        <option value="company">Company</option>
                        <option value="title">Title</option>
                        <option value="source">Source</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Color Scheme</label>
                    <select id="chartColorScheme"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 bg-white">
                        <option value="default">Default (Blue/Multi)</option>
                        <option value="green">Green Shades</option>
                        <option value="purple">Purple Shades</option>
                        <option value="warm">Warm Colors</option>
                        <option value="cool">Cool Colors</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sorting</label>
                    <select id="chartSort"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 bg-white">
                        <option value="default">Original / Default</option>
                        <option value="value_desc">Value (High to Low)</option>
                        <option value="value_asc">Value (Low to High)</option>
                        <option value="label_asc">Label (A-Z)</option>
                        <option value="label_desc">Label (Z-A)</option>
                    </select>
                </div>
                <div id="funnelSortContainer" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Funnel Sorting</label>
                    <select id="chartFunnelSort"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 bg-white">
                        <option value="default">Default Order</option>
                        <option value="name">Sort by Name</option>
                        <option value="value">Sort by Value</option>
                    </select>
                </div>
                <div>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" id="chartShowTotal"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">Show Total Row (for tables with numeric values)</span>
                    </label>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="App.closeChartBuilder()"
                        class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">
                        <span id="chartBuilderSubmitText">Create Chart</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- User Management Modal -->
<div id="userModal" class="fixed inset-0 z-20 overflow-y-auto hidden" aria-labelledby="modal-title"
    role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="App.closeUserModal()"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="userModalTitle">Add User</h3>
                <button onclick="App.closeUserModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="userForm" class="space-y-4">
                <input type="hidden" id="userEditId" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="modalUserFullName"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2"
                        placeholder="John Doe" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="modalUserEmail"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2"
                        placeholder="john@example.com" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <input type="text" id="modalUserRole"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2"
                        placeholder="admin, manager, staff, etc." required>
                </div>
                <div class="flex items-center mt-2">
                    <input type="checkbox" id="modalUserSuperAdmin" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label for="modalUserSuperAdmin" class="ml-2 text-sm text-gray-700">Super Admin</label>
                </div>
                
                <!-- Permissions Section -->
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Permissions</h4>
                            <p class="text-xs text-gray-500 mt-1">Select which features this user can access</p>
                        </div>

                    </div>
                    <div id="userPermissionsContainer" class="max-h-80 overflow-y-auto space-y-4 bg-gray-50 rounded-md p-4">
                        <div class="text-center py-4">
                            <p class="text-sm text-gray-500">Loading permissions...</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center mt-4">
                    <input type="checkbox" id="modalUserIsActive"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                    <label for="modalUserIsActive" class="ml-2 text-sm text-gray-700">Active (user can log in)</label>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="App.closeUserModal()"
                        class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">
                        <span id="userSubmitText">Add User</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="fixed inset-0 z-20 overflow-y-auto hidden"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="App.closeChangePasswordModal()"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Change Password</h3>
                <button onclick="App.closeChangePasswordModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="changePasswordForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" id="currentPassword"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" id="newPassword"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2"
                        minlength="6" required>
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" id="confirmPassword"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2"
                        minlength="6" required>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="App.closeChangePasswordModal()"
                        class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Lead Modal -->
<div id="createLeadModal" class="fixed inset-0 overflow-y-auto hidden" aria-labelledby="modal-title"
    role="dialog" aria-modal="true" style="z-index: 2000;">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="App.closeCreateModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Add New Lead</h3>
                <div class="mt-4">
                    <form id="createLeadForm" class="space-y-4">
                        <div class="grid grid-cols-1 gap-y-4 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="name" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Job Title</label>
                                <input type="text" name="title"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Company</label>
                                <input type="text" name="company"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Lead Value ($)</label>
                                <input type="number" name="lead_value" step="0.01"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="text" name="phone"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Source</label>
                                <select name="source"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white">
                                    <option value="Direct">Direct</option>
                                    <option value="Website">Website</option>
                                    <option value="LinkedIn">LinkedIn</option>
                                    <option value="Referral">Referral</option>
                                    <option value="Ads">Ads</option>
                                    <option value="Cold Call">Cold Call</option>
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="stage_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white">
                                    <option value="new">New</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="lost">Lost</option>
                                </select>
                            </div>
                            <div class="sm:col-span-6">
                                <label class="block text-sm font-medium text-gray-700">Assigned To</label>
                                <select name="assigned_to"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white">
                                    <option value="">Unassigned</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:col-start-2 sm:text-sm">
                                Save Lead
                            </button>
                            <button type="button" onclick="App.closeCreateModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:col-start-1 sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lead Detail Slide-over -->
<div id="leadDetailPanel" class="fixed inset-0 overflow-hidden hidden z-20"
    aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="App.closeLeadDetail()"></div>
        <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex">
            <div class="w-screen max-w-md">
                <div class="h-full flex flex-col bg-white shadow-xl overflow-y-scroll">
                    <div class="py-6 px-4 bg-gray-900 sm:px-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-white" id="slide-over-title">Lead Details</h2>
                            <div class="ml-3 h-7 flex items-center">
                                <button type="button"
                                    class="bg-gray-900 rounded-md text-gray-200 hover:text-white focus:outline-none"
                                    onclick="App.closeLeadDetail()">
                                    <span class="sr-only">Close panel</span>
                                    <i data-lucide="x" class="h-6 w-6"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-1">
                            <p class="text-sm text-gray-300" id="detailHeader">Loading...</p>
                        </div>
                    </div>
                    <div class="relative flex-1 py-6 px-4 sm:px-6" id="detailContent">
                        <div class="animate-pulse space-y-4">
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-4 bg-gray-200 rounded"></div>
                            <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title"
    role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="App.closeImportModal()"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full sm:p-6">
        
        <!-- Step 1: Upload File -->
        <div id="importStep1">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Import Leads</h3>
                <button onclick="App.closeImportModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-gray-400 transition-colors">
                <i data-lucide="upload-cloud" class="h-12 w-12 mx-auto text-gray-400 mb-4"></i>
                <label for="importFileInput" class="cursor-pointer">
                    <span class="mt-2 block text-sm font-medium text-gray-900">Drop your CSV file here or click to browse</span>
                </label>
                <input id="importFileInput" type="file" accept=".csv" class="hidden" onchange="App.handleFileUpload(event)">
            </div>
        </div>

        <!-- Step 2: Map Columns -->
        <div id="importStep2" class="hidden">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Map Columns</h3>
                <button onclick="App.closeImportModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="importStats"></div>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-3">Match your CSV columns to CRM fields:</p>
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-md p-4">
                    <div id="columnMappingList"></div>
                </div>
            </div>
            <div class="flex justify-end space-x-3">
                <button onclick="App.closeImportModal()"
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                <button onclick="App.proceedToImportOptions()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Next: Import Options</button>
            </div>
        </div>

        <!-- Step 3: Import Options (Dynamically populated by import.js) -->
        <div id="importStep3" class="hidden"></div>
    </div>
  </div>
</div>

<!-- Feature Knob Panel Modal (Admin Only) -->
<div id="featureKnobModal" class="fixed inset-0 z-20 overflow-y-auto hidden" aria-labelledby="modal-title"
    role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="FeatureKnobs.closeModal()"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Feature Permissions Control Panel</h3>
                <button onclick="FeatureKnobs.closeModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <p class="text-sm text-gray-600 mb-4">
                Control which features are available to each user role. Changes take effect immediately.
            </p>

            <!-- Role Tabs -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button onclick="FeatureKnobs.switchRole('staff')" 
                        class="role-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                        data-role="staff">
                        Staff
                    </button>
                    <button onclick="FeatureKnobs.switchRole('manager')" 
                        class="role-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                        data-role="manager">
                        Manager
                    </button>
                    <button onclick="FeatureKnobs.switchRole('admin')" 
                        class="role-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                        data-role="admin">
                        Admin
                    </button>
                    <button onclick="FeatureKnobs.switchRole('owner')" 
                        class="role-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                        data-role="owner">
                        Owner
                    </button>
                </nav>
            </div>

            <!-- Feature Knobs Content -->
            <div id="featureKnobsContent" class="max-h-96 overflow-y-auto">
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                    <p class="text-gray-500 mt-4">Loading feature permissions...</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-between items-center">
                <button type="button" onclick="FeatureKnobs.resetToDefaults()"
                    class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Reset to Defaults
                </button>
                <div class="flex space-x-3">
                    <button type="button" onclick="FeatureKnobs.closeModal()"
                        class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" onclick="FeatureKnobs.saveChanges()"
                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Meeting Modal -->
<div id="createMeetingModal" class="fixed inset-0 overflow-y-auto hidden" aria-labelledby="modal-title"
    role="dialog" aria-modal="true" style="z-index: 2000;">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="Meetings.closeCreateModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="meetingModalTitle">Schedule Meeting</h3>
                <div class="mt-4">
                    <form id="createMeetingForm" class="space-y-4" onsubmit="Meetings.saveMeeting(event)">
                        <input type="hidden" id="meetingId" name="id">
                        <input type="hidden" id="meetingLeadId" name="lead_id">
                        
                        <div class="grid grid-cols-1 gap-y-4 gap-x-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Meeting Title</label>
                                <input type="text" name="title" id="meetingTitle" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2"
                                    placeholder="e.g. Initial Discovery Call">
                            </div>
                            
                            <div id="leadSelectContainer">
                                <label class="block text-sm font-medium text-gray-700">Lead</label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="text" id="meetingLeadName" readonly
                                        class="block w-full border-gray-300 rounded-md sm:text-sm border p-2 bg-gray-50 text-gray-500 hidden"
                                        placeholder="Lead Name">
                                    <select name="lead_select" id="meetingLeadSelect"
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white">
                                        <option value="">Select a Lead...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Date</label>
                                    <input type="date" name="meeting_date" id="meetingDate" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Time</label>
                                    <input type="time" name="meeting_time" id="meetingTime" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Duration (min)</label>
                                    <select name="duration" id="meetingDuration"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white">
                                        <option value="15">15 minutes</option>
                                        <option value="30" selected>30 minutes</option>
                                        <option value="45">45 minutes</option>
                                        <option value="60">1 hour</option>
                                        <option value="90">1.5 hours</option>
                                        <option value="120">2 hours</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Mode</label>
                                    <select name="mode" id="meetingMode"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white">
                                        <option value="video">Video Call</option>
                                        <option value="phone">Phone Call</option>
                                        <option value="in_person">In Person</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description / Agenda</label>
                                <textarea name="description" id="meetingDescription" rows="3"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2"
                                    placeholder="Meeting agenda and notes..."></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:col-start-2 sm:text-sm">
                                Save Meeting
                            </button>
                            <button type="button" onclick="Meetings.closeCreateModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:col-start-1 sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invite User Modal -->
<div id="inviteUserModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="Invitations.closeModal()"></div>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Invite New User</h3>
                <button onclick="Invitations.closeModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="inviteUserForm" onsubmit="Invitations.submitInvitation(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="inviteEmail" required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2"
                            placeholder="user@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Role</label>
                        <select id="inviteRole" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border p-2 bg-white">
                            <option value="staff">Staff (Standard User)</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Feature Permissions</label>
                            <button type="button" onclick="Invitations.toggleAllFeatures(true)" class="text-xs text-blue-600 hover:text-blue-500 font-semibold">Select All</button>
                        </div>
                        <div id="inviteFeaturesContainer" class="max-h-64 overflow-y-auto border rounded-md p-4 bg-gray-50 space-y-4">
                            <!-- Dynamically populated categories and checkboxes -->
                            <div class="text-center py-4 text-gray-400">Loading features...</div>
                        </div>
                    </div>

                    <div id="invitationLinkContainer" class="hidden mt-4 bg-green-50 p-4 rounded-md border border-green-100">
                        <label class="block text-xs font-bold text-green-800 uppercase tracking-wide mb-1">Invitation Link Generated</label>
                        <div class="flex mt-1">
                            <input type="text" id="generatedInviteLink" readonly
                                class="block w-full border-green-300 rounded-l-md bg-white text-sm p-2 text-green-900 focus:ring-0 focus:border-green-300">
                            <button type="button" onclick="Invitations.copyLink()"
                                class="inline-flex items-center px-4 py-2 border border-l-0 border-green-300 rounded-r-md bg-green-100 text-green-700 hover:bg-green-200 text-sm font-medium">
                                Copy
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-green-600">Share this link with the user. It expires in 7 days.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="Invitations.closeModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" id="submitInviteBtn"
                        class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        Generate Invitation Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
