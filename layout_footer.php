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

<?php 
$isSuperAdmin = (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 'yes') || (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin');
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isOwnerOrKasir = isset($_SESSION['role_owner']) && $_SESSION['role_owner'];

if (!$isSuperAdmin && !$isAdmin): ?>
<?php include 'chat_widget.php'; ?>
<?php elseif ($isSuperAdmin): ?>
<?php include 'chat_admin_widget.php'; ?>
<?php endif; ?>

</body>
</html>