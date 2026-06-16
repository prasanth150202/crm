<?php
/**
 * Admin Login Page
 * Authenticates super-admin users (is_super_admin = 1).
 */
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/db.php';

Security::secureSession();

// Already logged in as super admin → straight to panel
if (!empty($_SESSION['is_super_admin'])) {
    header('Location: admin-panel.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCsrfToken($token)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Email and password are required.';
        } else {
            try {
                $pdo  = getDb();
                $stmt = $pdo->prepare(
                    "SELECT id, email, full_name, password_hash, is_super_admin
                     FROM users
                     WHERE email = ? AND is_super_admin = 1
                     LIMIT 1"
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Regenerate session on login
                    session_regenerate_id(true);
                    $_SESSION['user_id']       = $user['id'];
                    $_SESSION['email']         = $user['email'];
                    $_SESSION['full_name']     = $user['full_name'];
                    $_SESSION['role']          = 'super_admin';
                    $_SESSION['is_super_admin'] = true;
                    $_SESSION['org_id']        = null;

                    header('Location: admin-panel.php');
                    exit;
                } else {
                    $error = 'Invalid email or password, or you do not have admin access.';
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again.';
            }
        }
    }
}

$csrf = Security::generateCsrfToken();
$projectRoot = rtrim(Env::getProjectRoot(), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .panel-bg { background: linear-gradient(145deg, #1E3A5F 0%, #142a47 100%); }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- Left branding panel -->
    <div class="hidden lg:flex lg:w-1/2 panel-bg flex-col justify-center items-center p-12 text-white">
        <div class="mb-8">
            <div class="w-16 h-16 bg-blue-500 bg-opacity-20 rounded-2xl flex items-center justify-center mb-6">
                <i data-lucide="shield-check" class="w-9 h-9 text-blue-300"></i>
            </div>
            <h1 class="text-3xl font-bold mb-2">Admin Panel</h1>
            <p class="text-blue-200 text-sm leading-relaxed max-w-xs">
                Manage subscription plans, organizations, and leads across all tenants.
            </p>
        </div>
        <div class="space-y-3 w-full max-w-xs">
            <?php foreach (['Subscription Plan Management','Organization Management','Lead Management'] as $feat): ?>
            <div class="flex items-center gap-3 bg-white bg-opacity-5 rounded-lg px-4 py-3">
                <i data-lucide="check-circle" class="w-4 h-4 text-blue-300 shrink-0"></i>
                <span class="text-sm text-blue-100"><?= htmlspecialchars($feat) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right login form -->
    <div class="flex-1 flex flex-col justify-center items-center p-8 bg-gray-50">
        <div class="w-full max-w-md">

            <!-- Logo / title -->
            <div class="mb-8 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 rounded-xl mb-4">
                    <i data-lucide="shield" class="w-6 h-6 text-white"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Admin Sign In</h2>
                <p class="text-gray-500 text-sm mt-1">Super-admin access only</p>
            </div>

            <!-- Error banner -->
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2 text-sm text-red-700">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-5">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </span>
                        <input type="email" id="email" name="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required autofocus
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                            placeholder="admin@example.com">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input type="password" id="password" name="password"
                            required
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit"
                    class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Sign In to Admin Panel
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-gray-400">
                This area is restricted to super-admin accounts only.
            </p>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
