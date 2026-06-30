import api from './api';

const POLL_INTERVAL_MS = 60_000;

const formatBadgeCount = (count) => (count > 99 ? '99+' : String(count));

const typeIcon = (type) => {
    switch (type) {
        case 'leave_submitted':
            return '🏖';
        case 'leave_decision':
            return '🏖';
        case 'document_verification':
            return '📄';
        case 'document_decision':
            return '📄';
        case 'regularization_submitted':
            return '🕐';
        case 'regularization_decision':
            return '🕐';
        case 'moment_new':
            return '🎉';
        case 'profile_photo_submitted':
        case 'profile_photo_decision':
            return '📷';
        default:
            return '🔔';
    }
};

const renderNotificationItem = (notification) => {
    const unreadClass = notification.is_read ? '' : ' notifications-item-unread';
    const safeTitle = notification.title ?? 'Notification';
    const safeBody = notification.body ?? '';
    const safeUrl = notification.action_url ?? '#';
    const safeLabel = notification.created_at_label ?? '';

    return `
        <a
            href="${safeUrl}"
            class="notifications-item${unreadClass}"
            data-notification-id="${notification.id}"
            data-action-url="${safeUrl}"
        >
            <span class="notifications-item-icon" aria-hidden="true">${typeIcon(notification.type)}</span>
            <span class="notifications-item-body">
                <span class="notifications-item-title">${safeTitle}</span>
                <span class="notifications-item-text">${safeBody}</span>
                <span class="notifications-item-time">${safeLabel}</span>
            </span>
        </a>
    `;
};

const renderNotificationsList = (notifications, pendingActionsCount, canReview) => {
    const listEl = document.getElementById('notificationsList');

    if (!listEl) {
        return;
    }

    if (!notifications.length) {
        listEl.innerHTML = '<div class="notifications-empty">No notifications yet.</div>';
    } else {
        listEl.innerHTML = notifications.map(renderNotificationItem).join('');
    }

    const pendingEl = document.getElementById('notificationsPendingActions');

    if (pendingEl) {
        if (canReview && pendingActionsCount > 0) {
            pendingEl.hidden = false;
            pendingEl.innerHTML = `
                <a href="/requests" class="notifications-pending-link">
                    <span>${pendingActionsCount} pending action${pendingActionsCount === 1 ? '' : 's'} awaiting review</span>
                    <span aria-hidden="true">→</span>
                </a>
            `;
        } else {
            pendingEl.hidden = true;
            pendingEl.innerHTML = '';
        }
    }
};

const updateBadge = (count) => {
    const badge = document.getElementById('notificationsBadge');

    if (!badge) {
        return;
    }

    if (count > 0) {
        badge.textContent = formatBadgeCount(count);
        badge.hidden = false;
    } else {
        badge.hidden = true;
        badge.textContent = '';
    }
};

const loadSummary = async () => {
    const response = await api.get('/notifications/summary');
    const summary = response.data?.data ?? response.data ?? {};

    updateBadge(summary.badge_count ?? 0);

    return summary;
};

const loadNotifications = async () => {
    const [summaryResponse, listResponse] = await Promise.all([
        api.get('/notifications/summary'),
        api.get('/notifications', { params: { limit: 15 } }),
    ]);

    const summary = summaryResponse.data?.data ?? summaryResponse.data ?? {};
    const list = listResponse.data?.data ?? listResponse.data ?? {};

    updateBadge(summary.badge_count ?? 0);
    renderNotificationsList(
        list.notifications ?? [],
        summary.pending_actions_count ?? 0,
        summary.can_review ?? false,
    );
};

const markNotificationRead = async (notificationId) => {
    try {
        await api.post(`/notifications/${notificationId}/read`);
    } catch (error) {
        // Ignore mark-read failures; navigation should still proceed.
    }
};

const markAllRead = async () => {
    try {
        await api.post('/notifications/read-all');
        await loadNotifications();
    } catch (error) {
        // Ignore failures silently.
    }
};

export const initNotifications = () => {
    const menu = document.getElementById('notificationsMenu');

    if (!menu) {
        return;
    }

    const markAllButton = document.getElementById('notificationsMarkAllRead');

    menu.addEventListener('show.bs.dropdown', () => {
        loadNotifications().catch(() => {
            const listEl = document.getElementById('notificationsList');

            if (listEl) {
                listEl.innerHTML = '<div class="notifications-empty">Unable to load notifications.</div>';
            }
        });
    });

    menu.addEventListener('click', async (event) => {
        const item = event.target.closest('[data-notification-id]');

        if (!item) {
            return;
        }

        event.preventDefault();

        const notificationId = item.getAttribute('data-notification-id');
        const actionUrl = item.dataset.actionUrl || item.getAttribute('href') || '';

        if (notificationId) {
            await markNotificationRead(notificationId);
        }

        if (actionUrl && actionUrl !== '#') {
            window.location.href = actionUrl;
        }
    });

    if (markAllButton) {
        markAllButton.addEventListener('click', async (event) => {
            event.preventDefault();
            await markAllRead();
        });
    }

    loadSummary().catch(() => {});

    window.setInterval(() => {
        loadSummary().catch(() => {});
    }, POLL_INTERVAL_MS);
};

document.addEventListener('DOMContentLoaded', initNotifications);
