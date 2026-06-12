import api, { getErrorMessage } from './api';
import { initAttendancePunch } from './attendance-punch';

const VARIANT_CLASS = {
    primary: 'stat-card-primary',
    success: 'stat-card-success',
    warning: 'stat-card-warning',
    info: 'stat-card-info',
    danger: 'stat-card-danger',
};

const AVATAR_COLORS = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#14b8a6', '#6366f1', '#ef4444', '#22c55e'];

let refreshTimer = null;
let clockTimer = null;
let punchInitialized = false;
let punchController = null;

const avatarColor = (seed = '') => {
    let hash = 0;

    for (let i = 0; i < seed.length; i += 1) {
        hash = seed.charCodeAt(i) + ((hash << 5) - hash);
    }

    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
};

const renderPersonChip = (person) => `
    <div class="dash-person-chip dash-person-chip--static" title="${person.name}">
        <span class="dash-person-avatar" style="background:${avatarColor(person.name)}">${person.initials}</span>
        <span class="dash-person-meta">
            <span class="dash-person-name">${person.name}</span>
            <span class="dash-person-date">${person.date_label || person.joined_label || ''}</span>
        </span>
    </div>
`;

const renderCelebrationEmpty = (title, message) => `
    <div class="dash-celebration-empty">
        <div class="dash-celebration-empty-art" aria-hidden="true">🎉</div>
        <div class="fw-semibold mb-1">${title}</div>
        <div class="text-muted small">${message}</div>
    </div>
`;

const sortPeopleAscending = (people = []) => [...people].sort((a, b) => {
    const dateCompare = (a.occasion_date || a.sort_key || '').localeCompare(b.occasion_date || b.sort_key || '');

    if (dateCompare !== 0) {
        return dateCompare;
    }

    return (a.name || '').localeCompare(b.name || '');
});

const renderCelebrationSection = (label, people) => {
    if (!people?.length) {
        return '';
    }

    const sorted = sortPeopleAscending(people);

    return `
        <div class="dash-celebration-section">
            <div class="dash-celebration-label">${label}</div>
            <div class="dash-people-scroll">${sorted.map((person) => renderPersonChip(person)).join('')}</div>
        </div>
    `;
};

const formatAnniversaryPerson = (person) => ({
    ...person,
    date_label: `${person.date_label}${person.years ? ` · ${person.years} yr` : ''}`,
});

const renderCelebrations = (celebrations = {}) => {
    const birthdaysToday = celebrations.birthdays_today || [];
    const birthdaysUpcoming = celebrations.birthdays_upcoming || [];
    const anniversariesToday = celebrations.anniversaries_today || [];
    const anniversariesUpcoming = celebrations.anniversaries_upcoming || [];

    const birthdaysTodayEl = document.getElementById('dashboardBirthdaysToday');
    const birthdaysUpcomingEl = document.getElementById('dashboardBirthdaysUpcoming');
    const anniversariesEl = document.getElementById('dashboardAnniversariesUpcoming');

    const birthdaysHtml = [
        birthdaysToday.length ? renderCelebrationSection('Today', birthdaysToday) : '',
        birthdaysUpcoming.length ? renderCelebrationSection('Upcoming', birthdaysUpcoming) : '',
    ].join('');

    if (birthdaysTodayEl) {
        birthdaysTodayEl.innerHTML = '';
    }

    if (birthdaysUpcomingEl) {
        birthdaysUpcomingEl.innerHTML = birthdaysHtml
            || renderCelebrationEmpty(
                'Birthdays',
                'No birthdays coming up. Add date of birth on employee profiles to see them here.',
            );
    }

    const anniversariesHtml = [
        anniversariesToday.length
            ? renderCelebrationSection('Today', anniversariesToday.map(formatAnniversaryPerson))
            : '',
        anniversariesUpcoming.length
            ? renderCelebrationSection('Upcoming', anniversariesUpcoming.map(formatAnniversaryPerson))
            : '',
    ].join('');

    if (anniversariesEl) {
        anniversariesEl.innerHTML = anniversariesHtml
            || renderCelebrationEmpty(
                'Work anniversaries',
                'No work anniversaries coming up. They appear based on employee joining dates.',
            );
    }
};

const renderPendingApprovals = (items = [], showSection = true) => {
    const card = document.getElementById('dashboardPendingCard');
    const body = document.getElementById('dashboardPendingBody');
    const empty = document.getElementById('dashboardPendingEmpty');
    const tableWrap = document.getElementById('dashboardPendingTableWrap');
    const count = document.getElementById('dashboardPendingCount');

    card?.classList.toggle('d-none', !showSection);

    if (!showSection || !body) {
        return;
    }

    if (count) {
        count.textContent = String(items.length);
        count.classList.toggle('d-none', items.length === 0);
    }

    if (!items.length) {
        body.innerHTML = '';
        tableWrap?.classList.add('d-none');
        empty?.classList.remove('d-none');
        return;
    }

    tableWrap?.classList.remove('d-none');
    empty?.classList.add('d-none');
    body.innerHTML = items.map((item) => `
        <tr>
            <td>${item.request_by}</td>
            <td>${item.employee_code}</td>
            <td>${item.request_type}</td>
            <td>${item.requested_on_label}</td>
            <td><span class="badge text-bg-warning">${item.status}</span></td>
            <td class="text-end">
                ${item.url ? `<a href="${item.url}" class="btn btn-sm btn-outline-primary">View</a>` : '—'}
            </td>
        </tr>
    `).join('');
};

