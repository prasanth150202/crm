<?php
/**
 * Admin Panel — Standalone page for super-admin users.
 */
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/env.php';

Security::secureSession();

// Guard: must be super admin
if (empty($_SESSION['is_super_admin'])) {
    header('Location: admin-login.php');
    exit;
}

$adminName    = htmlspecialchars($_SESSION['full_name'] ?? 'Admin');
$adminEmail   = htmlspecialchars($_SESSION['email'] ?? '');
$csrf         = Security::generateCsrfToken();
$projectRoot  = rtrim(Env::getProjectRoot(), '/');
$apiUrl       = 'http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $projectRoot . '/api';
$initials     = strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        :root {
            --sidebar-bg: #1E3A5F;
            --sidebar-border: #2D4F7C;
            --sidebar-active: rgba(255,255,255,0.12);
        }
        #sidebar { background: var(--sidebar-bg); width: 220px; flex-shrink: 0; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px;
            color: #bfdbfe; font-size: 0.875rem; font-weight: 500; transition: all 0.15s; cursor: pointer; }
        .nav-link:hover { background: var(--sidebar-active); color: #fff; }
        .nav-link.active { background: var(--sidebar-active); color: #fff; }
        /* Toast */
        #toastContainer { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast { padding: 10px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 500;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12); animation: slideUp 0.2s ease; }
        .toast-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .toast-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        @keyframes slideUp { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

<!-- ── Sidebar ──────────────────────────────────────────────────────────────── -->
<div id="sidebar" class="flex flex-col h-screen sticky top-0">
    <!-- Logo -->
    <div class="flex items-center gap-2 px-4 py-5" style="border-bottom: 1px solid var(--sidebar-border);">
        <div class="w-8 h-8 rounded-lg bg-blue-500 bg-opacity-20 flex items-center justify-center">
            <i data-lucide="shield" class="w-4 h-4 text-blue-300"></i>
        </div>
        <span class="text-white font-semibold text-sm">Admin Panel</span>
    </div>

    <!-- Nav -->
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
        <button class="nav-link active w-full" id="nav-plans" onclick="Admin.switchTab('plans')">
            <i data-lucide="credit-card" class="w-4 h-4 shrink-0"></i>
            Subscription Plans
        </button>
        <button class="nav-link w-full" id="nav-organizations" onclick="Admin.switchTab('organizations')">
            <i data-lucide="building-2" class="w-4 h-4 shrink-0"></i>
            Organizations
        </button>
    </nav>

    <!-- User card -->
    <div class="p-3" style="border-top: 1px solid var(--sidebar-border); background: #152C4A;">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-semibold">
                <?= $initials ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white text-xs font-medium truncate"><?= $adminName ?></p>
                <p class="text-blue-300 text-xs truncate"><?= $adminEmail ?></p>
            </div>
            <a href="admin-logout.php" title="Sign out" class="text-blue-300 hover:text-white">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</div>

<!-- ── Main content ──────────────────────────────────────────────────────────── -->
<div class="flex-1 flex flex-col min-h-screen overflow-auto">

    <!-- Top bar -->
    <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between sticky top-0 z-10">
        <h1 id="pageTitle" class="text-base font-semibold text-gray-800">Subscription Plans</h1>
        <span class="text-xs text-gray-400">Super Admin</span>
    </header>

    <!-- Content area -->
    <main id="appContent" class="flex-1 p-6">
        <div class="flex justify-center pt-20">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
    </main>
</div>

<!-- Toast container -->
<div id="toastContainer"></div>

<!-- ── App shim: provides the helpers that admin.js depends on ────────────────── -->
<script>
window.AppData = {
    csrf_token: '<?= $csrf ?>',
    is_super_admin: true,
    user: { org_id: null },
    config: {
        projectRoot: '<?= $projectRoot ?>',
        apiUrl: '<?= $apiUrl ?>'
    }
};

window.App = {
    apiUrl: '<?= $apiUrl ?>',

    escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str ?? ''));
        return d.innerHTML;
    },

    async api(endpoint, method = 'GET', body = null) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': window.AppData.csrf_token
        };
        const options = { method, headers, credentials: 'include' };
        if (body) options.body = JSON.stringify(body);

        let url = this.apiUrl + endpoint;
        // For GET requests, strip the _admin=1 shim param we add; org_id from body is irrelevant for admin
        if (method === 'GET' && body === null) {
            // Don't auto-inject org_id for admin calls
        } else if (body && body.org_id === undefined) {
            // don't force inject
        }

        try {
            const res  = await fetch(url, options);
            const text = await res.text();
            const json = text ? JSON.parse(text) : null;
            if (!res.ok) return { error: (json && json.error) || `Request failed (${res.status})` };
            return json;
        } catch (e) {
            console.error('API error:', e);
            return { error: e.message };
        }
    },

    showToast(message, type = 'success', duration = 3000) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    },

    showModal(config) {
        this.closeAllModals();
        const id      = config.id || 'dynamicModal';
        const size    = config.size || 'max-w-md';
        const title   = config.title || '';
        const content = config.content || '';
        const footer  = config.footer || '';

        const existing = document.getElementById(id);
        if (existing) existing.remove();

        document.body.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="fixed inset-0 z-[1000] overflow-y-auto" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" onclick="App.closeModal('${id}')"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle ${size} w-full">
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-900">${title}</h3>
                                <button onclick="App.closeModal('${id}')" class="text-gray-400 hover:text-gray-600">
                                    <i data-lucide="x" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <div class="text-sm text-gray-600">${content}</div>
                        </div>
                        ${footer ? `<div class="bg-gray-50 px-6 py-4 flex justify-end gap-2">${footer}</div>` : ''}
                    </div>
                </div>
            </div>`);
        if (window.lucide) lucide.createIcons();
    },

    closeModal(id) {
        const el = document.getElementById(id || 'dynamicModal');
        if (el) el.remove();
    },

    closeAllModals() {
        document.querySelectorAll('[role="dialog"]').forEach(el => el.remove());
    },

    showConfirm(message, onConfirm, onCancel = null) {
        this.showModal({
            id: 'confirmModal',
            size: 'max-w-sm',
            title: 'Confirm',
            content: `<p class="text-gray-700">${this.escapeHtml(message)}</p>`,
            footer: `
                <button onclick="App.closeModal('confirmModal')" class="px-4 py-2 border border-gray-300 text-sm rounded-lg hover:bg-gray-50">Cancel</button>
                <button id="confirmOkBtn" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700">Confirm</button>`
        });
        setTimeout(() => {
            const btn = document.getElementById('confirmOkBtn');
            if (btn) btn.onclick = () => { this.closeModal('confirmModal'); onConfirm(); };
        }, 50);
    }
};
</script>

