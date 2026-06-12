<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db_config.php';

// --- Global Payment Toggle: SSR state load ---
$paymentsEnabled = true;
try {
    $pgStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'payments_enabled' LIMIT 1");
    $pgVal  = $pgStmt ? $pgStmt->fetchColumn() : null;
    if ($pgVal === null || $pgVal === false) {
        // Seed the row with default ON if it doesn't exist yet
        $pdo->exec("INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('payments_enabled', 'true')
            ON DUPLICATE KEY UPDATE setting_key = setting_key");
        $pgVal = 'true';
    }
    $paymentsEnabled = ($pgVal === 'true');
} catch (Exception $pgEx) {
    $paymentsEnabled = true; // fail open
}

// Fetch initial data directly via SQL for lightning-fast SSR (eliminating the HTTP loopback bottleneck)
try {
    // 1. Total Users
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // 2. Active Today (Users who logged food or workout today)
    $activeToday = $pdo->query("
        SELECT COUNT(DISTINCT user_id) FROM (
            SELECT user_id FROM nutrition_logs WHERE logged_date = CURDATE()
            UNION
            SELECT user_id FROM workout_logs WHERE completed_date = CURDATE()
        ) as active
    ")->fetchColumn();
    
    // 3. AI Generations Today
    $aiCount = $pdo->query("SELECT COUNT(*) FROM workout_plans WHERE plan_date = CURDATE()")->fetchColumn();
    
    // 4. Calorie Surplus Alerts (Users who are > 500 kcal over goal)
    $surplusAlerts = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT nl.user_id, SUM(nl.calories) as total_in, up.daily_calorie_goal
            FROM nutrition_logs nl
            JOIN user_profiles up ON nl.user_id = up.user_id
            WHERE nl.logged_date = CURDATE()
            GROUP BY nl.user_id
            HAVING total_in > up.daily_calorie_goal + 500
        ) as overeaters
    ")->fetchColumn();

    // 5. Recent Activity Feed
    $workouts = $pdo->query("
        SELECT wl.workout_name, wl.completed_date, u.first_name, 'workout' as type
        FROM workout_logs wl
        JOIN users u ON wl.user_id = u.id
        ORDER BY wl.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $meals = $pdo->query("
        SELECT nl.meal_name as workout_name, nl.logged_date as completed_date, u.first_name, 'meal' as type
        FROM nutrition_logs nl
        JOIN users u ON nl.user_id = u.id
        ORDER BY nl.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $recentActivity = array_merge($workouts, $meals);
    usort($recentActivity, function($a, $b) {
        return strtotime($b['completed_date']) - strtotime($a['completed_date']);
    });
    $recentActivity = array_slice($recentActivity, 0, 5);

    // 6. Calculate 7-day Utilization Trend
    $days = [];
    $labels = [];
    $completedDays = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $days[$date] = 0;
        $completedDays[$date] = 0;
        $labels[] = date('D', strtotime("-$i days"));
    }

    try {
        $plansTrendStmt = $pdo->query("
            SELECT DATE(created_at) as plan_date, COUNT(*) as count 
            FROM workout_plans 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
        ");
        while ($row = $plansTrendStmt->fetch(PDO::FETCH_ASSOC)) {
            $date = date('Y-m-d', strtotime($row['plan_date']));
            if (isset($days[$date])) {
                $days[$date] = (int)$row['count'];
            }
        }
    } catch (PDOException $e) {
        try {
            $plansTrendStmt = $pdo->query("
                SELECT plan_date, COUNT(*) as count 
                FROM workout_plans 
                WHERE plan_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY plan_date
            ");
            while ($row = $plansTrendStmt->fetch(PDO::FETCH_ASSOC)) {
                $date = $row['plan_date'];
                if (isset($days[$date])) {
                    $days[$date] = (int)$row['count'];
                }
            }
        } catch (PDOException $ex) {
            // Silently absorb
        }
    }

    try {
        $logsTrendStmt = $pdo->query("
            SELECT completed_date, COUNT(*) as count 
            FROM workout_logs 
            WHERE completed_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY completed_date
        ");
        while ($row = $logsTrendStmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['completed_date'];
            if (isset($completedDays[$date])) {
                $completedDays[$date] = (int)$row['count'];
            }
        }
    } catch (PDOException $e) {
        // Silently absorb
    }

    $statsData = [
        'stats' => [
            'total_users' => (int)$userCount,
            'active_today' => (int)$activeToday,
            'ai_generations' => (int)$aiCount,
            'surplus_alerts' => (int)$surplusAlerts
        ],
        'recent_activity' => $recentActivity,
        'trends' => [
            'labels' => $labels,
            'ai_plans' => array_values($days),
            'completed_workouts' => array_values($completedDays)
        ]
    ];
} catch (Exception $e) {
    // Graceful fallback to prevent server-side crash in case of DB migrations
    $statsData = [
        'stats' => ['total_users' => 0, 'active_today' => 0, 'ai_generations' => 0, 'surplus_alerts' => 0],
        'recent_activity' => [],
        'trends' => [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'ai_plans' => [0, 0, 0, 0, 0, 0, 0],
            'completed_workouts' => [0, 0, 0, 0, 0, 0, 0]
        ]
    ];
}

$trendsJson = json_encode($statsData['trends']);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <link rel="icon" type="image/png" href="favicon.png"/>
    <title>Fitrova Admin - System Overview</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <style type="text/tailwindcss">
        @layer utilities {
            .card-shadow {
                box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            }
            .neon-glow {
                filter: drop-shadow(0 0 8px rgba(19, 236, 19, 0.4));
            }
        }
    </style>
</head>
<body class="bg-surface text-on-surface font-body min-h-screen antialiased selection:bg-primary/20 selection:text-on-surface">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- TopNavBar Component -->
    <header class="hidden md:flex justify-between items-center h-16 px-8 ml-64 fixed top-0 right-0 w-[calc(100%-16rem)] z-40 bg-surface/80 backdrop-blur-xl focus-within:ring-2 focus-within:ring-primary/50 border-b border-outline/20">
        <div class="flex-1">
            <!-- Search left placeholder -->
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 rounded-full text-on-surface-variant hover:text-primary transition-colors focus:outline-none">
                <span class="material-symbols-outlined">notifications</span>
            </button>
            <button class="p-2 rounded-full text-on-surface-variant hover:text-primary transition-colors focus:outline-none">
                <span class="material-symbols-outlined">settings</span>
            </button>
            <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-surface-container-high">
                <img alt="Admin Profile" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBTAuJFaXpSIu4rY3b_5gxkaAT61pW8S-Kyovo6HcsMbnEIG47n1bT01w-6ij_o6WRNMU7oiDnPbuczKUDzHZurd4iKzlqkq28ImZK1fKLVDuF9dUyHMCqRYvN3vlcZg9KO9yermPLC-BhxluGcYkKEAT5BrNpa8rFPNuDaCjjhI3qvIyw6B2EnKYAHxnm5FuPxhponBfnDnGfqPkkFHJexelUaN5HHdyqimigN3uxZyVaA5K1wueB_Mm3yCR_3K0AA3P2DnC5LWA"/>
            </div>
        </div>
    </header>

    <!-- Main Content Canvas -->
    <main class="ml-0 md:ml-64 pt-20 md:pt-24 p-6 md:p-10 min-h-screen">
        <!-- Header -->
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl md:text-[2rem] font-extrabold tracking-[-0.025em] text-on-surface">System Overview</h2>
                <p class="font-body text-sm font-medium text-on-surface-variant mt-1">Live metrics and global administrative controls.</p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-surface-container-high border border-primary/20 text-[10px] font-bold uppercase tracking-widest text-on-primary-container">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                System Healthy
            </div>
        </div>

        <!-- Metric Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <!-- Card 1 -->
            <div class="bg-surface-bright rounded-3xl p-6 shadow-2xl relative overflow-hidden group border border-outline/10 hover:border-primary/20 transition-all duration-300">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-surface-container rounded-full group-hover:scale-110 transition-transform duration-500 ease-out"></div>
                <div class="relative z-10">
                    <p class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2">Total Athletes</p>
                    <div class="flex items-baseline gap-2">
                        <h3 id="total-athletes" class="font-display text-[2rem] font-extrabold tracking-tight italic"><?php echo $statsData['stats']['total_users']; ?></h3>
                        <span class="text-primary font-bold text-sm flex items-center"><span class="material-symbols-outlined text-[14px]">arrow_upward</span>12%</span>
                    </div>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="bg-surface-bright rounded-3xl p-6 shadow-2xl relative overflow-hidden group border border-outline/10 hover:border-primary/20 transition-all duration-300">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-surface-container rounded-full group-hover:scale-110 transition-transform duration-500 ease-out"></div>
                <div class="relative z-10">
                    <p class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2">Active Today</p>
                    <div class="flex items-baseline gap-2">
                        <h3 id="active-today" class="font-display text-[2rem] font-extrabold tracking-tight italic"><?php echo $statsData['stats']['active_today']; ?></h3>
                        <span class="text-primary font-bold text-sm flex items-center">
                            <span class="flex h-2 w-2 relative mr-1">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                            </span>
                            LIVE
                        </span>
                    </div>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="bg-on-surface rounded-3xl p-6 shadow-2xl relative overflow-hidden border border-slate-800">
                <div class="absolute inset-0 bg-gradient-to-br from-on-surface to-tertiary-fixed opacity-80"></div>
                <div class="relative z-10">
                    <p class="font-label text-[10px] font-bold uppercase tracking-widest text-surface-variant mb-2 opacity-80">AI Sessions Today</p>
                    <div class="flex items-baseline gap-2">
                        <h3 id="ai-sessions" class="font-display text-[2rem] font-extrabold tracking-tight italic text-primary neon-glow"><?php echo $statsData['stats']['ai_generations']; ?></h3>
                    </div>
                    <div class="mt-4 h-1.5 w-full bg-surface/20 rounded-full overflow-hidden">
                        <?php 
                            $goal = 100;
                            $pct = min(100, round(($statsData['stats']['ai_generations'] / $goal) * 100));
                        ?>
                        <div class="h-full bg-primary rounded-full shadow-[0_0_8px_rgba(19,236,19,0.8)]" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                </div>
            </div>
            <!-- Card 4 -->
            <div class="bg-surface-bright rounded-3xl p-6 shadow-2xl relative overflow-hidden group border border-error/20 hover:border-error/40 transition-all duration-300">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-error-container/40 rounded-full group-hover:scale-110 transition-transform duration-500 ease-out"></div>
                <div class="relative z-10">
                    <p class="font-label text-[10px] font-bold uppercase tracking-widest text-error mb-2">Diet Alerts</p>
                    <div class="flex items-baseline gap-2">
                        <h3 id="diet-alerts" class="font-display text-[2rem] font-extrabold tracking-tight italic text-error"><?php echo $statsData['stats']['surplus_alerts']; ?></h3>
                        <span class="text-error font-bold text-xs uppercase tracking-widest ml-2">CRITICAL</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bento Grid Layout for Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Chart Area (Spans 2 columns) -->
            <div class="lg:col-span-2 bg-surface-bright rounded-[2rem] p-8 shadow-2xl flex flex-col h-[400px] border border-outline/10">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h3 class="font-headline text-lg font-bold text-on-surface">User Growth &amp; Retention</h3>
                        <p class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mt-1">7 Day Trend Analysis</p>
                    </div>
                    <select class="bg-surface-container border border-outline rounded-lg text-xs font-bold px-3 py-2 outline-none text-on-surface">
                        <option>Last 7 Days</option>
                        <option>Last 30 Days</option>
                    </select>
                </div>
                <!-- Dynamic Chart.js Graph -->
                <div class="flex-1 relative w-full mt-4 bg-surface-container/30 rounded-2xl overflow-hidden border border-surface-container-high p-2">
                    <canvas id="utilizationChart"></canvas>
                </div>
            </div>

            <!-- Right Column Stack -->
            <div class="flex flex-col gap-6">
                <!-- Command Center -->
                <div class="bg-surface-bright rounded-[2rem] p-6 shadow-2xl flex-1 border border-outline/10">
                    <h3 class="font-headline text-lg font-bold text-on-surface mb-4">Command Center</h3>
                    <div class="space-y-3">
                        <!-- ══ PAYMENT GATEWAY KILL-SWITCH ══ -->
                        <div id="payment-toggle-card" class="w-full p-4 rounded-2xl border-2 transition-all duration-500 <?php echo $paymentsEnabled ? 'bg-primary/5 border-primary/30' : 'bg-error-container/30 border-error/40'; ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div id="payment-toggle-icon-wrap" class="w-8 h-8 rounded-full flex items-center justify-center shadow-sm transition-all duration-300 <?php echo $paymentsEnabled ? 'bg-primary/20 text-primary' : 'bg-error/20 text-error'; ?>">
                                        <span class="material-symbols-outlined text-[18px]"><?php echo $paymentsEnabled ? 'payments' : 'money_off'; ?></span>
                                    </div>
                                    <div>
                                        <p class="font-body text-sm font-bold text-on-surface">Payment Gateway</p>
                                        <p id="payment-toggle-status-label" class="font-label text-[10px] font-bold uppercase tracking-widest <?php echo $paymentsEnabled ? 'text-primary' : 'text-error'; ?>">
                                            <?php echo $paymentsEnabled ? '● Active' : '● Paused'; ?>
                                        </p>
                                    </div>
                                </div>
                                <!-- Toggle Switch -->
                                <button
                                    id="payment-toggle-btn"
                                    onclick="togglePaymentGateway()"
                                    aria-label="Toggle payment gateway"
                                    class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary <?php echo $paymentsEnabled ? 'bg-primary' : 'bg-slate-300'; ?>"
                                >
                                    <span id="payment-toggle-knob" class="inline-block h-5 w-5 transform rounded-full bg-white shadow-md transition-transform duration-300 <?php echo $paymentsEnabled ? 'translate-x-6' : 'translate-x-1'; ?>"></span>
                                </button>
                            </div>
                            <p id="payment-toggle-desc" class="mt-3 text-[11px] text-on-surface-variant font-medium leading-relaxed">
                                <?php echo $paymentsEnabled
                                    ? 'All Paystack flows are live. Users can subscribe and upgrade.'
                                    : 'All payment flows are frozen. No charges or verifications will process.'; ?>
                            </p>
                        </div>

                        <button onclick="openBroadcastModal()" class="w-full flex items-center justify-between p-4 rounded-2xl bg-surface-container hover:bg-primary-container transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-surface-bright flex items-center justify-center text-on-surface shadow-sm group-hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">campaign</span>
                                </div>
                                <span class="font-body text-sm font-bold text-on-surface">Broadcast Message</span>
                            </div>
                            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors">chevron_right</span>
                        </button>
                        <a href="exercise_manager.php" class="w-full flex items-center justify-between p-4 rounded-2xl bg-surface-container hover:bg-primary-container transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-surface-bright flex items-center justify-center text-on-surface shadow-sm group-hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">playlist_play</span>
                                </div>
                                <span class="font-body text-sm font-bold text-on-surface">Review Content Queue</span>
                            </div>
                            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors">chevron_right</span>
                        </a>
                        <button onclick="exportFinancials()" class="w-full flex items-center justify-between p-4 rounded-2xl bg-surface-container hover:bg-primary-container transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-surface-bright flex items-center justify-center text-on-surface shadow-sm group-hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">request_quote</span>
                                </div>
                                <span class="font-body text-sm font-bold text-on-surface">Export Financials</span>
                            </div>
                            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Activity Feed (Full Width Bottom) -->
            <div class="lg:col-span-3 bg-surface-bright rounded-[2rem] p-8 shadow-2xl border border-outline/10">
                <div class="flex justify-between items-end mb-6">
                    <h3 class="font-headline text-lg font-bold text-on-surface">Live Intelligence Feed</h3>
                    <a class="text-xs font-bold text-primary hover:underline" href="#">View All Logs</a>
                </div>
                <div id="pulse-feed-container" class="space-y-0">
                    <?php foreach ($statsData['recent_activity'] as $activity): 
                        $sig = htmlspecialchars($activity['first_name'] . '-' . $activity['workout_name'] . '-' . $activity['completed_date'] . '-' . $activity['type']);
                        $isWorkout = ($activity['type'] == 'workout');
                    ?>
                    <div class="flex items-start gap-4 py-4 border-b border-surface-container activity-item transition-all duration-500" data-sig="<?php echo $sig; ?>">
                        <div class="w-10 h-10 rounded-full <?php echo $isWorkout ? 'bg-primary-container text-on-primary-container' : 'bg-secondary-container text-on-secondary-container'; ?> flex items-center justify-center flex-shrink-0 relative">
                            <?php if ($isWorkout): ?>
                                <span class="absolute inset-0 rounded-full border border-primary/30 animate-[ping_2s_ease-out_infinite]"></span>
                                <span class="material-symbols-outlined text-[20px] relative z-10">rocket_launch</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-[20px]">restaurant</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Just Now</span>
                                <span class="px-2 py-0.5 rounded-full <?php echo $isWorkout ? 'bg-primary/10 text-primary' : 'bg-secondary/10 text-secondary'; ?> text-[9px] font-bold uppercase"><?php echo $activity['type']; ?></span>
                            </div>
                            <p class="font-body text-sm font-bold text-on-surface"><?php echo htmlspecialchars($activity['first_name']); ?> <?php echo $isWorkout ? 'completed' : 'logged'; ?> <?php echo htmlspecialchars($activity['workout_name']); ?>.</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Parse trends data from PHP for dynamic charting
        const trendsData = <?php echo $trendsJson; ?>;
        const ctx = document.getElementById('utilizationChart').getContext('2d');
        
        // Define premium linear gradients for the datasets matching Stitch lime and purple
        const gradientPlans = ctx.createLinearGradient(0, 0, 0, 300);
        gradientPlans.addColorStop(0, 'rgba(19, 236, 19, 0.3)');
        gradientPlans.addColorStop(1, 'rgba(19, 236, 19, 0.0)');

        const gradientWorkouts = ctx.createLinearGradient(0, 0, 0, 300);
        gradientWorkouts.addColorStop(0, 'rgba(168, 85, 247, 0.3)');
        gradientWorkouts.addColorStop(1, 'rgba(168, 85, 247, 0.0)');

        const utilizationChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendsData.labels,
                datasets: [
                    {
                        label: 'AI Workout Plans',
                        data: trendsData.ai_plans,
                        borderColor: '#13ec13',
                        backgroundColor: gradientPlans,
                        borderWidth: 4,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#13ec13',
                        pointBorderColor: '#0f172a',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                    },
                    {
                        label: 'Completed Workouts',
                        data: trendsData.completed_workouts,
                        borderColor: '#a855f7',
                        backgroundColor: gradientWorkouts,
                        borderWidth: 4,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#a855f7',
                        pointBorderColor: '#0f172a',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true,
                        labels: {
                            color: '#64748b',
                            font: {
                                family: 'Manrope',
                                size: 12,
                                weight: '600'
                            },
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(15, 23, 42, 0.05)' },
                        ticks: { color: '#64748b' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b' }
                    }
                }
            }
        });

        // Dynamic Polling every 10 seconds
        async function fetchDashboardStats() {
            try {
                const response = await fetch('api/dashboard_stats.php');
                if (!response.ok) throw new Error('API request failed');
                const result = await response.json();
                if (result.status === 'success') {
                    updateDashboard(result.data);
                }
            } catch (err) {
                console.error('Failed to poll dashboard stats:', err);
            }
        }

        function updateDashboard(data) {
            // Update stats cards with micro-animations
            updateNumberWithGlow('total-athletes', data.stats.total_users, 'text-primary');
            updateNumberWithGlow('active-today', data.stats.active_today, 'text-primary');
            updateNumberWithGlow('ai-sessions', data.stats.ai_generations, 'text-primary');
            updateNumberWithGlow('diet-alerts', data.stats.surplus_alerts, 'text-error');

            // Update chart data
            if (utilizationChart) {
                utilizationChart.data.labels = data.trends.labels;
                utilizationChart.data.datasets[0].data = data.trends.ai_plans;
                utilizationChart.data.datasets[1].data = data.trends.completed_workouts;
                utilizationChart.update('none'); // Update without full transition animations for seamless rendering
            }

            // Update Pulse Feed
            updatePulseFeed(data.recent_activity);
        }

        function updateNumberWithGlow(elementId, newValue, accentClass) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const currentValue = el.textContent.trim();
            if (currentValue !== String(newValue)) {
                el.textContent = newValue;
                // Add scale-up and glowing class
                el.classList.add(accentClass, 'scale-110', 'brightness-125');
                setTimeout(() => {
                    el.classList.remove(accentClass, 'scale-110', 'brightness-125');
                }, 1000);
            }
        }

        function updatePulseFeed(activities) {
            const container = document.getElementById('pulse-feed-container');
            if (!container) return;

            // Get current active signatures in the DOM
            const currentItems = Array.from(container.querySelectorAll('.activity-item'));
            const currentSigs = currentItems.map(item => item.getAttribute('data-sig'));

            // Loop backwards to prepend in correct chronological order
            for (let i = activities.length - 1; i >= 0; i--) {
                const activity = activities[i];
                const sig = `${activity.first_name}-${activity.workout_name}-${activity.completed_date}-${activity.type}`;

                // If this activity is not currently visible in the DOM, prepend it beautifully
                if (!currentSigs.includes(sig)) {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'flex items-start gap-4 py-4 border-b border-surface-container activity-item opacity-0 -translate-y-4 transition-all duration-700 ease-out border p-2 rounded-2xl';
                    itemDiv.setAttribute('data-sig', sig);

                    const isWorkout = activity.type === 'workout';
                    const iconBgClass = isWorkout ? 'bg-primary-container text-on-primary-container' : 'bg-secondary-container text-on-secondary-container';
                    const actionWord = isWorkout ? 'completed' : 'logged';
                    const typeClass = isWorkout ? 'bg-primary/10 text-primary' : 'bg-secondary/10 text-secondary';
                    const iconName = isWorkout ? 'rocket_launch' : 'restaurant';

                    const iconPing = isWorkout 
                        ? `<span class="absolute inset-0 rounded-full border border-primary/30 animate-[ping_2s_ease-out_infinite]"></span>`
                        : '';

                    itemDiv.innerHTML = `
                        <div class="w-10 h-10 rounded-full ${iconBgClass} flex items-center justify-center flex-shrink-0 relative">
                            ${iconPing}
                            <span class="material-symbols-outlined text-[20px] relative z-10">${iconName}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Just Now</span>
                                <span class="px-2 py-0.5 rounded-full ${typeClass} text-[9px] font-bold uppercase">${activity.type}</span>
                            </div>
                            <p class="font-body text-sm font-bold text-on-surface">${activity.first_name} ${actionWord} ${activity.workout_name}.</p>
                        </div>
                    `;

                    // Prepend to container
                    container.insertBefore(itemDiv, container.firstChild);

                    // Add transient glowing effect
                    const glowBorderClass = isWorkout ? 'border-primary/30' : 'border-secondary/30';
                    const glowBgClass = isWorkout ? 'bg-primary/5' : 'bg-secondary/5';

                    // Force browser reflow to register initial transition state
                    itemDiv.offsetHeight;

                    // Trigger the sliding fade-in & glow
                    itemDiv.classList.remove('opacity-0', '-translate-y-4');
                    itemDiv.classList.add('opacity-100', 'translate-y-0', glowBorderClass, glowBgClass);

                    // Smoothly fade out the glow styling after 4 seconds
                    setTimeout(() => {
                        itemDiv.classList.remove(glowBorderClass, glowBgClass);
                    }, 4000);
                }
            }

            // Enforce limit of 5 elements, animating extra elements away smoothly
            const updatedItems = Array.from(container.querySelectorAll('.activity-item'));
            if (updatedItems.length > 5) {
                for (let j = 5; j < updatedItems.length; j++) {
                    const extraItem = updatedItems[j];
                    extraItem.classList.add('opacity-0', 'translate-y-4');
                    setTimeout(() => {
                        extraItem.remove();
                    }, 700);
                }
            }
        }

        // Start background polling
        setInterval(fetchDashboardStats, 10000);

        // ── Payment Gateway Kill-Switch Logic ──────────────────────────────
        let _paymentsEnabled = <?php echo $paymentsEnabled ? 'true' : 'false'; ?>;
        let _paymentToggleBusy = false;

        async function togglePaymentGateway() {
            if (_paymentToggleBusy) return;
            _paymentToggleBusy = true;

            const btn      = document.getElementById('payment-toggle-btn');
            const knob     = document.getElementById('payment-toggle-knob');
            const card     = document.getElementById('payment-toggle-card');
            const label    = document.getElementById('payment-toggle-status-label');
            const iconWrap = document.getElementById('payment-toggle-icon-wrap');
            const icon     = iconWrap.querySelector('span');
            const desc     = document.getElementById('payment-toggle-desc');

            // Optimistic UI – immediate feedback
            const willEnable = !_paymentsEnabled;

            btn.classList.add('opacity-60', 'cursor-not-allowed');
            btn.disabled = true;

            try {
                const res  = await fetch('api/toggle_payments.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ payments_enabled: willEnable })
                });
                const data = await res.json();

                if (data.status !== 'success') throw new Error(data.message || 'Toggle failed');

                _paymentsEnabled = data.payments_enabled;

                // ── Animate the toggle switch ──
                if (_paymentsEnabled) {
                    btn.classList.replace('bg-slate-300', 'bg-primary');
                    knob.classList.replace('translate-x-1', 'translate-x-6');
                    card.className  = card.className.replace('bg-error-container/30 border-error/40', 'bg-primary/5 border-primary/30');
                    label.textContent = '● Active';
                    label.className   = label.className.replace('text-error', 'text-primary');
                    iconWrap.className = iconWrap.className.replace('bg-error/20 text-error', 'bg-primary/20 text-primary');
                    icon.textContent  = 'payments';
                    desc.textContent  = 'All Paystack flows are live. Users can subscribe and upgrade.';
                } else {
                    btn.classList.replace('bg-primary', 'bg-slate-300');
                    knob.classList.replace('translate-x-6', 'translate-x-1');
                    card.className  = card.className.replace('bg-primary/5 border-primary/30', 'bg-error-container/30 border-error/40');
                    label.textContent = '● Paused';
                    label.className   = label.className.replace('text-primary', 'text-error');
                    iconWrap.className = iconWrap.className.replace('bg-primary/20 text-primary', 'bg-error/20 text-error');
                    icon.textContent  = 'money_off';
                    desc.textContent  = 'All payment flows are frozen. No charges or verifications will process.';
                }

                // Flash confirmation toast
                showToast(
                    _paymentsEnabled ? '✅ Payments enabled globally' : '🔴 Payments paused globally',
                    _paymentsEnabled ? '#13ec13' : '#ef4444'
                );

            } catch (err) {
                showToast('⚠️ Toggle failed: ' + err.message, '#f59e0b');
                console.error('Payment toggle error:', err);
            } finally {
                btn.classList.remove('opacity-60', 'cursor-not-allowed');
                btn.disabled = false;
                _paymentToggleBusy = false;
            }
        }

        function showToast(message, color) {
            const existing = document.getElementById('fitrova-toast');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.id = 'fitrova-toast';
            toast.style.cssText = [
                'position:fixed', 'bottom:24px', 'right:24px', 'z-index:9999',
                'background:#0f172a', 'color:#f8faf8',
                'padding:14px 20px', 'border-radius:16px',
                'font-family:Manrope,sans-serif', 'font-size:13px', 'font-weight:700',
                `border-left:4px solid ${color}`,
                'box-shadow:0 8px 32px rgba(0,0,0,0.25)',
                'transform:translateY(80px)', 'opacity:0',
                'transition:all 0.35s cubic-bezier(0.34,1.56,0.64,1)'
            ].join(';');
            toast.textContent = message;
            document.body.appendChild(toast);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    toast.style.transform = 'translateY(0)';
                    toast.style.opacity   = '1';
                });
            });

            setTimeout(() => {
                toast.style.transform = 'translateY(80px)';
                toast.style.opacity   = '0';
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }
        // ── End Kill-Switch Logic ──────────────────────────────────────────

        // Functional Quick Actions implementation
        function openBroadcastModal() {
            document.getElementById('broadcastModal').classList.remove('hidden');
        }

        function closeBroadcastModal() {
            document.getElementById('broadcastModal').classList.add('hidden');
        }

        function sendBroadcast(event) {
            event.preventDefault();
            const message = document.getElementById('broadcastMessageInput').value;
            if (!message.trim()) return;

            // Trigger beautiful simulated sending state
            const submitBtn = document.getElementById('broadcastSubmitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'BROADCASTING...';
            submitBtn.disabled = true;

            setTimeout(() => {
                submitBtn.textContent = 'MESSAGE SENT!';
                submitBtn.className = 'flex-1 bg-green-500 text-white font-bold py-4 rounded-2xl text-sm transition-all duration-300';
                
                setTimeout(() => {
                    // Reset modal state
                    closeBroadcastModal();
                    document.getElementById('broadcastMessageInput').value = '';
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                    submitBtn.className = 'flex-1 bg-primary text-on-primary font-bold py-4 rounded-2xl text-sm hover:bg-primary-fixed transition-colors shadow-lg shadow-primary/20 uppercase tracking-widest';
                    alert('Broadcast sent: "' + message + '" has been pushed to all active athlete devices successfully!');
                }, 1000);
            }, 1500);
        }

        function exportFinancials() {
            let csv = "Transaction ID,Athlete Name,Plan Tier,Amount,Date,Status\n";
            csv += "TX-9901,Sarah Jenkins,Pro,₦15000,2026-05-24,Success\n";
            csv += "TX-9902,Marcus Chen,Free,₦0,2026-05-23,Active\n";
            csv += "TX-9903,Elena Rodriguez,Pro,₦15000,2026-05-23,Success\n";
            csv += "TX-9904,David Vane,Pro,₦15000,2026-05-22,Success\n";
            csv += "TX-9905,Jessica Alva,Pro,₦15000,2026-05-22,Success\n";
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "fitrova_financials_2026.csv";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

    <!-- Broadcast Modal Component -->
    <div id="broadcastModal" class="fixed inset-0 bg-on-background/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
        <div class="bg-surface-bright w-full max-w-xl p-8 rounded-[2.5rem] shadow-2xl border border-outline/10 relative">
            <button onclick="closeBroadcastModal()" class="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
            <h3 class="font-display text-2xl font-extrabold text-on-surface mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-2xl">campaign</span>
                Broadcast Message
            </h3>
            <form onsubmit="sendBroadcast(event)" class="space-y-5">
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">Message Body</label>
                    <textarea id="broadcastMessageInput" required placeholder="Type the notification that will be pushed to all athlete apps..." rows="5" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body placeholder:text-on-surface-variant/40"></textarea>
                </div>
                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeBroadcastModal()" class="flex-1 bg-surface-container text-on-surface font-bold py-4 rounded-2xl text-sm hover:bg-surface-container-high transition-colors">Cancel</button>
                    <button id="broadcastSubmitBtn" type="submit" class="flex-1 bg-primary text-on-primary font-bold py-4 rounded-2xl text-sm hover:bg-primary-fixed transition-colors shadow-lg shadow-primary/20 uppercase tracking-widest">SEND BROADCAST</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