const renderQuickActions = (actions = []) => {
    const container = document.getElementById('dashboardQuickActions');

    if (!container) {
        return;
    }

    if (!actions.length) {
        container.innerHTML = '<div class="text-muted small">No quick actions available.</div>';
        return;
    }

    container.innerHTML = actions.map((action) => {
        if (!action.enabled) {
            return `<button type="button" class="dash-quick-action-btn" disabled title="${action.hint || 'Unavailable'}">${action.label}</button>`;
        }

        return `<a href="${action.url}" class="dash-quick-action-btn">${action.label}</a>`;
    }).join('');
};

const renderNewJoinees = (people = [], title = 'New Joinees') => {
    const container = document.getElementById('dashboardNewJoinees');
    const titleEl = document.getElementById('dashboardNewJoineesTitle');

    if (titleEl) {
        titleEl.textContent = title;
    }

    if (!container) {
        return;
    }

    if (!people.length) {
        container.innerHTML = '<div class="text-muted small">No employees with a joining date yet.</div>';
        return;
    }

    const sorted = [...people].sort((a, b) => (b.occasion_date || b.sort_key || '').localeCompare(a.occasion_date || a.sort_key || ''));
    container.innerHTML = sorted.map((person) => renderPersonChip(person)).join('');
};

const renderWidget = (widget) => {
    const variant = VARIANT_CLASS[widget.variant] || VARIANT_CLASS.primary;
    const value = widget.value ?? '—';
    const clickable = widget.clickable && widget.url;

    const inner = `
        <div class="stat-card ${variant} ${clickable ? 'stat-card-clickable' : ''}">
            <div class="stat-card-icon">${widget.icon || '📊'}</div>
            <div class="stat-card-body">
                <p class="stat-card-label">${widget.label}</p>
                <h3 class="stat-card-value ${String(value).length > 8 ? 'stat-card-value--compact' : ''}">${value}</h3>
                <span class="stat-card-meta">${widget.meta || ''}</span>
            </div>
        </div>
    `;

    if (!clickable) {
        return `<div class="col-sm-6 col-xl-3">${inner}</div>`;
    }

    return `
        <div class="col-sm-6 col-xl-3">
            <a href="${widget.url}" class="stat-card-link text-decoration-none" aria-label="${widget.label}">
                ${inner}
            </a>
        </div>
    `;
};

const updateLastRefreshed = () => {
    const label = document.getElementById('dashboardLastUpdated');

    if (!label) {
        return;
    }

    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    label.textContent = `Updated ${time}`;
    label.classList.remove('d-none');
};

const updateLiveClock = () => {
    const dateEl = document.getElementById('dashboardClockDate');
    const timeEl = document.getElementById('dashboardClockTime');

    if (!dateEl || !timeEl) {
        return;
    }

    const now = new Date();
    dateEl.textContent = now.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
    timeEl.textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
};

const renderDashboard = (payload) => {
    const helloName = document.getElementById('dashboardHelloName');
    const timezone = document.getElementById('dashboardClockTimezone');
    const punchWrap = document.getElementById('dashboardPunchWrap');
    const homeWidgets = document.getElementById('dashboardHomeWidgets');
    const loadingState = document.getElementById('dashboardLoadingState');
    const homeRoot = document.getElementById('dashboardHomeRoot');

    loadingState?.classList.add('d-none');
    homeRoot?.classList.remove('d-none');

    if (helloName) {
        helloName.textContent = payload.greeting_name || 'there';
    }

    if (timezone && payload.timezone_label) {
        timezone.textContent = payload.timezone_label;
    }

    renderCelebrations(payload.celebrations || {});
    renderPendingApprovals(payload.pending_approvals || [], payload.show_pending_approvals === true);
    renderQuickActions(payload.quick_actions || []);
    renderNewJoinees(payload.new_joinees || [], payload.new_joinees_title || 'New Joinees');

    if (homeWidgets) {
        const widgets = payload.widgets || [];

        if (widgets.length) {
            homeWidgets.classList.remove('d-none');
            homeWidgets.innerHTML = widgets.map(renderWidget).join('');
        } else {
            homeWidgets.classList.add('d-none');
            homeWidgets.innerHTML = '';
        }
    }

    if (punchWrap) {
        punchWrap.classList.toggle('d-none', !payload.show_punch_widget);
    }

    if (payload.show_punch_widget && document.getElementById('dashboardPunchBtn')) {
        if (!punchInitialized) {
            punchController = initAttendancePunch({
                prefix: 'dashboard',
                alertElementId: 'dashboardAttendanceAlert',
            });
            punchInitialized = true;
        } else {
            punchController?.refreshStatus?.();
        }
    }

    updateLastRefreshed();
};

const loadDashboard = async () => {
    try {
        const { data } = await api.get('/dashboard');
        renderDashboard(data.data || {});
    } catch (error) {
        const message = getErrorMessage(error);
        const loadingState = document.getElementById('dashboardLoadingState');
        const pendingEmpty = document.getElementById('dashboardPendingEmpty');

        loadingState?.classList.add('d-none');
        document.getElementById('dashboardHomeRoot')?.classList.remove('d-none');

        if (pendingEmpty) {
            pendingEmpty.textContent = message;
            pendingEmpty.classList.remove('d-none');
        }
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('dashboardHomeRoot')) {
        return;
    }

    updateLiveClock();
    clockTimer = window.setInterval(updateLiveClock, 1000);

    await loadDashboard();

    refreshTimer = window.setInterval(loadDashboard, 30000);

    window.addEventListener('beforeunload', () => {
        if (refreshTimer) {
            window.clearInterval(refreshTimer);
        }

        if (clockTimer) {
            window.clearInterval(clockTimer);
        }
    });
});
