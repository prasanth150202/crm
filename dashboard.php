<?php
/**
 * Main Dashboard Entry Point
 */
$requestUri = $_SERVER['REQUEST_URI'];
$pageTitle = 'Dashboard';

if (strpos($requestUri, '/leads') !== false) {
    $pageTitle = 'Leads';
} elseif (strpos($requestUri, '/pipeline') !== false) {
    $pageTitle = 'Pipeline';
} elseif (strpos($requestUri, '/reports') !== false) {
    $pageTitle = 'Reports';
} elseif (strpos($requestUri, '/settings') !== false) {
    $pageTitle = 'Settings';
} elseif (strpos($requestUri, '/organizations') !== false) {
    $pageTitle = 'Organizations';
} elseif (strpos($requestUri, '/audit') !== false) {
    $pageTitle = 'Audit Trail';
} elseif (strpos($requestUri, '/invitations') !== false) {
    $pageTitle = 'User Invitations';
}

require_once 'includes/auth_check.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/db.php';

// Load org currency from settings
$orgCurrency = 'USD';
try {
    $pdo = getDb();
    $orgStmt = $pdo->prepare("SELECT settings FROM organizations WHERE id = ?");
    $orgStmt->execute([$_SESSION['org_id']]);
    $orgRow = $orgStmt->fetch();
    if ($orgRow && $orgRow['settings']) {
        $orgSettings = json_decode($orgRow['settings'], true);
        $orgCurrency = $orgSettings['currency'] ?? 'USD';
    }
} catch (Exception $e) {
    // keep default
}

// Prepare user data for JS injection to reduce API calls
$userData = [
    'user_id' => $_SESSION['user_id'],
    'org_id' => $_SESSION['org_id'],
    'role' => $_SESSION['role'],
    'email' => $_SESSION['email'] ?? 'User',
    'name' => $_SESSION['full_name'] ?? 'User',
    'is_super_admin' => $_SESSION['is_super_admin'],
    'currency' => $orgCurrency
];
?>

<?php require_once 'config/env.php'; ?>
<!-- User Data Injection -->
<script>
    window.AppData = {
        csrf_token: '<?php echo Security::generateCsrfToken(); ?>',
        user: <?php echo json_encode($userData); ?>,
        config: {
            projectRoot: '<?php echo rtrim(Env::getProjectRoot(), '/'); ?>',
            apiUrl: window.location.origin + '<?php echo rtrim(Env::getProjectRoot(), '/'); ?>/api',
            razorpayKeyId: '<?php echo Env::get('RAZORPAY_KEY_ID'); ?>'
        }
    };
</script>

<!-- Mobile Header -->
<div class="md:hidden flex items-center px-4 py-3 border-b border-slate-200 bg-white">
    <button onclick="App.toggleSidebar()"
        class="h-9 w-9 inline-flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 focus:outline-none transition-colors">
        <span class="sr-only">Open sidebar</span>
        <i data-lucide="menu" class="h-5 w-5"></i>
    </button>
    <span class="ml-3 text-sm font-semibold text-slate-900">CRM.Zingbot.io</span>
</div>

