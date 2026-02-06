<?php
/**
 * Main Dashboard Entry Point
 */
$pageTitle = 'Dashboard';
require_once 'includes/auth_check.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Prepare user data for JS injection to reduce API calls
$userData = [
    'user_id' => $_SESSION['user_id'],
    'org_id' => $_SESSION['org_id'],
    'role' => $_SESSION['role'],
    'email' => $_SESSION['email'] ?? 'User',
    'name' => $_SESSION['full_name'] ?? 'User',
    'is_super_admin' => $_SESSION['is_super_admin']
];
?>

<!-- User Data Injection -->
<script>
    window.AppData = {
        csrf_token: '<?php echo Security::generateCsrfToken(); ?>',
        user: <?php echo json_encode($userData); ?>,
        config: {
            projectRoot: '<?php echo $projectRoot; ?>',
            apiUrl: window.location.origin + '<?php echo $projectRoot; ?>/api'
        }
    };
</script>

<!-- Mobile Header (Duplicate from dashboard content for modularity) -->
<div class="md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3">
    <button
        class="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
        <span class="sr-only">Open sidebar</span>
        <i data-lucide="menu" class="h-6 w-6"></i>
    </button>
</div>

<main class="flex-1 relative z-0 overflow-y-auto focus:outline-none">
    <div class="py-6">
        <!-- Page Header -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 flex justify-between items-center mb-6 content-expand">
            <h1 id="pageTitle" class="text-2xl font-semibold text-gray-900">Leads</h1>
            <div class="flex items-center space-x-2">
                <!-- Organization Switcher -->
                <div id="orgSwitcher" class="relative" style="display: none;">
                    <button onclick="App.toggleOrgDropdown()"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i data-lucide="building-2" class="h-4 w-4 mr-2"></i>
                        <span id="currentOrgName">Loading...</span>
                        <i data-lucide="chevron-down" class="h-4 w-4 ml-2"></i>
                    </button>
                    <div id="orgDropdown"
                        class="hidden absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1" id="orgDropdownList">
                            <!-- Organizations will be loaded here -->
                        </div>
                    </div>
                </div>

                <div id="dashboardActions" class="flex items-center space-x-2">
                    <!-- Date Range Filters -->
                    <div class="flex items-center space-x-1 rounded-md bg-white p-1 shadow-sm border border-gray-200">
                        <button id="date-range-today" class="date-range-btn px-3 py-1 text-sm font-medium text-gray-700 rounded-md">Today</button>
                        <button id="date-range-7" class="date-range-btn px-3 py-1 text-sm font-medium text-gray-700 rounded-md">7 Days</button>
                        <button id="date-range-30" class="date-range-btn px-3 py-1 text-sm font-medium text-gray-700 rounded-md">30 Days</button>
                        <button id="date-range-custom" class="date-range-btn px-3 py-1 text-sm font-medium text-gray-700 rounded-md flex items-center">
                            <i data-lucide="calendar" class="h-4 w-4 mr-2"></i> Custom
                        </button>
                    </div>

                    <!-- Add/Remove Charts Button -->
                    <button id="toggle-chart-selector" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
                        <i data-lucide="plus" class="h-4 w-4 mr-2"></i>
                        Add / Remove Charts
                    </button>
                </div>

                <div id="leadActions" class="flex space-x-2">
                    <button onclick="App.showImportHistory()" data-feature="import_leads"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                        <i data-lucide="clock" class="h-4 w-4 mr-2"></i>
                        History
                    </button>
                    <button onclick="App.openImportModal()" data-feature="import_leads"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                        <i data-lucide="upload" class="h-4 w-4 mr-2"></i>
                        Import
                    </button>
                    <button onclick="App.openManageColumns()" data-feature="manage_custom_fields"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                        <i data-lucide="columns" class="h-4 w-4 mr-2"></i>
                        Columns
                    </button>
                    <button onclick="if(typeof openCreateModal === 'function') { openCreateModal(); } else if(window.App && typeof App.openCreateModal === 'function') { App.openCreateModal(); } else { alert('Please wait for the page to load completely.'); }" data-feature="create_leads"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
                        <i data-lucide="plus" class="h-4 w-4 mr-2"></i>
                        Add Lead
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters & Search -->
        <div id="leadFilters" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 mb-6 content-expand">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="searchInput" class="sr-only">Search</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
                        </div>
                        <input type="text" id="searchInput" oninput="App.handleSearch(this.value)"
                            class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md border p-2"
                            placeholder="Search leads...">
                    </div>
                </div>
                <!-- ... filters ... -->
                <div class="w-full md:w-48">
                    <select id="statusFilter" onchange="App.handleFilterChange()"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md border bg-white">
                        <option value="">All Stages</option>
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="qualified">Qualified</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div class="w-full md:w-48">
                    <select id="sourceFilter" onchange="App.handleFilterChange()"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md border bg-white">
                        <option value="">All Sources</option>
                        <option value="Direct">Direct</option>
                        <option value="Website">Website</option>
                        <option value="LinkedIn">LinkedIn</option>
                        <option value="Referral">Referral</option>
                        <option value="Ads">Ads</option>
                        <option value="Cold Call">Cold Call</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button id="advancedFiltersToggle" onclick="App.toggleAdvancedFilters()"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                        <i data-lucide="filter" class="h-4 w-4 mr-2"></i>
                        Advanced Filters
                        <i data-lucide="chevron-down" class="h-4 w-4 ml-2" id="advancedFiltersIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Advanced Filters Side Panel -->
            <div id="advancedFiltersOverlay" onclick="App.toggleAdvancedFilters()" 
                class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 transition-opacity duration-300 opacity-0"></div>
            
            <div id="advancedFiltersPanel" 
                class="fixed inset-y-0 right-0 max-w-sm w-full bg-white shadow-xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
                
                <!-- Panel Header -->
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">Advanced Filters</h3>
                    <button onclick="App.toggleAdvancedFilters()" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                        <i data-lucide="x" class="h-6 w-6"></i>
                    </button>
                </div>

                <!-- Panel Content (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6" id="advancedFiltersContainer">
                    <!-- Filters will be dynamically inserted here -->
                </div>

                <!-- Panel Footer -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex space-x-3">
                    <button onclick="App.clearAdvancedFilters()"
                        class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Clear All
                    </button>
                    <button onclick="App.applyAdvancedFilters()"
                        class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Page Content Container -->
        <div id="appContent" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 content-expand">
            <div id="paginationTop" class="mb-4"></div>
            <div id="leadsTableContainer">
                <div id="loading" class="text-center py-10">
                    <p class="text-gray-500">Loading...</p>
                </div>
            </div>
            <div id="paginationBottom" class="mt-4 pb-10"></div>
        </div>

        <!-- Chart Selector Side Panel -->
        <div id="chart-selector-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-30 transition-opacity duration-300"></div>
        <div id="chart-selector-panel" class="hidden fixed inset-y-0 right-0 max-w-sm w-full bg-white shadow-xl z-40 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
            <!-- Panel Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900">Manage Dashboard Charts</h3>
                <button id="close-chart-selector" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <!-- Panel Content (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chart-selector-list">
                <!-- Chart checkboxes will be dynamically inserted here -->
            </div>

            <!-- Panel Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 text-right">
                <button id="save-dashboard-btn-panel" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">Done</button>
            </div>
        </div>

    </div>

<?php require_once 'includes/footer.php'; ?>
