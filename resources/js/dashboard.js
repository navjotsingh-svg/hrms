import { renderDateTimeStackFromLabel } from './datetime-utils';
import Swal from 'sweetalert2';
import api, { getErrorMessage } from './api';
import { initAttendancePunch } from './attendance-punch';
import {
    bindRequestReviewHandlers,
    bulkReviewRequests,
    renderRequestActions,
} from './request-review';
import { bindPagination, bindPerPageSelect, paginateArray, readPerPage, renderListPagination } from './pagination';
import { renderEmployeeNameBlock } from './request-display';
import { prependAutoDismissAlert } from './form-utils';

import { renderAvatarHtml } from './avatar';

let clockTimer = null;
let punchInitialized = false;
let punchController = null;
let pendingPage = 1;
let pendingItems = [];
let pendingPagination = null;
let selectedPendingKeys = new Set();
const pendingPerPageSelect = document.getElementById('dashboardPendingPerPage');
const pendingPaginationWrap = document.getElementById('dashboardPendingPaginationWrap');
let pendingPerPage = readPerPage(pendingPerPageSelect);

let myRequestsPage = 1;
let myRequestsItems = [];
const myRequestsPerPageSelect = document.getElementById('dashboardMyRequestsPerPage');
const myRequestsPaginationWrap = document.getElementById('dashboardMyRequestsPaginationWrap');
let myRequestsPerPage = readPerPage(myRequestsPerPageSelect);

const statusBadgeClass = (status) => ({
    pending: 'text-bg-warning',
    approved: 'text-bg-success',
    rejected: 'text-bg-danger',
    cancelled: 'text-bg-secondary',
}[status] || 'text-bg-light');

const renderPersonChip = (person) => `
    <div class="dash-person-chip dash-person-chip--static" title="${person.name}">
        ${renderAvatarHtml({
            name: person.name,
            photoUrl: person.profile_photo_url,
            initials: person.initials,
            className: 'dash-person-avatar',
        })}
        <span class="dash-person-meta">
            <span class="dash-person-name">${person.name}</span>
            <span class="dash-person-date">${person.date_label || person.joined_label || ''}</span>
        </span>
    </div>
`;

