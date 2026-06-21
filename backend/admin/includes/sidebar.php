<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to check if a link is active
if (!function_exists('isActive')) {
    function isActive($pageName, $current) {
        return ($pageName === $current)
            ? 'bg-primary-container text-on-primary-container font-bold italic'
            : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface';
    }
}

if (!function_exists('activeIcon')) {
    function activeIcon($pageName, $current) {
        return ($pageName === $current) ? "style=\"font-variation-settings: 'FILL' 1;\"" : "";
    }
}

// Stats for the sidebar monitor (Direct DB query instead of slow and fragile HTTP loopback)
$aiGenerationsToday = 0;
try {
    if (isset($pdo)) {
        $aiGenerationsToday = (int)$pdo->query("SELECT COUNT(*) FROM workout_plans WHERE plan_date = CURDATE()")->fetchColumn();
    }
} catch (PDOException $e) {
    $aiGenerationsToday = 0;
}
$stats = ['ai_generations' => $aiGenerationsToday];
?>
<!-- SideNavBar Component -->
<nav id="adminSidebar" class="flex flex-col h-screen w-64 fixed left-0 top-0 bg-surface shadow-2xl p-4 z-50 border-r border-outline/20 transition-transform duration-300 -translate-x-full md:translate-x-0">
    <div class="mb-10 mt-4 px-4">
        <h1 class="font-display text-2xl font-extrabold tracking-tighter text-on-surface">Fitrova<span class="text-primary">.</span></h1>
        <p class="font-body text-xs font-bold uppercase tracking-widest text-on-surface-variant mt-1">Admin Nexus</p>
    </div>
    
    <div class="flex-1 space-y-2 font-body text-sm font-medium tracking-tight">
        <!-- Dashboard -->
        <a class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 active:scale-95 <?php echo isActive('index.php', $current_page); ?>" href="index.php">
            <span class="material-symbols-outlined" <?php echo activeIcon('index.php', $current_page); ?>>dashboard</span>
            Dashboard
        </a>
        
        <!-- Users -->
        <a class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 active:scale-95 <?php echo isActive('users.php', $current_page); ?>" href="users.php">
            <span class="material-symbols-outlined" <?php echo activeIcon('users.php', $current_page); ?>>group</span>
            Users
        </a>
        
        <!-- Content Control (Exercise Matrix) -->
        <a class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 active:scale-95 <?php echo isActive('exercise_manager.php', $current_page); ?>" href="exercise_manager.php">
            <span class="material-symbols-outlined" <?php echo activeIcon('exercise_manager.php', $current_page); ?>>movie</span>
            Content
        </a>
        
        <!-- Analytics & Settings (AI Control) -->
        <a class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 active:scale-95 <?php echo isActive('ai_control.php', $current_page); ?>" href="ai_control.php">
            <span class="material-symbols-outlined" <?php echo activeIcon('ai_control.php', $current_page); ?>>insights</span>
            Analytics
        </a>
        
        <!-- App Experience -->
        <a class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 active:scale-95 <?php echo isActive('app_experience.php', $current_page); ?>" href="app_experience.php">
            <span class="material-symbols-outlined" <?php echo activeIcon('app_experience.php', $current_page); ?>>phone_iphone</span>
            App Experience
        </a>
        
        <!-- Logout -->
        <a class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 active:scale-95 text-red-500 hover:bg-red-500/10 hover:text-red-600" href="logout.php">
            <span class="material-symbols-outlined">logout</span>
            Logout
        </a>
    </div>
    
    <div class="mt-auto pt-4 space-y-3">
        <!-- AI Heartbeat Status Card -->
        <div class="p-4 bg-primary/10 border border-primary/20 rounded-2xl">
            <p class="text-[10px] font-bold text-primary mb-1 uppercase tracking-widest">AI Heartbeat</p>
            <div class="flex items-center space-x-2">
                <div class="w-2.5 h-2.5 <?php echo ($stats['ai_generations'] > 0) ? 'bg-primary animate-pulse neon-glow' : 'bg-slate-400'; ?> rounded-full"></div>
                <p class="text-sm text-on-surface font-bold"><?php echo $stats['ai_generations']; ?> Today</p>
            </div>
        </div>
        
        <!-- Export Action -->
        <button onclick="globalExportData()" class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-surface-container-high text-on-surface font-bold hover:bg-primary hover:text-on-primary transition-all duration-300 active:scale-95 border border-primary/20 text-xs">
            <span class="material-symbols-outlined text-[18px]">download</span>
            Export Data
        </button>
    </div>
</nav>

<script>
function globalExportData() {
    // 1. Check if we have an active data table on the page
    const table = document.querySelector('table');
    if (table) {
        let csv = [];
        const rows = table.querySelectorAll('tr');
        for (let i = 0; i < rows.length; i++) {
            // Ignore hidden rows
            if (rows[i].classList.contains('hidden')) continue;
            
            const cols = rows[i].querySelectorAll('td, th');
            let row = [];
            for (let j = 0; j < cols.length - 1; j++) { // Ignore last action column
                // Get clean text content, escape double quotes
                let text = cols[j].textContent.replace(/\s+/g, ' ').trim();
                text = text.replace(/"/g, '""');
                row.push('"' + text + '"');
            }
            csv.push(row.join(','));
        }
        
        // Trigger download
        const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "fitrova_export_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } else {
        // Fallback for pages without tables (like index.php or ai_control.php)
        let summaryText = "Fitrova Admin Summary Report\n";
        summaryText += "Export Date: " + new Date().toLocaleDateString() + "\n";
        summaryText += "========================================\n\n";
        
        // Collect metric values if they exist
        const totalAthletesEl = document.getElementById('total-athletes');
        const activeTodayEl = document.getElementById('active-today');
        const aiSessionsEl = document.getElementById('ai-sessions');
        const dietAlertsEl = document.getElementById('diet-alerts');
        
        if (totalAthletesEl) summaryText += "Total Athletes: " + totalAthletesEl.textContent.trim() + "\n";
        if (activeTodayEl) summaryText += "Active Athletes Today: " + activeTodayEl.textContent.trim() + "\n";
        if (aiSessionsEl) summaryText += "AI Sessions Today: " + aiSessionsEl.textContent.trim() + "\n";
        if (dietAlertsEl) summaryText += "Diet Alerts Logged: " + dietAlertsEl.textContent.trim() + "\n";
        
        // General text extraction fallback
        summaryText += "\nSystem Core Parameters:\n";
        const textareas = document.querySelectorAll('textarea');
        textareas.forEach(ta => {
            const label = ta.previousElementSibling ? ta.previousElementSibling.textContent.trim() : 'Setting';
            summaryText += `${label}:\n${ta.value}\n---\n`;
        });
        
        const blob = new Blob([summaryText], { type: 'text/plain' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "fitrova_summary_" + new Date().toISOString().slice(0,10) + ".txt";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
</script>

<!-- Mobile Sidebar Backdrop Overlay -->
<div id="sidebarBackdrop" onclick="toggleMobileSidebar(event)" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden md:hidden"></div>

<script>
function toggleMobileSidebar(event) {
    if (event) event.stopPropagation();
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        backdrop.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        backdrop.classList.add('hidden');
    }
}
</script>
