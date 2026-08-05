import { registerSW } from 'virtual:pwa-register';
import './firebase';
import './capacitor-push';

registerSW({ immediate: true });

window.addEventListener('DOMContentLoaded', () => {
    const sidebar                 = document.getElementById('sidebar');
    const desktopSidebarToggleBtn = document.getElementById('desktopSidebarToggleBtn');

    const STORAGE_KEY = 'sidebar_desktop_collapsed';

    const isDesktop = () => window.innerWidth >= 1024;

    const setDesktopCollapsed = (collapsed) => {
        if (!sidebar) return;
        if (collapsed) {
            sidebar.classList.add('sidebar-collapsed');
        } else {
            sidebar.classList.remove('sidebar-collapsed');
        }
        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    };

    if (isDesktop() && sidebar) {
        const stored = localStorage.getItem(STORAGE_KEY);
        const shouldCollapse = stored === '1';
        sidebar.style.transition = 'none';
        setDesktopCollapsed(shouldCollapse);
        sidebar.offsetHeight;
        requestAnimationFrame(() => { sidebar.style.transition = ''; });
    }

    desktopSidebarToggleBtn?.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        setDesktopCollapsed(!isCollapsed);
    });

    window.addEventListener('resize', () => {
        if (!isDesktop() && sidebar) {
            sidebar.classList.remove('sidebar-collapsed');
        } else if (isDesktop() && sidebar) {
            const stored = localStorage.getItem(STORAGE_KEY);
            setDesktopCollapsed(stored === '1');
        }
    });

    const catatBtn = document.getElementById('catatBtn');
    const catatMenu = document.getElementById('catatMenu');
    catatBtn?.addEventListener('click', () => {
        catatMenu?.classList.toggle('hidden');
    });
    document.addEventListener('click', (event) => {
        if (!catatMenu || catatMenu.classList.contains('hidden')) return;
        if (!catatBtn?.contains(event.target) && !catatMenu.contains(event.target)) {
            catatMenu.classList.add('hidden');
        }
    });
});
