<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db_config.php';

// Self-Heal Database Schema (Ensure ai_engine column exists)
try {
    $pdo->exec("ALTER TABLE user_profiles ADD COLUMN ai_engine VARCHAR(20) DEFAULT 'eco'");
} catch (PDOException $e) {
    // Already exists
}

$successMessage = '';
$errorMessage = '';

// Handle athlete profile edit action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_athlete') {
    try {
        $userId = intval($_POST['user_id']);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $healthScore = intval($_POST['health_score'] ?? 0);
        $currentWeight = !empty($_POST['current_weight']) ? floatval($_POST['current_weight']) : null;
        $targetWeight = !empty($_POST['target_weight']) ? floatval($_POST['target_weight']) : null;
        $tier = $_POST['subscription_tier'] ?? 'free';
        $expiry = !empty($_POST['subscription_expiry']) ? $_POST['subscription_expiry'] : null;
        
        if (($tier === 'premium' || $tier === 'advanced_premium') && !$expiry) {
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        } elseif ($tier === 'free') {
            $expiry = null;
        }
        
        $aiEngine = ($tier === 'premium' || $tier === 'advanced_premium') ? 'premium' : 'eco';
        
        // 1. Update identity details in users table
        $uStmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        $uStmt->execute([$firstName, $lastName, $email, $userId]);

        // 2. Update profile details in user_profiles table
        $pStmt = $pdo->prepare("
            UPDATE user_profiles 
            SET health_score = ?, current_weight = ?, target_weight = ?, ai_engine = ?, subscription_tier = ?, subscription_expiry = ? 
            WHERE user_id = ?
        ");
        $pStmt->execute([
            $healthScore, 
            $currentWeight,
            $targetWeight, 
            $aiEngine, 
            $tier, 
            $expiry, 
            $userId
        ]);
        $successMessage = "Athlete profile for {$firstName} {$lastName} updated successfully!";
    } catch (PDOException $e) {
        $errorMessage = "Database Error updating athlete: " . $e->getMessage();
        error_log("Database Error in users.php edit_athlete: " . $e->getMessage());
    }
}

// Handle athlete delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_athlete') {
    try {
        $deleteUserId = intval($_POST['user_id']);
        
        // Prevent deleting active logged in admin account
        $checkUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $checkUser->execute([$deleteUserId]);
        $targetEmail = $checkUser->fetchColumn();

        if ($targetEmail && strtolower($targetEmail) === strtolower($_SESSION['admin_email'] ?? '')) {
            $errorMessage = "Security Error: You cannot delete your own active admin account!";
        } else {
            // Disable Foreign Key checks temporarily to prevent constraint crashes
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            $pdo->prepare("DELETE FROM weight_history WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM workout_plans WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM workout_logs WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM nutrition_logs WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM form_check_logs WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM payment_transactions WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM ai_food_recommendations WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM user_achievements WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM personal_records WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM ai_insights WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM challenge_messages WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM pending_verifications WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM user_challenges WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM user_connections WHERE user_id = ? OR connected_user_id = ?")->execute([$deleteUserId, $deleteUserId]);
            $pdo->prepare("DELETE FROM user_profiles WHERE user_id = ?")->execute([$deleteUserId]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$deleteUserId]);

            // Re-enable Foreign Key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $successMessage = "Athlete account deleted successfully!";
        }
    } catch (PDOException $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $errorMessage = "Failed to delete user account: " . $e->getMessage();
        error_log("Database Error in users.php delete_athlete: " . $e->getMessage());
    }
}

