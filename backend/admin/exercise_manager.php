<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db_config.php';

// Handle Add/Edit/Delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO exercise_library (name, keywords, video_url, category) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['keywords'], $_POST['video_url'], $_POST['category']]);
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM exercise_library WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE exercise_library SET name = ?, keywords = ?, video_url = ?, category = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['keywords'], $_POST['video_url'], $_POST['category'], $_POST['id']]);
        } elseif ($_POST['action'] === 'delete_workout') {
            $stmt = $pdo->prepare("DELETE FROM workout_plans WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        }
    }
}

$exercises = $pdo->query("SELECT * FROM exercise_library ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all generated workout plans with user context
$workoutPlans = $pdo->query("
    SELECT wp.*, u.first_name, u.last_name, u.email 
    FROM workout_plans wp 
    LEFT JOIN users u ON wp.user_id = u.id 
    ORDER BY wp.plan_date DESC, wp.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all nutrition logs across all users
$nutritionLogs = $pdo->query("
    SELECT nl.id, nl.user_id, nl.meal_name, nl.calories, nl.protein, nl.carbs, nl.fats,
           nl.meal_type, nl.logged_date, nl.created_at,
           u.first_name, u.last_name, u.email
    FROM nutrition_logs nl
    LEFT JOIN users u ON nl.user_id = u.id
    ORDER BY nl.logged_date DESC, nl.created_at DESC
    LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

// Nutrition summary aggregates
$nutritionStats = $pdo->query("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as unique_users,
        COALESCE(SUM(calories), 0) as total_calories,
        COALESCE(ROUND(AVG(calories), 0), 0) as avg_calories,
        COALESCE(ROUND(AVG(protein), 1), 0) as avg_protein,
        COUNT(CASE WHEN logged_date = CURDATE() THEN 1 END) as logs_today
    FROM nutrition_logs
")->fetch(PDO::FETCH_ASSOC);

// Thumbnail matching for workout plans
if (!function_exists('getWorkoutImage')) {
    function getWorkoutImage($category, $name) {
        $category = strtolower($category);
        $name = strtolower($name);
        
        $images = [
            'strength'  => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80',
            'cardio'    => 'https://images.unsplash.com/photo-1538805060514-97d9cc17730c?w=800&q=80',
            'recovery'  => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=800&q=80',
            'yoga'      => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=800&q=80',
            'core'      => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=800&q=80',
            'legs'      => 'https://images.unsplash.com/photo-1434608519344-49d77a699e1d?w=800&q=80',
            'push'      => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=800&q=80',
            'pull'      => 'https://images.unsplash.com/photo-1598971639058-fab3c3109a00?w=800&q=80',
            'full body' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80'
        ];

        foreach ($images as $key => $url) {
            if (strpos($category, $key) !== false || strpos($name, $key) !== false) {
                return $url;
            }
        }

        return 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80'; // Default
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <link rel="icon" type="image/png" href="favicon.png"/>
    <title>Fitrova Admin - Content Control</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
    <style type="text/tailwindcss">
        @layer utilities {
            .card-shadow { box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.2); }
            .neon-glow { filter: drop-shadow(0 0 8px rgba(19, 236, 19, 0.4)); }
        }
    </style>
</head>
<body class="flex bg-surface min-h-screen selection:bg-primary selection:text-on-primary">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- TopNavBar -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Main Content Canvas -->
    <main class="ml-0 md:ml-64 mt-16 p-4 md:p-8 w-full max-w-7xl">
        <!-- Page Header -->
        <div class="flex justify-between items-end mb-10 mt-4">
            <div>
                <h2 class="font-display text-3xl font-extrabold tracking-tight text-on-surface mb-2">Content Control</h2>
                <p class="font-body text-on-surface-variant">Manage workouts, meal libraries, and AI templates.</p>
            </div>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-primary text-on-primary font-bold px-6 py-3 rounded-xl flex items-center gap-2 hover:bg-primary-fixed transition-colors active:scale-95 neon-glow uppercase tracking-wider text-xs">
                <span class="material-symbols-outlined">add</span>
                Add New Content
            </button>
        </div>

        <!-- Navigation Tabs -->
        <div id="tabContainer" class="flex gap-4 mb-8 pb-4 border-b border-outline-variant/30 flex-wrap">
            <button id="tabBtnExercise" onclick="switchTab('exercise')" class="px-6 py-3 rounded-xl bg-surface-container-high text-on-primary-container font-bold border border-primary/20 text-sm tracking-tight transition-all">Exercise Library</button>
            <button id="tabBtnWorkout" onclick="switchTab('workout')" class="px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-variant transition-all text-sm font-semibold tracking-tight">Workout Programs</button>
            <button id="tabBtnMeal" onclick="switchTab('meal')" class="px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-variant transition-all text-sm font-semibold tracking-tight">Meal Database</button>
            <button id="tabBtnAI" onclick="switchTab('ai')" class="px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-variant transition-all text-sm font-semibold tracking-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">auto_awesome</span>
                AI Templates
            </button>
        </div>

        <!-- Main Content Area (Full Width) -->
        <div class="space-y-6">
                
                <!-- 1. EXERCISE LIBRARY TAB CONTENT -->
                <div id="exerciseTabContent" class="space-y-6">
                    <!-- Filters & Search -->
                    <div class="flex gap-4 mb-6">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                            <input id="exSearchInput" oninput="filterExercises()" class="w-full pl-12 pr-4 py-3 rounded-xl bg-surface-bright border-none shadow-sm focus:ring-2 focus:ring-primary/50 text-sm font-body text-on-surface placeholder:text-on-surface-variant/40" placeholder="Search exercises..." type="text"/>
                        </div>
                        <select id="exCategorySelect" onchange="filterExercises()" class="px-4 py-3 pr-10 rounded-xl bg-surface-bright border-none shadow-sm focus:ring-2 focus:ring-primary/50 text-sm font-body text-on-surface-variant">
                            <option value="all">All Categories</option>
                            <option value="strength">Strength</option>
                            <option value="cardio">Cardio</option>
                            <option value="core">Core</option>
                            <option value="recovery">Recovery</option>
                            <option value="full_body">Full Body</option>
                        </select>
                    </div>

                    <!-- Bento Grid for Exercises -->
                    <div id="exerciseCardsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($exercises as $ex): 
                            preg_match('/(?:v=|\/)([0-9A-Za-z_-]{11}).*/', $ex['video_url'], $matches);
                            $yt_id = $matches[1] ?? 'default';
                        ?>
                        <div class="ex-card bg-surface-bright rounded-2xl p-4 card-shadow hover:shadow-2xl transition-all flex flex-col border border-outline/10" data-category="<?php echo htmlspecialchars(strtolower($ex['category'])); ?>" data-search="<?php echo htmlspecialchars(strtolower($ex['name'] . ' ' . $ex['keywords'])); ?>">
                            <div class="h-40 rounded-xl bg-surface-variant mb-4 overflow-hidden relative border border-outline/10">
                                <img src="https://img.youtube.com/vi/<?php echo $yt_id; ?>/0.jpg" class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($ex['name']); ?>">
                                <div class="absolute top-2 right-2 bg-surface-container-high text-on-primary-container px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border border-primary/20 backdrop-blur-md">Active</div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-headline text-lg font-bold text-on-surface mb-1"><?php echo htmlspecialchars($ex['name']); ?></h3>
                                <p class="font-label text-xs uppercase tracking-widest text-on-surface-variant mb-4"><?php echo htmlspecialchars($ex['category']); ?> • Compound</p>
                                <p class="text-xs text-on-surface-variant italic mb-4">Keywords: <?php echo htmlspecialchars($ex['keywords']); ?></p>
                            </div>
                            <div class="flex justify-between items-center pt-4 border-t border-outline-variant/30">
                                <a href="<?php echo htmlspecialchars($ex['video_url']); ?>" target="_blank" class="flex items-center gap-1 text-primary hover:text-primary-fixed-dim text-xs font-bold uppercase tracking-wider">
                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    Watch Video
                                </a>
                                
                                <div class="flex items-center gap-2">
                                    <button onclick="openEditModal(<?php echo $ex['id']; ?>, '<?php echo htmlspecialchars($ex['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($ex['keywords'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($ex['video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($ex['category'], ENT_QUOTES); ?>')" class="text-primary hover:text-primary-fixed-dim transition-colors p-1">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    
                                    <form action="" method="POST" onsubmit="return confirm('Delete this exercise from the matrix?');" class="m-0 inline-block">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $ex['id']; ?>">
                                        <button type="submit" class="text-error hover:text-red-700 p-1 bg-transparent border-none">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 2. WORKOUT PROGRAMS TAB CONTENT -->
                <div id="workoutTabContent" class="space-y-6 hidden">
                    <!-- Filters & Search -->
                    <div class="flex gap-4 mb-6">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                            <input id="workoutSearchInput" oninput="filterWorkouts()" class="w-full pl-12 pr-4 py-3 rounded-xl bg-surface-bright border-none shadow-sm focus:ring-2 focus:ring-primary/50 text-sm font-body text-on-surface placeholder:text-on-surface-variant/40" placeholder="Search workouts..." type="text"/>
                        </div>
                        <select id="workoutTypeSelect" onchange="filterWorkouts()" class="px-4 py-3 pr-10 rounded-xl bg-surface-bright border-none shadow-sm focus:ring-2 focus:ring-primary/50 text-sm font-body text-on-surface-variant">
                            <option value="all">All Types</option>
                            <option value="strength">Strength</option>
                            <option value="cardio">Cardio</option>
                            <option value="flexibility">Flexibility</option>
                            <option value="mixed">Mixed</option>
                        </select>
                    </div>

                    <!-- Bento Grid for Workouts -->
                    <div id="workoutCardsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if (empty($workoutPlans)): ?>
                            <div class="col-span-full bg-surface-bright rounded-2xl p-8 text-center border border-outline/10 card-shadow">
                                <span class="material-symbols-outlined text-[48px] text-on-surface-variant/30 mb-2">sports_gymnastics</span>
                                <p class="font-headline text-lg font-bold text-on-surface mb-1">No Workout Plans Generated</p>
                                <p class="text-xs text-on-surface-variant">Plans generated by the AI Coach or local rules engine will show up here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($workoutPlans as $plan): 
                                $data = json_decode($plan['plan_data'], true);
                                $workout = $data['todays_workout'] ?? null;
                                $duration = $plan['duration_minutes'] ?: ($workout['duration'] ?? 45);
                                $difficulty = ucfirst($plan['difficulty'] ?: ($workout['difficulty'] ?? 'intermediate'));
                                $type = ucfirst($plan['workout_type'] ?: ($workout['type'] ?? 'mixed'));
                                $exercisesCount = $workout ? count($workout['exercises'] ?? []) : 0;
                                $thumb = getWorkoutImage($plan['workout_type'], $plan['name']);
                            ?>
                            <div class="workout-card bg-surface-bright rounded-2xl p-4 card-shadow hover:shadow-2xl transition-all flex flex-col border border-outline/10" data-type="<?php echo htmlspecialchars(strtolower($plan['workout_type'])); ?>" data-search="<?php echo htmlspecialchars(strtolower($plan['name'] . ' ' . $plan['first_name'] . ' ' . $plan['last_name'] . ' ' . $plan['email'])); ?>">
                                <div class="h-40 rounded-xl bg-surface-variant mb-4 overflow-hidden relative border border-outline/10">
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($plan['name']); ?>">
                                    <div class="absolute top-2 right-2 bg-emerald-500 text-white px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border border-emerald-400/20 backdrop-blur-md">Active</div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-headline text-lg font-bold text-on-surface mb-1"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                    <p class="text-xs text-on-surface-variant font-medium mb-1">
                                        <span class="font-bold text-primary-fixed-dim">Athlete:</span> <?php echo htmlspecialchars($plan['first_name'] . ' ' . $plan['last_name']); ?> 
                                        <span class="text-on-surface-variant/60">(<?php echo htmlspecialchars($plan['email']); ?>)</span>
                                    </p>
                                    <p class="text-[11px] text-on-surface-variant mb-4">
                                        <span class="font-bold text-on-surface-variant">Scheduled:</span> <?php echo date('M d, Y', strtotime($plan['plan_date'])); ?>
                                    </p>
                                    
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <span class="px-2 py-1 rounded-lg bg-surface-variant text-on-surface-variant text-[10px] font-bold uppercase"><?php echo htmlspecialchars($type); ?></span>
                                        <span class="px-2 py-1 rounded-lg bg-primary-container text-on-primary-container text-[10px] font-bold uppercase"><?php echo htmlspecialchars($difficulty); ?></span>
                                        <span class="px-2 py-1 rounded-lg bg-secondary-container text-on-secondary-container text-[10px] font-bold uppercase"><?php echo htmlspecialchars($duration); ?> Min</span>
                                        <span class="px-2 py-1 rounded-lg bg-surface-container text-on-surface-variant text-[10px] font-bold uppercase"><?php echo htmlspecialchars($exercisesCount); ?> Exercises</span>
                                    </div>
                                    
                                    <!-- Dropdown for Exercises -->
                                    <?php if ($workout && !empty($workout['exercises'])): ?>
                                        <div class="mb-4">
                                            <button type="button" onclick="toggleWorkoutExercises(<?php echo $plan['id']; ?>)" class="w-full flex justify-between items-center bg-surface-container/50 hover:bg-surface-container transition-colors px-3 py-2 rounded-xl text-xs font-bold text-on-surface-variant border border-outline/5 outline-none">
                                                <span>Show Exercises List</span>
                                                <span id="workoutExIcon-<?php echo $plan['id']; ?>" class="material-symbols-outlined text-[18px]">expand_more</span>
                                            </button>
                                            <div id="workoutExList-<?php echo $plan['id']; ?>" class="hidden mt-2 p-3 bg-surface-container rounded-xl text-xs space-y-2 border border-outline/10 transition-all duration-300">
                                                <?php foreach ($workout['exercises'] as $index => $ex): ?>
                                                    <div class="flex justify-between items-start text-[11px] text-on-surface-variant border-b border-outline-variant/10 pb-1.5 last:border-b-0 last:pb-0">
                                                        <div class="pr-2">
                                                            <p class="font-bold text-on-surface text-[12px]"><?php echo ($index + 1) . '. ' . htmlspecialchars($ex['name']); ?></p>
                                                            <p class="text-[10px] text-on-surface-variant/70 italic line-clamp-1"><?php echo htmlspecialchars(is_array($ex['instructions']) ? implode(' ', $ex['instructions']) : $ex['instructions']); ?></p>
                                                        </div>
                                                        <span class="shrink-0 bg-primary-container text-on-primary-container px-2 py-0.5 rounded text-[10px] font-bold">
                                                            <?php echo ($ex['sets'] ?? 3) . 'x' . ($ex['reps'] ?? 10); ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex justify-end pt-4 border-t border-outline-variant/30">
                                    <form action="" method="POST" onsubmit="return confirm('Delete this workout plan from the system?');" class="m-0">
                                        <input type="hidden" name="action" value="delete_workout">
                                        <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                                        <button type="submit" class="text-error hover:text-red-700 p-1 bg-transparent border-none flex items-center gap-1 text-xs font-bold uppercase tracking-wider transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                            Delete Workout
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. MEAL DATABASE TAB CONTENT -->
                <div id="mealTabContent" class="space-y-6 hidden">

                    <!-- Summary Stats Row -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div class="bg-surface-bright rounded-2xl p-4 border border-outline/10 card-shadow text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Total Logs</p>
                            <p class="text-2xl font-extrabold text-on-surface"><?php echo number_format($nutritionStats['total_logs']); ?></p>
                        </div>
                        <div class="bg-surface-bright rounded-2xl p-4 border border-outline/10 card-shadow text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Active Users</p>
                            <p class="text-2xl font-extrabold text-primary"><?php echo $nutritionStats['unique_users']; ?></p>
                        </div>
                        <div class="bg-surface-bright rounded-2xl p-4 border border-outline/10 card-shadow text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Logged Today</p>
                            <p class="text-2xl font-extrabold text-secondary"><?php echo $nutritionStats['logs_today']; ?></p>
                        </div>
                        <div class="bg-surface-bright rounded-2xl p-4 border border-outline/10 card-shadow text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Total kcal</p>
                            <p class="text-2xl font-extrabold text-on-surface"><?php echo number_format($nutritionStats['total_calories']); ?></p>
                        </div>
                        <div class="bg-surface-bright rounded-2xl p-4 border border-outline/10 card-shadow text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Avg kcal/meal</p>
                            <p class="text-2xl font-extrabold text-on-surface"><?php echo $nutritionStats['avg_calories']; ?></p>
                        </div>
                        <div class="bg-surface-bright rounded-2xl p-4 border border-outline/10 card-shadow text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Avg Protein</p>
                            <p class="text-2xl font-extrabold text-on-surface"><?php echo $nutritionStats['avg_protein']; ?>g</p>
                        </div>
                    </div>

                    <!-- Search & Filter Bar -->
                    <div class="bg-surface-bright rounded-2xl p-4 border border-outline/10 card-shadow flex flex-wrap gap-3 items-center">
                        <div class="relative flex-1 min-w-[180px]">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                            <input type="text" id="mealSearch" placeholder="Search by user or meal name…"
                                class="w-full bg-surface-container/50 border border-outline/20 rounded-xl pl-9 pr-4 py-2.5 text-sm text-on-surface outline-none focus:ring-2 focus:ring-primary/40 placeholder:text-on-surface-variant/40"
                                oninput="filterMealTable()">
                        </div>
                        <select id="mealTypeFilter" onchange="filterMealTable()"
                            class="bg-surface-container/50 border border-outline/20 rounded-xl px-4 py-2.5 text-sm text-on-surface outline-none focus:ring-2 focus:ring-primary/40">
                            <option value="">All meal types</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="snack">Snack</option>
                        </select>
                        <input type="date" id="mealDateFilter" onchange="filterMealTable()"
                            class="bg-surface-container/50 border border-outline/20 rounded-xl px-4 py-2.5 text-sm text-on-surface outline-none focus:ring-2 focus:ring-primary/40">
                        <span class="text-xs text-on-surface-variant font-semibold ml-auto" id="mealRowCount">
                            <?php echo count($nutritionLogs); ?> entries
                        </span>
                    </div>

                    <!-- Data Table -->
                    <div class="bg-surface-bright rounded-2xl border border-outline/10 card-shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm" id="mealTable">
                                <thead>
                                    <tr class="border-b border-outline/10 bg-surface-container/40">
                                        <th class="text-left px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Date</th>
                                        <th class="text-left px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">User</th>
                                        <th class="text-left px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Meal</th>
                                        <th class="text-center px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Type</th>
                                        <th class="text-right px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">kcal</th>
                                        <th class="text-right px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Protein</th>
                                        <th class="text-right px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Carbs</th>
                                        <th class="text-right px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Fats</th>
                                        <th class="text-center px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="mealTableBody">
                                    <?php if (empty($nutritionLogs)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-16 text-on-surface-variant text-sm">
                                                <span class="material-symbols-outlined text-[40px] block mb-2 opacity-30">restaurant_menu</span>
                                                No meal logs yet. Users' nutrition entries will appear here.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($nutritionLogs as $log): ?>
                                            <?php
                                            $mealTypeColors = [
                                                'breakfast' => 'bg-amber-100 text-amber-700',
                                                'lunch'     => 'bg-green-100 text-green-700',
                                                'dinner'    => 'bg-blue-100 text-blue-700',
                                                'snack'     => 'bg-purple-100 text-purple-700',
                                            ];
                                            $typeClass = $mealTypeColors[$log['meal_type']] ?? 'bg-surface-container text-on-surface-variant';
                                            $userName = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'User #' . $log['user_id'];
                                            $initials = strtoupper(substr($log['first_name'] ?? 'U', 0, 1) . substr($log['last_name'] ?? '', 0, 1));
                                            ?>
                                            <tr class="meal-row border-b border-outline/5 hover:bg-surface-container/30 transition-colors"
                                                data-name="<?php echo htmlspecialchars(strtolower($log['meal_name'] . ' ' . $userName)); ?>"
                                                data-type="<?php echo htmlspecialchars($log['meal_type']); ?>"
                                                data-date="<?php echo htmlspecialchars($log['logged_date']); ?>">
                                                <td class="px-4 py-3 text-on-surface-variant text-xs whitespace-nowrap">
                                                    <?php echo date('M j, Y', strtotime($log['logged_date'])); ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                                                            <span class="text-[10px] font-bold text-primary"><?php echo htmlspecialchars($initials); ?></span>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold text-on-surface text-xs leading-tight"><?php echo htmlspecialchars($userName); ?></p>
                                                            <p class="text-[10px] text-on-surface-variant truncate max-w-[140px]"><?php echo htmlspecialchars($log['email'] ?? ''); ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <p class="font-medium text-on-surface text-xs leading-tight max-w-[180px] truncate"><?php echo htmlspecialchars($log['meal_name'] ?? '—'); ?></p>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?php echo $typeClass; ?>">
                                                        <?php echo htmlspecialchars($log['meal_type'] ?? 'snack'); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-right font-bold text-on-surface text-xs"><?php echo number_format($log['calories']); ?></td>
                                                <td class="px-4 py-3 text-right text-on-surface-variant text-xs"><?php echo number_format($log['protein'], 1); ?>g</td>
                                                <td class="px-4 py-3 text-right text-on-surface-variant text-xs"><?php echo number_format($log['carbs'], 1); ?>g</td>
                                                <td class="px-4 py-3 text-right text-on-surface-variant text-xs"><?php echo number_format($log['fats'], 1); ?>g</td>
                                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                                    <button type="button" onclick="viewMealDetails(this)" 
                                                            data-date="<?php echo htmlspecialchars(date('M j, Y', strtotime($log['logged_date']))); ?>"
                                                            data-user="<?php echo htmlspecialchars($userName); ?>"
                                                            data-email="<?php echo htmlspecialchars($log['email'] ?? ''); ?>"
                                                            data-meal="<?php echo htmlspecialchars($log['meal_name'] ?? ''); ?>"
                                                            data-type="<?php echo htmlspecialchars($log['meal_type']); ?>"
                                                            data-calories="<?php echo htmlspecialchars($log['calories']); ?>"
                                                            data-protein="<?php echo htmlspecialchars($log['protein']); ?>"
                                                            data-carbs="<?php echo htmlspecialchars($log['carbs']); ?>"
                                                            data-fats="<?php echo htmlspecialchars($log['fats']); ?>"
                                                            class="bg-primary/10 text-primary hover:bg-primary hover:text-on-primary px-3 py-1 rounded-xl text-[10px] font-bold uppercase tracking-wider transition-all hover:scale-105 active:scale-95">
                                                        View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($nutritionLogs)): ?>
                        <div class="px-4 py-3 border-t border-outline/10 flex items-center justify-between">
                            <p class="text-[10px] text-on-surface-variant">Showing latest 500 entries. Use search/filter to narrow results.</p>
                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 rounded-xl text-primary font-bold text-[10px] uppercase tracking-wider">
                                <span class="material-symbols-outlined text-[14px]">restaurant_menu</span>
                                CONNECTED TO SYSTEM NUTRITION API
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 4. AI TEMPLATES TAB CONTENT -->
                <div id="aiTabContent" class="space-y-6 hidden">
                    <div class="bg-surface-bright rounded-2xl p-8 text-center border border-outline/10 card-shadow">
                        <span class="material-symbols-outlined text-[48px] text-on-surface-variant/30 mb-2">psychology</span>
                        <p class="font-headline text-lg font-bold text-on-surface mb-1">AI Prompt & Model Templates</p>
                        <p class="text-xs text-on-surface-variant mb-4">System directives and context instructions active inside the Fitrova LLM pipeline.</p>
                        <div class="inline-flex px-4 py-2 bg-secondary-container text-on-secondary-container rounded-xl font-bold text-xs uppercase tracking-wider">Managed by Analytics Engine</div>
                    </div>
                </div>

        </div>
    </main>

    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-on-background/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
        <div class="bg-surface-bright w-full max-w-xl p-8 rounded-[2.5rem] shadow-2xl border border-outline/10 relative">
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
            <h3 class="font-display text-2xl font-extrabold text-on-surface mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-2xl">auto_awesome</span>
                Add New Exercise
            </h3>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">Exercise Name</label>
                    <input type="text" name="name" required class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body">
                </div>
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">Keywords (comma separated)</label>
                    <input type="text" name="keywords" required placeholder="squat, back squat, legs" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body placeholder:text-on-surface-variant/40">
                </div>
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">YouTube Video URL</label>
                    <input type="url" name="video_url" required placeholder="https://www.youtube.com/watch?v=..." class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body placeholder:text-on-surface-variant/40">
                </div>
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">Category</label>
                    <select name="category" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body">
                        <option value="strength">Strength</option>
                        <option value="cardio">Cardio</option>
                        <option value="core">Core</option>
                        <option value="recovery">Recovery</option>
                        <option value="full_body">Full Body</option>
                    </select>
                </div>
                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="flex-1 bg-surface-container text-on-surface font-bold py-4 rounded-2xl text-sm hover:bg-surface-container-high transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 bg-primary text-on-primary font-bold py-4 rounded-2xl text-sm hover:bg-primary-fixed transition-colors shadow-lg shadow-primary/20 uppercase tracking-widest">Add to Matrix</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Hide all tab contents
            document.getElementById('exerciseTabContent').classList.add('hidden');
            document.getElementById('workoutTabContent').classList.add('hidden');
            document.getElementById('mealTabContent').classList.add('hidden');
            document.getElementById('aiTabContent').classList.add('hidden');

            // Reset all tab button classes
            const tabs = ['exercise', 'workout', 'meal', 'ai'];
            tabs.forEach(t => {
                const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
                if (btn) {
                    btn.className = "px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-variant transition-all text-sm font-semibold tracking-tight flex items-center gap-2";
                }
            });

            // Show selected tab content and active button style
            const activeContent = document.getElementById(tab + 'TabContent');
            if (activeContent) {
                activeContent.classList.remove('hidden');
            }
            
            const activeBtn = document.getElementById('tabBtn' + tab.charAt(0).toUpperCase() + tab.slice(1));
            if (activeBtn) {
                activeBtn.className = "px-6 py-3 rounded-xl bg-surface-container-high text-on-primary-container font-bold border border-primary/20 text-sm tracking-tight transition-all flex items-center gap-2";
            }
        }

        function filterExercises() {
            const query = document.getElementById('exSearchInput').value.toLowerCase().trim();
            const category = document.getElementById('exCategorySelect').value.toLowerCase();
            const cards = document.querySelectorAll('.ex-card');

            cards.forEach(card => {
                const exCategory = card.getAttribute('data-category');
                const searchString = card.getAttribute('data-search');

                const matchesCategory = (category === 'all' || exCategory === category);
                const matchesSearch = (!query || searchString.includes(query));

                if (matchesCategory && matchesSearch) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        function filterWorkouts() {
            const query = document.getElementById('workoutSearchInput').value.toLowerCase().trim();
            const type = document.getElementById('workoutTypeSelect').value.toLowerCase();
            const cards = document.querySelectorAll('.workout-card');

            cards.forEach(card => {
                const cardType = card.getAttribute('data-type');
                const searchString = card.getAttribute('data-search');

                const matchesType = (type === 'all' || cardType === type);
                const matchesSearch = (!query || searchString.includes(query));

                if (matchesType && matchesSearch) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        function filterMealTable() {
            const search = document.getElementById('mealSearch').value.toLowerCase().trim();
            const type = document.getElementById('mealTypeFilter').value.toLowerCase();
            const date = document.getElementById('mealDateFilter').value;
            const rows = document.querySelectorAll('#mealTableBody .meal-row');
            let visible = 0;
            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const rowType = row.getAttribute('data-type') || '';
                const rowDate = row.getAttribute('data-date') || '';
                const matchSearch = !search || name.includes(search);
                const matchType = !type || rowType === type;
                const matchDate = !date || rowDate === date;

                if (matchSearch && matchType && matchDate) {
                    row.classList.remove('hidden');
                    visible++;
                } else {
                    row.classList.add('hidden');
                }
            });
            const countEl = document.getElementById('mealRowCount');
            if (countEl) {
                countEl.textContent = visible + ' entries';
            }
        }

        function toggleWorkoutExercises(id) {
            const list = document.getElementById('workoutExList-' + id);
            const icon = document.getElementById('workoutExIcon-' + id);
            if (list.classList.contains('hidden')) {
                list.classList.remove('hidden');
                icon.textContent = 'expand_less';
            } else {
                list.classList.add('hidden');
                icon.textContent = 'expand_more';
            }
        }

        // Edit Exercise modal handlers
        function openEditModal(id, name, keywords, url, category) {
            document.getElementById('editExId').value = id;
            document.getElementById('editExName').value = name;
            document.getElementById('editExKeywords').value = keywords;
            document.getElementById('editExUrl').value = url;
            document.getElementById('editExCategory').value = category.toLowerCase();
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // AI Approvals Verification Queue logic
        let activeApprovalType = '';
        function openReviewModal(type) {
            activeApprovalType = type;
            const title = document.getElementById('reviewModalTitle');
            const body = document.getElementById('reviewModalBody');
            
            if (type === 'meal') {
                title.innerHTML = '<span class="material-symbols-outlined text-primary text-2xl">restaurant_menu</span> Verify AI Meal Suggestion';
                body.innerHTML = `
                    <div class="space-y-4">
                        <div class="p-4 bg-surface-container rounded-2xl border border-outline/10 text-xs space-y-1">
                            <p class="font-bold text-on-surface text-sm">"High Protein Vegan Bowl"</p>
                            <p class="text-on-surface-variant leading-relaxed mt-1">AI detected high quality macro-nutritional balance suited for muscle gain strategies.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="font-label text-[10px] font-bold uppercase tracking-wider text-on-surface-variant block mb-1.5">Calories (kcal)</label>
                                <input type="number" id="reviewMealCal" value="480" class="w-full bg-surface-container/30 border border-outline/30 rounded-xl p-3 text-on-surface outline-none text-xs focus:ring-2 focus:ring-primary/40 font-body">
                            </div>
                            <div>
                                <label class="font-label text-[10px] font-bold uppercase tracking-wider text-on-surface-variant block mb-1.5">Protein (g)</label>
                                <input type="number" id="reviewMealProt" value="28" class="w-full bg-surface-container/30 border border-outline/30 rounded-xl p-3 text-on-surface outline-none text-xs focus:ring-2 focus:ring-primary/40 font-body">
                            </div>
                            <div>
                                <label class="font-label text-[10px] font-bold uppercase tracking-wider text-on-surface-variant block mb-1.5">Carbs (g)</label>
                                <input type="number" id="reviewMealCarb" value="52" class="w-full bg-surface-container/30 border border-outline/30 rounded-xl p-3 text-on-surface outline-none text-xs focus:ring-2 focus:ring-primary/40 font-body">
                            </div>
                            <div>
                                <label class="font-label text-[10px] font-bold uppercase tracking-wider text-on-surface-variant block mb-1.5">Fats (g)</label>
                                <input type="number" id="reviewMealFat" value="12" class="w-full bg-surface-container/30 border border-outline/30 rounded-xl p-3 text-on-surface outline-none text-xs focus:ring-2 focus:ring-primary/40 font-body">
                            </div>
                        </div>
                    </div>
                `;
            } else if (type === 'form') {
                title.innerHTML = '<span class="material-symbols-outlined text-primary text-2xl">sports_gymnastics</span> Verify AI Form Mechanics';
                body.innerHTML = `
                    <div class="space-y-4">
                        <div class="p-4 bg-surface-container rounded-2xl border border-outline/10 text-xs space-y-1">
                            <p class="font-bold text-on-surface text-sm">New Variation Detected: Plank</p>
                            <p class="text-on-surface-variant leading-relaxed mt-1">The local computer vision module captured "forearm plank with alternative leg raises" for the exercise Plank.</p>
                        </div>
                        <div>
                            <label class="font-label text-[10px] font-bold uppercase tracking-wider text-on-surface-variant block mb-1.5">Biomechanics Variation Label</label>
                            <input type="text" id="reviewFormLabel" value="Plank (with Alternating Leg Lifts)" class="w-full bg-surface-container/30 border border-outline/30 rounded-xl p-3.5 text-on-surface outline-none text-xs focus:ring-2 focus:ring-primary/40 font-body">
                        </div>
                        <div>
                            <label class="font-label text-[10px] font-bold uppercase tracking-wider text-on-surface-variant block mb-1.5">Validation Action</label>
                            <select id="reviewFormAction" class="w-full bg-surface-container/30 border border-outline/30 rounded-xl p-3.5 text-on-surface outline-none text-xs focus:ring-2 focus:ring-primary/40 font-body">
                                <option value="merge">Approve as Variation Keywords</option>
                                <option value="new">Create as New Separate Exercise</option>
                            </select>
                        </div>
                    </div>
                `;
            }
            document.getElementById('reviewModal').classList.remove('hidden');
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').classList.add('hidden');
        }

        function approveReview() {
            closeReviewModal();
            const type = activeApprovalType;
            
            // Dynamic insertion/toast simulation
            const successToast = document.createElement('div');
            successToast.className = "fixed bottom-6 right-6 bg-inverse-surface text-inverse-on-surface px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 z-[200] transition-all duration-300 translate-y-20 opacity-0 border border-primary/20";
            successToast.innerHTML = `
                <span class="material-symbols-outlined text-primary">check_circle</span>
                <div class="text-xs">
                    <p class="font-bold">Content Synced Successfully</p>
                    <p class="text-[10px] text-on-surface-variant/80">Approved and successfully deployed to database matrix.</p>
                </div>
            `;
            document.body.appendChild(successToast);
            
            // Animate toast in
            setTimeout(() => {
                successToast.classList.remove('translate-y-20', 'opacity-0');
            }, 50);
            
            // Animate toast out
            setTimeout(() => {
                successToast.classList.add('translate-y-20', 'opacity-0');
                setTimeout(() => successToast.remove(), 300);
            }, 3500);

            // Fade out and remove the sidebar item
            deleteApproval(type);
        }

        function deleteApproval(type) {
            const card = document.getElementById('approval-item-' + type);
            if (card) {
                card.classList.add('opacity-0', 'scale-95', 'translate-x-12');
                setTimeout(() => {
                    card.remove();
                }, 350);
            }
        }

        function viewAllQueue() {
            alert('AI Verification Queue is fully synced and up-to-date!');
        }

        function viewMealDetails(btn) {
            const date = btn.getAttribute('data-date');
            const user = btn.getAttribute('data-user');
            const email = btn.getAttribute('data-email');
            const meal = btn.getAttribute('data-meal');
            const type = btn.getAttribute('data-type');
            const calories = parseInt(btn.getAttribute('data-calories')) || 0;
            const protein = parseFloat(btn.getAttribute('data-protein')) || 0;
            const carbs = parseFloat(btn.getAttribute('data-carbs')) || 0;
            const fats = parseFloat(btn.getAttribute('data-fats')) || 0;

            // Set text details
            document.getElementById('mdDate').textContent = date;
            document.getElementById('mdUserName').textContent = user;
            document.getElementById('mdUserEmail').textContent = email;
            document.getElementById('mdMealName').textContent = meal;
            document.getElementById('mdCalories').textContent = calories.toLocaleString();
            document.getElementById('mdProtein').textContent = protein.toFixed(1) + 'g';
            document.getElementById('mdCarbs').textContent = carbs.toFixed(1) + 'g';
            document.getElementById('mdFats').textContent = fats.toFixed(1) + 'g';

            // User initials
            const initials = user.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) || 'U';
            document.getElementById('mdUserInitials').textContent = initials;

            // Meal Type badge styling
            const typeEl = document.getElementById('mdMealType');
            typeEl.textContent = type;
            const typeColors = {
                breakfast: 'bg-amber-100 text-amber-700',
                lunch: 'bg-green-100 text-green-700',
                dinner: 'bg-blue-100 text-blue-700',
                snack: 'bg-purple-100 text-purple-700'
            };
            typeEl.className = `inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase mb-2 ${typeColors[type] || 'bg-surface-container text-on-surface-variant'}`;

            // Calculate percentage split
            const totalGrams = protein + carbs + fats;
            let protPct = 0, carbPct = 0, fatPct = 0;
            if (totalGrams > 0) {
                protPct = Math.round((protein / totalGrams) * 100);
                carbPct = Math.round((carbs / totalGrams) * 100);
                fatPct = 100 - protPct - carbPct; // Ensure it adds up to 100
                if (fatPct < 0) fatPct = 0;
            }

            document.getElementById('mdProteinPct').textContent = protPct;
            document.getElementById('mdCarbsPct').textContent = carbPct;
            document.getElementById('mdFatsPct').textContent = fatPct;

            document.getElementById('mdProteinBar').style.width = protPct + '%';
            document.getElementById('mdCarbsBar').style.width = carbPct + '%';
            document.getElementById('mdFatsBar').style.width = fatPct + '%';

            // Open Modal
            document.getElementById('mealDetailsModal').classList.remove('hidden');
        }

        function closeMealDetailsModal() {
            document.getElementById('mealDetailsModal').classList.add('hidden');
        }
    </script>

    <!-- Edit Exercise Modal Component -->
    <div id="editModal" class="fixed inset-0 bg-on-background/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
        <div class="bg-surface-bright w-full max-w-xl p-8 rounded-[2.5rem] shadow-2xl border border-outline/10 relative">
            <button onclick="closeEditModal()" class="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
            <h3 class="font-display text-2xl font-extrabold text-on-surface mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-2xl">edit_note</span>
                Edit Exercise Details
            </h3>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editExId">
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">Exercise Name</label>
                    <input type="text" name="name" id="editExName" required class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body">
                </div>
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">Keywords (comma separated)</label>
                    <input type="text" name="keywords" id="editExKeywords" required placeholder="squat, back squat, legs" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body placeholder:text-on-surface-variant/40">
                </div>
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">YouTube Video URL</label>
                    <input type="url" name="video_url" id="editExUrl" required placeholder="https://www.youtube.com/watch?v=..." class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body placeholder:text-on-surface-variant/40">
                </div>
                <div>
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-2">Category</label>
                    <select name="category" id="editExCategory" class="w-full bg-surface-container/50 border border-outline/30 rounded-2xl p-4 text-on-surface outline-none focus:ring-2 focus:ring-primary/50 text-sm font-body">
                        <option value="strength">Strength</option>
                        <option value="cardio">Cardio</option>
                        <option value="core">Core</option>
                        <option value="recovery">Recovery</option>
                        <option value="full_body">Full Body</option>
                    </select>
                </div>
                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-surface-container text-on-surface font-bold py-4 rounded-2xl text-sm hover:bg-surface-container-high transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 bg-primary text-on-primary font-bold py-4 rounded-2xl text-sm hover:bg-primary-fixed transition-colors shadow-lg shadow-primary/20 uppercase tracking-widest">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- AI Approvals Verification Modal Component -->
    <div id="reviewModal" class="fixed inset-0 bg-on-background/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
        <div class="bg-surface-bright w-full max-w-lg p-8 rounded-[2.5rem] shadow-2xl border border-outline/10 relative">
            <button onclick="closeReviewModal()" class="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
            <h3 id="reviewModalTitle" class="font-display text-xl font-extrabold text-on-surface mb-6 flex items-center gap-2">
                <!-- Dynamic Title -->
            </h3>
            <div id="reviewModalBody" class="mb-6">
                <!-- Dynamic Content -->
            </div>
            <div class="flex gap-4 pt-4 border-t border-outline-variant/30">
                <button type="button" onclick="closeReviewModal()" class="flex-1 bg-surface-container text-on-surface font-bold py-3.5 rounded-2xl text-xs hover:bg-surface-container-high transition-colors uppercase tracking-widest">Reject</button>
                <button type="button" onclick="approveReview()" class="flex-1 bg-primary text-on-primary font-bold py-3.5 rounded-2xl text-xs hover:bg-primary-fixed transition-colors shadow-lg shadow-primary/20 uppercase tracking-widest">Approve & Sync</button>
            </div>
        </div>
    </div>

    <!-- Meal Details Modal Component -->
    <div id="mealDetailsModal" class="fixed inset-0 bg-on-background/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
        <div class="bg-surface-bright w-full max-w-lg p-8 rounded-[2.5rem] shadow-2xl border border-outline/10 relative">
            <button onclick="closeMealDetailsModal()" class="absolute top-6 right-6 text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
            
            <h3 class="font-display text-2xl font-extrabold text-on-surface mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-2xl">restaurant_menu</span>
                Logged Meal Details
            </h3>
            
            <div class="space-y-6">
                <!-- User Profile & Date -->
                <div class="flex items-center justify-between pb-4 border-b border-outline/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                            <span id="mdUserInitials" class="text-sm font-bold text-primary"></span>
                        </div>
                        <div>
                            <p id="mdUserName" class="font-bold text-on-surface text-sm"></p>
                            <p id="mdUserEmail" class="text-xs text-on-surface-variant"></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Logged Date</p>
                        <p id="mdDate" class="text-xs text-on-surface font-semibold"></p>
                    </div>
                </div>

                <!-- Meal Title and Type -->
                <div>
                    <span id="mdMealType" class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase mb-2"></span>
                    <h4 id="mdMealName" class="font-headline text-xl font-bold text-on-surface leading-tight"></h4>
                </div>

                <!-- Macronutrient Grid -->
                <div class="grid grid-cols-4 gap-4 p-4 bg-surface-container rounded-2xl border border-outline/5 text-center">
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Calories</p>
                        <p class="text-lg font-extrabold text-on-surface" id="mdCalories"></p>
                        <p class="text-[9px] text-on-surface-variant">kcal</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Protein</p>
                        <p class="text-lg font-extrabold text-emerald-500" id="mdProtein"></p>
                        <p class="text-[9px] text-on-surface-variant">grams</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Carbs</p>
                        <p class="text-lg font-extrabold text-amber-500" id="mdCarbs"></p>
                        <p class="text-[9px] text-on-surface-variant">grams</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Fats</p>
                        <p class="text-lg font-extrabold text-red-400" id="mdFats"></p>
                        <p class="text-[9px] text-on-surface-variant">grams</p>
                    </div>
                </div>

                <!-- Macro Progress Visualization -->
                <div class="space-y-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Macronutrient Split</p>
                    <div class="w-full bg-surface-container h-4 rounded-full overflow-hidden flex">
                        <div id="mdProteinBar" class="bg-emerald-500 h-full transition-all duration-500"></div>
                        <div id="mdCarbsBar" class="bg-amber-500 h-full transition-all duration-500"></div>
                        <div id="mdFatsBar" class="bg-red-400 h-full transition-all duration-500"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-on-surface-variant">
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Protein (<span id="mdProteinPct">0</span>%)</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Carbs (<span id="mdCarbsPct">0</span>%)</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-400"></span> Fats (<span id="mdFatsPct">0</span>%)</span>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-outline/10 flex justify-end">
                <button type="button" onclick="closeMealDetailsModal()" 
                        class="bg-surface-container text-on-surface font-bold px-6 py-3 rounded-xl text-xs hover:bg-surface-container-high transition-colors uppercase tracking-wider">
                    Close
                </button>
            </div>
        </div>
    </div>
</body>
</html>