<!-- ── Admin module ───────────────────────────────────────────────────────────── -->
<script type="module">
    await import('<?= $projectRoot ?>/js/modules/admin.js?v=<?= time() ?>');
    // window.Admin is now set by the module

    const Admin = window.Admin;
    const TAB_TITLES = { plans: 'Subscription Plans', organizations: 'Organizations' };

    // Override renderShell — standalone panel already has its own layout
    Admin.renderShell = function() {};

    // Override switchTab — update sidebar nav + page title, then call original logic
    const _origSwitch = Admin.switchTab.bind(Admin);
    Admin.switchTab = function(tab) {
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        const navEl = document.getElementById('nav-' + tab);
        if (navEl) navEl.classList.add('active');
        document.getElementById('pageTitle').textContent = TAB_TITLES[tab] || tab;
        // In standalone mode #appContent serves as #adminContent
        const ac = document.getElementById('adminContent') || document.getElementById('appContent');
        if (ac) ac.innerHTML = '<div class="flex justify-center pt-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
        // Temporarily ensure element has id="adminContent" so original loadXTab() finds it
        if (ac && ac.id !== 'adminContent') ac.id = 'adminContent';
        _origSwitch(tab);
    };

    // Override init — skip the SPA guard and alias the container
    Admin.init = function() {
        const appContent = document.getElementById('appContent');
        if (appContent) appContent.id = 'adminContent';
        Admin.switchTab('plans');
    };

    lucide.createIcons();
    Admin.init();
</script>

</body>
</html>
