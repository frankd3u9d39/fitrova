<?php
// backend/admin/includes/header.php

// Query system notifications for the bell icon
$notifications = [];
try {
    if (isset($pdo)) {
        // Fetch latest 2 successful payments
        $latestPayments = $pdo->query("SELECT reference, subscription_tier, created_at FROM payment_transactions WHERE status = 'success' ORDER BY created_at DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($latestPayments as $p) {
            $notifications[] = [
                'type' => 'payment',
                'title' => 'New Upgrade Success',
                'desc' => 'Ref ' . substr($p['reference'], 0, 10) . '... bought ' . ($p['subscription_tier'] === 'advanced_premium' ? 'Advanced' : 'Premium AI'),
                'time' => date('H:i', strtotime($p['created_at']))
            ];
        }

        // Fetch latest form check log
        $latestForm = $pdo->query("SELECT exercise_name, score, created_at FROM form_check_logs ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($latestForm) {
            $notifications[] = [
                'type' => 'scan',
                'title' => 'AI Biomechanics Scan',
                'desc' => $latestForm['exercise_name'] . ' scanned with score: ' . $latestForm['score'] . '%',
                'time' => date('H:i', strtotime($latestForm['created_at']))
            ];
        }
    }
} catch (PDOException $e) {
    // Graceful fallback
}

if (empty($notifications)) {
    $notifications[] = [
        'type' => 'system',
        'title' => 'System Initialized',
        'desc' => 'Admin Nexus core interface activated successfully.',
        'time' => '12:00'
    ];
}

// Fallback profile picture if none exists
$adminPhoto = ($adminUser && !empty($adminUser['profile_picture'])) 
    ? $adminUser['profile_picture'] 
    : 'https://lh3.googleusercontent.com/aida-public/AB6AXuBTAuJFaXpSIu4rY3b_5gxkaAT61pW8S-Kyovo6HcsMbnEIG47n1bT01w-6ij_o6WRNMU7oiDnPbuczKUDzHZurd4iKzlqkq28ImZK1fKLVDuF9dUyHMCqRYvN3vlcZg9KO9yermPLC-BhxluGcYkKEAT5BrNpa8rFPNuDaCjjhI3qvIyw6B2EnKYAHxnm5FuPxhponBfnDnGfqPkkFHJexelUaN5HHdyqimigN3uxZyVaA5K1wueB_Mm3yCR_3K0AA3P2DnC5LWA';

$adminFullName = ($adminUser) 
    ? htmlspecialchars($adminUser['first_name'] . ' ' . $adminUser['last_name']) 
    : 'Ibrahim Admin';
?>

