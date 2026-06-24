import api, { getErrorMessage } from './api';
import { debounce } from './form-utils';
import { Modal } from 'bootstrap';

const pad = (value) => String(value).padStart(2, '0');

const monthKey = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

const monthLabel = (key) => {
    const date = new Date(`${key}-01T00:00:00`);

    return date.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const cellClass = (status, awaitingPunchOut = false) => {
    if (awaitingPunchOut) {
        return 'attendance-matrix-cell attendance-matrix-cell--incomplete';
    }

    return ({
        present: 'attendance-matrix-cell attendance-matrix-cell--present',
        half_day: 'attendance-matrix-cell attendance-matrix-cell--half-day',
        short_leave: 'attendance-matrix-cell attendance-matrix-cell--short-leave',
        absent: 'attendance-matrix-cell attendance-matrix-cell--absent',
        on_leave: 'attendance-matrix-cell attendance-matrix-cell--on-leave',
        holiday: 'attendance-matrix-cell attendance-matrix-cell--holiday',
        weekly_off: 'attendance-matrix-cell attendance-matrix-cell--weekly-off',
        regularization_pending: 'attendance-matrix-cell attendance-matrix-cell--regularization-pending',
        incomplete: 'attendance-matrix-cell attendance-matrix-cell--incomplete',
        before_portal: 'attendance-matrix-cell attendance-matrix-cell--muted',
        future: 'attendance-matrix-cell attendance-matrix-cell--muted',
    }[status] || 'attendance-matrix-cell attendance-matrix-cell--muted');
};

const cellTitle = (cell) => {
    const parts = [cell.status_label || cell.status];

    if (cell.punch_in_label) {
        parts.push(`In: ${cell.punch_in_label}`);
    }

    if (cell.punch_out_label) {
        parts.push(`Out: ${cell.punch_out_label}`);
    }

    if (cell.worked_hours_label && cell.worked_hours_label !== '0m') {
        parts.push(`Worked: ${cell.worked_hours_label}`);
    }

    if (cell.holiday_name) {
        parts.push(cell.holiday_name);
    }

    if (cell.leave_type_name) {
        parts.push(cell.leave_type_name);
    }

    return parts.filter(Boolean).join(' · ');
};

document.addEventListener('DOMContentLoaded', async () => {
    const alertBox = document.getElementById('attendanceOverviewAlert');
    const subtitle = document.getElementById('attendanceOverviewSubtitle');
    const monthLabelEl = document.getElementById('attendanceOverviewMonthLabel');
    const prevMonthBtn = document.getElementById('attendanceOverviewPrevMonth');
    const nextMonthBtn = document.getElementById('attendanceOverviewNextMonth');
    const departmentSelect = document.getElementById('attendanceOverviewDepartment');
    const statusSelect = document.getElementById('attendanceOverviewStatus');
    const searchInput = document.getElementById('attendanceOverviewSearch');
    const resetBtn = document.getElementById('attendanceOverviewReset');
    const matrixHead = document.getElementById('attendanceMatrixHead');
    const matrixBody = document.getElementById('attendanceMatrixBody');
    const overviewTitle = document.getElementById('attendanceOverviewTitle');
    const paginationInfo = document.getElementById('attendanceOverviewPaginationInfo');
    const paginationSummary = document.getElementById('attendanceOverviewPaginationSummary');
    const paginationList = document.getElementById('attendanceOverviewPaginationList');
    const dayModalEl = document.getElementById('attendanceOverviewDayModal');
    const dayModalTitle = document.getElementById('attendanceOverviewDayModalTitle');
    const dayModalSubtitle = document.getElementById('attendanceOverviewDayModalSubtitle');
    const dayModalBody = document.getElementById('attendanceOverviewDayModalBody');
    const employeeCalendarLink = document.getElementById('attendanceOverviewEmployeeCalendarLink');

    const summaryEls = {
        employees: document.getElementById('attendanceOverviewEmployees'),
        present: document.getElementById('attendanceOverviewPresent'),
        halfDay: document.getElementById('attendanceOverviewHalfDay'),
        absent: document.getElementById('attendanceOverviewAbsent'),
        onLeave: document.getElementById('attendanceOverviewOnLeave'),
        incomplete: document.getElementById('attendanceOverviewIncomplete'),
    };

    if (!matrixHead || !matrixBody) {
        return;
    }

    let currentMonth = monthKey();
    let currentPage = 1;
    let dayColumns = [];
    let dayModal = dayModalEl ? Modal.getOrCreateInstance(dayModalEl) : null;
    let selectedEmployeeId = null;

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const filters = () => ({
        month: currentMonth,
        department_id: departmentSelect?.value || undefined,
        status: statusSelect?.value || 'active',
        search: searchInput?.value?.trim() || undefined,
        page: currentPage,
        per_page: 25,
    });

    const renderSummary = (summary, scope) => {
        summaryEls.employees.textContent = String(summary?.employees || 0);
        summaryEls.present.textContent = String(summary?.present || 0);
        summaryEls.halfDay.textContent = String(summary?.half_day || 0);
        summaryEls.absent.textContent = String(summary?.absent || 0);
        summaryEls.onLeave.textContent = String(summary?.on_leave || 0);
        summaryEls.incomplete.textContent = String(
            (summary?.incomplete || 0) + (summary?.regularization_pending || 0),
        );

        if (subtitle) {
            subtitle.textContent = scope === 'team'
                ? 'Team attendance matrix — click any day for punch details.'
                : 'Company-wide attendance matrix — click any day for punch details.';
        }
    };

    const renderMatrixHead = () => {
        if (!dayColumns.length) {
            matrixHead.innerHTML = '<tr><th colspan="8" class="text-muted py-4">No days in month.</th></tr>';

            return;
        }

        matrixHead.innerHTML = `
            <tr>
                <th class="attendance-matrix-sticky attendance-matrix-sticky--code">Code</th>
                <th class="attendance-matrix-sticky attendance-matrix-sticky--name">Employee</th>
                <th class="attendance-matrix-sticky attendance-matrix-sticky--dept">Dept</th>
                <th class="attendance-matrix-sticky attendance-matrix-sticky--stat text-center" title="Present days">P</th>
                <th class="attendance-matrix-sticky attendance-matrix-sticky--stat text-center" title="Absent days">A</th>
                <th class="attendance-matrix-sticky attendance-matrix-sticky--stat text-center" title="Leave days">L</th>
                ${dayColumns.map((day) => `
                    <th class="attendance-matrix-day ${day.is_today ? 'is-today' : ''} ${day.is_weekend ? 'is-weekend' : ''}" title="${escapeHtml(day.date)}">
                        <span class="attendance-matrix-day-num">${day.day}</span>
                        <span class="attendance-matrix-day-week">${escapeHtml(day.weekday)}</span>
                    </th>
                `).join('')}
            </tr>
        `;
    };

    const renderMatrixBody = (employees) => {
        if (!dayColumns.length) {
            matrixBody.innerHTML = '';

            return;
        }

        if (!employees.length) {
            matrixBody.innerHTML = `<tr><td colspan="${dayColumns.length + 6}" class="text-center text-muted py-5">No employees found for the selected filters.</td></tr>`;

            return;
        }

        matrixBody.innerHTML = employees.map((employee) => `
            <tr class="companies-data-row">
                <td class="attendance-matrix-sticky attendance-matrix-sticky--code">
                    <span class="fw-semibold">${escapeHtml(employee.employee_code)}</span>
                </td>
                <td class="attendance-matrix-sticky attendance-matrix-sticky--name">
                    <span class="fw-semibold">${escapeHtml(employee.full_name)}</span>
                    ${employee.designation ? `<div class="small text-muted">${escapeHtml(employee.designation)}</div>` : ''}
                </td>
                <td class="attendance-matrix-sticky attendance-matrix-sticky--dept">${escapeHtml(employee.department || '—')}</td>
                <td class="attendance-matrix-sticky attendance-matrix-sticky--stat text-center fw-semibold text-success">${employee.summary?.present ?? 0}</td>
                <td class="attendance-matrix-sticky attendance-matrix-sticky--stat text-center fw-semibold text-danger">${employee.summary?.absent ?? 0}</td>
                <td class="attendance-matrix-sticky attendance-matrix-sticky--stat text-center fw-semibold text-info">${employee.summary?.on_leave ?? 0}</td>
                ${(employee.cells || []).map((cell) => {
                    const label = cell.abbrev || '·';
                    const clickable = cell.is_clickable ? ' attendance-matrix-cell-btn' : '';
                    const attrs = cell.is_clickable
                        ? ` data-employee-id="${employee.id}" data-date="${cell.date}" role="button" tabindex="0"`
                        : '';

                    return `<td class="text-center${cell.is_today ? ' is-today-col' : ''}">
                        <span class="${cellClass(cell.status, cell.awaiting_punch_out)}${clickable}" title="${escapeHtml(cellTitle(cell))}"${attrs}>${escapeHtml(label)}</span>
                    </td>`;
                }).join('')}
            </tr>
        `).join('');
    };

    const renderPagination = (pagination) => {
        if (!pagination) {
            paginationInfo.textContent = '—';
            paginationSummary.textContent = '';
            paginationList.innerHTML = '';

            return;
        }

        paginationInfo.textContent = pagination.total
            ? `${pagination.total} employee(s)`
            : 'No employees';

        paginationSummary.textContent = pagination.total
            ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
            : '';

        if (!pagination.last_page || pagination.last_page <= 1) {
            paginationList.innerHTML = '';

            return;
        }

        const pages = [];
        const { current_page: current, last_page: last } = pagination;
        const pushPage = (page) => {
            pages.push(`<li class="page-item ${page === current ? 'active' : ''}">
                <button type="button" class="page-link" data-page="${page}">${page}</button>
            </li>`);
        };

        pushPage(1);

        if (current > 3) {
            pages.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
        }

        for (let page = Math.max(2, current - 1); page <= Math.min(last - 1, current + 1); page += 1) {
            pushPage(page);
        }

        if (current < last - 2) {
            pages.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
        }

        if (last > 1) {
            pushPage(last);
        }

        paginationList.innerHTML = pages.join('');
    };

    const renderDayModal = (payload) => {
        if (!dayModalBody) {
            return;
        }

        const regularization = payload.regularization_request;
        const regularizationBlock = regularization
            ? `<div class="alert alert-warning mb-3">
                <div class="fw-semibold">Regularization ${escapeHtml(regularization.status_label)}</div>
                <div class="small">Requested: ${escapeHtml(regularization.requested_punch_in_label || '—')} to ${escapeHtml(regularization.requested_punch_out_label || '—')}</div>
                <div class="small mt-1">${escapeHtml(regularization.reason || '')}</div>
            </div>`
            : '';

        const leaveBlock = payload.status === 'on_leave'
            ? `<div class="alert alert-info mb-3">
                <div class="fw-semibold">${escapeHtml(payload.leave_type_name || 'On Leave')}</div>
                ${payload.leave_session_label ? `<div class="small">${escapeHtml(payload.leave_session_label)}</div>` : ''}
            </div>`
            : '';

        const holidayBlock = payload.holiday_name
            ? `<div class="alert alert-secondary mb-3">${escapeHtml(payload.holiday_name)}</div>`
            : '';

        const punches = (payload.punches || []).map((punch) => `
            <div class="attendance-punch-card mb-2">
                <span class="badge ${punch.punch_type === 'in' ? 'text-bg-success' : 'text-bg-warning'}">${escapeHtml(punch.punch_label)}</span>
                <span class="ms-2 fw-semibold">${escapeHtml(punch.punched_at_label || punch.punched_at)}</span>
            </div>
        `).join('') || '<div class="text-muted small">No punch records.</div>';

        dayModalBody.innerHTML = `
            ${regularizationBlock}
            ${holidayBlock}
            ${leaveBlock}
            <div class="attendance-day-overview mb-3">
                <div><span class="text-muted">Status:</span> <strong>${escapeHtml(payload.status_label || payload.status)}</strong></div>
                <div><span class="text-muted">Worked:</span> <strong>${escapeHtml(payload.worked_hours_label || '—')}</strong></div>
                <div><span class="text-muted">First in:</span> <strong>${escapeHtml(payload.punch_in_label || '—')}</strong></div>
                <div><span class="text-muted">Last out:</span> <strong>${escapeHtml(payload.punch_out_label || '—')}</strong></div>
            </div>
            <h6 class="mb-2">Punch records</h6>
            ${punches}
        `;
    };

    const openDayModal = async (employeeId, date) => {
        if (!dayModal || !dayModalBody) {
            return;
        }

        selectedEmployeeId = employeeId;
        dayModalBody.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
        dayModal.show();

        try {
            const { data } = await api.get(`/attendance/day/${date}`, { params: { employee_id: employeeId } });
            const payload = data.data;

            if (dayModalTitle) {
                dayModalTitle.textContent = payload.date_label || date;
            }

            if (dayModalSubtitle) {
                dayModalSubtitle.textContent = payload.employee?.full_name || '';
            }

            if (employeeCalendarLink) {
                const routes = window.HRMS_WEB_ROUTES || {};
                const base = routes.attendanceIndex || '/attendance';
                employeeCalendarLink.href = `${base}?employee_id=${employeeId}&month=${date.slice(0, 7)}`;
                employeeCalendarLink.classList.remove('d-none');
            }

            renderDayModal(payload);
        } catch (error) {
            dayModalBody.innerHTML = `<div class="text-center text-danger py-4">${escapeHtml(getErrorMessage(error))}</div>`;
        }
    };

    const loadMatrix = async () => {
        matrixBody.innerHTML = `<tr><td colspan="12" class="text-center text-muted py-5">Loading...</td></tr>`;

        if (monthLabelEl) {
            monthLabelEl.textContent = monthLabel(currentMonth);
        }

        try {
            const { data } = await api.get('/attendance/month-matrix', { params: filters() });
            const payload = data.data;

            dayColumns = payload.days || [];
            overviewTitle.textContent = `Attendance matrix — ${payload.month_label || monthLabel(currentMonth)}`;
            renderSummary(payload.summary || {}, payload.scope);
            renderMatrixHead();
            renderMatrixBody(payload.employees || []);
            renderPagination(payload.pagination);
        } catch (error) {
            matrixHead.innerHTML = '';
            matrixBody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-5">${escapeHtml(getErrorMessage(error))}</td></tr>`;
            renderPagination(null);
            showAlert(getErrorMessage(error));
        }
    };

    const reload = () => {
        loadMatrix();
    };

    const loadDepartments = async () => {
        if (!departmentSelect) {
            return;
        }

        try {
            const { data } = await api.get('/departments', { params: { per_page: 100, status: 'active' } });
            const departments = data.data.departments || [];

            departmentSelect.innerHTML = '<option value="">All departments</option>' + departments
                .map((department) => `<option value="${department.id}">${escapeHtml(department.name)}</option>`)
                .join('');
        } catch {
            // Department filter is optional when the user lacks permission.
        }
    };

    prevMonthBtn?.addEventListener('click', () => {
        const date = new Date(`${currentMonth}-01T00:00:00`);
        date.setMonth(date.getMonth() - 1);
        currentMonth = monthKey(date);
        currentPage = 1;
        reload();
    });

    nextMonthBtn?.addEventListener('click', () => {
        const date = new Date(`${currentMonth}-01T00:00:00`);
        date.setMonth(date.getMonth() + 1);
        currentMonth = monthKey(date);
        currentPage = 1;
        reload();
    });

    departmentSelect?.addEventListener('change', () => {
        currentPage = 1;
        reload();
    });

    statusSelect?.addEventListener('change', () => {
        currentPage = 1;
        reload();
    });

    searchInput?.addEventListener('input', debounce(() => {
        currentPage = 1;
        reload();
    }, 300));

    resetBtn?.addEventListener('click', () => {
        if (departmentSelect) {
            departmentSelect.value = '';
        }

        if (statusSelect) {
            statusSelect.value = 'active';
        }

        if (searchInput) {
            searchInput.value = '';
        }

        currentPage = 1;
        reload();
    });

    paginationList?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');

        if (!button) {
            return;
        }

        currentPage = Number(button.dataset.page);
        reload();
    });

    matrixBody?.addEventListener('click', (event) => {
        const cell = event.target.closest('.attendance-matrix-cell-btn[data-employee-id][data-date]');

        if (!cell) {
            return;
        }

        openDayModal(Number(cell.dataset.employeeId), cell.dataset.date);
    });

    matrixBody?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const cell = event.target.closest('.attendance-matrix-cell-btn[data-employee-id][data-date]');

        if (!cell) {
            return;
        }

        event.preventDefault();
        openDayModal(Number(cell.dataset.employeeId), cell.dataset.date);
    });

    await loadDepartments();
    reload();
});
