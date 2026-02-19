/**
 * PPOB Express - Common Sidebar Toggle Script
 * Include this file in all pages for smooth sidebar functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (!sidebar) return;
    
    // Initialize sidebar state from localStorage
    const savedState = localStorage.getItem('sidebar_visible');
    const isMobile = window.innerWidth < 768;
    
    if (savedState === 'false' || (isMobile && savedState !== 'true')) {
        sidebar.classList.add('hidden');
        if (overlay) overlay.classList.add('hidden');
    } else {
        sidebar.classList.remove('hidden');
        if (overlay) overlay.classList.remove('hidden');
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('hidden');
            if (overlay) overlay.classList.add('hidden');
        }
    });
    
    // Close sidebar when clicking on menu item (mobile)
    document.querySelectorAll('.menu-item').forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth < 768) {
                toggleSidebar();
            }
        });
    });
});

// Global toggle function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (!sidebar) return;
    
    const isHidden = sidebar.classList.contains('hidden');
    
    if (isHidden) {
        sidebar.classList.remove('hidden');
        if (overlay) overlay.classList.remove('hidden');
        localStorage.setItem('sidebar_visible', 'true');
    } else {
        sidebar.classList.add('hidden');
        if (overlay) overlay.classList.add('hidden');
        localStorage.setItem('sidebar_visible', 'false');
    }
}
