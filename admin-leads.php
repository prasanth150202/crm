<?php
session_start();
require_once 'config/env.php';
require_once 'config/db.php';

$admin_password = Env::get('ADMIN_PASSWORD', 'admin123');

if (isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['site_admin_logged_in'] = true;
    } else {
        $error = "Invalid password.";
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['site_admin_logged_in']);
    header('Location: admin-leads.php');
    exit;
}

$pageTitle = 'Admin Leads | Crm.Zingbot.io';
include_once 'includes/landing-header.php';
?>

<section class="pt-40 pb-20 bg-slate-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php if (!isset($_SESSION['site_admin_logged_in'])): ?>
            <div class="max-w-md mx-auto bg-white p-12 rounded-[2.5rem] shadow-xl border border-slate-100 mt-20">
                <div class="text-center mb-10">
                    <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="material-symbols-outlined text-4xl">lock</span>
                    </div>
                    <h2 class="text-3xl font-black text-slate-900 mb-2">Admin Login</h2>
                   
                </div>
                <form method="POST" class="space-y-6">
                    <div>
                        <input type="password" name="password" required placeholder="Enter password" class="w-full px-6 py-4 rounded-2xl border-2 border-slate-50 focus:border-primary focus:ring-0 transition-all outline-none bg-slate-50">
                    </div>
                    <?php if (isset($error)): ?>
                        <p class="text-red-500 text-sm font-bold text-center"><?php echo $error; ?></p>
                    <?php endif; ?>
                    <button type="submit" class="w-full bg-slate-900 text-white font-black py-4 rounded-2xl hover:bg-slate-800 transition-all shadow-lg active:scale-95">Access Dashboard</button>
                </form>
            </div>
        <?php else: ?>
            <?php 
            $view = $_GET['view'] ?? 'inquiries'; 
            ?>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-6">
                <div>
                    <h1 class="text-4xl font-black text-slate-900">Admin <span class="text-primary italic">Console</span></h1>
                    <div class="flex gap-8 mt-6">
                        <a href="?view=inquiries" class="text-sm font-black uppercase tracking-widest transition-all pb-2 border-b-2 <?php echo $view === 'inquiries' ? 'text-primary border-primary' : 'text-slate-400 border-transparent hover:text-slate-600'; ?>">
                            Inquiries
                        </a>
                        <a href="?view=organizations" class="text-sm font-black uppercase tracking-widest transition-all pb-2 border-b-2 <?php echo $view === 'organizations' ? 'text-primary border-primary' : 'text-slate-400 border-transparent hover:text-slate-600'; ?>">
                            Organizations
                        </a>
                    </div>
                </div>
                <div class="flex gap-4">
                    <a href="dashboard.php" class="px-6 py-3 bg-white border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50 transition-all">App Home</a>
                    <a href="?logout=1" class="px-6 py-3 bg-red-50 text-red-600 rounded-xl font-bold hover:bg-red-100 transition-all">Logout</a>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <?php if ($view === 'inquiries'): ?>
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest">Date</th>
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest">Lead Details</th>
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest">Message</th>
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest">Status</th>
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php
                                $pdo = getDb();
                                $leads = $pdo->query("SELECT * FROM site_leads ORDER BY created_at DESC")->fetchAll();
                                
                                if (empty($leads)): ?>
                                    <tr>
                                        <td colspan="5" class="px-8 py-20 text-center text-slate-400 font-bold">No inquiries yet. Keep marketing!</td>
                                    </tr>
                                <?php else:
                                    foreach ($leads as $lead): ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-8 py-8 whitespace-nowrap">
                                                <span class="text-sm font-bold text-slate-900"><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></span>
                                                <div class="text-xs text-slate-400 mt-1"><?php echo date('H:i', strtotime($lead['created_at'])); ?></div>
                                            </td>
                                            <td class="px-8 py-8">
                                                <div class="font-black text-slate-900"><?php echo htmlspecialchars($lead['name']); ?></div>
                                                <div class="text-sm text-primary font-bold lowercase"><?php echo htmlspecialchars($lead['email']); ?></div>
                                                <?php if ($lead['organization']): ?>
                                                    <div class="flex items-center gap-1 mt-2 text-xs font-bold text-slate-400 bg-slate-100 w-fit px-2 py-1 rounded">
                                                        <span class="material-symbols-outlined text-sm">business</span>
                                                        <?php echo htmlspecialchars($lead['organization']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-8 py-8 min-w-[300px]">
                                                <p class="text-sm text-slate-600 leading-relaxed max-w-sm"><?php echo htmlspecialchars($lead['message']); ?></p>
                                            </td>
                                            <td class="px-8 py-8">
                                                <?php 
                                                $statusColors = [
                                                    'new' => 'bg-orange-100 text-orange-600',
                                                    'contacted' => 'bg-blue-100 text-blue-600',
                                                    'converted' => 'bg-emerald-100 text-emerald-600',
                                                    'ignored' => 'bg-slate-100 text-slate-500'
                                                ];
                                                $colorClass = $statusColors[$lead['status']] ?? 'bg-slate-100 text-slate-600';
                                                ?>
                                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest <?php echo $colorClass; ?>">
                                                    <?php echo $lead['status']; ?>
                                                </span>
                                            </td>
                                            <td class="px-8 py-8 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center hover:bg-primary hover:text-white transition-all">
                                                        <span class="material-symbols-outlined text-xl">mail</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; 
                                endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest">Org ID</th>
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest">Organization Name</th>
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest">Plan</th>
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest">Users</th>
                                    <th class="px-8 py-6 text-sm font-black text-slate-400 uppercase tracking-widest text-right">Active Since</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php
                                $pdo = getDb();
                                $sql = "SELECT o.*, p.name as plan_name, 
                                        (SELECT COUNT(*) FROM users WHERE org_id = o.id) as user_count 
                                        FROM organizations o 
                                        LEFT JOIN plans p ON o.current_plan_id = p.id 
                                        ORDER BY o.created_at DESC";
                                $orgs = $pdo->query($sql)->fetchAll();
                                
                                foreach ($orgs as $org): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-8 py-8">
                                            <span class="font-mono text-slate-400 font-bold">#<?php echo $org['id']; ?></span>
                                        </td>
                                        <td class="px-8 py-8">
                                            <div class="font-black text-slate-900"><?php echo htmlspecialchars($org['name']); ?></div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest <?php echo $org['status'] === 'active' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'; ?>">
                                                    <?php echo $org['status']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-8 py-8">
                                            <span class="text-sm font-bold text-slate-600"><?php echo $org['plan_name'] ?: 'No Plan'; ?></span>
                                        </td>
                                        <td class="px-8 py-8">
                                            <div class="flex items-center gap-2">
                                                <span class="material-symbols-outlined text-slate-300">group</span>
                                                <span class="text-sm font-black text-slate-900"><?php echo $org['user_count']; ?></span>
                                            </div>
                                        </td>
                                        <td class="px-8 py-8 text-right">
                                            <span class="text-sm font-bold text-slate-400"><?php echo date('M d, Y', strtotime($org['created_at'])); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include_once 'includes/landing-footer.php'; ?>
