<?php
/**
 * Database Migration Runner
 * Access: /crm-final/migrate.php  (super-admin session required)
 */
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/db.php';

Security::secureSession();

if (empty($_SESSION['is_super_admin'])) {
    http_response_code(403);
    exit('<h1>403 Forbidden</h1><p>Super-admin access required.</p>');
}

// ── Migration definitions ─────────────────────────────────────────────────────
// Each migration has an id, description, and a callable that receives $pdo.
// Use SHOW COLUMNS / SHOW INDEX / information_schema checks inside the callable
// to make every migration idempotent (safe to run multiple times).

$migrations = [

    [
        'id'   => 'plans__trial_days',
        'desc' => 'plans — add trial_days INT DEFAULT 14',
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM plans LIKE 'trial_days'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE plans ADD COLUMN trial_days INT NOT NULL DEFAULT 14 AFTER razorpay_plan_id_yearly");
            return 'applied';
        },
    ],

    [
        'id'   => 'plans__is_custom',
        'desc' => 'plans — add is_custom TINYINT DEFAULT 0',
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM plans LIKE 'is_custom'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE plans ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0 AFTER trial_days");
            return 'applied';
        },
    ],

    [
        'id'   => 'plans__custom_org_id',
        'desc' => 'plans — add custom_org_id INT NULL',
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM plans LIKE 'custom_org_id'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE plans ADD COLUMN custom_org_id INT(11) DEFAULT NULL AFTER is_custom");
            return 'applied';
        },
    ],

    [
        'id'   => 'plans__tax_treatment',
        'desc' => "plans — add tax_treatment ENUM('none','exclusive','inclusive') DEFAULT 'none'",
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM plans LIKE 'tax_treatment'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE plans ADD COLUMN tax_treatment ENUM('none','exclusive','inclusive') NOT NULL DEFAULT 'none' AFTER base_price_yearly");
            return 'applied';
        },
    ],

    [
        'id'   => 'organizations__status_trial',
        'desc' => "organizations — expand status ENUM to include 'trial'",
        'run'  => function (PDO $pdo) {
            $row = $pdo->query("SHOW COLUMNS FROM organizations LIKE 'status'")->fetch();
            if ($row && strpos($row['Type'], 'trial') !== false) return 'already exists';
            $pdo->exec("ALTER TABLE organizations MODIFY COLUMN status ENUM('active','suspended','trial') NOT NULL DEFAULT 'active'");
            return 'applied';
        },
    ],

    [
        'id'   => 'organizations__is_active',
        'desc' => 'organizations — add is_active TINYINT DEFAULT 1',
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM organizations LIKE 'is_active'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE organizations ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER status");
            return 'applied';
        },
    ],

    [
        'id'   => 'subscriptions__rzp_nullable',
        'desc' => 'subscriptions — make razorpay_subscription_id nullable',
        'run'  => function (PDO $pdo) {
            $row = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'razorpay_subscription_id'")->fetch();
            if (!$row) return 'column not found — skipped';
            if (stripos($row['Null'], 'YES') !== false) return 'already nullable';
            $pdo->exec("ALTER TABLE subscriptions MODIFY COLUMN razorpay_subscription_id VARCHAR(255) NULL DEFAULT NULL");
            // Drop unique index if it exists so we can recreate it allowing NULLs
            $idx = $pdo->query("SHOW INDEX FROM subscriptions WHERE Key_name = 'razorpay_subscription_id'")->rowCount();
            if ($idx) $pdo->exec("ALTER TABLE subscriptions DROP INDEX razorpay_subscription_id");
            $pdo->exec("ALTER TABLE subscriptions ADD UNIQUE KEY uq_rzp_sub_id (razorpay_subscription_id)");
            return 'applied';
        },
    ],

    [
        'id'   => 'subscriptions__notes',
        'desc' => 'subscriptions — add notes TEXT NULL',
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'notes'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE subscriptions ADD COLUMN notes TEXT DEFAULT NULL AFTER ends_at");
            return 'applied';
        },
    ],

    [
        'id'   => 'subscriptions__managed_by',
        'desc' => 'subscriptions — add managed_by INT NULL',
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'managed_by'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE subscriptions ADD COLUMN managed_by INT(11) DEFAULT NULL AFTER notes");
            return 'applied';
        },
    ],

    [
        'id'   => 'subscriptions__current_period_start',
        'desc' => 'subscriptions — add current_period_start DATETIME NULL',
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'current_period_start'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE subscriptions ADD COLUMN current_period_start DATETIME DEFAULT NULL AFTER managed_by");
            return 'applied';
        },
    ],

    [
        'id'   => 'subscriptions__current_period_end',
        'desc' => 'subscriptions — add current_period_end DATETIME NULL',
        'run'  => function (PDO $pdo) {
            $col = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'current_period_end'")->rowCount();
            if ($col) return 'already exists';
            $pdo->exec("ALTER TABLE subscriptions ADD COLUMN current_period_end DATETIME DEFAULT NULL AFTER current_period_start");
            return 'applied';
        },
    ],

];

