<?php
require_once __DIR__ . '/includes/bootstrap.php';

$loginError = '';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $user = fetchUser($email);
    if ($user && ($user['role'] ?? '') === 'admin' && $user['password'] === $password) {
        $_SESSION['user_email'] = $email;
        header('Location: admin');
        exit;
    }
    $loginError = 'Invalid admin email or password.';
}

$admin = requireAdminSession();
$metrics = $admin ? getSystemMetrics() : null;
$planCoins = ['basic' => 250, 'medium' => 500, 'ultra' => 1000, 'max' => 1500];
$registeredUsers = $metrics ? ($metrics['totalUsers'] - $metrics['totalGuests']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Astro Healer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #020617; color: #e2e8f0; font-family: Inter, system-ui, sans-serif; }
        .card { background: rgba(15,23,42,.9); border: 1px solid rgba(99,102,241,.2); border-radius: 16px; }
        table { width: 100%; min-width: 760px; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 10px 8px; border-bottom: 1px solid rgba(51,65,85,.6); text-align: left; vertical-align: top; }
        th { color: #94a3b8; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; }
        .badge-guest { background: rgba(245,158,11,.15); color: #fbbf24; border: 1px solid rgba(245,158,11,.25); }
        .badge-user { background: rgba(99,102,241,.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,.25); }
        .badge-premium { background: rgba(16,185,129,.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,.25); }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">
<?php if (!$admin): ?>
    <div class="max-w-md mx-auto mt-8 sm:mt-16 card p-5 sm:p-8 shadow-2xl">
        <h1 class="text-2xl font-extrabold text-white mb-1">Admin Dashboard</h1>
        <p class="text-sm text-slate-400 mb-6">Login to view leads, signups, payments and coins.</p>
        <?php if ($loginError): ?>
            <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <div>
                <label class="block text-xs uppercase tracking-wider text-slate-400 mb-1.5">Admin Email</label>
                <input type="email" name="email" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500" placeholder="admin@theastrohealer.com">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-slate-400 mb-1.5">Password</label>
                <input type="password" name="password" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500">
            </div>
            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm">Login to Dashboard</button>
        </form>
        <p class="text-center text-xs text-slate-500 mt-5"><a href="./" class="text-indigo-300 hover:underline">Back to Chatbot</a></p>
    </div>
<?php else: ?>
    <div class="max-w-7xl mx-auto space-y-5 sm:space-y-6">
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-white">Admin Leads Dashboard</h1>
                <p class="text-sm text-slate-400 mt-1">Visitors, signups, payments, coins and revenue</p>
            </div>
            <div class="flex gap-2 w-full sm:w-auto">
                <a href="./" class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-sm text-slate-300 hover:text-white">Open Chatbot</a>
                <a href="admin?logout=1" class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl bg-red-950 border border-red-900 text-sm text-red-300 hover:text-red-200">Logout</a>
            </div>
        </div>

        <?php if (!empty($db_error) && $db_mode === 'json'): ?>
            <div class="card p-4 text-amber-300 text-sm"><?= htmlspecialchars($db_error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
            <div class="card p-4"><p class="text-[10px] uppercase text-slate-500">Total Visitors</p><p class="text-2xl font-bold mt-1"><?= (int)$metrics['totalUsers'] ?></p></div>
            <div class="card p-4"><p class="text-[10px] uppercase text-slate-500">Guest Users</p><p class="text-2xl font-bold mt-1 text-amber-300"><?= (int)$metrics['totalGuests'] ?></p></div>
            <div class="card p-4"><p class="text-[10px] uppercase text-slate-500">Signups</p><p class="text-2xl font-bold mt-1 text-indigo-300"><?= (int)$registeredUsers ?></p></div>
            <div class="card p-4"><p class="text-[10px] uppercase text-slate-500">Payments</p><p class="text-2xl font-bold mt-1"><?= count($metrics['transactions']) ?></p></div>
            <div class="card p-4"><p class="text-[10px] uppercase text-slate-500">Revenue</p><p class="text-2xl font-bold mt-1 text-emerald-400">$<?= number_format((float)$metrics['totalRevenue'], 2) ?></p></div>
        </div>

        <div class="card p-3 sm:p-5">
            <h2 class="text-lg font-bold text-white mb-4">All Users / Leads</h2>
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Plan</th>
                            <th>Coins</th>
                            <th>Questions</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($metrics['users'] as $u):
                        if (($u['role'] ?? '') === 'admin') continue;
                        $guest = isGuestEmail($u['email'] ?? '');
                    ?>
                        <tr>
                            <td class="font-semibold text-white"><?= htmlspecialchars($u['name'] ?? '') ?></td>
                            <td class="text-slate-300"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                            <td class="text-slate-400"><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
                            <td><span class="badge <?= $guest ? 'badge-guest' : 'badge-user' ?>"><?= $guest ? 'GUEST' : 'SIGNUP' ?></span></td>
                            <td><span class="badge <?= ($u['plan'] ?? '') === 'premium' ? 'badge-premium' : 'badge-user' ?>"><?= strtoupper(htmlspecialchars($u['plan'] ?? 'free')) ?></span></td>
                            <td class="font-mono text-indigo-300"><?= (int)($u['coins'] ?? 0) ?></td>
                            <td><?= (int)($u['questionsAsked'] ?? 0) ?></td>
                            <td class="text-slate-500"><?= !empty($u['createdAt']) ? date('M j, Y g:i A', (int)($u['createdAt'] / 1000)) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-3 sm:p-5">
            <h2 class="text-lg font-bold text-white mb-4">Payments</h2>
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Plan</th>
                            <th>Coins Added</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Stripe Session</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($metrics['transactions'])): ?>
                        <tr><td colspan="6" class="text-slate-500 py-6 text-center">No payments yet.</td></tr>
                    <?php else: foreach ($metrics['transactions'] as $tx):
                        $plan = strtolower($tx['planKey'] ?? 'basic');
                        $coins = $planCoins[$plan] ?? 250;
                    ?>
                        <tr>
                            <td class="text-slate-200"><?= htmlspecialchars($tx['email'] ?? '') ?></td>
                            <td><?= strtoupper(htmlspecialchars($plan)) ?></td>
                            <td class="font-mono text-indigo-300"><?= $coins ?> coins</td>
                            <td class="text-emerald-300">$<?= number_format((float)($tx['amount'] ?? 0), 2) ?></td>
                            <td class="text-slate-500"><?= !empty($tx['timestamp']) ? date('M j, Y g:i A', (int)($tx['timestamp'] / 1000)) : '—' ?></td>
                            <td class="text-[10px] text-slate-600 break-all font-mono"><?= htmlspecialchars($tx['sessionId'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
