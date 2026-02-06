<script>
function populateSidebarUserInfo() {
    // Permission gating for reports link
    if (window.userPermissions && !window.userPermissions['view_reports']) {
        var reportsLink = document.querySelector('a[href$="/reports"]');
        if (reportsLink) reportsLink.style.display = 'none';
    }
    // Permission gating for meetings link
    if (window.userPermissions && window.userPermissions['view_meetings']) {
        var meetingsLink = document.getElementById('meetingsLink');
        if (meetingsLink) meetingsLink.style.display = 'flex';
    }
    // Permission gating for invitations link
    if (window.userPermissions && window.userPermissions['manage_users']) {
        var invitationsLink = document.getElementById('invitationsLink');
        if (invitationsLink) invitationsLink.style.display = 'flex';
    }
    // Permission gating for audit link
    if (window.userPermissions && window.userPermissions['view_activity_log']) {
        var auditLink = document.getElementById('auditLink');
        if (auditLink) auditLink.style.display = 'flex';
    }
    // Permission gating for automations link
    if (window.userPermissions && window.userPermissions['access_automations']) {
        var automationsLink = document.getElementById('automationsLink');
        if (automationsLink) automationsLink.style.display = 'flex';
    }
    // Populate user info in sidebar
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
    // Wait for App.getUser to be available
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
<?php
/**
 * Modular Sidebar
 */
?>
<!-- Sidebar -->
<div class="hidden md:flex md:flex-shrink-0" id="sidebar-container">
    <div class="flex flex-col w-64" id="sidebar-inner">
        <div class="flex flex-col h-0 flex-1 bg-gray-900 border-r border-gray-800">
            <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                <div class="flex items-center justify-between flex-shrink-0 px-4 mb-5">
                    <h1 class="text-xl font-bold text-white logo-text truncate">Lead Manager</h1>
                    <button onclick="App.toggleSidebar()" class="text-gray-400 hover:text-white focus:outline-none p-1 rounded-md hover:bg-gray-800">
                        <i data-lucide="chevron-left" id="sidebarToggleIcon" class="h-6 w-6"></i>
                    </button>
                </div>
                <nav class="flex-1 px-2 space-y-1">
                    <a href="<?php echo $projectRoot; ?>/" onclick="event.preventDefault(); App.router('dashboard')" title="Dashboard"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        <i data-lucide="home" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Dashboard</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/leads" onclick="event.preventDefault(); App.router('leads')" title="Leads"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        <i data-lucide="users" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Leads</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/pipeline" onclick="event.preventDefault(); App.router('pipeline')"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md" title="Pipeline">
                        <i data-lucide="trello" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Pipeline</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/meetings" id="meetingsLink" onclick="event.preventDefault(); App.router('meetings')"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md"
                        style="display: none;" title="Meetings">
                        <i data-lucide="calendar" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Meetings</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/reports" onclick="event.preventDefault(); App.router('reports')"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md" title="Reports">
                        <i data-lucide="bar-chart-2"
                            class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Reports</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/organizations" id="organizationsLink" onclick="event.preventDefault(); App.router('organizations')"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md"
                        style="display: none;" title="Organizations">
                        <i data-lucide="building-2"
                            class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Organizations</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/invitations" id="invitationsLink" onclick="event.preventDefault(); App.router('invitations')"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md"
                        style="display: none;" title="User Invitations">
                        <i data-lucide="user-plus" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">User Invitations</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/audit" id="auditLink" onclick="event.preventDefault(); App.router('audit')"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md"
                        style="display: none;" title="Audit Trail">
                        <i data-lucide="activity" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Audit Trail</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/settings" id="settingsLink" onclick="event.preventDefault(); App.router('settings')"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md" title="Settings">
                        <i data-lucide="settings" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Settings</span>
                    </a>

                    <a href="<?php echo $projectRoot; ?>/automations" id="automationsLink" onclick="event.preventDefault(); App.router('automations')"
                        class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md"
                        style="display: none;" title="Automations">
                        <i data-lucide="zap" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-300"></i>
                        <span class="nav-label">Automations</span>
                    </a>
                </nav>

                <!-- Organization Selector (Super Admin) -->
                <div id="orgSelector" class="px-2 mt-auto mb-4" style="display: none;">
                    <label class="block text-xs font-medium text-gray-400 mb-2 org-selector-label uppercase">Viewing</label>
                    <select id="orgSelectorDropdown" onchange="App.handleOrgChange(this.value)"
                        class="w-full bg-gray-700 text-white text-sm rounded-md border-gray-600 focus:border-blue-500 focus:ring-blue-500 p-2">
                        <!-- Organizations will be loaded here -->
                    </select>
                </div>
            </div>
            <div class="flex-shrink-0 flex bg-gray-800 p-4">
                <div class="flex-shrink-0 w-full group block">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div
                                class="inline-block h-9 w-9 rounded-full bg-gray-500 text-white flex items-center justify-center">
                                <span id="userInitials" class="font-medium">U</span>
                            </div>
                        </div>
                        <div class="ml-3 user-details overflow-hidden">
                            <p id="userName" class="text-sm font-medium text-white truncate">User Name</p>
                            <p id="userEmail" class="text-xs text-gray-400 truncate max-w-[150px]">Email</p>
                            <div class="flex flex-col mt-1">
                                <p onclick="App.openChangePasswordModal()"
                                    class="text-[10px] font-medium text-gray-400 hover:text-white cursor-pointer uppercase tracking-tighter">
                                    Password</p>
                                <p onclick="App.logout()"
                                    class="text-[10px] font-medium text-gray-400 hover:text-white cursor-pointer uppercase tracking-tighter">
                                    Log Out</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