<!-- TopNavBar Component -->
<header class="fixed top-0 right-0 w-[calc(100%-16rem)] z-40 bg-surface/80 backdrop-blur-xl flex justify-between items-center h-16 px-8 border-b border-outline/20">
    <!-- Search Bar Section -->
    <div class="flex-1">
        <?php if (isset($headerSearchPlaceholder)): ?>
            <div class="flex items-center gap-4 focus-within:ring-2 focus-within:ring-primary/50 rounded-full bg-surface-variant/50 px-4 py-2 w-96 transition-all">
                <span class="material-symbols-outlined text-on-surface-variant text-sm">search</span>
                <input 
                    id="<?php echo htmlspecialchars($headerSearchInputId ?? 'searchInput'); ?>" 
                    oninput="<?php echo htmlspecialchars($headerSearchOnInput ?? ''); ?>" 
                    class="bg-transparent border-none outline-none text-sm font-body text-on-surface w-full placeholder:text-on-surface-variant/50 focus:ring-0 p-0" 
                    placeholder="<?php echo htmlspecialchars($headerSearchPlaceholder); ?>" 
                    type="text"
                    value="<?php echo htmlspecialchars($headerSearchValue ?? ''); ?>"
                />
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Controls Section -->
    <div class="flex items-center gap-6 relative">
        <!-- Notification Bell Dropdown Button -->
        <div class="relative">
            <button onclick="toggleBellDropdown(event)" class="text-on-surface-variant hover:text-primary transition-colors relative flex items-center p-2 rounded-full hover:bg-surface-container/50">
                <span class="material-symbols-outlined">notifications</span>
                <?php if (!empty($notifications)): ?>
                    <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-primary rounded-full border border-surface neon-glow"></span>
                <?php endif; ?>
            </button>

            <!-- Bell Dropdown Panel -->
            <div id="bell-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-surface-bright rounded-2xl shadow-2xl border border-outline/10 p-4 z-50 text-left">
                <h4 class="font-display font-extrabold text-sm text-on-surface mb-3 flex items-center justify-between">
                    <span>System Notifications</span>
                    <span class="text-[10px] bg-primary/10 text-primary px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Realtime</span>
                </h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    <?php foreach ($notifications as $n):
                        $nIcon = $n['type'] === 'payment' ? 'payments' : ($n['type'] === 'scan' ? 'auto_awesome' : 'info');
                        $nColor = $n['type'] === 'payment' ? 'text-primary' : ($n['type'] === 'scan' ? 'text-secondary' : 'text-on-surface-variant');
                        ?>
                        <div class="flex gap-3 hover:bg-surface-variant/30 p-2 rounded-xl transition-colors">
                            <span class="material-symbols-outlined <?php echo $nColor; ?> shrink-0 text-[18px]"><?php echo $nIcon; ?></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-on-surface truncate"><?php echo htmlspecialchars($n['title']); ?></p>
                                <p class="text-[10px] text-on-surface-variant mt-0.5 leading-relaxed"><?php echo htmlspecialchars($n['desc']); ?></p>
                            </div>
                            <span class="text-[9px] text-on-surface-variant shrink-0 font-medium"><?php echo htmlspecialchars($n['time']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Settings Quick Link -->
        <a href="ai_control.php" class="text-on-surface-variant hover:text-primary transition-colors flex items-center p-2 rounded-full hover:bg-surface-container/50">
            <span class="material-symbols-outlined">settings</span>
        </a>

        <!-- Avatar Popover Button -->
        <div class="relative">
            <button onclick="toggleAvatarPopover(event)" class="w-10 h-10 rounded-full overflow-hidden border-2 border-surface-container-high flex items-center justify-center hover:border-primary transition-all active:scale-95">
                <img alt="Admin Profile" id="headerAdminAvatar" class="w-full h-full object-cover" src="<?php echo $adminPhoto; ?>" />
            </button>

            <!-- Avatar Popover Panel -->
            <div id="avatar-popover" class="hidden absolute right-0 mt-3 w-64 bg-surface-bright rounded-2xl shadow-2xl border border-outline/10 p-5 z-50 text-left">
                <div class="flex items-center gap-3 border-b border-outline/10 pb-4 mb-4">
                    <div class="w-10 h-10 rounded-full overflow-hidden shrink-0">
                        <img alt="Admin Profile" id="dropdownAdminAvatar" class="w-full h-full object-cover" src="<?php echo $adminPhoto; ?>" />
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm font-bold text-on-surface truncate" id="dropdownAdminName"><?php echo $adminFullName; ?></h4>
                        <span class="text-[10px] text-primary font-bold uppercase tracking-wider bg-primary/10 px-2 py-0.5 rounded-full">Nexus Superuser</span>
                    </div>
                </div>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-on-surface-variant">
                        <span>Status:</span>
                        <span class="text-primary font-bold">Active Node</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pb-2">
                        <span>Environment:</span>
                        <span class="font-medium text-on-surface">Online</span>
                    </div>
                    <button onclick="openEditProfileModal()" class="w-full block text-center py-2 rounded-xl bg-primary/10 text-primary font-bold hover:bg-primary hover:text-on-primary transition-all active:scale-95 duration-200 mb-2">
                        Edit Profile
                    </button>
                    <a href="logout.php" class="w-full block text-center py-2.5 rounded-xl bg-slate-900 text-white font-bold hover:bg-red-500 hover:text-white transition-all active:scale-95 duration-200">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Edit Profile Modal -->
<div id="editProfileModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-md">
    <div class="bg-surface-bright border border-outline/20 rounded-3xl p-8 max-w-md w-full shadow-2xl text-left transform scale-100 transition-all duration-300">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-display font-extrabold text-xl text-on-surface">Edit Admin Profile</h3>
            <button onclick="closeEditProfileModal()" class="text-on-surface-variant hover:text-primary transition-colors p-1.5 rounded-full hover:bg-surface-container/50">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="editProfileForm" onsubmit="submitAdminProfile(event)">
            <!-- Error message container -->
            <div id="editProfileError" class="hidden mb-4 bg-red-500/10 text-red-500 border border-red-500/20 rounded-2xl p-4 text-xs font-medium"></div>

            <!-- Profile Picture preview & file picker -->
            <div class="flex flex-col items-center mb-6">
                <div class="relative w-24 h-24 rounded-full overflow-hidden border-2 border-primary group cursor-pointer" onclick="document.getElementById('editProfilePicFile').click()">
                    <img id="editProfileAvatarPreview" class="w-full h-full object-cover" src="<?php echo $adminPhoto; ?>" alt="Avatar Preview" />
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                        <span class="material-symbols-outlined text-white text-lg">photo_camera</span>
                    </div>
                </div>
                <input type="file" id="editProfilePicFile" accept="image/*" class="hidden" onchange="previewAndConvertImage(event)" />
                <input type="hidden" id="editProfilePicBase64" name="profile_picture" value="<?php echo htmlspecialchars($adminUser['profile_picture'] ?? ''); ?>" />
                <p class="text-[10px] text-on-surface-variant mt-2">Click photo to upload a new avatar image</p>
            </div>

            <!-- Inputs -->
            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2">First Name</label>
                    <input 
                        type="text" 
                        id="editProfileFirstName" 
                        name="first_name" 
                        required 
                        class="w-full bg-surface-variant/40 border border-outline/20 rounded-2xl px-4 py-3 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/50"
                        value="<?php echo htmlspecialchars($adminUser['first_name'] ?? ''); ?>"
                    />
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2">Last Name</label>
                    <input 
                        type="text" 
                        id="editProfileLastName" 
                        name="last_name" 
                        required 
                        class="w-full bg-surface-variant/40 border border-outline/20 rounded-2xl px-4 py-3 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/50"
                        value="<?php echo htmlspecialchars($adminUser['last_name'] ?? ''); ?>"
                    />
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2">Email Address</label>
                    <input 
                        type="email" 
                        id="editProfileEmail" 
                        name="email" 
                        required 
                        class="w-full bg-surface-variant/40 border border-outline/20 rounded-2xl px-4 py-3 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/50"
                        value="<?php echo htmlspecialchars($adminUser['email'] ?? ''); ?>"
                    />
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2">New Password (Optional)</label>
                    <input 
                        type="password" 
                        id="editProfilePassword" 
                        name="password" 
                        placeholder="Leave blank to keep current" 
                        class="w-full bg-surface-variant/40 border border-outline/20 rounded-2xl px-4 py-3 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/50"
                    />
                </div>
            </div>

            <!-- Submit buttons -->
            <div class="flex gap-3 mt-8">
                <button type="button" onclick="closeEditProfileModal()" class="flex-1 bg-surface-container border border-outline/20 text-on-surface font-bold py-3.5 rounded-2xl text-xs transition-colors hover:bg-surface-container-high uppercase tracking-widest">
                    Cancel
                </button>
                <button type="submit" id="editProfileSubmitBtn" class="flex-1 bg-primary text-on-primary font-bold py-3.5 rounded-2xl text-xs hover:bg-primary-fixed transition-colors uppercase tracking-widest shadow-lg shadow-primary/20">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts for header & popovers -->
<script>
function toggleBellDropdown(event) {
    event.stopPropagation();
    const bellDropdown = document.getElementById('bell-dropdown');
    const avatarPopover = document.getElementById('avatar-popover');

    bellDropdown.classList.toggle('hidden');
    avatarPopover.classList.add('hidden');
}

function toggleAvatarPopover(event) {
    event.stopPropagation();
    const bellDropdown = document.getElementById('bell-dropdown');
    const avatarPopover = document.getElementById('avatar-popover');

    avatarPopover.classList.toggle('hidden');
    bellDropdown.classList.add('hidden');
}

function openEditProfileModal() {
    document.getElementById('avatar-popover').classList.add('hidden');
    document.getElementById('editProfileModal').classList.remove('hidden');
    document.getElementById('editProfileError').classList.add('hidden');
    document.getElementById('editProfilePassword').value = '';
}

function closeEditProfileModal() {
    document.getElementById('editProfileModal').classList.add('hidden');
}

function previewAndConvertImage(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Preview
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('editProfileAvatarPreview').src = e.target.result;
        document.getElementById('editProfilePicBase64').value = e.target.result;
    };
    reader.readAsDataURL(file);
}