// Fetch users directly via SQL
$users = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email, up.current_weight, up.target_weight, up.health_score, up.activity_level, up.ai_engine, up.subscription_tier, up.subscription_expiry, up.profile_picture
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
<html class="dark" lang="en">
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
                        "on-secondary-container": "#ffedd5",
                        "tertiary-container": "#1e293b",
                        "surface-container-highest": "#1e293b",
                        "surface": "#070a12",
                        "outline-variant": "#334155",
                        "error-container": "#fee2e2",
                        "on-tertiary-fixed-variant": "#ffffff",
                        "on-error": "#ffffff",
                        "surface-container-high": "#1e293b",
                        "secondary": "#fb923c",
                        "surface-dim": "#0b0f19",
                        "on-error-container": "#7f1d1d",
                        "surface-variant": "#111827",
                        "on-background": "#ffffff",
                        "tertiary-fixed-dim": "#f8faf8",
                        "surface-container": "#111827",
                        "primary-container": "rgba(19, 236, 19, 0.1)",
                        "tertiary": "#ffffff",
                        "surface-bright": "#0d1321",
                        "secondary-fixed-dim": "#f97316",
                        "surface-container-lowest": "#04060b",
                        "primary": "#13ec13",
                        "outline": "#1e293b",
                        "primary-fixed-dim": "#06bf06",
                        "on-secondary-fixed": "#000000",
                        "on-tertiary-container": "#ffffff",
                        "surface-container-low": "#0b0f19",
                        "on-primary-container": "#13ec13",
                        "background": "#070a12",
                        "tertiary-fixed": "#1e293b",
                        "on-secondary": "#ffffff",
                        "on-primary-fixed": "#000000",
                        "primary-fixed": "#13ec13",
                        "on-surface-variant": "#94a3b8",
                        "secondary-fixed": "#fb923c",
                        "inverse-surface": "#ffffff",
                        "inverse-on-surface": "#070a12",
                        "on-surface": "#ffffff",
                        "on-primary": "#070a12",
                        "secondary-container": "#431407",
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
    <div class="flex-1 ml-0 md:ml-64 flex flex-col min-h-screen relative">
        <!-- TopNavBar -->
        <?php
        $headerSearchPlaceholder = "Search Users...";
        $headerSearchInputId = "searchInput";
        $headerSearchOnInput = "filterTable()";
        include __DIR__ . '/includes/header.php';
        ?>

        <!-- Canvas -->
        <main class="flex-1 pt-24 px-4 md:px-8 pb-12 overflow-y-auto">
            <div class="flex flex-col gap-8 max-w-7xl mx-auto">
                <!-- Page Header & Summary Bento -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                    <div class="md:col-span-6 lg:col-span-8 flex flex-col gap-2">
                        <h2 class="font-display text-2xl font-extrabold tracking-tighter text-on-surface">User Management</h2>
                        <p class="font-body text-sm font-medium text-on-surface-variant">Oversee community performance and platform access.</p>
                        <?php if (!empty($successMessage)): ?>
                            <div class="mt-2 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">check_circle</span>
                                <?php echo htmlspecialchars($successMessage); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($errorMessage)): ?>
                            <div class="mt-2 p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-bold flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">error</span>
                                <?php echo htmlspecialchars($errorMessage); ?>
                            </div>
                        <?php endif; ?>
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
                                            <?php if (!empty($user['profile_picture'])): ?>
                                                <img class="w-10 h-10 rounded-full object-cover shrink-0 border border-outline/20 bg-surface-container" src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['first_name']); ?>">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center text-primary font-bold text-sm uppercase shrink-0">
                                                    <?php echo substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1); ?>
                                                </div>
                                            <?php endif; ?>
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
                                            <span class="inline-flex px-2.5 py-1 rounded-md bg-purple-500/10 text-purple-400 border border-purple-500/20 text-[11px] font-bold tracking-wide uppercase">Advanced Premium</span>
                                        <?php elseif ($subTier === 'premium'): ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-md bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[11px] font-bold tracking-wide uppercase">Premium AI</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-md bg-surface-container text-on-surface-variant text-[11px] font-bold tracking-wide uppercase">Free Trial</span>
                                        <?php endif; ?>
                                        <?php if ($subExpiry && ($subTier === 'premium' || $subTier === 'advanced_premium')): ?>
                                            <div class="text-[10px] text-on-surface-variant mt-1 font-semibold">Exp: <?php echo date('Y-m-d', strtotime($subExpiry)); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="text-on-surface-variant font-medium text-xs capitalize"><?php echo htmlspecialchars($user['activity_level'] ?? 'Moderate'); ?></span>
                                    </td>
                                    <td class="p-4 pr-6 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode([
                                                'id' => $user['id'],
                                                'first_name' => $user['first_name'],
                                                'last_name' => $user['last_name'],
                                                'email' => $user['email'],
                                                'health_score' => $user['health_score'] ?: 0,
                                                'current_weight' => $user['current_weight'] ?: '',
                                                'target_weight' => $user['target_weight'] ?: '',
                                                'subscription_tier' => $user['subscription_tier'] ?: 'free',
                                                'subscription_expiry' => $user['subscription_expiry'] ?: ''
                                            ]), ENT_QUOTES); ?>)" class="text-on-surface-variant hover:text-primary transition-colors p-2 rounded-lg hover:bg-primary-container/50" title="Edit Athlete Profile">
                                                <span class="material-symbols-outlined text-[20px]">edit</span>
                                            </button>
                                            <button onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>')" class="text-on-surface-variant hover:text-red-400 transition-colors p-2 rounded-lg hover:bg-red-500/10" title="Delete Athlete Account">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                        </div>
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
                                                    <span class="inline-flex px-2 py-0.5 rounded-md bg-purple-500/10 text-purple-400 border border-purple-500/20 text-[10px] font-bold tracking-wide uppercase">Advanced</span>
                                                <?php else: ?>
                                                    <span class="inline-flex px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[10px] font-bold tracking-wide uppercase">Premium</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4">
                                                <?php if ($pay['status'] === 'success'): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-[10px] font-bold uppercase border border-emerald-500/20">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        Success / Paid
                                                    </span>
                                                <?php elseif ($pay['status'] === 'failed'): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500/10 text-red-400 text-[10px] font-bold uppercase border border-red-500/20">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                        Failed
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-yellow-500/10 text-yellow-400 text-[10px] font-bold uppercase border border-yellow-500/20">
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
        function openEditModal(userData) {
            if (typeof userData === 'string') {
                try { userData = JSON.parse(userData); } catch(e) {}
            }
            document.getElementById('editUserId').value = userData.id || '';
            document.getElementById('editFirstName').value = userData.first_name || '';
            document.getElementById('editLastName').value = userData.last_name || '';
            document.getElementById('editEmail').value = userData.email || '';
            document.getElementById('editHealthScore').value = userData.health_score !== undefined ? userData.health_score : 0;
            document.getElementById('editCurrentWeight').value = userData.current_weight || '';
            document.getElementById('editTargetWeight').value = userData.target_weight || '';
            document.getElementById('editSubscriptionTier').value = userData.subscription_tier || 'free';
            
            // Format datetime-local value (YYYY-MM-DDTHH:MM)
            const expiry = userData.subscription_expiry;
            if (expiry && expiry !== '0000-00-00 00:00:00') {
                let date = new Date(expiry);
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

        // Delete Profile modal handlers
        function openDeleteModal(userId, name) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').innerText = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
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
                            if (!expiryInput.value) {
                                const date = new Date();
                                date.setDate(date.getDate() + 30);
                                const year = date.getFullYear();
                                const month = String(date.getMonth() + 1).padStart(2, '0');
                                const day = String(date.getDate()).padStart(2, '0');
                                const hours = String(date.getHours()).padStart(2, '0');
                                const minutes = String(date.getMinutes()).padStart(2, '0');
                                expiryInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                            }
                        } else {
                            expiryInput.value = '';
                        }
                    }
                });
            }
        });
    </script>

    <!-- Edit Profile Modal Component -->
    <div id="editModal" class="fixed inset-0 bg-on-background/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
        <div class="bg-surface-bright w-full max-w-xl p-8 rounded-[2.5rem] shadow-2xl border border-outline/10 relative max-h-[90vh] overflow-y-auto">
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
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">First Name</label>
                        <input type="text" name="first_name" id="editFirstName" required class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                    </div>
                    <div>
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Last Name</label>
                        <input type="text" name="last_name" id="editLastName" required class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                    </div>
                </div>

                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Email Address</label>
                    <input type="email" name="email" id="editEmail" required class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Current Wt (kg)</label>
                        <input type="number" name="current_weight" id="editCurrentWeight" step="0.1" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                    </div>
                    <div>
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Target Wt (kg)</label>
                        <input type="number" name="target_weight" id="editTargetWeight" step="0.1" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-3 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-xs font-body">
                    </div>
                    <div>
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">Health Score</label>
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

                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-surface-container text-on-surface font-bold py-3.5 rounded-2xl text-xs hover:bg-surface-container-high transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 bg-primary text-on-primary font-bold py-3.5 rounded-2xl text-xs hover:bg-primary-fixed transition-colors shadow-lg shadow-primary/20 uppercase tracking-widest">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Profile Modal Component -->
    <div id="deleteModal" class="fixed inset-0 bg-on-background/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
        <div class="bg-surface-bright w-full max-w-md p-8 rounded-[2.5rem] shadow-2xl border border-red-500/20 relative">
            <button onclick="closeDeleteModal()" class="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
            <div class="w-12 h-12 rounded-full bg-red-500/10 text-red-400 flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-2xl">warning</span>
            </div>
            <h3 class="font-display text-xl font-extrabold text-on-surface mb-2">
                Delete Athlete Account?
            </h3>
            <p class="text-xs text-on-surface-variant leading-relaxed mb-6">
                Are you sure you want to delete <span id="deleteUserName" class="font-bold text-on-surface">this athlete</span>? This action cannot be undone and will permanently erase their user profile, workout history, weight logs, and payment records.
            </p>
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="delete_athlete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="flex gap-3">
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 bg-surface-container text-on-surface font-bold py-3 rounded-2xl text-xs hover:bg-surface-container-high transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 bg-red-500 text-white font-bold py-3 rounded-2xl text-xs hover:bg-red-600 transition-colors shadow-lg shadow-red-500/20 uppercase tracking-widest">Delete Account</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