<main class="flex-1 relative z-0 overflow-y-auto focus:outline-none">
    <div class="py-6">
        <!-- Page Header -->
        <div class="mx-auto px-4 sm:px-6 md:px-8 flex justify-between items-center mb-6 content-expand">
            <h1 id="pageTitle" class="text-2xl font-semibold" style="color: var(--color-text)">Dashboard</h1>
            <div class="flex items-center gap-2">
                <!-- Organization Switcher -->
                <div id="orgSwitcher" class="relative" style="display: none;">
                    <button onclick="App.toggleOrgDropdown()"
                        class="inline-flex items-center px-3 py-2 border text-sm font-medium rounded-lg bg-white hover:bg-slate-50 transition-colors shadow-sm"
                        style="border-color: var(--color-border); color: var(--color-text)">
                        <i data-lucide="building-2" class="h-4 w-4 mr-2" style="color: var(--color-muted)"></i>
                        <span id="currentOrgName">Loading...</span>
                        <i data-lucide="chevron-down" class="h-4 w-4 ml-2" style="color: var(--color-muted)"></i>
                    </button>
                    <div id="orgDropdown"
                        class="hidden absolute right-0 mt-2 w-56 rounded-xl shadow-lg bg-white border z-50 overflow-hidden"
                        style="border-color: var(--color-border)">
                        <div class="py-1" id="orgDropdownList"></div>
                    </div>
                </div>

                <div id="dashboardActions" class="flex items-center gap-2" style="display: none;">
                    <!-- Date Range Filters -->
                    <div class="flex items-center gap-1 rounded-xl bg-white p-1 shadow-sm border" style="border-color: var(--color-border)">
                        <button id="date-range-all" class="date-range-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-colors" style="color: var(--color-muted)">ALL</button>
                        <button id="date-range-today" class="date-range-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-colors" style="color: var(--color-muted)">Today</button>
                        <button id="date-range-7" class="date-range-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-colors" style="color: var(--color-muted)">7 Days</button>
                        <button id="date-range-30" class="date-range-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-colors" style="color: var(--color-muted)">30 Days</button>
                        <button id="date-range-custom" class="date-range-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-colors flex items-center gap-1" style="color: var(--color-muted)">
                            <i data-lucide="calendar" class="h-3 w-3"></i> Custom
                        </button>
                    </div>

                    <!-- Edit Layout (pencil) -->
                    <button id="dashboard-edit-mode-btn"
                        title="Edit layout (drag &amp; resize)"
                        onclick="App.dashboardInstance && App.dashboardInstance.toggleEditMode()"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border shadow-sm bg-white transition-all focus:outline-none"
                        style="border-color:var(--color-border);color:var(--color-text)">
                        <i data-lucide="pencil" class="h-4 w-4"></i>
                        <span class="hidden sm:inline">Edit Layout</span>
                    </button>

                    <?php if ($_SESSION['role'] === 'admin' || !empty($_SESSION['is_super_admin'])): ?>
                    <!-- Apply layout to all users (admin only, visible only in edit mode) -->
                    <button id="dashboard-apply-all-btn"
                        title="Apply this layout to all users in your org"
                        onclick="App.dashboardInstance && App.dashboardInstance.applyLayoutToAllUsers()"
                        style="display:none;background:var(--color-primary);color:#fff"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg shadow-sm transition-colors focus:outline-none">
                        <i data-lucide="users" class="h-4 w-4"></i>
                        <span class="hidden sm:inline">Apply to All Users</span>
                    </button>
                    <?php endif; ?>

                    <!-- Add/Remove Charts Button -->
                    <button id="toggle-chart-selector" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg shadow-sm text-white transition-colors focus:outline-none"
                        style="background: var(--color-primary);"
                        onmouseover="this.style.background='var(--color-primary-dark)'"
                        onmouseout="this.style.background='var(--color-primary)'">
                        <i data-lucide="layout-dashboard" class="h-4 w-4 mr-2"></i>
                        Add / Remove Charts
                    </button>
                </div>

                <div id="leadActions" class="flex gap-2" style="display: none;">
