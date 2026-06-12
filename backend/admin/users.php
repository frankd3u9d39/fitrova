<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db_config.php';

// Self-Heal Database Schema (Ensure ai_engine column exists)
try {
    $pdo->exec("ALTER TABLE user_profiles ADD COLUMN ai_engine VARCHAR(20) DEFAULT 'eco'");
} catch (PDOException $e) {
    // Already exists
}

// Handle athlete profile edit action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_athlete') {
    try {
        $tier = $_POST['subscription_tier'] ?? 'free';
        $expiry = !empty($_POST['subscription_expiry']) ? $_POST['subscription_expiry'] : null;
        
        // Auto-align expiration date (defaults to +30 days if left blank for active tiers)
        if (($tier === 'premium' || $tier === 'advanced_premium') && !$expiry) {
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        } elseif ($tier === 'free') {
            $expiry = null;
        }
        
        // Auto-align ai_engine (legacy compatibility): premium/advanced sets to 'premium', otherwise 'eco'
        $aiEngine = ($tier === 'premium' || $tier === 'advanced_premium') ? 'premium' : 'eco';
        
        $stmt = $pdo->prepare("
            UPDATE user_profiles 
            SET health_score = ?, target_weight = ?, ai_engine = ?, subscription_tier = ?, subscription_expiry = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([
            $_POST['health_score'], 
            $_POST['target_weight'], 
            $aiEngine, 
            $tier, 
            $expiry, 
            $_POST['user_id']
        ]);
        $success = true;
    } catch (PDOException $e) {
        error_log("Database Error in users.php edit_athlete: " . $e->getMessage());
    }
}

