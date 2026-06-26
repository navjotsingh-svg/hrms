import api, { getErrorMessage } from './api';
import { bindEmployeeSearchSelect } from './employee-autocomplete';

const pad = (value) => String(value).padStart(2, '0');

const todayInputValue = (date = new Date()) => [
    date.getFullYear(),
    pad(date.getMonth() + 1),
    pad(date.getDate()),
].join('-');

const statusPillClass = (status, awaitingPunchOut = false) => {
    if (awaitingPunchOut) {
        return 'attendance-status-pill attendance-status-pill--pending';
    }

    return ({
        present: 'attendance-status-pill attendance-status-pill--present',
        half_day: 'attendance-status-pill attendance-status-pill--half-day',
        absent: 'attendance-status-pill attendance-status-pill--absent',
        on_leave: 'attendance-status-pill attendance-status-pill--on-leave',
        incomplete: 'attendance-status-pill attendance-status-pill--pending',
        regularization_pending: 'attendance-status-pill attendance-status-pill--regularization',
        holiday: 'attendance-status-pill attendance-status-pill--holiday',
        weekly_off: 'attendance-status-pill attendance-status-pill--weekly-off',
        short_leave: 'attendance-status-pill attendance-status-pill--half-day',
    }[status] || 'attendance-status-pill attendance-status-pill--muted');
};

const markedPillClass = (label) => {
    if (label === 'Yes') {
        return 'company-status-pill company-status-pill--active';
    }

    if (label === 'Partial') {
        return 'company-status-pill company-status-pill--inactive';
    }

    if (label === 'No') {
        return 'company-status-pill company-status-pill--danger';
    }

    return 'text-muted';
};

