/**
 * Entry point that stitches together the App modules.
 */


// Ensure the global is available for inline event handlers
window.App = window.App || {};

// --- Permissions Bootstrap ---
async function fetchAndSetUserPermissions() {
	try {
		const user = window.AppData?.user;
		if (!user || !user.user_id) {
			console.warn('[BOOTSTRAP] No user data available for permissions');
			window.userPermissions = {};
			return;
		}
		const apiUrl = window.AppData?.config?.apiUrl || '/api';
		const res = await fetch(`${apiUrl}/users/get_permissions.php?user_id=${user.user_id}`, { credentials: 'include' });
		const data = await res.json();
		if (data.success && data.permissions) {
			window.userPermissions = data.permissions;
			console.log('[BOOTSTRAP] Loaded userPermissions:', window.userPermissions);
		} else {
			window.userPermissions = {};
			console.warn('[BOOTSTRAP] Failed to load permissions:', data.error);
		}
	} catch (e) {
		window.userPermissions = {};
		console.error('[BOOTSTRAP] Error loading permissions:', e);
	}
}

(async () => {
	   await fetchAndSetUserPermissions();
	   // Now load all modules that depend on permissions
	   const coreModule = await import('./modules/core.js');
	   // Attach getUser to window.App synchronously
	   if (coreModule.getUser) {
		   window.App.getUser = coreModule.getUser;
	   } else if (coreModule.default && coreModule.default.getUser) {
		   window.App.getUser = coreModule.default.getUser;
	   }
	   await import('./modules/custom-fields.js');
	   await import('./modules/leads.js');
	   await import('./modules/kanban.js');
	await import('./modules/reports.js');
	await import('./modules/settings.js');
	await import('./modules/organizations.js');
	await import('./modules/import.js');
	await import('./modules/utils.js');
	await import('./modules/dashboard.js');
	await import('./modules/meetings.js');
	await import('./modules/invitations.js');
	await import('./modules/audit.js');
})();
