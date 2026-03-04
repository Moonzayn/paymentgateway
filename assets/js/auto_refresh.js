/**
 * Auto-refresh Saldo & Dark Mode
 */

(function() {
    'use strict';

    // =========================================
    // AUTO REFRESH SALDO
    // =========================================

    let lastSaldo = null;
    let pollInterval = null;
    const SALDO_POLL_INTERVAL = 30000; // 30 detik

    // Cek apakah tab aktif
    function isTabActive() {
        return !document.hidden;
    }

    function formatRupiah(num) {
        return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function updateSaldoDisplay(saldo) {
        const saldoEl = document.querySelector('.balance-chip span:not(.chip-icon)');
        if (saldoEl) {
            const newSaldoText = formatRupiah(saldo);
            saldoEl.textContent = newSaldoText;

            // Update session storage for cross-tab
            sessionStorage.setItem('current_saldo', saldo);
        }
    }

    function checkSaldoChange(newSaldo) {
        const oldSaldo = parseFloat(sessionStorage.getItem('current_saldo')) || lastSaldo;

        if (oldSaldo !== null && newSaldo !== oldSaldo) {
            const diff = newSaldo - oldSaldo;
            const direction = diff > 0 ? 'naik' : 'turun';
            const absDiff = Math.abs(diff);

            showToast(
                `Saldo ${direction} ${formatRupiah(absDiff)}`,
                direction === 'naik' ? 'success' : 'warning',
                'fas fa-wallet'
            );
        }

        lastSaldo = newSaldo;
        sessionStorage.setItem('current_saldo', newSaldo);
    }

    function fetchSaldo() {
        fetch('/payment/api/get_saldo.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateSaldoDisplay(data.saldo);
                    checkSaldoChange(data.saldo);
                }
            })
            .catch(err => console.error('Error fetching saldo:', err));
    }

    function initAutoRefresh() {
        // Initial saldo from DOM
        const saldoEl = document.querySelector('.balance-chip span:not(.chip-icon)');
        if (saldoEl) {
            const currentSaldoText = saldoEl.textContent.replace(/[^0-9]/g, '');
            lastSaldo = parseFloat(currentSaldoText) || null;
            if (lastSaldo) {
                sessionStorage.setItem('current_saldo', lastSaldo);
            }
        }

        // Initial fetch
        fetchSaldo();

        // Set interval dengan visibility check
        pollInterval = setInterval(function() {
            if (isTabActive()) {
                fetchSaldo();
            }
        }, SALDO_POLL_INTERVAL);

        // Listen visibility change - langsung fetch saat tab aktif lagi
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                fetchSaldo();
            }
        });
    }

    // =========================================
    // TOAST NOTIFICATION
    // =========================================

    function showToast(message, type = 'info', icon = 'fas fa-bell') {
        const colors = {
            success: { bg: '#dcfce7', border: '#bbf7d0', text: '#166534', icon: '#16a34a' },
            error: { bg: '#fee2e2', border: '#fecaca', text: '#991b1b', icon: '#ef4444' },
            warning: { bg: '#fef9c3', border: '#fde68a', text: '#854d0e', icon: '#d97706' },
            info: { bg: '#dbeafe', border: '#bfdbfe', text: '#1e40af', icon: '#2563eb' }
        };

        const style = colors[type] || colors.info;

        const toast = document.createElement('div');
        toast.className = 'saldo-toast';
        toast.innerHTML = `
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:${style.bg};border:1px solid ${style.border};border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                <i class="${icon}" style="color:${style.icon};font-size:18px;width:24px;text-align:center;"></i>
                <span style="font-size:14px;font-weight:500;color:${style.text};flex:1;">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:${style.text};opacity:0.6;font-size:14px;">&times;</button>
            </div>
        `;

        // Style untuk toast
        toast.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            max-width: 320px;
        `;

        document.body.appendChild(toast);

        // Auto remove setelah 5 detik
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    // =========================================
    // DARK MODE
    // =========================================

    const DARK_MODE_KEY = 'ppob_dark_mode';

    function getDarkMode() {
        return localStorage.getItem(DARK_MODE_KEY) === 'true';
    }

    function setDarkMode(enabled) {
        localStorage.setItem(DARK_MODE_KEY, enabled);
        document.body.classList.toggle('dark-mode', enabled);

        // Update toggle icon
        const toggleBtn = document.getElementById('darkModeToggle');
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.className = enabled ? 'fas fa-moon' : 'fas fa-sun';
            }
        }
    }

    function toggleDarkMode() {
        const newState = !getDarkMode();
        setDarkMode(newState);
    }

    function initDarkMode() {
        const isDark = getDarkMode();

        // Add dark mode styles
        const darkStyles = document.createElement('style');
        darkStyles.id = 'dark-mode-styles';
        darkStyles.innerHTML = `
            body.dark-mode {
                --bg: #0f172a;
                --surface: #1e293b;
                --border: #334155;
                --text: #f1f5f9;
                --text-secondary: #94a3b8;
                --text-muted: #64748b;
            }
            body.dark-mode .sidebar {
                background: #1e293b;
                border-color: #334155;
            }
            body.dark-mode .sidebar-brand,
            body.dark-mode .nav-item {
                color: #f1f5f9;
            }
            body.dark-mode .nav-item:hover {
                background: rgba(99, 83, 216, 0.2);
            }
            body.dark-mode .main-wrapper,
            body.dark-mode .top-navbar,
            body.dark-mode .card,
            body.dark-mode .section-card,
            body.dark-mode .summary-card,
            body.dark-mode .promo-banner {
                background: #1e293b;
                border-color: #334155;
            }
            body.dark-mode .page-content,
            body.dark-mode .main-footer {
                background: #0f172a;
            }
            body.dark-mode h1, body.dark-mode h2, body.dark-mode h3,
            body.dark-mode .stat-card, body.dark-mode .table-row,
            body.dark-mode .trx-row, body.dark-mode .product-card {
                background: #1e293b;
                color: #f1f5f9;
                border-color: #334155;
            }
            body.dark-mode .text-gray-500,
            body.dark-mode .text-gray-600,
            body.dark-mode .text-gray-700,
            body.dark-mode .text-gray-800,
            body.dark-mode .text-gray-900,
            body.dark-mode .text-muted {
                color: #94a3b8 !important;
            }
            body.dark-mode input,
            body.dark-mode select,
            body.dark-mode textarea {
                background: #334155;
                border-color: #475569;
                color: #f1f5f9;
            }
            body.dark-mode .balance-chip {
                background: rgba(99, 83, 216, 0.2);
            }
            body.dark-mode .table-header-row th {
                background: #334155;
                color: #f1f5f9;
            }
            body.dark-mode .table-row td {
                border-color: #334155;
            }
            body.dark-mode .input-field,
            body.dark-mode input[type="text"],
            body.dark-mode input[type="password"],
            body.dark-mode input[type="tel"],
            body.dark-mode input[type="number"],
            body.dark-mode input[type="date"],
            body.dark-mode select {
                background: #1e293b;
                border-color: #475569;
                color: #f1f5f9;
            }

            @keyframes slideInRight {
                from { opacity: 0; transform: translateX(100px); }
                to { opacity: 1; transform: translateX(0); }
            }
            @keyframes slideOutRight {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(100px); }
            }
        `;

        if (!document.getElementById('dark-mode-styles')) {
            document.head.appendChild(darkStyles);
        }

        // Apply initial state
        document.body.classList.toggle('dark-mode', isDark);

        // Create toggle button if not exists
        const navbarRight = document.querySelector('.navbar-right');
        if (navbarRight && !document.getElementById('darkModeToggle')) {
            const toggleBtn = document.createElement('button');
            toggleBtn.id = 'darkModeToggle';
            toggleBtn.className = 'chat-icon-btn';
            toggleBtn.title = 'Toggle Dark Mode';
            toggleBtn.onclick = toggleDarkMode;
            toggleBtn.innerHTML = `<i class="fas ${isDark ? 'fa-moon' : 'fa-sun'}"></i>`;
            toggleBtn.style.marginLeft = '8px';
            navbarRight.insertBefore(toggleBtn, navbarRight.firstChild);
        }
    }

    // =========================================
    // INITIALIZE
    // =========================================

    document.addEventListener('DOMContentLoaded', function() {
        // Init dark mode
        initDarkMode();

        // Init auto-refresh saldo (hanya kalau user login)
        if (document.querySelector('.balance-chip')) {
            initAutoRefresh();
        }
    });

    // Expose untuk global use
    window.showToast = showToast;
    window.toggleDarkMode = toggleDarkMode;
    window.setDarkMode = setDarkMode;
    window.getDarkMode = getDarkMode;

})();