<button onclick="App.openImportModal()" data-feature="import_leads"
                        class="inline-flex items-center px-3 py-2 border text-sm font-medium rounded-lg shadow-sm bg-white hover:bg-slate-50 transition-colors focus:outline-none"
                        style="border-color: var(--color-border); color: var(--color-text)">
                        <i data-lucide="upload" class="h-4 w-4 mr-2" style="color: var(--color-muted)"></i>
                        Import
                    </button>
                    <button onclick="App.openManageColumns()" data-feature="manage_custom_fields"
                        class="inline-flex items-center px-3 py-2 border text-sm font-medium rounded-lg shadow-sm bg-white hover:bg-slate-50 transition-colors focus:outline-none"
                        style="border-color: var(--color-border); color: var(--color-text)">
                        <i data-lucide="columns" class="h-4 w-4 mr-2" style="color: var(--color-muted)"></i>
                        Columns
                    </button>
                    <button onclick="if(typeof openCreateModal === 'function') { openCreateModal(); } else if(window.App && typeof App.openCreateModal === 'function') { App.openCreateModal(); } else { alert('Please wait for the page to load completely.'); }" data-feature="create_leads"
                        class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg shadow-sm text-white transition-colors focus:outline-none"
                        style="background: var(--color-primary);"
                        onmouseover="this.style.background='var(--color-primary-dark)'"
                        onmouseout="this.style.background='var(--color-primary)'">
                        <i data-lucide="plus" class="h-4 w-4 mr-1.5"></i>
                        Add Lead
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters & Search -->
        <div id="leadFilters" class="mx-auto px-4 sm:px-6 md:px-8 mb-5 content-expand" style="display: none;">
            <div class="flex flex-col md:flex-row gap-3">
                <div class="flex-1">
                    <label for="searchInput" class="sr-only">Search</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="search" class="h-4 w-4" style="color: var(--color-muted)"></i>
                        </div>
                        <input type="text" id="searchInput" oninput="App.handleSearch(this.value)"
                            class="block w-full pl-10 pr-4 py-2 text-sm bg-white border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            style="border-color: var(--color-border); color: var(--color-text)"
                            placeholder="Search leads...">
                    </div>
                </div>
                <div class="w-full md:w-44">
                    <select id="statusFilter" onchange="App.handleFilterChange()"
                        class="block w-full px-3 py-2 text-sm bg-white border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        style="border-color: var(--color-border); color: var(--color-text)">
                        <option value="">All Status</option>
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="qualified">Qualified</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div class="w-full md:w-44">
                    <select id="sourceFilter" onchange="App.handleFilterChange()"
                        class="block w-full px-3 py-2 text-sm bg-white border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        style="border-color: var(--color-border); color: var(--color-text)">
                        <option value="">All Sources</option>
                        <option value="Direct">Direct</option>
                        <option value="Website">Website</option>
                        <option value="LinkedIn">LinkedIn</option>
                        <option value="Referral">Referral</option>
                        <option value="Ads">Ads</option>
                        <option value="Cold Call">Cold Call</option>
                    </select>
                </div>
                <div class="flex items-center">
                    <button id="advancedFiltersToggle" onclick="App.toggleAdvancedFilters()"
                        class="inline-flex items-center px-4 py-2 border text-sm font-medium rounded-lg shadow-sm bg-white hover:bg-slate-50 focus:outline-none transition-colors"
                        style="border-color: var(--color-border); color: var(--color-text)">
                        <i data-lucide="sliders-horizontal" class="h-4 w-4 mr-2" style="color: var(--color-muted)"></i>
                        Filters
                        <i data-lucide="chevron-down" class="h-4 w-4 ml-2" id="advancedFiltersIcon" style="color: var(--color-muted)"></i>
                    </button>
                </div>
            </div>

            <!-- Advanced Filters Side Panel -->
            <div id="advancedFiltersOverlay" onclick="App.toggleAdvancedFilters()"
                class="hidden fixed inset-0 bg-black bg-opacity-40 z-40 transition-opacity duration-300 opacity-0"></div>

            <div id="advancedFiltersPanel"
                class="fixed inset-y-0 right-0 w-full sm:max-w-md md:max-w-lg bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
                <!-- Panel Header -->
                <div class="px-6 py-4 border-b flex items-center justify-between bg-white" style="border-color: var(--color-border)">
                    <div class="flex items-center gap-2">
                        <i data-lucide="sliders-horizontal" class="h-5 w-5" style="color: var(--color-primary)"></i>
                        <h3 class="text-base font-semibold" style="color: var(--color-text)">Advanced Filters</h3>
                    </div>
                    <button onclick="App.toggleAdvancedFilters()" class="p-1.5 rounded-lg hover:bg-slate-100 transition-colors" style="color: var(--color-muted)">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
                <!-- Panel Content -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6" id="advancedFiltersContainer"></div>
                <!-- Panel Footer -->
                <div class="px-6 py-4 border-t flex gap-3" style="border-color: var(--color-border); background: var(--color-bg)">
                    <button onclick="App.clearAdvancedFilters()"
                        class="flex-1 px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-slate-50 shadow-sm focus:outline-none transition-colors"
                        style="border-color: var(--color-border); color: var(--color-text)">
                        Clear All
                    </button>
                    <button onclick="App.applyAdvancedFilters()"
                        class="flex-1 px-4 py-2 text-sm font-semibold text-white rounded-lg shadow-sm focus:outline-none transition-colors"
                        style="background: var(--color-primary)"
                        onmouseover="this.style.background='var(--color-primary-dark)'"
                        onmouseout="this.style.background='var(--color-primary)'">
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Facet Filter Bar -->
        <div id="facetFilterBar" style="display:none; position:relative;"
            class="mx-auto px-4 sm:px-6 md:px-8 content-expand mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide" style="color: var(--color-muted)">Filter:</span>
                <div id="facetButtons" class="flex flex-wrap items-center gap-2"></div>
                <div id="inlineFilterChips" class="flex flex-wrap items-center gap-2"></div>
                <button id="addFilterBtn" data-facet-btn data-facet-field="__add__"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-full border border-dashed bg-white transition-all focus:outline-none"
                    style="border-color: var(--color-border); color: var(--color-muted)"
                    onmouseover="this.style.borderColor='var(--color-primary)'; this.style.color='var(--color-primary)'"
                    onmouseout="this.style.borderColor='var(--color-border)'; this.style.color='var(--color-muted)'">
                    <i data-lucide="plus" class="h-3 w-3"></i>
                    Add Filter
                </button>
                <button id="facetClearAll" onclick="App.clearFacetFilters()"
                    class="hidden text-xs hover:underline ml-1 focus:outline-none transition-colors"
                    style="color: var(--color-muted)"
                    onmouseover="this.style.color='#ef4444'"
                    onmouseout="this.style.color='var(--color-muted)'">
                    Clear all
                </button>
            </div>
            <div id="facetDropdown"
                class="hidden absolute bg-white rounded-xl shadow-xl border"
                style="top:calc(100% + 6px); left:0; min-width:220px; max-height:320px; overflow:hidden; display:flex; flex-direction:column; z-index:9999; border-color: var(--color-border)">
            </div>
        </div>

        <!-- Shared value picker panel -->
        <div id="valuePickerPanel"
            class="hidden fixed bg-white rounded-xl shadow-xl border"
            style="min-width:200px; max-height:280px; overflow:hidden; display:flex; flex-direction:column; z-index:10000; border-color: var(--color-border)">
        </div>

        <!-- Page Content Container -->
        <div id="contentOuter" class="mx-auto px-4 sm:px-6 md:px-8 content-expand">
            <div id="appContent" class="mx-auto content-expand">
                <div id="paginationTop" class="mb-4"></div>
                <div id="leadsTableContainer">
                    <div id="loading" class="text-center py-16">
                        <div class="inline-flex items-center gap-3" style="color: var(--color-muted)">
                            <svg class="animate-spin h-5 w-5" style="color: var(--color-primary)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <p class="text-sm font-medium">Loading...</p>
                        </div>
                    </div>
                </div>
                <div id="paginationBottom" class="mt-4 pb-10"></div>
            </div>
        </div>

        <!-- Chart Selector Side Panel -->
        <div id="chart-selector-overlay" class="hidden fixed inset-0 bg-black bg-opacity-40 z-30 transition-opacity duration-300"></div>
        <div id="chart-selector-panel" class="hidden fixed inset-y-0 right-0 max-w-sm w-full bg-white shadow-2xl z-40 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
            <!-- Panel Header -->
            <div class="px-6 py-4 border-b flex items-center justify-between bg-white" style="border-color: var(--color-border)">
                <div class="flex items-center gap-2">
                    <i data-lucide="bar-chart-2" class="h-5 w-5" style="color: var(--color-primary)"></i>
                    <h3 class="text-base font-semibold" style="color: var(--color-text)">Manage Charts</h3>
                </div>
                <button id="close-chart-selector" class="p-1.5 rounded-lg hover:bg-slate-100 transition-colors" style="color: var(--color-muted)">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <!-- Panel Content -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chart-selector-list"></div>
            <!-- Panel Footer -->
            <div class="px-6 py-4 border-t text-right" style="border-color: var(--color-border); background: var(--color-bg)">
                <button id="save-dashboard-btn-panel"
                    class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white rounded-lg shadow-sm transition-colors"
                    style="background: var(--color-primary)"
                    onmouseover="this.style.background='var(--color-primary-dark)'"
                    onmouseout="this.style.background='var(--color-primary)'">Done</button>
            </div>
        </div>

    </div>

<?php require_once 'includes/footer.php'; ?>

<!-- Fetch allowed features for the current org/plan -->
<?php
$orgId = $_SESSION['org_id']; // Or however you get the org id
$planId = 2; // Replace with dynamic plan id for the org

$allowedFeatures = [];
$stmt = $pdo->prepare("SELECT knob_key FROM plan_features WHERE plan_id = ?");
$stmt->execute([$planId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allowedFeatures[] = $row['knob_key'];
}
?>
<script>
window.planFeatures = <?php echo json_encode($allowedFeatures); ?>;
</script>
