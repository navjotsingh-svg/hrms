import './bootstrap';
import 'bootstrap';
import './leave-calendar';
import './notifications';
import './moments-badges';
import './employee-assistant';
import api, { clearToken, destroyWebSession } from './api';
import { initFormStatusToggles, initReturnUrlCapture } from './form-utils';

const SIDEBAR_COLLAPSED_KEY = 'hrms_sidebar_collapsed';

const isSidebarCollapsed = () => document.documentElement.classList.contains('sidebar-collapsed');

const updateSidebarToggleUi = () => {
    const toggle = document.getElementById('sidebarDesktopToggle');
    const icon = document.getElementById('sidebarDesktopToggleIcon');
    const collapsed = isSidebarCollapsed();

    if (toggle) {
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.setAttribute('title', collapsed ? 'Show sidebar' : 'Hide sidebar');
        toggle.setAttribute('aria-label', collapsed ? 'Show sidebar' : 'Hide sidebar');
    }

    if (icon) {
        icon.textContent = collapsed ? '\u2630' : '\u276E';
    }
};

const setSidebarCollapsed = (collapsed) => {
    document.documentElement.classList.toggle('sidebar-collapsed', collapsed);

    try {
        window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0');
    } catch (error) {
        // Ignore storage errors (private browsing, etc.).
    }

    updateSidebarToggleUi();
};

document.addEventListener('DOMContentLoaded', () => {
    initFormStatusToggles();
    initReturnUrlCapture();

    const sidebarToggle = document.getElementById('sidebarDesktopToggle');

    if (sidebarToggle) {
        updateSidebarToggleUi();
        sidebarToggle.addEventListener('click', () => setSidebarCollapsed(!isSidebarCollapsed()));
    }

    const logoutButton = document.getElementById('logoutButton');

    if (!logoutButton) {
        return;
    }

    logoutButton.addEventListener('click', async (event) => {
        event.preventDefault();

        try {
            await api.post('/auth/logout');
        } catch (error) {
            // Continue logout cleanup even if API token revoke fails.
        }

        try {
            await destroyWebSession();
        } catch (error) {
            // Continue logout cleanup even if web session destroy fails.
        }

        clearToken();
        window.location.href = '/';
    });
});
