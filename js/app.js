/**
 * Entry point that stitches together the App modules.
 */

// Ensure global object exists immediately
window.App = window.App || {};
window.AppReady = false;

// --- Permissions Bootstrap ---
async function fetchAndSetUserPermissions() {
    try {
        const apiUrl = window.AppData?.config?.apiUrl || '/api';

        const res = await fetch(
            `${apiUrl}/auth/get_current_permissions.php`,
            { credentials: 'include' }
        );

        const data = await res.json();

        if (data.success && data.permissions) {
            window.userPermissions = data.permissions;
            console.log('[BOOTSTRAP] Loaded plan-calculated permissions:', window.userPermissions);
        } else {
            window.userPermissions = {};
            console.warn('[BOOTSTRAP] Failed to load permissions:', data.error);
        }

    } catch (e) {
        window.userPermissions = {};
        console.error('[BOOTSTRAP] Error loading permissions:', e);
    }
}

// --- MAIN BOOTSTRAP ---
(async () => {

    await fetchAndSetUserPermissions();

    const v = Date.now();

    // Load modules in order
    await import(`./modules/core.js?v=${v}`);
    await import(`./modules/permissions_ui.js?v=${v}`); // Load UI gating module
    await import(`./modules/custom-fields.js?v=${v}`);
    await import(`./modules/leads.js?v=${v}`);
    await import(`./modules/kanban.js?v=${v}`);
    await import(`./modules/reports.js?v=${v}`);
    await import(`./modules/settings.js?v=${v}`);
    await import(`./modules/organizations.js?v=${v}`);
    await import(`./modules/import.js?v=${v}`);
    await import(`./modules/utils.js?v=${v}`);
    await import(`./modules/dashboard.js?v=${v}`);
    await import(`./modules/meetings.js?v=${v}`);
    await import(`./modules/invitations.js?v=${v}`);
    await import(`./modules/audit.js?v=${v}`);
    await import(`./modules/subscription.js?v=${v}`);
    await import(`./modules/partners.js?v=${v}`);

    // ✅ SIGNAL THAT APP IS FULLY READY
    window.AppReady = true;

    console.log('[BOOTSTRAP] App Ready');

    // Start the application (Routing)
    if (typeof App.start === 'function') {
        App.start();
    } else {
        console.error('[BOOTSTRAP] App.start not found in core.js');
    }

})();