const renderCelebrationEmpty = (title, message, variant = 'birthdays') => `
    <div class="dash-celebration-empty dash-celebration-empty--${variant}">
        <div class="dash-celebration-empty-art" aria-hidden="true">${variant === 'anniversaries' ? '🏆' : '🎂'}</div>
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
                'anniversaries',
            );
    }
};

const updatePendingBulkBar = () => {
    const bar = document.getElementById('dashboardPendingBulkBar');
    const countLabel = document.getElementById('dashboardPendingSelectedCount');
    const count = selectedPendingKeys.size;

    bar?.classList.toggle('d-none', count === 0);
    bar?.classList.toggle('d-flex', count > 0);

    if (countLabel) {
        countLabel.textContent = `${count} selected`;
    }
};

const updatePendingSelectAll = () => {
    const selectAll = document.getElementById('dashboardPendingSelectAll');
    const reviewable = pendingItems.filter((item) => item.can_review && item.review_kind && item.review_target);

    if (!selectAll) {
        return;
    }

    selectAll.disabled = reviewable.length === 0;
    selectAll.checked = reviewable.length > 0 && reviewable.every((item) => selectedPendingKeys.has(item.key));
    selectAll.indeterminate = reviewable.some((item) => selectedPendingKeys.has(item.key))
        && !selectAll.checked;
};

const renderPendingApprovals = (items = [], pagination = null, showSection = true) => {
    const card = document.getElementById('dashboardPendingCard');
    const body = document.getElementById('dashboardPendingBody');
    const count = document.getElementById('dashboardPendingCount');
    const paginationWrap = document.getElementById('dashboardPendingPaginationWrap');
    const paginationInfo = document.getElementById('dashboardPendingPaginationInfo');
    const paginationList = document.getElementById('dashboardPendingPaginationList');

    card?.classList.toggle('d-none', !showSection);

    if (!showSection || !body) {
        return;
    }

    pendingItems = items;

    if (pagination) {
        pendingPagination = pagination;
    }

    const activePagination = pagination || pendingPagination;
    const totalCount = activePagination?.total ?? items.length;

    if (count) {
        count.textContent = String(totalCount);
        count.classList.toggle('d-none', totalCount === 0);
    }

    if (!items.length) {
        body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Well done. No request approvals.</td></tr>';
        paginationWrap?.classList.add('d-none');
        selectedPendingKeys.clear();
        updatePendingBulkBar();
        updatePendingSelectAll();
        return;
    }

    body.innerHTML = items.map((item) => {
        const canSelect = item.can_review && item.review_kind && item.review_target;

        return `
        <tr>
            <td>
                ${canSelect ? `<input type="checkbox" class="form-check-input dashboard-pending-select" data-pending-key="${item.key}" aria-label="Select request from ${item.requester_name || 'employee'}" ${selectedPendingKeys.has(item.key) ? 'checked' : ''}>` : ''}
            </td>
            <td>${renderEmployeeNameBlock(item.requester_name, item.requester_code)}</td>
            <td>${item.category_label || item.subject || 'Request'}</td>
            <td>${renderDateTimeStackFromLabel(item.submitted_at_label)}</td>
            <td><span class="badge text-bg-warning">${item.status_label || 'Pending'}</span></td>
            <td class="text-end">${renderRequestActions(item, { includeReview: true })}</td>
        </tr>
    `;
    }).join('');

    if (activePagination && activePagination.total > 0) {
        paginationWrap?.classList.remove('d-none');
        renderListPagination({
            infoEl: paginationInfo,
            listEl: paginationList,
            perPageSelectEl: pendingPerPageSelect,
            pagination: activePagination,
            itemLabel: 'pending requests',
            emptyMessage: 'No pending requests',
        });
    } else {
        paginationWrap?.classList.add('d-none');
    }

    updatePendingBulkBar();
    updatePendingSelectAll();
};

const renderMyRequests = (items = [], showSection = true, page = myRequestsPage) => {
    const card = document.getElementById('dashboardMyRequestsCard');
    const body = document.getElementById('dashboardMyRequestsBody');
    const paginationWrap = document.getElementById('dashboardMyRequestsPaginationWrap');
    const paginationInfo = document.getElementById('dashboardMyRequestsPaginationInfo');
    const paginationList = document.getElementById('dashboardMyRequestsPaginationList');

    card?.classList.toggle('d-none', !showSection);

    if (!showSection || !body) {
        return;
    }

    myRequestsItems = items;
    myRequestsPage = page;

    const { items: pageItems, pagination } = paginateArray(items, myRequestsPage, myRequestsPerPage);

    if (!pageItems.length) {
        body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No requests submitted yet.</td></tr>';
        paginationWrap?.classList.add('d-none');
        return;
    }

    body.innerHTML = pageItems.map((item) => `
        <tr>
            <td>${item.category_label || item.subject || 'Request'}</td>
            <td>${item.detail || item.subject || '—'}</td>
            <td>${renderDateTimeStackFromLabel(item.submitted_at_label)}</td>
            <td><span class="badge ${statusBadgeClass(item.status)}">${item.status_label || 'Pending'}</span></td>
            <td class="text-end">${renderRequestActions(item)}</td>
        </tr>
    `).join('');

    if (pagination.total > 0) {
        paginationWrap?.classList.remove('d-none');
        renderListPagination({
            infoEl: paginationInfo,
            listEl: paginationList,
            perPageSelectEl: myRequestsPerPageSelect,
            pagination,
            itemLabel: 'requests',
            emptyMessage: 'No requests submitted yet.',
        });
    } else {
        paginationWrap?.classList.add('d-none');
    }
};

const loadMyRequests = async () => {
    const showSection = document.getElementById('dashboardMyRequestsCard')
        && !document.getElementById('dashboardMyRequestsCard').classList.contains('d-none');

    if (!showSection) {
        return;
    }

    try {
        const { data } = await api.get('/request-hub/mine');
        renderMyRequests(data.data?.requests || [], true, myRequestsPage);
    } catch (error) {
        const body = document.getElementById('dashboardMyRequestsBody');
        if (body) {
            body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${getErrorMessage(error)}</td></tr>`;
        }
    }
};

