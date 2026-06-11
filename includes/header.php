<?php
$projectRoot = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"]));
if ($projectRoot === '/') $projectRoot = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - CRM.Zingbot.io' : 'CRM.Zingbot.io'; ?></title>

    <!-- Inject user permissions for JS UI gates -->
    <?php
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/permissions.php';
    $user = null;
    if (isset($_SESSION['user_id'])) {
        // You may need to adjust this to match your user session structure
        $user = $_SESSION['user'] ?? [
            'id' => $_SESSION['user_id'],
            'org_id' => $_SESSION['org_id'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
        $pm = new PermissionManager($pdo, $user);
        $userPermissions = $pm->getAllPermissions();
    } else {
        $userPermissions = [];
    }
    ?>
    <script>
        window.userPermissions = <?php echo json_encode($userPermissions); ?>;
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <!-- GridStack — drag/resize dashboard widgets -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack-all.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dist/output.css">
    <link rel="stylesheet" href="css/reports-filters.css">
    <style>
        :root {
            --color-primary: #2563EB;
            --color-primary-dark: #1D4ED8;
            --color-primary-soft: #EFF6FF;
            --color-sidebar: #1E3A5F;
            --color-sidebar-hover: #2D4F7C;
            --color-sidebar-active: #3B63A0;
            --color-bg: #F8FAFC;
            --color-surface: #FFFFFF;
            --color-border: #E2E8F0;
            --color-text: #0F172A;
            --color-muted: #64748B;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--color-bg);
        }

        /* ── GridStack dashboard ─────────────────────────────────── */
        .grid-stack { background: transparent; }
        .grid-stack-item-content {
            border-radius: 16px;
            overflow: visible !important; /* must not clip the resize handle */
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            background: #fff;
        }
        /* Inner card clips its own content (the chart-container div) */
        .grid-stack-item-content > [id^="chart-container-"] {
            overflow: hidden;
        }
        /* Resize handles — hidden in static mode, visible in edit mode */
        .grid-stack > .grid-stack-item > .ui-resizable-handle { display: none; }
        .gs-edit-mode > .grid-stack-item > .ui-resizable-handle { display: block; }
        .gs-edit-mode > .grid-stack-item > .ui-resizable-se {
            width: 20px; height: 20px; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M0 12 L12 0 L12 12 Z' fill='%232563EB' fill-opacity='0.7'/%3E%3C/svg%3E") no-repeat center;
            cursor: se-resize;
            border-radius: 0 0 16px 0;
            z-index: 10;
        }
        .gs-edit-mode > .grid-stack-item > .ui-resizable-s {
            height: 8px; bottom: 0; left: 12px; right: 12px; cursor: s-resize;
        }
        .gs-edit-mode > .grid-stack-item > .ui-resizable-e {
            width: 8px; right: 0; top: 12px; bottom: 12px; cursor: e-resize;
        }
        /* Drag cursor on title bar — only active in edit mode */
        .grid-stack-item .gs-drag-handle { cursor: default; user-select: none; }
        .gs-edit-mode .grid-stack-item .gs-drag-handle { cursor: grab; }
        .gs-edit-mode .grid-stack-item .gs-drag-handle:active { cursor: grabbing; }
        /* Drag icon + header tint in edit mode */
        .gs-edit-mode .chart-card-header { background: #F0F7FF !important; }
        .gs-edit-mode .chart-drag-icon { color: #93C5FD !important; }
        /* Block chart click-through in edit mode */
        .gs-edit-mode .chart-inner-container { pointer-events: none; }
        /* Edit mode: card border highlight */
        .gs-edit-mode .grid-stack-item-content > [id^="chart-container-"] {
            box-shadow: 0 0 0 2px #93C5FD !important;
        }
        /* Placeholder while dragging */
        .grid-stack-placeholder > .placeholder-content {
            background: #EFF6FF;
            border: 2px dashed #93C5FD;
            border-radius: 16px;
        }

        /* ── Chart card toolbar ─────────────────────────────────── */
        .chart-toolbar-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 6px; border: none;
            background: transparent; color: #94A3B8;
            transition: background 0.15s, color 0.15s;
        }
        .chart-toolbar-btn:hover { background: #F1F5F9; color: #475569; }
        .chart-menu-wrapper { position: relative; }
        .chart-dropdown-menu {
            position: absolute; right: 0; top: calc(100% + 4px); z-index: 50;
            min-width: 190px; background: #fff; border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12); border: 1px solid #E2E8F0;
            padding: 4px; overflow: hidden;
        }
        .chart-dropdown-menu button {
            display: flex; align-items: center; gap: 8px; width: 100%;
            padding: 8px 12px; font-size: 0.8125rem; font-weight: 500;
            background: transparent; border: none; border-radius: 6px;
            color: #374151; cursor: pointer; text-align: left;
            transition: background 0.12s;
        }
        .chart-dropdown-menu button:hover { background: #F8FAFC; }
        /* Canvas wrapper fills card body */
        .chart-canvas-wrap { display: flex; }
        .chart-canvas-wrap canvas { width: 100% !important; height: 100% !important; }
        /* Fullscreen card overrides */
        #appContent :fullscreen .chart-inner-container { max-height: calc(100vh - 48px) !important; }

        /* Custom scrollbar for table container */
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            min-width: 300px;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }

        .toast.success { background: #10b981; color: white; border-left: 4px solid #059669; }
        .toast.error { background: #ef4444; color: white; border-left: 4px solid #dc2626; }
        .toast.warning { background: #f59e0b; color: white; border-left: 4px solid #d97706; }
        .toast.info { background: #2563EB; color: white; border-left: 4px solid #1D4ED8; }

        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }

        #advancedFiltersSection {
            transition: all 0.3s ease-in-out;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
        }

        #advancedFiltersIcon {
            transition: transform 0.3s ease;
        }

        /* Sticky Table Header & Persistent Scrollbar */
        .table-wrapper {
            max-height: calc(100vh - 280px);
            overflow: auto;
            position: relative;
        }

        .sticky-header th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #F8FAFC;
        }

        /* Ensure filter row is also sticky if shown */
        #headerFilterRow th {
            position: sticky;
            top: 45px;
            z-index: 9;
            background: #F8FAFC;
        }

        /* Sidebar Toggle Styles */
        #sidebar-container {
            transition: width 0.3s ease-in-out;
        }
        
        #sidebar-container .nav-label,
        #sidebar-container .logo-text,
        #sidebar-container .user-details,
        #sidebar-container .org-selector-label {
            transition: opacity 0.2s ease-in-out;
        }

        .sidebar-collapsed #sidebar-container {
            width: 4.5rem !important;
        }

        .sidebar-collapsed .logo-text,
        .sidebar-collapsed .nav-label,
        .sidebar-collapsed .user-details,
        .sidebar-collapsed .org-selector-label,
        .sidebar-collapsed #orgSelectorDropdown {
            display: none !important;
        }

        .sidebar-collapsed #sidebar-container nav a {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .sidebar-collapsed #sidebar-container nav a i {
            margin-right: 0 !important;
        }

        .sidebar-collapsed #sidebar-container .px-4,
        .sidebar-collapsed #sidebar-container .px-2 {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }

        .sidebar-collapsed .content-expand {
            max-width: none !important;
        }
        
        /* Date range active button */
        .date-range-btn.active {
            background: var(--color-primary);
            color: #ffffff !important;
        }

        /* Org dropdown items */
        #orgDropdownList a, #orgDropdownList button {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: var(--color-text);
            text-align: left;
        }
        #orgDropdownList a:hover, #orgDropdownList button:hover {
            background: var(--color-primary-soft);
            color: var(--color-primary);
        }
    </style>
</head>
<body class="h-screen flex overflow-hidden" style="background: var(--color-bg)">
<div id="toastContainer" class="toast-container"></div>