document.addEventListener('DOMContentLoaded', () => {
    const alertBox = document.getElementById('attendanceTodayAlert');
    const subtitle = document.getElementById('attendanceTodaySubtitle');
    const dateInput = document.getElementById('attendanceTodayDate');
    const refreshBtn = document.getElementById('attendanceTodayRefresh');
    const prevDayBtn = document.getElementById('attendanceTodayPrevDay');
    const nextDayBtn = document.getElementById('attendanceTodayNextDay');
    const goTodayBtn = document.getElementById('attendanceTodayGoToday');
    const resetBtn = document.getElementById('attendanceTodayReset');
    const departmentSelect = document.getElementById('attendanceTodayDepartment');
    const statusSelect = document.getElementById('attendanceTodayStatus');
    const markedSelect = document.getElementById('attendanceTodayMarkedFilter');
    const tableBody = document.getElementById('attendanceTodayTableBody');

    const summaryEls = {
        total: document.getElementById('attendanceTodayTotal'),
        present: document.getElementById('attendanceTodayPresent'),
        halfDay: document.getElementById('attendanceTodayHalfDay'),
        absent: document.getElementById('attendanceTodayAbsent'),
        onLeave: document.getElementById('attendanceTodayOnLeave'),
        incomplete: document.getElementById('attendanceTodayIncomplete'),
        marked: document.getElementById('attendanceTodayMarked'),
        notMarked: document.getElementById('attendanceTodayNotMarked'),
    };

    let employees = [];
    let employeeSearch = null;

    if (dateInput) {
        dateInput.value = todayInputValue();
        dateInput.max = todayInputValue();
    }

    const syncTodayNavButtons = () => {
        const isToday = dateInput?.value === todayInputValue();

        if (nextDayBtn) {
            nextDayBtn.disabled = isToday;
        }

        if (goTodayBtn) {
            goTodayBtn.disabled = isToday;
        }
    };

    const setSelectedDate = (value) => {
        if (!dateInput || !value) {
            return;
        }

        dateInput.value = value;
        syncTodayNavButtons();
        loadOverview();
    };

    const shiftSelectedDate = (days) => {
        if (!dateInput?.value) {
            return;
        }

        const date = new Date(`${dateInput.value}T00:00:00`);
        date.setDate(date.getDate() + days);

        if (date > new Date()) {
            return;
        }

        setSelectedDate(todayInputValue(date));
    };

    employeeSearch = bindEmployeeSearchSelect({
        inputId: 'attendanceTodayEmployeeInput',
        hiddenId: 'attendanceTodayEmployeeId',
        onSelect: () => {
            renderTable();
        },
        onClear: () => {
            renderTable();
        },
    });

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const populateDepartments = (rows) => {
        if (!departmentSelect) {
            return;
        }

        const departments = [...new Set(rows.map((row) => row.department).filter(Boolean))].sort();

        departmentSelect.innerHTML = [
            '<option value="">All departments</option>',
            ...departments.map((department) => `<option value="${department}">${department}</option>`),
        ].join('');
    };

    const renderSummary = (summary, dateLabel, isToday) => {
        summaryEls.total.textContent = String(summary.total || 0);
        summaryEls.present.textContent = String(summary.present || 0);
        summaryEls.halfDay.textContent = String(summary.half_day || 0);
        summaryEls.absent.textContent = String(summary.absent || 0);
        summaryEls.onLeave.textContent = String(summary.on_leave || 0);
        summaryEls.incomplete.textContent = String(summary.incomplete || 0);
        summaryEls.marked.textContent = String(summary.marked || 0);
        summaryEls.notMarked.textContent = String(summary.not_marked || 0);

        if (subtitle) {
            subtitle.textContent = isToday
                ? `Live view for ${dateLabel}.`
                : `Attendance snapshot for ${dateLabel}.`;
        }

        syncTodayNavButtons();
    };

    const filteredRows = () => {
        const selectedEmployeeId = employeeSearch?.getSelectedId?.() || null;
        const department = departmentSelect?.value || '';
        const status = statusSelect?.value || '';
        const marked = markedSelect?.value || '';

        return employees.filter((row) => {
            if (selectedEmployeeId && Number(row.employee_id) !== Number(selectedEmployeeId)) {
                return false;
            }

            if (department && row.department !== department) {
                return false;
            }

            if (status && row.status !== status) {
                return false;
            }

            if (marked === 'yes' && row.marked_label !== 'Yes') {
                return false;
            }

            if (marked === 'no' && row.marked_label !== 'No') {
                return false;
            }

            if (marked === 'partial' && row.marked_label !== 'Partial') {
                return false;
            }

            return true;
        });
    };

    const renderTable = () => {
        if (!tableBody) {
            return;
        }

        const rows = filteredRows();

        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No employees match the selected filters.</td></tr>';
            return;
        }

        tableBody.innerHTML = rows.map((row, index) => {
            const statusLabel = row.status_label
                || (row.leave_type_name ? `${row.leave_type_name}${row.leave_session_label ? ` · ${row.leave_session_label}` : ''}` : '—');

            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <div class="fw-semibold">${row.employee_name || '—'}</div>
                        <div class="small text-muted">${row.employee_code || row.employee_id || ''}</div>
                    </td>
                    <td>${row.department || '—'}</td>
                    <td>${row.punch_in_label || '—'}</td>
                    <td>${row.punch_out_label || '—'}</td>
                    <td>${row.worked_hours_label || '—'}</td>
                    <td>
                        ${row.marked_label === '—'
                            ? '<span class="text-muted">—</span>'
                            : `<span class="company-status-pill ${markedPillClass(row.marked_label)}">${row.marked_label}</span>`}
                    </td>
                    <td>
                        <span class="${statusPillClass(row.status, row.awaiting_punch_out)}">${statusLabel}</span>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const loadOverview = async () => {
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading attendance...</td></tr>';

        try {
            const params = {};

            if (dateInput?.value) {
                params.date = dateInput.value;
            }

            const { data } = await api.get('/attendance/today-overview', { params });
            const payload = data.data || {};

            employees = payload.employees || [];
            populateDepartments(employees);
            renderSummary(payload.summary || {}, payload.date_label || '—', payload.is_today);
            renderTable();
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${getErrorMessage(error)}</td></tr>`;
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const resetFilters = () => {
        employeeSearch?.clearSelection();

        if (departmentSelect) {
            departmentSelect.value = '';
        }

        if (statusSelect) {
            statusSelect.value = '';
        }

        if (markedSelect) {
            markedSelect.value = '';
        }

        renderTable();
    };

    [departmentSelect, statusSelect, markedSelect].forEach((element) => {
        element?.addEventListener('change', renderTable);
    });

    dateInput?.addEventListener('change', () => {
        syncTodayNavButtons();
        loadOverview();
    });
    refreshBtn?.addEventListener('click', loadOverview);
    prevDayBtn?.addEventListener('click', () => shiftSelectedDate(-1));
    nextDayBtn?.addEventListener('click', () => shiftSelectedDate(1));
    goTodayBtn?.addEventListener('click', () => setSelectedDate(todayInputValue()));
    resetBtn?.addEventListener('click', resetFilters);

    syncTodayNavButtons();
    loadOverview();
});
