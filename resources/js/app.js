// ── Sidebar controller ────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    // ── Element refs ─────────────────────────────────────────────────────────
    const sidebar                 = document.getElementById('sidebar');
    const mobileSidebarOverlay    = document.getElementById('mobileSidebarOverlay');
    const openSidebarBtn          = document.getElementById('openSidebarBtn');
    const closeSidebarBtn         = document.getElementById('closeSidebarBtn');
    const desktopSidebarToggleBtn = document.getElementById('desktopSidebarToggleBtn');

    // ── localStorage key ─────────────────────────────────────────────────────
    const STORAGE_KEY = 'sidebar_desktop_collapsed';

    // ── Helpers: Mobile (off-canvas) ─────────────────────────────────────────
    const openMobileSidebar = () => {
        if (!sidebar || !mobileSidebarOverlay) return;
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        mobileSidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
        mobileSidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');
        document.body.classList.add('overflow-hidden');
    };

    const closeMobileSidebar = () => {
        if (!sidebar || !mobileSidebarOverlay) return;
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        mobileSidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
        mobileSidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
        document.body.classList.remove('overflow-hidden');
    };

    // ── Helpers: Desktop (collapse / expand) ─────────────────────────────────
    const isDesktop = () => window.innerWidth >= 1024;

    /**
     * Apply the collapsed state on desktop.
     * We add/remove a CSS class; the sidebar uses `transition-all` so the
     * width animates smoothly. The main content (flex-1) automatically
     * fills the remaining space because the wrapper is `lg:flex`.
     */
    const setDesktopCollapsed = (collapsed) => {
        if (!sidebar) return;
        if (collapsed) {
            sidebar.classList.add('sidebar-collapsed');
        } else {
            sidebar.classList.remove('sidebar-collapsed');
        }
        // Persist
        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    };

    const toggleDesktopSidebar = () => {
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        setDesktopCollapsed(!isCollapsed);
    };

    // ── Restore state on page load ────────────────────────────────────────────
    if (isDesktop() && sidebar) {
        const stored = localStorage.getItem(STORAGE_KEY);
        const shouldCollapse = stored === '1';
        // Disable transition temporarily so no visible animation on initial restore
        sidebar.style.transition = 'none';
        setDesktopCollapsed(shouldCollapse);
        sidebar.offsetHeight; // force reflow
        requestAnimationFrame(() => { sidebar.style.transition = ''; });
    }

    // ── Event listeners ───────────────────────────────────────────────────────
    openSidebarBtn?.addEventListener('click', openMobileSidebar);
    closeSidebarBtn?.addEventListener('click', closeMobileSidebar);
    mobileSidebarOverlay?.addEventListener('click', closeMobileSidebar);
    desktopSidebarToggleBtn?.addEventListener('click', toggleDesktopSidebar);

    // If window resizes to mobile, remove collapsed class to avoid stale state
    window.addEventListener('resize', () => {
        if (!isDesktop() && sidebar) {
            sidebar.classList.remove('sidebar-collapsed');
        } else if (isDesktop() && sidebar) {
            const stored = localStorage.getItem(STORAGE_KEY);
            setDesktopCollapsed(stored === '1');
        }
    });
});