// Fetch users directly via SQL
$users = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email, up.current_weight, up.target_weight, up.health_score, up.activity_level, up.ai_engine, up.subscription_tier, up.subscription_expiry
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch payments directly via SQL
$payments = [];
try {
    $payments = $pdo->query("
        SELECT t.reference, t.amount, t.currency, t.subscription_tier, t.status, t.created_at, u.first_name, u.last_name, u.email
        FROM payment_transactions t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payments = [];
}

// Count new athletes registered today
$newToday = 0;
try {
    $newToday = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
} catch (PDOException $e) {
    $newToday = 0;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <link rel="icon" type="image/png" href="favicon.png"/>
    <title>Fitrova Admin - User Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "on-tertiary-fixed": "#ffffff",
                        "error": "#ef4444",
                        "on-secondary-container": "#7c2d12",
                        "tertiary-container": "#334155",
                        "surface-container-highest": "#dae6d1",
                        "surface": "#f8faf8",
                        "outline-variant": "#cbd5e1",
                        "error-container": "#fee2e2",
                        "on-tertiary-fixed-variant": "#ffffff",
                        "on-error": "#ffffff",
                        "surface-container-high": "#e8fbe8",
                        "secondary": "#fb923c",
                        "surface-dim": "#d2dec9",
                        "on-error-container": "#7f1d1d",
                        "surface-variant": "#f1f5f9",
                        "on-background": "#0f172a",
                        "tertiary-fixed-dim": "#0f172a",
                        "surface-container": "#f1fde8",
                        "primary-container": "#e8fbe8",
                        "tertiary": "#000000",
                        "surface-bright": "#ffffff",
                        "secondary-fixed-dim": "#f97316",
                        "surface-container-lowest": "#ffffff",
                        "primary": "#13ec13",
                        "outline": "#e2e8f0",
                        "primary-fixed-dim": "#06bf06",
                        "on-secondary-fixed": "#000000",
                        "on-tertiary-container": "#f8faf8",
                        "surface-container-low": "#f8faf8",
                        "on-primary-container": "#065f06",
                        "background": "#f8faf8",
                        "tertiary-fixed": "#1e293b",
                        "on-secondary": "#ffffff",
                        "on-primary-fixed": "#000000",
                        "primary-fixed": "#13ec13",
                        "on-surface-variant": "#64748b",
                        "secondary-fixed": "#fb923c",
                        "inverse-surface": "#0c140c",
                        "inverse-on-surface": "#f8faf8",
                        "on-surface": "#0f172a",
                        "on-primary": "#0c140c",
                        "secondary-container": "#ffedd5",
                        "surface-tint": "#13ec13",
                        "on-tertiary": "#ffffff",
                        "on-secondary-fixed-variant": "#431407",
                        "inverse-primary": "#13ec13",
                        "on-primary-fixed-variant": "#002200"
                    },
                    "borderRadius": {
                        "DEFAULT": "1rem",
                        "lg": "2rem",
                        "xl": "3rem",
                        "full": "9999px"
                    },
                    "fontFamily": {
                        "headline": ["Manrope"],
                        "display": ["Manrope"],
                        "body": ["Manrope"],
                        "label": ["Manrope"]
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-surface text-on-surface font-body antialiased selection:bg-primary/30 min-h-screen flex">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen relative">
        <!-- TopNavBar -->
        <header class="bg-surface/80 backdrop-blur-xl fixed top-0 right-0 w-[calc(100%-16rem)] z-40 flex justify-between items-center h-16 px-8 border-b border-outline/20">
            <div class="flex items-center gap-4 focus-within:ring-2 focus-within:ring-primary/50 rounded-full bg-surface-variant/50 px-4 py-2 w-96 transition-all">
                <span class="material-symbols-outlined text-on-surface-variant text-sm">search</span>
                <input id="searchInput" oninput="filterTable()" class="bg-transparent border-none outline-none text-sm font-body text-on-surface w-full placeholder:text-on-surface-variant/50 focus:ring-0 p-0" placeholder="Search Users..." type="text"/>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex gap-4">
                    <button class="text-on-surface-variant hover:text-primary transition-colors active:scale-95 p-2 rounded-full hover:bg-surface-container">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <button class="text-on-surface-variant hover:text-primary transition-colors active:scale-95 p-2 rounded-full hover:bg-surface-container">
                        <span class="material-symbols-outlined">settings</span>
                    </button>
                </div>
                <div class="h-8 w-8 rounded-full overflow-hidden border border-outline/20 cursor-pointer active:scale-95 transition-transform">
                    <img alt="Admin Profile" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBtr4qFlKr0bjqfJhrtnlq5JuqeLMv4BDrGfo7RiyRU48E7j4iKH1c0qDxvfa0vbMRL8Y-AvCzFgJbTWiPLqbQtU58U2ySLzcpEk19mkUdWOI3GPUqmOF0Fug-SsZUH71PD-32jvr1VtahfMNVbuCz1xnW24ez0JxclvhjXnuOUNGk-Gzpld98eJgLfVOs6ve0Xup9PXcqkH5zLLgtIK_dna7Nr-nxffaxLhQLxkAfK_OKxu8w_jTlVGEzmruQhdXCCMDxnBeQNvw"/>
                </div>
            </div>
        </header>

        <!-- Canvas -->
        <main class="flex-1 pt-24 px-8 pb-12 overflow-y-auto">
            <div class="flex flex-col gap-8 max-w-7xl mx-auto">
                <!-- Page Header & Summary Bento -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                    <div class="md:col-span-6 lg:col-span-8 flex flex-col gap-2">
                        <h2 class="font-display text-2xl font-extrabold tracking-tighter text-on-surface">User Management</h2>
                        <p class="font-body text-sm font-medium text-on-surface-variant">Oversee community performance and platform access.</p>
                    </div>
                    <div class="md:col-span-6 lg:col-span-4 grid grid-cols-2 gap-4">
                        <div class="bg-surface-bright shadow-2xl rounded-3xl p-5 flex flex-col gap-1 relative overflow-hidden group border border-outline/10 hover:border-primary/20 transition-all duration-300">
                            <div class="absolute -right-4 -top-4 w-16 h-16 bg-surface-container-high rounded-full opacity-50 blur-xl group-hover:bg-primary/20 transition-colors"></div>
                            <span class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Total Users</span>
                            <span class="font-display text-2xl font-extrabold tracking-tighter text-on-surface flex items-baseline gap-1">
                                <span class="italic"><?php echo number_format(count($users)); ?></span>
                            </span>
                        </div>
                        <div class="bg-surface-bright shadow-2xl rounded-3xl p-5 flex flex-col gap-1 relative overflow-hidden group border border-outline/10 hover:border-primary/20 transition-all duration-300">
                            <div class="absolute -right-4 -top-4 w-16 h-16 bg-primary-container rounded-full opacity-50 blur-xl group-hover:bg-primary/30 transition-colors"></div>
                            <span class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">New Today</span>
                            <span class="font-display text-2xl font-extrabold tracking-tighter text-primary flex items-baseline gap-1 drop-shadow-[0_0_8px_rgba(19,236,19,0.3)]">
                                <span class="italic">+<?php echo $newToday; ?></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex border-b border-outline/20 gap-4 mb-1">
                    <button id="tabBtnProfiles" onclick="switchTab('profiles')" class="tab-btn px-6 py-3 border-b-2 border-primary text-primary font-bold text-sm tracking-tight transition-all bg-primary/5 rounded-t-xl">
                        👤 Athlete Profiles
                    </button>
                    <button id="tabBtnPayments" onclick="switchTab('payments')" class="tab-btn px-6 py-3 border-b-2 border-transparent text-on-surface-variant font-semibold text-sm tracking-tight hover:text-primary transition-all hover:bg-surface-container rounded-t-xl">
                        ₦ Paystack Transactions
                    </button>
                </div>

                <div id="profilesTab" class="tab-content">
                    <!-- Main Data Card -->
                    <div class="bg-surface-bright shadow-2xl rounded-[2rem] flex flex-col overflow-hidden border border-outline/10">
                    <!-- Toolbar -->
                    <div class="p-6 border-b border-surface-container flex flex-col sm:flex-row gap-4 justify-between items-center bg-surface-container-low/50">
                        <div class="flex items-center gap-2">
                            <span class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Filter By:</span>
                            <div class="flex gap-2">
                                <button id="filterBtnAll" onclick="setFilter('all')" class="filter-btn px-4 py-1.5 rounded-full bg-primary-container text-on-primary-container font-bold text-xs tracking-tight border border-primary/20 hover:bg-primary/20 transition-colors">All</button>
                                <button id="filterBtnPro" onclick="setFilter('pro')" class="filter-btn px-4 py-1.5 rounded-full bg-surface-container text-on-surface font-medium text-xs tracking-tight hover:bg-surface-container-high transition-colors">Pro</button>
                                <button id="filterBtnFree" onclick="setFilter('free')" class="filter-btn px-4 py-1.5 rounded-full bg-surface-container text-on-surface font-medium text-xs tracking-tight hover:bg-surface-container-high transition-colors">Free</button>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button class="flex items-center gap-2 px-4 py-2 rounded-xl bg-surface text-on-surface font-bold text-sm tracking-tight hover:bg-surface-container transition-all active:scale-95 border border-outline/10 shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">filter_list</span>
                                More Filters
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface-container/30 border-b border-surface-container text-on-surface-variant font-label text-[10px] font-bold uppercase tracking-widest">
                                    <th class="p-5 pl-6 font-medium">Athlete Profile</th>
                                    <th class="p-5 font-medium">Health Score</th>
                                    <th class="p-5 font-medium">Weight (Current/Goal)</th>
                                    <th class="p-5 font-medium">Tier Plan</th>
                                    <th class="p-5 font-medium">Activity Level</th>
                                    <th class="p-5 pr-6 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody" class="divide-y divide-surface-container font-body text-sm">
                                <?php foreach ($users as $user): 
                                    $isPro = ($user['health_score'] > 75);
                                ?>
                                <tr class="user-row hover:bg-surface-container-low/50 transition-colors group cursor-pointer" data-plan="<?php echo $isPro ? 'pro' : 'free'; ?>" data-search="<?php echo strtolower(htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' ' . $user['email'])); ?>">
                                    <td class="p-4 pl-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center text-primary font-bold text-sm uppercase shrink-0">
                                                <?php echo substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1); ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-on-surface group-hover:text-primary transition-colors"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                <span class="text-xs text-on-surface-variant"><?php echo htmlspecialchars($user['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-24 h-1.5 bg-outline rounded-full overflow-hidden shrink-0">
                                                <div class="h-full bg-primary shadow-[0_0_8px_rgba(19,236,19,0.8)]" style="width: <?php echo $user['health_score'] ?: 0; ?>%"></div>
                                            </div>
                                            <span class="font-bold text-on-surface text-xs"><?php echo $user['health_score'] ?: 0; ?></span>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="text-on-surface font-medium text-xs"><?php echo $user['current_weight'] ?: '--'; ?>kg <span class="text-on-surface-variant">/ <?php echo $user['target_weight'] ?: '--'; ?>kg</span></span>
                                    </td>
                                    <td class="p-4">
                                        <?php 
                                        $subTier = strtolower($user['subscription_tier'] ?? 'free');
                                        $subExpiry = $user['subscription_expiry'] ?? null;
                                        if ($subTier === 'advanced_premium'): ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-md bg-purple-50 text-purple-700 border border-purple-200 text-[11px] font-bold tracking-wide uppercase">Advanced Premium</span>
                                        <?php elseif ($subTier === 'premium'): ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[11px] font-bold tracking-wide uppercase">Premium AI</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-md bg-surface-container text-on-surface-variant text-[11px] font-bold tracking-wide uppercase">Free Trial</span>
                                        <?php endif; ?>
                                        <?php if ($subExpiry && ($subTier === 'premium' || $subTier === 'advanced_premium')): ?>
                                            <div class="text-[10px] text-on-surface-variant mt-1 font-semibold">Exp: <?php echo date('Y-m-d', strtotime($subExpiry)); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 pr-6 text-right">
                                        <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>', <?php echo $user['health_score'] ?: 0; ?>, <?php echo $user['target_weight'] ?: 0; ?>, '<?php echo $user['subscription_tier'] ?: 'free'; ?>', '<?php echo $user['subscription_expiry'] ?: ''; ?>')" class="text-on-surface-variant hover:text-primary transition-colors p-2 rounded-lg hover:bg-primary-container/50">
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination / Footer -->
                    <div class="p-5 border-t border-surface-container flex items-center justify-between bg-surface-bright">
                        <span id="showingText" class="font-body text-xs font-medium text-on-surface-variant">Showing all <span class="font-bold text-on-surface"><?php echo count($users); ?></span> athletes</span>
                        <div class="flex gap-2">
                            <button class="p-2 rounded-lg bg-surface-container text-on-surface-variant hover:bg-surface-container-high transition-colors disabled:opacity-50" disabled>
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </button>
                            <button class="p-2 rounded-lg bg-surface-container text-on-surface-variant hover:bg-surface-container-high transition-colors disabled:opacity-50" disabled>
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div> <!-- End of profilesTab -->

                <!-- Payments Ledger Tab -->
                <div id="paymentsTab" class="tab-content hidden">
                    <div class="bg-surface-bright shadow-2xl rounded-[2rem] flex flex-col overflow-hidden border border-outline/10">
                        <!-- Header -->
                        <div class="p-6 border-b border-surface-container flex justify-between items-center bg-surface-container-low/50">
                            <div class="flex flex-col gap-1">
                                <h3 class="font-display font-bold text-lg text-on-surface">₦ Paystack Financial Ledger</h3>
                                <p class="text-xs text-on-surface-variant">Real-time listing of customer billing transactions secured via Paystack.</p>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-surface-container/30 border-b border-surface-container text-on-surface-variant font-label text-[10px] font-bold uppercase tracking-widest">
                                        <th class="p-5 pl-6 font-medium">Customer / Athlete</th>
                                        <th class="p-5 font-medium">Reference ID</th>
                                        <th class="p-5 font-medium">Amount (₦)</th>
                                        <th class="p-5 font-medium">Sub Tier</th>
                                        <th class="p-5 font-medium">Status</th>
                                        <th class="p-5 pr-6 font-medium text-right">Transaction Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-container font-body text-sm">
                                    <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="6" class="p-12 text-center text-on-surface-variant font-medium">
                                            <span class="material-symbols-outlined text-4xl block mb-2 text-outline">payments</span>
                                            No payment transactions logged in database yet.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($payments as $pay): ?>
                                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                                            <td class="p-4 pl-6">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-on-surface"><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></span>
                                                    <span class="text-xs text-on-surface-variant"><?php echo htmlspecialchars($pay['email']); ?></span>
                                                </div>
                                            </td>
                                            <td class="p-4 font-mono text-xs text-on-surface-variant">
                                                <?php echo htmlspecialchars($pay['reference']); ?>
                                            </td>
                                            <td class="p-4 font-extrabold text-on-surface">
                                                ₦<?php echo number_format($pay['amount'], 2); ?>
                                            </td>
                                            <td class="p-4">
                                                <?php 
                                                $pTier = strtolower($pay['subscription_tier']);
                                                if ($pTier === 'advanced_premium'): ?>
                                                    <span class="inline-flex px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 text-[10px] font-bold tracking-wide uppercase">Advanced</span>
                                                <?php else: ?>
                                                    <span class="inline-flex px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold tracking-wide uppercase">Premium</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4">
                                                <?php if ($pay['status'] === 'success'): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase border border-emerald-200">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        Success / Paid
                                                    </span>
                                                <?php elseif ($pay['status'] === 'failed'): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-50 text-red-700 text-[10px] font-bold uppercase border border-red-200">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                        Failed
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-yellow-50 text-yellow-700 text-[10px] font-bold uppercase border border-yellow-200">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 animate-pulse"></span>
                                                        Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 pr-6 text-right text-xs text-on-surface-variant font-medium">
                                                <?php echo date('Y-m-d H:i', strtotime($pay['created_at'])); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div> <!-- End of paymentsTab -->
            </div>
        </main>
    </div>

    <script>
        let currentFilter = 'all';

        function switchTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            // Remove active classes
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-primary', 'text-primary', 'bg-primary/5', 'font-bold');
                btn.classList.add('border-transparent', 'text-on-surface-variant', 'font-semibold');
            });
            
            // Show target
            document.getElementById(tabId + 'Tab').classList.remove('hidden');
            
            // Add active to button
            const activeBtn = document.getElementById('tabBtn' + tabId.charAt(0).toUpperCase() + tabId.slice(1));
            activeBtn.classList.remove('border-transparent', 'text-on-surface-variant', 'font-semibold');
            activeBtn.classList.add('border-primary', 'text-primary', 'bg-primary/5', 'font-bold');
        }

        function setFilter(filter) {
            currentFilter = filter;
            
            // Update filter buttons classes
            const btns = document.querySelectorAll('.filter-btn');
            btns.forEach(btn => {
                btn.className = "filter-btn px-4 py-1.5 rounded-full bg-surface-container text-on-surface font-medium text-xs tracking-tight hover:bg-surface-container-high transition-colors";
            });

            const activeBtn = document.getElementById('filterBtn' + filter.charAt(0).toUpperCase() + filter.slice(1));
            if (activeBtn) {
                activeBtn.className = "filter-btn px-4 py-1.5 rounded-full bg-primary-container text-on-primary-container font-bold text-xs tracking-tight border border-primary/20 hover:bg-primary/20 transition-colors";
            }

            filterTable();
        }

        function filterTable() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.user-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const plan = row.getAttribute('data-plan');
                const searchString = row.getAttribute('data-search');
                
                const matchesFilter = (currentFilter === 'all' || plan === currentFilter);
                const matchesSearch = (!query || searchString.includes(query));

                if (matchesFilter && matchesSearch) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });

            document.getElementById('showingText').innerHTML = `Showing <span class="font-bold text-on-surface">${visibleCount}</span> of <span class="font-bold text-on-surface">${rows.length}</span> athletes`;
        }

        // Edit Profile modal handlers
        function openEditModal(userId, name, healthScore, targetWeight, subscriptionTier, subscriptionExpiry) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUserName').value = name;
            document.getElementById('editHealthScore').value = healthScore;
            document.getElementById('editTargetWeight').value = targetWeight;
            document.getElementById('editSubscriptionTier').value = subscriptionTier || 'free';
            
            // Format datetime-local value (YYYY-MM-DDTHH:MM)
            if (subscriptionExpiry && subscriptionExpiry !== '0000-00-00 00:00:00') {
                let date = new Date(subscriptionExpiry);
                let tzoffset = date.getTimezoneOffset() * 60000;
                let localISOTime = (new Date(date.getTime() - tzoffset)).toISOString().slice(0, 16);
                document.getElementById('editSubscriptionExpiry').value = localISOTime;
            } else {
                document.getElementById('editSubscriptionExpiry').value = '';
            }
            
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Auto-fill Subscription Expiration on changing Subscription Tier
        document.addEventListener('DOMContentLoaded', () => {
            const tierSelect = document.getElementById('editSubscriptionTier');
            if (tierSelect) {
                tierSelect.addEventListener('change', function() {
                    const tier = this.value;
                    const expiryInput = document.getElementById('editSubscriptionExpiry');
                    
                    if (expiryInput) {
                        if (tier === 'premium' || tier === 'advanced_premium') {
                            // If currently empty, set to 30 days from now
                            if (!expiryInput.value) {
                                const date = new Date();
                                date.setDate(date.getDate() + 30);
                                
                                // Format to YYYY-MM-DDTHH:MM (datetime-local format)
                                const year = date.getFullYear();
                                const month = String(date.getMonth() + 1).padStart(2, '0');
                                const day = String(date.getDate()).padStart(2, '0');
                                const hours = String(date.getHours()).padStart(2, '0');
                                const minutes = String(date.getMinutes()).padStart(2, '0');
                                
                                expiryInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                            }
                        } else {
                            // If switching to free, clear expiration date
                            expiryInput.value = '';
                        }
                    }
                });
            }
        });
    </script>

    <!-- Edit Profile Modal Component -->
    <div id="editModal" class="fixed inset-0 bg-on-background/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
        <div class="bg-surface-bright w-full max-w-xl p-8 rounded-[2.5rem] shadow-2xl border border-outline/10 relative">
            <button onclick="closeEditModal()" class="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
            <h3 class="font-display text-2xl font-extrabold text-on-surface mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-2xl">manage_accounts</span>
                Edit Athlete Profile
            </h3>
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_athlete">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Athlete Name</label>
                        <input type="text" id="editUserName" readonly class="w-full bg-surface-container/30 border border-outline/30 rounded-2xl p-3 text-on-surface-variant outline-none text-xs font-body cursor-not-allowed">
                    </div>
                    <div>
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Health Score (0-100)</label>
                        <input type="number" name="health_score" id="editHealthScore" min="0" max="100" required class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Subscription Tier</label>
                        <select name="subscription_tier" id="editSubscriptionTier" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                            <option value="free">🍃 Free Tier (Trial Mode)</option>
                            <option value="premium">✨ Premium Tier (Naira ₦1,500/mo)</option>
                            <option value="advanced_premium">🚀 Advanced Premium (Naira ₦3,000/mo)</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Subscription Expiration</label>
                        <input type="datetime-local" name="subscription_expiry" id="editSubscriptionExpiry" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                    </div>
                </div>

                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Target Weight (kg)</label>
                    <input type="number" name="target_weight" id="editTargetWeight" step="0.1" required class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-surface-container text-on-surface font-bold py-3.5 rounded-2xl text-xs hover:bg-surface-container-high transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 bg-primary text-on-primary font-bold py-3.5 rounded-2xl text-xs hover:bg-primary-fixed transition-colors shadow-lg shadow-primary/20 uppercase tracking-widest">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
