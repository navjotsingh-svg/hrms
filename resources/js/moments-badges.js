import api from './api';

const formatBadgeCount = (count) => (count > 99 ? '99+' : String(count));

export const applyMomentsUnreadBadges = (unread = {}) => {
    const total = unread.total || 0;
    const byType = unread.by_type || {};

    const sidebarBadge = document.getElementById('sidebarMomentsBadge');
    const homeTabBadge = document.getElementById('homeTabMomentsBadge');
    const experienceTabBadge = document.getElementById('experienceTabSocialWallBadge');

    [sidebarBadge, homeTabBadge, experienceTabBadge].forEach((el) => {
        if (!el) return;

        if (total > 0) {
            el.textContent = formatBadgeCount(total);
            el.classList.remove('d-none');
            el.classList.add('moments-count-badge--pulse');
            el.setAttribute('aria-label', `${total} new moment${total === 1 ? '' : 's'}`);
        } else {
            el.textContent = '';
            el.classList.add('d-none');
            el.classList.remove('moments-count-badge--pulse');
            el.removeAttribute('aria-label');
        }
    });

    document.querySelectorAll('[data-moments-filter-count]').forEach((el) => {
        const key = el.dataset.momentsFilterCount;
        const count = key === 'all' ? total : (byType[key] || 0);

        if (count > 0) {
            el.textContent = formatBadgeCount(count);
            el.classList.remove('d-none');
        } else {
            el.textContent = '';
            el.classList.add('d-none');
        }
    });
};

export const fetchMomentsSummary = async () => {
    try {
        const { data } = await api.get('/home/moments/summary');
        return data.data || {};
    } catch {
        return null;
    }
};

export const markMomentsFeedSeen = async () => {
    try {
        const { data } = await api.patch('/home/moments/mark-seen');
        applyMomentsUnreadBadges(data.data?.unread || { total: 0, by_type: {} });
        return data.data;
    } catch {
        return null;
    }
};

let pollTimer = null;

export const initMomentsBadges = () => {
    if (!document.getElementById('sidebarMomentsBadge')
        && !document.getElementById('homeTabMomentsBadge')
        && !document.getElementById('experienceTabSocialWallBadge')) {
        return;
    }

    const refresh = async () => {
        const summary = await fetchMomentsSummary();
        if (summary?.unread) {
            applyMomentsUnreadBadges(summary.unread);
        }
    };

    refresh();

    if (pollTimer) {
        window.clearInterval(pollTimer);
    }

    pollTimer = window.setInterval(refresh, 60_000);
};

document.addEventListener('DOMContentLoaded', initMomentsBadges);

window.addEventListener('moments:unread-updated', (event) => {
    applyMomentsUnreadBadges(event.detail || {});
});