function submitAdminProfile(event) {
    event.preventDefault();

    const submitBtn = document.getElementById('editProfileSubmitBtn');
    const errContainer = document.getElementById('editProfileError');
    errContainer.classList.add('hidden');

    const firstName = document.getElementById('editProfileFirstName').value.trim();
    const lastName = document.getElementById('editProfileLastName').value.trim();
    const email = document.getElementById('editProfileEmail').value.trim();
    const password = document.getElementById('editProfilePassword').value;
    const profilePic = document.getElementById('editProfilePicBase64').value;

    submitBtn.textContent = 'SAVING...';
    submitBtn.disabled = true;

    fetch('api/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            first_name: firstName,
            last_name: lastName,
            email: email,
            password: password,
            profile_picture: profilePic
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            submitBtn.textContent = 'SAVED!';
            submitBtn.className = 'flex-1 bg-green-500 text-white font-bold py-3.5 rounded-2xl text-xs transition-all duration-300';
            
            // Instantly update header assets dynamically
            document.getElementById('headerAdminAvatar').src = data.data.profile_picture || 'https://lh3.googleusercontent.com/aida-public/AB6AXuBTAuJFaXpSIu4rY3b_5gxkaAT61pW8S-Kyovo6HcsMbnEIG47n1bT01w-6ij_o6WRNMU7oiDnPbuczKUDzHZurd4iKzlqkq28ImZK1fKLVDuF9dUyHMCqRYvN3vlcZg9KO9yermPLC-BhxluGcYkKEAT5BrNpa8rFPNuDaCjjhI3qvIyw6B2EnKYAHxnm5FuPxhponBfnDnGfqPkkFHJexelUaN5HHdyqimigN3uxZyVaA5K1wueB_Mm3yCR_3K0AA3P2DnC5LWA';
            document.getElementById('dropdownAdminAvatar').src = data.data.profile_picture || 'https://lh3.googleusercontent.com/aida-public/AB6AXuBTAuJFaXpSIu4rY3b_5gxkaAT61pW8S-Kyovo6HcsMbnEIG47n1bT01w-6ij_o6WRNMU7oiDnPbuczKUDzHZurd4iKzlqkq28ImZK1fKLVDuF9dUyHMCqRYvN3vlcZg9KO9yermPLC-BhxluGcYkKEAT5BrNpa8rFPNuDaCjjhI3qvIyw6B2EnKYAHxnm5FuPxhponBfnDnGfqPkkFHJexelUaN5HHdyqimigN3uxZyVaA5K1wueB_Mm3yCR_3K0AA3P2DnC5LWA';
            document.getElementById('dropdownAdminName').textContent = firstName + ' ' + lastName;

            setTimeout(() => {
                closeEditProfileModal();
                // Restore button state
                submitBtn.textContent = 'Save Changes';
                submitBtn.disabled = false;
                submitBtn.className = 'flex-1 bg-primary text-on-primary font-bold py-3.5 rounded-2xl text-xs hover:bg-primary-fixed transition-colors uppercase tracking-widest shadow-lg shadow-primary/20';
                
                // Trigger quick reload to refresh PHP-bound layout variables securely
                window.location.reload();
            }, 800);
        } else {
            throw new Error(data.message || 'Failed to update profile');
        }
    })
    .catch(err => {
        errContainer.textContent = err.message || 'An error occurred while updating profile.';
        errContainer.classList.remove('hidden');
        submitBtn.textContent = 'Save Changes';
        submitBtn.disabled = false;
    });
}

// Close dropdowns when clicking anywhere outside
document.addEventListener('click', function (event) {
    const bellDropdown = document.getElementById('bell-dropdown');
    const avatarPopover = document.getElementById('avatar-popover');

    if (bellDropdown && !bellDropdown.contains(event.target) && !event.target.closest('button')) {
        bellDropdown.classList.add('hidden');
    }
    if (avatarPopover && !avatarPopover.contains(event.target) && !event.target.closest('button')) {
        avatarPopover.classList.add('hidden');
    }
});
</script>
