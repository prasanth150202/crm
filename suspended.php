<?php
// suspended.php
require_once 'config/db.php';
require_once 'config/security.php';

Security::secureSession();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['org_id'])) {
    header('Location: login.php');
    exit;
}

$org_id = $_SESSION['org_id'];

// Get Org details
$stmt = $pdo->prepare("SELECT name FROM organizations WHERE id = ?");
$stmt->execute([$org_id]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended - CRM.Zingbot.io</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center border-t-4 border-red-500">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Account Suspended</h1>
        <p class="text-gray-600 mb-6">Your organization <strong><?php echo htmlspecialchars($org['name'] ?? 'This organization'); ?></strong> has been suspended. Please contact support at <strong>support@zingbot.io</strong> to resolve this issue.</p>
        
        <div class="space-y-3">
            <a href="logout.php" class="block w-full bg-gray-900 hover:bg-black text-white font-bold py-3 px-6 rounded-xl transition duration-200">
                Sign Out
            </a>
            <a href="dashboard.php" class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-6 rounded-xl transition duration-200">
                Switch Organization
            </a>
        </div>
    </div>
</body>
</html>