// ── Run migrations if requested ───────────────────────────────────────────────
$results = [];
$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    $ran = true;
    foreach ($migrations as $m) {
        try {
            $status = ($m['run'])($pdo);
            $results[$m['id']] = ['ok' => true, 'status' => $status];
        } catch (Throwable $e) {
            $results[$m['id']] = ['ok' => false, 'status' => $e->getMessage()];
        }
    }
}

// ── Check current state for preview ──────────────────────────────────────────
$preview = [];
foreach ($migrations as $m) {
    try {
        // Dry-run: temporarily wrap in a transaction that we roll back
        $pdo->beginTransaction();
        $status = ($m['run'])($pdo);
        $pdo->rollBack();
        $preview[$m['id']] = ['ok' => true, 'status' => $status];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $preview[$m['id']] = ['ok' => false, 'status' => $e->getMessage()];
    }
}

$projectRoot = rtrim(Env::getProjectRoot(), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Migrations — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen p-8">

<div class="max-w-3xl mx-auto">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Database Migrations</h1>
            <p class="text-sm text-gray-500 mt-0.5">Each migration is idempotent — safe to run multiple times.</p>
        </div>
        <a href="<?= $projectRoot ?>/admin-panel.php"
           class="text-sm text-blue-600 hover:underline">← Back to Admin Panel</a>
    </div>

    <!-- Results banner -->
    <?php if ($ran): ?>
        <?php $failed = array_filter($results, fn($r) => !$r['ok']); ?>
        <?php if ($failed): ?>
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-medium">
                <?= count($failed) ?> migration(s) failed — see details below.
            </div>
        <?php else: ?>
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 font-medium">
                All migrations completed successfully.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Migration table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-6">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Migration</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-40">Preview</th>
                    <?php if ($ran): ?>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-40">Result</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($migrations as $i => $m): ?>
                <?php
                    $pre = $preview[$m['id']] ?? ['ok' => false, 'status' => '?'];
                    $res = $results[$m['id']] ?? null;
                    $preColor   = !$pre['ok'] ? 'text-red-600' : ($pre['status'] === 'applied' ? 'text-amber-600' : 'text-gray-400');
                    $preLabel   = !$pre['ok'] ? '✗ error' : ($pre['status'] === 'applied' ? '⚡ pending' : '✓ ' . $pre['status']);
                    $resColor   = $res ? (!$res['ok'] ? 'text-red-600' : 'text-green-600') : '';
                    $resLabel   = $res ? (!$res['ok'] ? '✗ failed' : '✓ ' . $res['status']) : '';
                ?>
                <tr class="<?= ($res && !$res['ok']) ? 'bg-red-50' : '' ?>">
                    <td class="px-4 py-3 text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($m['desc']) ?></p>
                        <p class="text-xs text-gray-400 font-mono mt-0.5"><?= htmlspecialchars($m['id']) ?></p>
                        <?php if ($res && !$res['ok']): ?>
                        <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($res['status']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs font-medium <?= $preColor ?>"><?= htmlspecialchars($preLabel) ?></td>
                    <?php if ($ran): ?>
                    <td class="px-4 py-3 text-xs font-medium <?= $resColor ?>"><?= htmlspecialchars($resLabel) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Legend -->
    <?php if (!$ran): ?>
    <div class="flex gap-4 text-xs text-gray-500 mb-6">
        <span><span class="font-semibold text-amber-600">⚡ pending</span> — will be applied</span>
        <span><span class="font-semibold text-gray-400">✓ already exists</span> — will be skipped</span>
        <span><span class="font-semibold text-red-600">✗ error</span> — dry-run failed</span>
    </div>
    <?php endif; ?>

    <!-- Run button -->
    <?php
        $pendingCount = count(array_filter($preview, fn($p) => $p['ok'] && $p['status'] === 'applied'));
    ?>
    <?php if (!$ran || array_filter($results, fn($r) => !$r['ok'])): ?>
    <form method="POST">
        <button type="submit" name="run" value="1"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 disabled:opacity-50"
            <?= $pendingCount === 0 && !$ran ? 'disabled' : '' ?>>
            <?php if ($pendingCount > 0): ?>
                Run <?= $pendingCount ?> pending migration<?= $pendingCount !== 1 ? 's' : '' ?>
            <?php else: ?>
                Re-run all migrations
            <?php endif; ?>
        </button>
        <?php if ($pendingCount === 0 && !$ran): ?>
        <p class="mt-2 text-xs text-gray-500">Nothing pending — database is up to date.</p>
        <?php endif; ?>
    </form>
    <?php else: ?>
    <div class="flex gap-3">
        <a href="<?= $projectRoot ?>/admin-panel.php"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700">
            Go to Admin Panel
        </a>
        <form method="POST">
            <button type="submit" name="run" value="1"
                class="px-5 py-2.5 border border-gray-300 text-sm font-medium rounded-xl hover:bg-gray-50">
                Run again
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
