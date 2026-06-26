import api, { getErrorMessage } from './api';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const parseDate = (value) => {
    const [year, month, day] = String(value).split('-').map(Number);
    return new Date(year, month - 1, day);
};

const barClassForPosition = (dateString, entry) => {
    const isSingle = entry.from_date === entry.to_date;
    if (isSingle) return 'is-single';

    const date = parseDate(dateString);
    const dayOfWeek = date.getDay() === 0 ? 7 : date.getDay();
    const isStart = dateString === entry.from_date || dayOfWeek === 1;
    const isEnd = dateString === entry.to_date || dayOfWeek === 7;

    if (isStart && isEnd) return 'is-single';
    if (isStart) return 'is-start';
    if (isEnd) return 'is-end';
    return 'is-middle';
};

const statusClassForEntry = (status) => {
    if (status === 'pending') return 'is-pending';
    if (status === 'rejected') return 'is-rejected';

    return '';
};

const renderSummaryText = (summary) => {
    const parts = [
        `${summary.leave_requests} leave request(s)`,
        `${summary.employees_on_leave} employee(s)`,
    ];

    if (summary.approved) parts.push(`${summary.approved} approved`);
    if (summary.pending) parts.push(`${summary.pending} pending`);
    if (summary.rejected) parts.push(`${summary.rejected} rejected`);

    return parts.join(' · ');
};

const entriesForDate = (entries, dateString) => entries.filter((entry) => (
    dateString >= entry.from_date && dateString <= entry.to_date
));

export const initLeaveCalendar = ({ prefix = 'leaveCalendar', onLoad = null } = {}) => {
    const grid = document.getElementById(`${prefix}Grid`);
    const monthLabel = document.getElementById(`${prefix}MonthLabel`);
    const summaryEl = document.getElementById(`${prefix}Summary`);
    const legendsEl = document.getElementById(`${prefix}Legends`);
    const alertBox = document.getElementById(`${prefix}Alert`);
    const showHolidaysToggle = document.getElementById(`${prefix}ShowHolidays`);

    if (!grid) {
        return null;
    }

    let currentYear = new Date().getFullYear();
    let currentMonth = new Date().getMonth() + 1;
    let loadedOnce = false;

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderLegends = (leaveTypes) => {
        if (!legendsEl) return;

        const typeLegends = leaveTypes.length
            ? leaveTypes.map((type) => `
                <div class="leave-calendar-legend-item">
                    <span class="leave-calendar-legend-swatch" style="background:${escapeHtml(type.color)}"></span>
                    <span>${escapeHtml(type.name)}</span>
                </div>
            `).join('')
            : '<div class="text-muted small mb-2">No leave types configured.</div>';

        legendsEl.innerHTML = `
            ${typeLegends}
            <div class="leave-calendar-legend-divider"></div>
            <div class="leave-calendar-legend-item">
                <span class="leave-calendar-legend-swatch leave-calendar-legend-swatch--status is-approved"></span>
                <span>Approved</span>
            </div>
            <div class="leave-calendar-legend-item">
                <span class="leave-calendar-legend-swatch leave-calendar-legend-swatch--status is-pending"></span>
                <span>Pending</span>
            </div>
            <div class="leave-calendar-legend-item">
                <span class="leave-calendar-legend-swatch leave-calendar-legend-swatch--status is-rejected"></span>
                <span>Rejected</span>
            </div>
        `;
    };

    const renderCalendar = (calendar) => {
        if (monthLabel) monthLabel.textContent = calendar.month_label;
        if (summaryEl) {
            summaryEl.textContent = renderSummaryText(calendar.summary);
        }
        renderLegends(calendar.leave_types);

        const holidaysByDate = {};
        if (showHolidaysToggle?.checked) {
            (calendar.holidays || []).forEach((holiday) => {
                holidaysByDate[holiday.date] = holiday;
            });
        }

        grid.innerHTML = calendar.days.map((day) => {
            const dayEntries = entriesForDate(calendar.entries, day.date);
            const holiday = holidaysByDate[day.date];

            const bars = dayEntries.map((entry) => `
                <span
                    class="leave-calendar-bar ${barClassForPosition(day.date, entry)} ${statusClassForEntry(entry.status)}"
                    style="background:${escapeHtml(entry.color)}"
                    title="${escapeHtml(entry.label)}${entry.leave_type ? ` · ${escapeHtml(entry.leave_type)}` : ''}${entry.status_label ? ` · ${escapeHtml(entry.status_label)}` : ''}"
                >${escapeHtml(entry.label)}</span>
            `).join('');

            return `
                <div class="leave-calendar-day${day.is_current_month ? '' : ' is-outside'}${day.is_today ? ' is-today' : ''}" data-date="${escapeHtml(day.date)}">
                    <div class="leave-calendar-day-number">${day.day}</div>
                    ${holiday ? `<div class="leave-calendar-holiday" title="${escapeHtml(holiday.name)}">${escapeHtml(holiday.name)}</div>` : ''}
                    <div class="leave-calendar-bars">${bars}</div>
                </div>
            `;
        }).join('');
    };

    const load = async () => {
        grid.innerHTML = '<div class="leave-calendar-loading text-muted">Loading calendar…</div>';

        try {
            const { data } = await api.get('/leave-calendar', {
                params: {
                    year: currentYear,
                    month: currentMonth,
                    include_holidays: showHolidaysToggle?.checked ? 1 : 0,
                },
            });

            renderCalendar(data.data.calendar);
            loadedOnce = true;

            if (typeof onLoad === 'function') {
                onLoad(data.data.calendar);
            }
        } catch (error) {
            grid.innerHTML = '<div class="leave-calendar-loading text-danger">Unable to load calendar.</div>';
            showAlert(getErrorMessage(error));
        }
    };

    const goToToday = () => {
        const today = new Date();
        currentYear = today.getFullYear();
        currentMonth = today.getMonth() + 1;
        load();
    };

    document.getElementById(`${prefix}PrevBtn`)?.addEventListener('click', () => {
        currentMonth -= 1;
        if (currentMonth < 1) {
            currentMonth = 12;
            currentYear -= 1;
        }
        load();
    });

    document.getElementById(`${prefix}NextBtn`)?.addEventListener('click', () => {
        currentMonth += 1;
        if (currentMonth > 12) {
            currentMonth = 1;
            currentYear += 1;
        }
        load();
    });

    document.getElementById(`${prefix}TodayBtn`)?.addEventListener('click', goToToday);
    showHolidaysToggle?.addEventListener('change', load);

    if (prefix === 'leaveCalendar') {
        load();
    }

    return {
        load,
        goToToday,
        isLoaded: () => loadedOnce,
    };
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('leaveCalendarGrid')) {
        initLeaveCalendar({ prefix: 'leaveCalendar' });
    }

    const modalController = document.getElementById('topbarLeaveCalendarGrid')
        ? initLeaveCalendar({ prefix: 'topbarLeaveCalendar' })
        : null;
    const modalEl = document.getElementById('leaveCalendarModal');

    if (modalEl && modalController) {
        modalEl.addEventListener('shown.bs.modal', () => {
            if (!modalController.isLoaded()) {
                modalController.load();
            }
        });
    }
});
