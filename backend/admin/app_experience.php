<?php
// backend/admin/app_experience.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db_config.php';

$success_message = "";
$error_message = "";

// ═══════════════════════════════════════════════════════════════
// POST ACTION HANDLERS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. Save Update Announcement Settings & Onboarding Toggle
    if ($action === 'save_general_settings') {
        try {
            $version = trim($_POST['version'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $force_update = isset($_POST['force_update']) ? 1 : 0;
            $onboarding_enabled = isset($_POST['onboarding_enabled']) ? 'true' : 'false';

            // 1. Update onboarding global setting
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'onboarding_enabled'");
            $stmt->execute([$onboarding_enabled]);

            // 2. Check if an app_updates record already exists
            $checkStmt = $pdo->query("SELECT id FROM app_updates ORDER BY id DESC LIMIT 1");
            $latestUpdate = $checkStmt->fetch();

            if ($latestUpdate) {
                // Update existing record
                $updateStmt = $pdo->prepare("
                    UPDATE app_updates 
                    SET version = ?, message = ?, is_active = ?, force_update = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$version, $message, $is_active, $force_update, $latestUpdate['id']]);
            } else {
                // Insert new record
                $insertStmt = $pdo->prepare("
                    INSERT INTO app_updates (version, message, is_active, force_update) 
                    VALUES (?, ?, ?, ?)
                ");
                $insertStmt->execute([$version, $message, $is_active, $force_update]);
            }
            $success_message = "General App settings deployed successfully.";
        } catch (Exception $e) {
            $error_message = "Error saving settings: " . $e->getMessage();
        }
    }

    // B. Add New Onboarding Slide
    elseif ($action === 'add_slide') {
        try {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($title) || empty($description)) {
                throw new Exception("Title and Description cannot be empty.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO onboarding_slides (title, description, sort_order, is_active) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$title, $description, $sort_order, $is_active]);
            $success_message = "New onboarding slide added successfully.";
        } catch (Exception $e) {
            $error_message = "Error adding slide: " . $e->getMessage();
        }
    }

    // C. Edit Existing Onboarding Slide
    elseif ($action === 'edit_slide') {
        try {
            $slide_id = intval($_POST['slide_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($title) || empty($description) || !$slide_id) {
                throw new Exception("Slide ID, Title, and Description cannot be empty.");
            }

            $stmt = $pdo->prepare("
                UPDATE onboarding_slides 
                SET title = ?, description = ?, sort_order = ?, is_active = ? 
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $sort_order, $is_active, $slide_id]);
            $success_message = "Onboarding slide updated successfully.";
        } catch (Exception $e) {
            $error_message = "Error updating slide: " . $e->getMessage();
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// GET ACTION HANDLERS
// ═══════════════════════════════════════════════════════════════
if (isset($_GET['action']) && $_GET['action'] === 'delete_slide') {
    try {
        $slide_id = intval($_GET['id'] ?? 0);
        if ($slide_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM onboarding_slides WHERE id = ?");
            $stmt->execute([$slide_id]);
            $success_message = "Onboarding slide deleted successfully.";
        }
    } catch (Exception $e) {
        $error_message = "Error deleting slide: " . $e->getMessage();
    }
}

// ═══════════════════════════════════════════════════════════════
// FETCH RENDER DATA
// ═══════════════════════════════════════════════════════════════
// 1. Fetch latest update status
$updateStmt = $pdo->query("SELECT * FROM app_updates ORDER BY id DESC LIMIT 1");
$appUpdate = $updateStmt ? $updateStmt->fetch() : null;
if (!$appUpdate) {
    $appUpdate = [
        'version'      => '1.0.0',
        'message'      => 'A new update is here. Update now to enjoy the latest improvements.',
        'is_active'    => 0,
        'force_update' => 0
    ];
}

// 2. Fetch onboarding global status
$onboardingSettingStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'onboarding_enabled' LIMIT 1");
$onboardingSetting = $onboardingSettingStmt ? $onboardingSettingStmt->fetchColumn() : 'true';
$onboardingEnabled = ($onboardingSetting === 'true');

// 3. Fetch onboarding slides
$slidesStmt = $pdo->query("SELECT * FROM onboarding_slides ORDER BY sort_order ASC, id ASC");
$slides = $slidesStmt ? $slidesStmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link rel="icon" type="image/png" href="favicon.png" />
    <title>Fitrova Admin - App Experience</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800;900&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "error": "#ef4444",
                        "surface": "#070a12",
                        "outline": "#1e293b",
                        "primary": "#13ec13",
                        "background": "#070a12",
                        "on-surface": "#ffffff",
                        "surface-bright": "#0d1321",
                        "on-surface-variant": "#94a3b8",
                        "primary-container": "rgba(19, 236, 19, 0.1)",
                        "on-primary-container": "#13ec13"
                    },
                    borderRadius: {
                        "DEFAULT": "1rem",
                        "lg": "2rem",
                        "xl": "3rem"
                    },
                    fontFamily: {
                        "headline": ["Manrope"],
                        "body": ["Manrope"]
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .neon-glow { filter: drop-shadow(0 0 8px rgba(19, 236, 19, 0.4)); }
        }
    </style>
</head>

<body class="bg-surface text-on-surface min-h-screen flex overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="ml-64 flex-1 flex flex-col relative min-h-screen">
        <!-- Header -->
        <?php
        $headerSearchPlaceholder = "Search page controls...";
        $headerSearchInputId = "searchExperienceInput";
        include __DIR__ . '/includes/header.php';
        ?>

        <!-- Canvas -->
        <div class="p-8 pt-20 pb-24 flex-1 mt-4">
            <!-- Title -->
            <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
                <div>
                    <h2 class="font-display text-2xl font-extrabold tracking-tight text-on-surface mb-1">App Experience</h2>
                    <p class="font-body text-sm text-on-surface-variant">Manage application update schedules and first-time onboarding tutorials.</p>
                </div>
                
                <!-- Feedback banners -->
                <?php if (!empty($success_message)): ?>
                    <div class="bg-primary/20 text-on-primary-container px-6 py-2.5 rounded-xl border border-primary/30 font-bold text-xs flex items-center gap-2 shadow-lg">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="bg-error/20 text-error px-6 py-2.5 rounded-xl border border-error/30 font-bold text-xs flex items-center gap-2 shadow-lg">
                        <span class="material-symbols-outlined text-sm">error</span>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bento Controls Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Column 1: General Settings & Update Modal Control -->
                <div class="lg:col-span-1 space-y-6">
                    <form action="" method="POST" class="bg-surface-bright rounded-2xl p-6 border border-outline/20 shadow-xl space-y-6">
                        <input type="hidden" name="action" value="save_general_settings">
                        
                        <h3 class="font-headline text-md font-bold text-on-surface border-b border-outline/20 pb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">system_update_alt</span>
                            Update Announcement Settings
                        </h3>

                        <!-- Version Field -->
                        <div class="space-y-1">
                            <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block">Latest App Version</label>
                            <input type="text" name="version" required placeholder="e.g. 1.2.0" value="<?php echo htmlspecialchars($appUpdate['version']); ?>"
                                class="w-full bg-surface/50 border border-outline/30 rounded-xl p-3 text-on-surface text-sm focus:ring-1 focus:ring-primary outline-none transition-all">
                        </div>

                        <!-- Update Message Field -->
                        <div class="space-y-1">
                            <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block">Update Message Details</label>
                            <textarea name="message" rows="4" placeholder="Brief announcement text..." 
                                class="w-full bg-surface/50 border border-outline/30 rounded-xl p-3 text-on-surface text-sm focus:ring-1 focus:ring-primary outline-none transition-all"><?php echo htmlspecialchars($appUpdate['message']); ?></textarea>
                        </div>

                        <!-- Checkbox Controls -->
                        <div class="space-y-3 pt-2">
                            <!-- Toggle Active Status -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold text-on-surface">Activate Announcement</p>
                                    <p class="text-[10px] text-on-surface-variant">Shows the update popup to users.</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" <?php echo $appUpdate['is_active'] ? 'checked' : ''; ?> class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                </label>
                            </div>

                            <!-- Toggle Force Update -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold text-on-surface">Enable Force Update</p>
                                    <p class="text-[10px] text-on-surface-variant">Locks user out of app until updated.</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="force_update" value="1" <?php echo $appUpdate['force_update'] ? 'checked' : ''; ?> class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                </label>
                            </div>

                            <!-- Global Onboarding Switch -->
                            <div class="flex items-center justify-between border-t border-outline/10 pt-4 mt-2">
                                <div>
                                    <p class="text-xs font-bold text-on-surface">Enable Onboarding system</p>
                                    <p class="text-[10px] text-on-surface-variant">Toggles onboarding slideshow for new users.</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="onboarding_enabled" value="1" <?php echo $onboardingEnabled ? 'checked' : ''; ?> class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Deploy Button -->
                        <button type="submit" class="w-full bg-primary text-surface font-extrabold py-3.5 rounded-xl uppercase tracking-wider text-xs shadow-md shadow-primary/10 hover:bg-green-500 active:scale-95 duration-150 transition-all">
                            Save Global Configuration
                        </button>
                    </form>
                </div>

                <!-- Column 2 & 3: Onboarding Slides CRUD -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Table Grid Card -->
                    <div class="bg-surface-bright rounded-2xl border border-outline/20 shadow-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-outline/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <h3 class="font-headline text-md font-bold text-on-surface flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary">view_carousel</span>
                                    Onboarding Swipe Slides
                                </h3>
                                <p class="text-xs text-on-surface-variant mt-0.5">Slides show up in exact order of their Sort Order value.</p>
                            </div>
                            <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 bg-primary text-surface font-extrabold px-4 py-2 rounded-xl text-xs uppercase tracking-wider hover:bg-green-500 transition-all duration-150 active:scale-95 shadow-sm">
                                <span class="material-symbols-outlined text-sm font-bold">add</span>
                                Add Slide
                            </button>
                        </div>

                        <!-- Slides List -->
                        <div class="p-6">
                            <?php if (empty($slides)): ?>
                                <div class="text-center py-10 border-2 border-dashed border-outline/10 rounded-xl">
                                    <span class="material-symbols-outlined text-4xl text-on-surface-variant opacity-30 mb-2">view_carousel</span>
                                    <p class="text-sm text-on-surface-variant">No onboarding slides defined yet.</p>
                                    <p class="text-[10px] text-on-surface-variant/70 mt-0.5">Create your first slide to guide your athletes.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-outline/20 text-on-surface-variant text-[10px] font-bold uppercase tracking-wider">
                                                <th class="py-3 px-2 w-12 text-center">Order</th>
                                                <th class="py-3 px-3">Title</th>
                                                <th class="py-3 px-3">Description</th>
                                                <th class="py-3 px-3 w-20 text-center">Status</th>
                                                <th class="py-3 px-3 w-28 text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-outline/10 text-sm">
                                            <?php foreach ($slides as $slide): ?>
                                                <tr class="hover:bg-surface-container/20">
                                                    <td class="py-4 px-2 text-center font-bold text-primary">
                                                        <?php echo $slide['sort_order']; ?>
                                                    </td>
                                                    <td class="py-4 px-3 font-semibold text-on-surface">
                                                        <?php echo htmlspecialchars($slide['title']); ?>
                                                    </td>
                                                    <td class="py-4 px-3 text-on-surface-variant max-w-xs truncate">
                                                        <?php echo htmlspecialchars($slide['description']); ?>
                                                    </td>
                                                    <td class="py-4 px-3 text-center">
                                                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase <?php echo $slide['is_active'] ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-slate-800 text-slate-400 border border-slate-700/50'; ?>">
                                                            <?php echo $slide['is_active'] ? 'Active' : 'Disabled'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-4 px-3 text-center">
                                                        <div class="inline-flex gap-2">
                                                            <!-- Edit Button -->
                                                            <button 
                                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($slide)); ?>)" 
                                                                class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-on-surface-variant hover:text-white transition-colors"
                                                                title="Edit Slide"
                                                            >
                                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                            </button>
                                                            <!-- Delete Button -->
                                                            <a 
                                                                href="?action=delete_slide&id=<?php echo $slide['id']; ?>" 
                                                                onclick="return confirm('Are you sure you want to delete this onboarding slide?')"
                                                                class="p-1.5 rounded-lg bg-red-950/30 hover:bg-red-900/40 text-error transition-colors"
                                                                title="Delete Slide"
                                                            >
                                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Modal backdrop -->
    <div id="slideModal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <!-- Modal body -->
        <div class="bg-surface-bright border border-outline/25 rounded-3xl w-full max-w-md p-6 shadow-2xl relative">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-on-surface-variant hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
            
            <h3 id="modalTitle" class="font-headline text-lg font-bold text-on-surface border-b border-outline/20 pb-3 mb-4">Add Slide</h3>
            
            <form id="slideForm" action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="add_slide">
                <input type="hidden" name="slide_id" id="slideId" value="">

                <!-- Slide Title -->
                <div class="space-y-1">
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block">Slide Title</label>
                    <input type="text" name="title" id="slideTitleInput" required placeholder="Welcome to Fitrova..."
                        class="w-full bg-surface/50 border border-outline/30 rounded-xl p-3 text-on-surface text-sm focus:ring-1 focus:ring-primary outline-none transition-all">
                </div>

                <!-- Slide Description -->
                <div class="space-y-1">
                    <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block">Slide Description</label>
                    <textarea name="description" id="slideDescInput" required rows="3" placeholder="Brief tagline explanation..." 
                        class="w-full bg-surface/50 border border-outline/30 rounded-xl p-3 text-on-surface text-sm focus:ring-1 focus:ring-primary outline-none transition-all"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Sort Order -->
                    <div class="space-y-1">
                        <label class="font-label text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block">Sort Order</label>
                        <input type="number" name="sort_order" id="slideSortInput" required value="0" min="0"
                            class="w-full bg-surface/50 border border-outline/30 rounded-xl p-3 text-on-surface text-sm focus:ring-1 focus:ring-primary outline-none transition-all">
                    </div>

                    <!-- Slide Active Toggle -->
                    <div class="flex flex-col justify-end pb-3">
                        <div class="flex items-center justify-between bg-surface/50 border border-outline/30 rounded-xl px-3 py-2.5">
                            <span class="text-xs font-semibold text-on-surface-variant">Active</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" id="slideActiveInput" value="1" checked class="sr-only peer">
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full bg-primary text-surface font-extrabold py-3.5 rounded-xl uppercase tracking-wider text-xs shadow-md shadow-primary/10 hover:bg-green-500 active:scale-95 duration-150 transition-all mt-2">
                    Submit Onboarding Slide
                </button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('slideModal');
        const formAction = document.getElementById('formAction');
        const slideId = document.getElementById('slideId');
        const modalTitle = document.getElementById('modalTitle');
        const slideTitleInput = document.getElementById('slideTitleInput');
        const slideDescInput = document.getElementById('slideDescInput');
        const slideSortInput = document.getElementById('slideSortInput');
        const slideActiveInput = document.getElementById('slideActiveInput');

        function openAddModal() {
            modalTitle.textContent = "Add Onboarding Slide";
            formAction.value = "add_slide";
            slideId.value = "";
            slideTitleInput.value = "";
            slideDescInput.value = "";
            slideSortInput.value = "0";
            slideActiveInput.checked = true;
            modal.classList.remove('hidden');
        }

        function openEditModal(slide) {
            modalTitle.textContent = "Edit Onboarding Slide";
            formAction.value = "edit_slide";
            slideId.value = slide.id;
            slideTitleInput.value = slide.title;
            slideDescInput.value = slide.description;
            slideSortInput.value = slide.sort_order;
            slideActiveInput.checked = (slide.is_active == 1);
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }
    </script>
</body>

</html>
