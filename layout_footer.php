<?php // layout_footer.php ?>

    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <span class="footer-text">&copy; <?= date('Y') ?> PPOB Express. All rights reserved.</span>
        <div style="display:flex;gap:16px;">
            <a href="#" class="footer-link">Kebijakan Privasi</a>
            <a href="#" class="footer-link">Syarat & Ketentuan</a>
        </div>
    </footer>
</div><!-- /main-wrapper -->

<script>
// ═══════════════════════════════════════
//  SIDEBAR LOGIC
// ═══════════════════════════════════════
const sidebar      = document.getElementById('sidebar');
const mainWrapper  = document.getElementById('mainWrapper');
const overlay      = document.getElementById('overlay');
const navToggle    = document.getElementById('navbarToggleBtn');
const sideToggle   = document.getElementById('sidebarToggleBtn');
const STORAGE_KEY  = 'sidebar_open';

function isDesktop() { return window.innerWidth >= 768; }

function openSidebar() {
    if (isDesktop()) {
        sidebar.classList.remove('sidebar-hidden');
        mainWrapper.classList.remove('sidebar-hidden');
        localStorage.setItem(STORAGE_KEY, 'true');
        // Update icon
        if (sideToggle) sideToggle.innerHTML = '<i class="fas fa-chevron-left"></i>';
    } else {
        sidebar.classList.add('open');
        overlay.classList.add('show');
    }
}

function closeSidebar() {
    if (isDesktop()) {
        sidebar.classList.add('sidebar-hidden');
        mainWrapper.classList.add('sidebar-hidden');
        localStorage.setItem(STORAGE_KEY, 'false');
        if (sideToggle) sideToggle.innerHTML = '<i class="fas fa-chevron-right"></i>';
    } else {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    }
}

function toggleSidebar() {
    if (isDesktop()) {
        const isHidden = sidebar.classList.contains('sidebar-hidden');
        isHidden ? openSidebar() : closeSidebar();
    } else {
        const isOpen = sidebar.classList.contains('open');
        isOpen ? closeSidebar() : openSidebar();
    }
}

function initSidebar() {
    if (isDesktop()) {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'false') {
            sidebar.classList.add('sidebar-hidden');
            mainWrapper.classList.add('sidebar-hidden');
            if (sideToggle) sideToggle.innerHTML = '<i class="fas fa-chevron-right"></i>';
        } else {
            sidebar.classList.remove('sidebar-hidden');
            mainWrapper.classList.remove('sidebar-hidden');
            if (sideToggle) sideToggle.innerHTML = '<i class="fas fa-chevron-left"></i>';
        }
        // Remove mobile classes
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    } else {
        // Mobile: sidebar hidden by default
        sidebar.classList.remove('sidebar-hidden');
        sidebar.classList.remove('open');
        mainWrapper.classList.remove('sidebar-hidden');
        overlay.classList.remove('show');
    }
}

document.addEventListener('DOMContentLoaded', initSidebar);

let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(initSidebar, 100);
});

// Close sidebar on mobile nav click
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
        if (!isDesktop()) closeSidebar();
    });
});
</script>

<!-- Auto Refresh Saldo & Dark Mode -->
<script src="/payment/assets/js/auto_refresh.js"></script>

<?php
$isSuperAdmin = (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 'yes') || (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin');
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isOwnerOrKasir = isset($_SESSION['role_owner']) && $_SESSION['role_owner'];

if (!$isSuperAdmin && !$isAdmin): ?>
<?php include 'chat_widget.php'; ?>
<?php elseif ($isSuperAdmin): ?>
<?php include 'chat_admin_widget.php'; ?>
<?php endif; ?>

<!-- PWA Service Worker -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('SW registered:', registration.scope);
            })
            .catch(function(error) {
                console.log('SW registration failed:', error);
            });
    });
}

// PWA Install Prompt - Hybrid Approach
let deferredPrompt;
const installBanner = document.getElementById('installBanner');

// Track visit count for mobile (show after 3 visits)
const VISIT_KEY = 'ppob_visit_count';
const DISMISS_KEY = 'ppob_install_dismissed';
let visitCount = parseInt(localStorage.getItem(VISIT_KEY) || '0');
visitCount++;
localStorage.setItem(VISIT_KEY, visitCount);

