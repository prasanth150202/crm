<script>
function populateSidebarUserInfo() {
    if (window.App && typeof window.App.getUser === 'function') {
        var user = window.App.getUser();
        if (user) {
            var userName = document.getElementById('userName');
            var userEmail = document.getElementById('userEmail');
            var userInitials = document.getElementById('userInitials');
            if (userName) userName.textContent = user.name || user.email || 'User';
            if (userEmail) userEmail.textContent = user.email || '';
            if (userInitials && user.name) userInitials.textContent = user.name.charAt(0).toUpperCase();
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    function tryPopulateSidebar() {
        if (window.App && typeof window.App.getUser === 'function') {
            populateSidebarUserInfo();
        } else {
            setTimeout(tryPopulateSidebar, 50);
        }
    }
    tryPopulateSidebar();
});
</script>

<style>
    /* Sidebar nav link hover & active states */
    #sidebar-container nav a:hover {
        background: #2D4F7C;
    }
    #sidebar-container nav a.nav-active {
        background: #3B63A0 !important;
        color: #ffffff !important;
    }
    #sidebar-container nav a.nav-active i,
    #sidebar-container nav a.nav-active svg {
        color: #ffffff !important;
    }
    #sidebar-container nav a.nav-active .nav-label {
        color: #ffffff !important;
    }
</style>

<!-- Sidebar -->
<div class="hidden md:flex md:flex-shrink-0" id="sidebar-container">
    <div class="flex flex-col w-64" id="sidebar-inner">
        <div class="flex flex-col h-0 flex-1 border-r border-blue-900" style="background: var(--color-sidebar)">
            <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                <!-- Logo -->
                <div class="flex items-center justify-between flex-shrink-0 px-4 mb-6">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: var(--color-primary)">
                            <i data-lucide="zap" class="h-4 w-4 text-white"></i>
                        </div>
                        <h1 class="text-base font-bold text-white logo-text truncate tracking-tight">CRM.Zingbot.io</h1>
                    </div>
                    <button onclick="App.toggleSidebar()" class="text-blue-300 hover:text-white focus:outline-none p-1 rounded-md transition-colors" style="hover:background: var(--color-sidebar-hover)">
                        <i data-lucide="chevron-left" id="sidebarToggleIcon" class="h-5 w-5"></i>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 px-3 space-y-0.5">
                    <a href="<?php echo $projectRoot; ?>/" onclick="event.preventDefault(); App.router('dashboard')" title="Dashboard"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all"
                        style="hover:background: #2D4F7C">
                        <i data-lucide="home" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Dashboard</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/leads" onclick="event.preventDefault(); App.router('leads')" title="Leads"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <i data-lucide="users" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Leads</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/pipeline" onclick="event.preventDefault(); App.router('pipeline')" title="Pipeline"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <i data-lucide="kanban" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Pipeline</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/partners" onclick="event.preventDefault(); App.router('partners')" title="Partners"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <i data-lucide="handshake" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Partners</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/meetings" id="meetingsLink" data-feature="view_meetings" onclick="event.preventDefault(); App.router('meetings')"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all"
                        style="display: none;" title="Meetings">
                        <i data-lucide="calendar" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Meetings</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/reports" data-feature="view_reports" onclick="event.preventDefault(); App.router('reports')" title="Reports"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <i data-lucide="bar-chart-2" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Reports</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/organizations" id="organizationsLink" onclick="event.preventDefault(); App.router('organizations')" title="Organizations"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <i data-lucide="building-2" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Organizations</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/invitations" id="invitationsLink" data-feature="manage_users" onclick="event.preventDefault(); App.router('invitations')"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all"
                        style="display: none;" title="User Invitations">
                        <i data-lucide="user-plus" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">User Invitations</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/settings" id="settingsLink" onclick="event.preventDefault(); App.router('settings')" title="Settings"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <i data-lucide="settings" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Settings</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/automations" id="automationsLink" data-feature="access_automations" onclick="event.preventDefault(); App.router('automations')"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all"
                        style="display: none;" title="Automations">
                        <i data-lucide="zap" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Automations</span>
                    </a>

                    <?php if (!empty($_SESSION['is_super_admin'])): ?>
                    <a href="<?php echo $projectRoot; ?>/admin" onclick="event.preventDefault(); App.router('admin')"
                        class="text-blue-100 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all"
                        title="Admin">
                        <i data-lucide="shield" class="mr-3 h-4 w-4 text-blue-300 group-hover:text-white flex-shrink-0"></i>
                        <span class="nav-label">Admin</span>
                    </a>
                    <?php endif; ?>
                </nav>

                <!-- Organization Selector (Super Admin) -->
                <div id="orgSelector" class="px-3 mt-4 mb-2" style="display: none;">
                    <label class="block text-xs font-semibold text-blue-300 mb-1.5 org-selector-label uppercase tracking-wider">Viewing</label>
                    <select id="orgSelectorDropdown" onchange="App.handleOrgChange(this.value)"
                        class="w-full text-white text-sm rounded-lg border p-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                        style="background: #152C4A; border-color: #2D4F7C;">
                        <!-- Organizations will be loaded here -->
                    </select>
                </div>
            </div>

            <!-- User Profile Card -->
            <div class="flex-shrink-0 p-3" style="background: #152C4A; border-top: 1px solid #2D4F7C;">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="h-9 w-9 rounded-full flex items-center justify-center text-white font-semibold text-sm ring-2 ring-blue-400"
                            style="background: var(--color-primary)">
                            <span id="userInitials">U</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0 user-details">
                        <p id="userName" class="text-sm font-semibold text-white truncate">User Name</p>
                        <p id="userEmail" class="text-xs truncate" style="color: #93C5FD; max-width: 150px;">Email</p>
                        <div class="flex items-center gap-3 mt-1">
                            <button onclick="App.openChangePasswordModal()"
                                class="text-xs font-medium transition-colors hover:text-white"
                                style="color: #60A5FA;">Password</button>
                            <span style="color: #2D4F7C;">|</span>
                            <button onclick="App.logout()"
                                class="text-xs font-medium transition-colors hover:text-white"
                                style="color: #60A5FA;">Log Out</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
