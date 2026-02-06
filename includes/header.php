<?php
$projectRoot = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"]));
if ($projectRoot === '/') $projectRoot = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - Lead Manager' : 'Lead Manager'; ?></title>

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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dist/output.css">
    <link rel="stylesheet" href="css/reports-filters.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

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

        .toast.success { background: #10b981; color: white; }
        .toast.error { background: #ef4444; color: white; }
        .toast.warning { background: #f59e0b; color: white; }
        .toast.info { background: #3b82f6; color: white; }

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
            background: #f9fafb; /* gray-50 */
        }

        /* Ensure filter row is also sticky if shown */
        #headerFilterRow th {
            position: sticky;
            top: 45px; /* Adjust based on height of first header row */
            z-index: 9;
            background: #f9fafb;
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
        
        /* Adjust tooltips for collapsed icons could be a future enhancement */
    </style>
</head>
<body class="bg-gray-50 h-screen flex overflow-hidden">
<div id="toastContainer" class="toast-container"></div>
