<?php
// payment_required.php
require_once 'config/db.php';
require_once 'config/security.php';
require_once 'config/env.php';

Security::secureSession();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['org_id'])) {
    header('Location: login.php');
    exit;
}

$org_id = $_SESSION['org_id'];

// Get Org and Subscription details
$stmt = $pdo->prepare("
    SELECT o.name as org_name, s.razorpay_subscription_id, p.name as plan_name, p.base_price_monthly, p.currency
    FROM organizations o
    LEFT JOIN subscriptions s ON o.id = s.organization_id
    LEFT JOIN plans p ON o.current_plan_id = p.id
    WHERE o.id = ?
");
$stmt->execute([$org_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data || empty($data['razorpay_subscription_id'])) {
    // If no subscription found, something is wrong
    die("No pending subscription found for this organization.");
}

// Get all organizations for the user
$stmtOrgs = $pdo->prepare("
    SELECT o.id, o.name, o.is_active, o.status
    FROM user_organizations uo
    JOIN organizations o ON o.id = uo.org_id
    WHERE uo.user_id = :user_id AND uo.is_active = 1
");
$stmtOrgs->execute([':user_id' => $_SESSION['user_id']]);
$allOrgs = $stmtOrgs->fetchAll(PDO::FETCH_ASSOC);

$otherOrgs = array_filter($allOrgs, function($o) use ($org_id) {
    return $o['id'] != $org_id && $o['is_active'] == 1 && $o['status'] === 'active';
});

$razorpay_key = Env::get('RAZORPAY_KEY_ID');
$org_status = $_SESSION['org_status'] ?? 'pending_payment'; // You might need to fetch this from DB effectively
// Let's re-fetch status from DB to be fresh
$stmtStatus = $pdo->prepare("SELECT status FROM organizations WHERE id = ?");
$stmtStatus->execute([$org_id]);
$org_status = $stmtStatus->fetchColumn();

$title = ($org_status === 'suspended') ? 'Account Suspended' : 'Payment Required';
$msg = ($org_status === 'suspended') 
    ? "Your organization <strong>" . htmlspecialchars($data['org_name']) . "</strong> has been suspended due to an issue with your subscription. Please pay the outstanding amount to reactivate your access."
    : "Your organization <strong>" . htmlspecialchars($data['org_name']) . "</strong> is currently pending payment for the <strong>" . htmlspecialchars($data['plan_name']) . "</strong>.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - CRM.Zingbot.io</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center">
        <div class="w-16 h-16 <?php echo $org_status === 'suspended' ? 'bg-red-100' : 'bg-yellow-100'; ?> rounded-full flex items-center justify-center mx-auto mb-6">
            <?php if ($org_status === 'suspended'): ?>
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            <?php else: ?>
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            <?php endif; ?>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-900 mb-2"><?php echo $title; ?></h1>
        <p class="text-gray-600 mb-6"><?php echo $msg; ?></p>
        
        <div class="bg-gray-50 rounded-xl p-4 mb-8 text-left border border-gray-100">
            <div class="flex justify-between mb-2">
                <span class="text-gray-500">Plan</span>
                <span class="font-medium"><?php echo htmlspecialchars($data['plan_name']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Amount</span>
                <span class="font-bold text-green-600"><?php echo $data['currency'] . ' ' . number_format($data['base_price_monthly'], 2); ?></span>
            </div>
        </div>

        <button id="payNowBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl transition duration-200 shadow-lg shadow-blue-100 flex items-center justify-center mb-6">
            <span>Pay to Unlock Access</span>
            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
            </svg>
        </button>

        <?php if (!empty($otherOrgs)): ?>
            <div class="border-t border-gray-100 pt-6 mt-2 text-left">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Switch to another organization</h3>
                <div class="space-y-2">
                    <?php foreach ($otherOrgs as $org): ?>
                        <button onclick="switchOrg(<?php echo $org['id']; ?>)" class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 border border-gray-100 transition duration-150">
                            <span class="font-medium text-gray-700"><?php echo htmlspecialchars($org['name']); ?></span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="text-sm text-gray-400 italic mb-6">
                You don't have any other active organizations to switch to.
            </div>
        <?php endif; ?>

        <div class="mt-8">
            <a href="logout.php" class="text-gray-500 hover:text-red-600 font-medium flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Sign Out
            </a>
        </div>
    </div>

    <script>
        // Organization Switching Logic
        async function switchOrg(orgId) {
            try {
                const response = await fetch('api/organizations/switch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ org_id: orgId })
                });
                
                const result = await response.json();
                if (result.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    alert(result.error || 'Failed to switch organization');
                }
            } catch (error) {
                console.error('Switch error:', error);
                alert('An error occurred while switching organization');
            }
        }

        // Razorpay Payment Logic
        document.getElementById('payNowBtn').onclick = function(e) {
            const options = {
                "key": "<?php echo $razorpay_key; ?>",
                "subscription_id": "<?php echo $data['razorpay_subscription_id']; ?>",
                "name": "CRM.Zingbot.io",
                "description": "Subscription Payment - <?php echo htmlspecialchars($data['org_name']); ?>",
                "handler": function (response) {
                    alert("Payment successful! Unlocking your organization...");
                    window.location.reload();
                },
                "prefill": {
                    "name": "<?php echo htmlspecialchars($data['org_name']); ?>",
                    "email": "<?php echo $_SESSION['email'] ?? ''; ?>"
                },
                "theme": {
                    "color": "#2563eb"
                }
            };
            const rzp = new Razorpay(options);
            rzp.open();
            e.preventDefault();
        }
    </script>
</body>
</html>