const loadPendingApprovals = async (page = pendingPage) => {
    const showSection = document.getElementById('dashboardPendingCard') && !document.getElementById('dashboardPendingCard').classList.contains('d-none');

    if (!showSection) {
        return;
    }

    try {
        const { data } = await api.get('/request-hub/pending', {
            params: { page, per_page: pendingPerPage },
        });

        pendingPage = page;
        renderPendingApprovals(data.data?.requests || [], data.data?.pagination || null, true);
    } catch (error) {
        const body = document.getElementById('dashboardPendingBody');
        if (body) {
            body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${getErrorMessage(error)}</td></tr>`;
        }
    }
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
    renderMyRequests([], payload.show_my_requests === true);
    renderPendingApprovals([], null, payload.show_pending_approvals === true);
    renderQuickActions(payload.quick_actions || []);
    renderNewJoinees(payload.new_joinees || [], payload.new_joinees_title || 'New Joinees');

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

        if (data.data?.show_my_requests) {
            await loadMyRequests();
        }

        if (data.data?.show_pending_approvals) {
            await loadPendingApprovals(pendingPage);
        }
    } catch (error) {
        const message = getErrorMessage(error);
        const loadingState = document.getElementById('dashboardLoadingState');
        const myRequestsBody = document.getElementById('dashboardMyRequestsBody');
        const pendingBody = document.getElementById('dashboardPendingBody');

        loadingState?.classList.add('d-none');
        document.getElementById('dashboardHomeRoot')?.classList.remove('d-none');

        if (myRequestsBody) {
            myRequestsBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${message}</td></tr>`;
        }

        if (pendingBody) {
            pendingBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${message}</td></tr>`;
        }
    }
};

const showReviewError = (error) => {
    Swal.fire({
        icon: 'error',
        title: 'Action failed',
        text: getErrorMessage(error),
        confirmButtonColor: '#0d6efd',
    });
};

document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('dashboardHomeRoot')) {
        return;
    }

    updateLiveClock();
    clockTimer = window.setInterval(updateLiveClock, 1000);

    await loadDashboard();

    const pendingCard = document.getElementById('dashboardPendingCard');
    const myRequestsCard = document.getElementById('dashboardMyRequestsCard');

    bindRequestReviewHandlers(myRequestsCard, {
        onSuccess: async (message) => {
            prependAutoDismissAlert(
                myRequestsCard?.querySelector('.dash-home-card-body'),
                message,
                'success',
                { className: 'mx-3 mt-3' },
            );
            await loadMyRequests();
        },
        onError: (error) => {
            showReviewError(error);
        },
    });

    bindRequestReviewHandlers(pendingCard, {
        onSuccess: async (message) => {
            prependAutoDismissAlert(
                pendingCard?.querySelector('.dash-home-card-body'),
                message,
                'success',
                { className: 'mx-3 mt-3' },
            );
            selectedPendingKeys.clear();
            await loadPendingApprovals(pendingPage);
            await loadDashboard();
        },
        onError: (error) => {
            showReviewError(error);
        },
    });

    document.getElementById('dashboardPendingSelectAll')?.addEventListener('change', (event) => {
        const checked = event.target.checked;
        pendingItems
            .filter((item) => item.can_review && item.review_kind && item.review_target)
            .forEach((item) => {
                if (checked) {
                    selectedPendingKeys.add(item.key);
                } else {
                    selectedPendingKeys.delete(item.key);
                }
            });
        renderPendingApprovals(pendingItems, pendingPagination, true);
    });

    pendingCard?.addEventListener('change', (event) => {
        const checkbox = event.target.closest('.dashboard-pending-select');
        if (!checkbox) return;

        const key = checkbox.dataset.pendingKey;
        if (checkbox.checked) {
            selectedPendingKeys.add(key);
        } else {
            selectedPendingKeys.delete(key);
        }

        updatePendingBulkBar();
        updatePendingSelectAll();
    });

    document.getElementById('dashboardPendingBulkApprove')?.addEventListener('click', async () => {
        const items = pendingItems.filter((item) => selectedPendingKeys.has(item.key));
        if (!items.length) return;

        try {
            const result = await bulkReviewRequests(items, 'approve');
            if (!result) return;
            selectedPendingKeys.clear();
            await loadPendingApprovals(pendingPage);
            await loadDashboard();
        } catch (error) {
            showReviewError(error);
        }
    });

    document.getElementById('dashboardPendingBulkReject')?.addEventListener('click', async () => {
        const items = pendingItems.filter((item) => selectedPendingKeys.has(item.key));
        if (!items.length) return;

        try {
            const result = await bulkReviewRequests(items, 'reject');
            if (!result) return;
            selectedPendingKeys.clear();
            await loadPendingApprovals(pendingPage);
            await loadDashboard();
        } catch (error) {
            showReviewError(error);
        }
    });

    bindPagination(pendingPaginationWrap, async (page) => {
        selectedPendingKeys.clear();
        await loadPendingApprovals(page);
    });

    bindPerPageSelect(pendingPerPageSelect, async (perPage) => {
        pendingPerPage = perPage;
        selectedPendingKeys.clear();
        await loadPendingApprovals(1);
    });

    bindPagination(myRequestsPaginationWrap, async (page) => {
        renderMyRequests(myRequestsItems, true, page);
    });

    bindPerPageSelect(myRequestsPerPageSelect, async (perPage) => {
        myRequestsPerPage = perPage;
        renderMyRequests(myRequestsItems, true, 1);
    });

    window.addEventListener('beforeunload', () => {
        if (clockTimer) {
            window.clearInterval(clockTimer);
        }
    });
});