const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
const isDismissed = localStorage.getItem(DISMISS_KEY) === 'true';

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show banner based on platform
    if (installBanner) {
        if (isMobile) {
            // Mobile: Show after 3 visits and not dismissed
            if (visitCount >= 3 && !isDismissed) {
                installBanner.style.display = 'flex';
            }
        } else {
            // Desktop: Show always
            installBanner.style.display = 'flex';
        }
    }
});

function installApp() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            deferredPrompt = null;
            if (installBanner) {
                installBanner.style.display = 'none';
            }
        });
    }
}

function dismissBanner() {
    if (installBanner) {
        installBanner.style.display = 'none';
    }
    localStorage.setItem(DISMISS_KEY, 'true');
    const overlay = document.getElementById('pwaOverlay');
    if (overlay) overlay.style.display = 'none';
}

function dismissPwa() {
    dismissBanner();
}

// Profile Dropdown
function toggleProfileMenu() {
    const dropdown = document.getElementById('profileDropdown');
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifDropdown) notifDropdown.classList.add('hidden');
    dropdown.classList.toggle('hidden');
}

// Notifications
function toggleNotifications() {
    const dropdown = document.getElementById('notifDropdown');
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileDropdown) profileDropdown.classList.add('hidden');
    dropdown.classList.toggle('hidden');
    loadNotifications();
}

function loadNotifications() {
    const list = document.getElementById('notifList');
    if (!list) return;

    // Fetch notifications
    fetch('/api/get_notifications.php')
        .then(r => r.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                let html = '';
                data.notifications.forEach(n => {
                    html += `<div class="p-2 border-b hover:bg-gray-50 cursor-pointer ${n.is_read === 'no' ? 'bg-blue-50' : ''}" onclick="handleNotifClick(${n.id}, '${n.type}')">
                        <div class="flex items-start gap-2">
                            <div class="w-8 h-8 rounded-full ${n.type === 'deposit' ? 'bg-green-100 text-green-600' : n.type === 'chat' ? 'bg-blue-100 text-blue-600' : 'bg-yellow-100 text-yellow-600'} flex items-center justify-center">
                                <i class="fas fa-${n.type === 'deposit' ? 'money-bill' : n.type === 'chat' ? 'comment' : 'exclamation'}"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm">${n.title}</div>
                                <div class="text-xs text-gray-500">${n.message}</div>
                                <div class="text-xs text-gray-400">${n.time_ago}</div>
                            </div>
                        </div>
                    </div>`;
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<div class="text-center text-gray-500 py-8"><i class="fas fa-bell-slash text-3xl mb-2"></i><div>Tidak ada notifikasi</div></div>';
            }

            // Update badge
            const badge = document.getElementById('notifBadge');
            if (data.unread > 0 && badge) {
                badge.textContent = data.unread > 9 ? '9+' : data.unread;
                badge.style.display = 'flex';
            } else if (badge) {
                badge.style.display = 'none';
            }
        })
        .catch(() => {
            list.innerHTML = '<div class="text-center text-gray-500 py-8">Gagal load notifikasi</div>';
        });
}

function handleNotifClick(id, type) {
    // Mark as read
    fetch('/api/mark_notif_read.php?id=' + id);
    // Redirect based on type
    if (type === 'deposit') {
        window.location.href = 'deposit.php';
    } else if (type === 'chat') {
        window.location.href = 'index.php?chat=1';
    }
}

function markAllRead() {
    fetch('/api/mark_all_notif_read.php');
    loadNotifications();
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    const profileDropdown = document.getElementById('profileDropdown');
    const notifDropdown = document.getElementById('notifDropdown');
    const avatarBtn = document.querySelector('.user-avatar');
    const notifBtn = document.querySelector('[onclick="toggleNotifications()"]');

    if (profileDropdown && !profileDropdown.contains(e.target) && !avatarBtn?.contains(e.target)) {
        profileDropdown.classList.add('hidden');
    }
    if (notifDropdown && !notifDropdown.contains(e.target) && !notifBtn?.contains(e.target)) {
        notifDropdown.classList.add('hidden');
    }
});
</script>

</body>
</html>