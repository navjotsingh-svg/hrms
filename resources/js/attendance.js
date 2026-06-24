import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import {
    bindEmployeeSearchSelect,
    formatEmployeeLabel,
    matchesEmployeeSearch,
    searchEmployees,
} from './employee-autocomplete';

const pad = (value) => String(value).padStart(2, '0');

const currentMonthKey = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusClass = (status, awaitingPunchOut = false) => {
    if (awaitingPunchOut) {
        return 'attendance-day--pending';
    }

    if (status === 'present' || status === 'complete') {
        return 'attendance-day--present';
    }

    if (status === 'half_day') {
        return 'attendance-day--half-day';
    }

    if (status === 'incomplete') {
        return 'attendance-day--short-leave';
    }

    if (status === 'short_leave') {
        return 'attendance-day--absent';
    }

    if (status === 'absent') {
        return 'attendance-day--absent';
    }

    if (status === 'holiday') {
        return 'attendance-day--holiday';
    }

    if (status === 'weekly_off') {
        return 'attendance-day--weekly-off';
    }

    if (status === 'before_portal') {
        return 'attendance-day--blank';
    }

    if (status === 'on_leave') {
        return 'attendance-day--on-leave';
    }

    if (status === 'regularization_pending') {
        return 'attendance-day--regularization-pending';
    }

    return 'attendance-day--future';
};

const statusBadgeClass = (status) => `attendance-cal-status-badge attendance-cal-status-badge--${status === 'complete' ? 'present' : status}`;

const HOLIDAY_TYPE_LABELS = {
    public: 'Public',
    company: 'Company',
    optional: 'Optional',
    other: 'Other',
};

const holidayTypeLabel = (type) => HOLIDAY_TYPE_LABELS[type] || (type ? String(type) : 'Holiday');

const statusBadgeLabel = (dayData) => {
    if (dayData.awaiting_punch_out) {
        return '';
    }

    if (dayData.status === 'present' || dayData.status === 'complete') {
        return 'Present full day';
    }

    if (dayData.status === 'absent') {
        return 'Absent full day';
    }

    if (dayData.status === 'half_day') {
        return 'Half day';
    }

    if (dayData.status === 'holiday') {
        return dayData.holiday_name || 'Holiday';
    }

    if (dayData.status === 'weekly_off') {
        return 'Weekly off';
    }

    if (dayData.status === 'on_leave') {
        if (dayData.leave_type_name && dayData.leave_session_label && dayData.leave_session_label !== 'Full Day') {
            return `${dayData.leave_type_name} · ${dayData.leave_session_label}`;
        }

        return dayData.status_label || dayData.leave_type_name || 'On leave';
    }

    if (dayData.status === 'regularization_pending') {
        return 'Regularization pending';
    }

    if (dayData.status === 'incomplete') {
        return 'In progress';
    }

    if (dayData.status === 'future') {
        return 'Upcoming';
    }

    return dayData.status_label || dayData.status || '—';
};

const renderPunchBadges = (dayData) => (dayData.punch_entries || [])
    .map((entry) => `<span class="attendance-cal-punch-badge">${entry.label}</span>`)
    .join('');

const renderJoiningMarker = (dayData) => {
    if (!dayData.is_joining_date) {
        return '';
    }

    const dateLabel = dayData.joining_date_label || '';

    return `
        <div class="attendance-cal-joining-marker">
            <span class="attendance-cal-joining-label">Joining date</span>
            ${dateLabel ? `<span class="attendance-cal-joining-date">${dateLabel}</span>` : ''}
        </div>
    `;
};

const renderDayPunchTimes = (dayData) => {
    const joiningMarker = renderJoiningMarker(dayData);

    if (dayData.status === 'before_portal' || dayData.status === 'future') {
        return joiningMarker
            ? `<div class="attendance-day-content">${joiningMarker}</div>`
            : '';
    }

    if (dayData.awaiting_punch_out) {
        const punchBadges = renderPunchBadges(dayData);
        const expectedOut = dayData.expected_clock_out_label
            ? `<div class="attendance-day-expected-out">Expected clock out time for full day present ${dayData.expected_clock_out_label}</div>`
            : '';

        return `
            <div class="attendance-day-content">
                ${joiningMarker}
                ${expectedOut}
                ${punchBadges ? `<div class="attendance-day-punches">${punchBadges}</div>` : ''}
            </div>
        `;
    }

    const badgeLabel = statusBadgeLabel(dayData);
    const statusBadge = badgeLabel
        ? `<span class="${statusBadgeClass(dayData.status)}">${badgeLabel}</span>`
        : '';
    const punchBadges = renderPunchBadges(dayData);

    return `
        <div class="attendance-day-content">
            ${joiningMarker}
            ${statusBadge}
            ${punchBadges ? `<div class="attendance-day-punches">${punchBadges}</div>` : ''}
        </div>
    `;
};

const mondayFirstLeadingBlanks = (monthKey) => {
    const firstDay = new Date(`${monthKey}-01T00:00:00`);

    return (firstDay.getDay() + 6) % 7;
};

const setText = (element, value) => {
    if (element) {
        element.textContent = value;
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    const alertBox = document.getElementById('attendanceAlert');
    const monthLabel = document.getElementById('attendanceMonthLabel');
    const prevMonthBtn = document.getElementById('attendancePrevMonth');
    const nextMonthBtn = document.getElementById('attendanceNextMonth');
    const calendarDays = document.getElementById('attendanceCalendarDays');
    const presentCount = document.getElementById('attendancePresentCount');
    const halfDayCount = document.getElementById('attendanceHalfDayCount');
    const absentCount = document.getElementById('attendanceAbsentCount');
    const weeklyOffCount = document.getElementById('attendanceWeeklyOffCount');
    const holidayCount = document.getElementById('attendanceHolidayCount');
    const onLeaveCount = document.getElementById('attendanceOnLeaveCount');
    const requiredHours = document.getElementById('attendanceRequiredHours');
    const filterEmployeeInput = document.getElementById('filterEmployeeInput');
    const filterEmployeeId = document.getElementById('filterEmployeeId');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const dayModalElement = document.getElementById('attendanceDayModal');
    const dayModalBody = document.getElementById('attendanceDayModalBody');
    const dayModalLabel = document.getElementById('attendanceDayModalLabel');
    const dayModalSubtitle = document.getElementById('attendanceDayModalSubtitle');

    let currentMonth = currentMonthKey();
    let capabilities = { can_mark: false, can_view_all: false };
    let employees = [];
    let employeeAutocomplete = null;
    let dayModal = dayModalElement ? Modal.getOrCreateInstance(dayModalElement) : null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const selectedEmployeeId = () => {
        if (filterEmployeeId?.value) {
            return Number(filterEmployeeId.value);
        }

        return employeeAutocomplete?.getSelectedId?.() || null;
    };

    const employeeOption = (employee) => ({
        id: employee.id,
        label: formatEmployeeLabel(employee),
        employee,
    });

    const findEmployeeById = (employeeId) => {
        if (!employeeId) {
            return null;
        }

        return employees.find((employee) => Number(employee.id) === Number(employeeId)) || null;
    };

    const setEmployeeSelection = (employee) => {
        if (!employeeAutocomplete || !employee) {
            return;
        }

        employeeAutocomplete.setSelection(employeeOption(employee));
    };

    const defaultEmployeeId = () => {
        const preferSelf = capabilities.default_view_own
            || (!capabilities.can_view_all && capabilities.can_view_team);

        if (preferSelf && capabilities.self_employee_id) {
            return Number(capabilities.self_employee_id);
        }

        const scopedEmployees = filteredEmployees();

        return scopedEmployees[0]?.id ? Number(scopedEmployees[0].id) : null;
    };

    const ensureEmployeeSelection = () => {
        if (!employeeAutocomplete) {
            return;
        }

        const scopedEmployees = filteredEmployees();
        const selected = selectedEmployeeId();

        if (selected && scopedEmployees.some((employee) => Number(employee.id) === selected)) {
            setEmployeeSelection(findEmployeeById(selected));
            return;
        }

        const defaultId = defaultEmployeeId();
        const defaultEmployee = scopedEmployees.find((employee) => Number(employee.id) === defaultId);

        if (defaultEmployee) {
            setEmployeeSelection(defaultEmployee);
        }
    };

    const resolveCalendarEmployeeId = () => selectedEmployeeId();

    const filterLocalEmployees = (term = '') => {
        const needle = term.trim().toLowerCase();
        const scoped = filteredEmployees();

        if (!needle) {
            return scoped.slice(0, 50).map(employeeOption);
        }

        return scoped
            .filter((employee) => matchesEmployeeSearch(employee, needle))
            .slice(0, 50)
            .map(employeeOption);
    };

    const initEmployeeFilter = () => {
        if (!filterEmployeeInput || !filterEmployeeId) {
            return;
        }

        employeeAutocomplete = bindEmployeeSearchSelect({
            inputId: 'filterEmployeeInput',
            hiddenId: 'filterEmployeeId',
            fetchSuggestions: async (term) => {
                if (capabilities.can_view_team && !capabilities.can_view_all) {
                    return filterLocalEmployees(term);
                }

                return searchEmployees(term);
            },
            onSelect: () => {
                loadCalendar();
            },
        });
    };

    const loadFilters = async () => {
        if (!capabilities.can_view_all) {
            if (capabilities.can_view_team && filterEmployeeInput) {
                employees = [...(capabilities.team_employees || [])];

                if (capabilities.self_employee_id) {
                    const selfAlreadyListed = employees.some(
                        (employee) => Number(employee.id) === Number(capabilities.self_employee_id),
                    );

                    if (!selfAlreadyListed) {
                        employees.unshift({
                            id: Number(capabilities.self_employee_id),
                            full_name: 'My Attendance',
                            employee_code: '',
                        });
                    }
                }

                initEmployeeFilter();
                ensureEmployeeSelection();
            }

            return;
        }

        try {
            const { data } = await api.get('/employees', { params: { per_page: 100, status: 'active' } });
            employees = data.data.employees || [];

            initEmployeeFilter();
            ensureEmployeeSelection();
        } catch (error) {
            console.error(getErrorMessage(error));
        }
    };

    const filteredEmployees = () => {
        if (!capabilities.can_view_all) {
            if (capabilities.can_view_team) {
                const team = [...(capabilities.team_employees || [])];

                if (capabilities.self_employee_id) {
                    team.unshift({ id: Number(capabilities.self_employee_id) });
                }

                return team;
            }

            return [];
        }

        return employees;
    };

    const renderPolicyInfo = (monthData) => {
        const policyInfo = document.getElementById('attendancePolicyInfo');
        if (!policyInfo) {
            return;
        }

        const parts = [];

        if (monthData.employee_joining_date_label) {
            parts.push(`<span><strong>Joining date:</strong> ${monthData.employee_joining_date_label}</span>`);
        }

        if (monthData.weekly_off_labels?.length) {
            parts.push(`<span><strong>Weekly off:</strong> ${monthData.weekly_off_labels.join(', ')}</span>`);
        }

        if (monthData.month_holidays?.length) {
            const holidayText = monthData.month_holidays
                .map((holiday) => `${holiday.name} (${holiday.date_label})`)
                .join(' · ');
            parts.push(`<span><strong>Holidays:</strong> ${holidayText}</span>`);
        }

        if (!parts.length) {
            policyInfo.classList.add('d-none');
            policyInfo.innerHTML = '';
            return;
        }

        policyInfo.classList.remove('d-none');
        policyInfo.innerHTML = parts.join('<span class="attendance-policy-divider">|</span>');
    };

    const renderMonthHolidays = (monthData) => {
        const wrap = document.getElementById('attendanceMonthHolidays');
        const list = document.getElementById('attendanceMonthHolidaysList');
        const manageLink = document.getElementById('attendanceManageHolidaysLink');
        const routes = window.HRMS_WEB_ROUTES || {};

        if (manageLink) {
            manageLink.classList.toggle('d-none', !capabilities.can_manage_attendance_masters);
            manageLink.href = routes.holidaysIndex || '/masters/attendance/holidays';
        }

        if (!wrap || !list) {
            return;
        }

        const holidays = monthData.month_holidays || [];

        if (!holidays.length) {
            wrap.classList.add('d-none');
            list.innerHTML = '';
            return;
        }

        wrap.classList.remove('d-none');
        list.innerHTML = holidays.map((holiday) => {
            const editUrl = capabilities.can_manage_attendance_masters && holiday.id
                ? `${routes.holidayEdit || '/masters/attendance/holidays'}/${holiday.id}/edit`
                : null;
            const patternNote = holiday.pattern_label && holiday.pattern_label !== holiday.date_label
                ? `<span class="attendance-month-holiday-pattern">${holiday.pattern_label}</span>`
                : '';
            const manageAction = editUrl
                ? `<a href="${editUrl}" class="attendance-month-holiday-action">Edit</a>`
                : '';

            return `
                <div class="attendance-month-holiday-item">
                    <div class="attendance-month-holiday-main">
                        <span class="attendance-month-holiday-name">${holiday.name}</span>
                        <span class="attendance-month-holiday-meta">${holidayTypeLabel(holiday.type)} · ${holiday.date_label}</span>
                        ${patternNote}
                    </div>
                    ${manageAction}
                </div>
            `;
        }).join('');
    };

    const renderCalendarShell = (monthData) => {
        const firstDay = new Date(`${monthData.month}-01T00:00:00`);
        const leading = mondayFirstLeadingBlanks(monthData.month);
        const daysInMonth = new Date(firstDay.getFullYear(), firstDay.getMonth() + 1, 0).getDate();
        const trailing = (7 - ((leading + daysInMonth) % 7)) % 7;
        const statusFilter = filterStatus?.value || '';
        const cells = [];

        for (let index = 0; index < leading; index += 1) {
            cells.push('<td class="attendance-day attendance-day--empty"></td>');
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = `${monthData.month}-${pad(day)}`;
            const dayData = monthData.days.find((item) => item.date === date);

            if (!dayData) {
                cells.push('<td class="attendance-day attendance-day--empty"></td>');
                continue;
            }

            const dayNumberClass = dayData.is_today ? 'attendance-day-number attendance-day-number--today' : 'attendance-day-number';

            if (dayData.status === 'before_portal') {
                const dayContent = renderDayPunchTimes(dayData);
                cells.push(`
                    <td class="attendance-day attendance-day--blank${dayData.is_joining_date ? ' attendance-day--joining' : ''}" data-date="${date}" title="${dayData.is_joining_date ? 'Joining date' : 'Before attendance tracking'}">
                        <span class="${dayNumberClass}">${day}</span>
                        ${dayContent}
                    </td>
                `);
                continue;
            }

            if (statusFilter && dayData.status !== statusFilter) {
                cells.push(`
                    <td class="attendance-day attendance-day--muted ${statusClass(dayData.status, dayData.awaiting_punch_out)}" data-date="${date}">
                        <span class="${dayNumberClass}">${day}</span>
                    </td>
                `);
                continue;
            }

            const approverNote = dayData.status === 'on_leave' && dayData.leave_approved_by_name
                ? ` · Approved by ${dayData.leave_approved_by_name}`
                : '';
            const joiningNote = dayData.is_joining_date ? ' · Joining date' : '';
            const title = dayData.awaiting_punch_out
                ? `Punch out pending · In ${dayData.current_punch_in_label || dayData.punch_in_label || '—'}${dayData.expected_clock_out_label ? ` · Expected clock out time for full day present ${dayData.expected_clock_out_label}` : ''}${joiningNote}`
                : `${dayData.status_label || dayData.status}${approverNote}${joiningNote} · ${dayData.worked_hours_label} / ${dayData.required_hours_label}`;
            const dayContent = renderDayPunchTimes(dayData);
            const isInteractive = dayData.status !== 'before_portal'
                && (dayData.status === 'on_leave' || dayData.status === 'holiday' || !dayData.is_future);

            if (isInteractive) {
                cells.push(`
                    <td
                        role="button"
                        tabindex="0"
                        class="attendance-day attendance-day--interactive ${statusClass(dayData.status, dayData.awaiting_punch_out)}${dayData.is_joining_date ? ' attendance-day--joining' : ''}"
                        data-date="${date}"
                        title="${title}"
                    >
                        <span class="${dayNumberClass}">${day}</span>
                        ${dayContent}
                    </td>
                `);
            } else {
                cells.push(`
                    <td class="attendance-day attendance-day--static ${statusClass(dayData.status, dayData.awaiting_punch_out)}${dayData.is_joining_date ? ' attendance-day--joining' : ''}" data-date="${date}">
                        <span class="${dayNumberClass}">${day}</span>
                        ${dayContent}
                    </td>
                `);
            }
        }

        for (let index = 0; index < trailing; index += 1) {
            cells.push('<td class="attendance-day attendance-day--empty"></td>');
        }

        const rows = [];

        for (let index = 0; index < cells.length; index += 7) {
            rows.push(`<tr class="attendance-calendar-row">${cells.slice(index, index + 7).join('')}</tr>`);
        }

        if (!calendarDays) {
            return;
        }

        calendarDays.innerHTML = rows.join('');
        setText(monthLabel, monthData.month_label);
        setText(presentCount, monthData.summary.present_days);
        setText(halfDayCount, monthData.summary.half_day_days);
        setText(absentCount, monthData.summary.absent_days);
        setText(weeklyOffCount, monthData.summary.weekly_off_days || 0);
        setText(holidayCount, monthData.summary.holiday_days || 0);
        setText(onLeaveCount, monthData.summary.on_leave_days || 0);
        setText(requiredHours, `Required: ${monthData.required_hours_label}`);
        renderPolicyInfo(monthData);
        renderMonthHolidays(monthData);
    };

    const loadCalendar = async () => {
        if (!calendarDays) {
            return;
        }

        try {
            if (capabilities.can_view_all || capabilities.can_view_team) {
                ensureEmployeeSelection();
            }

            const params = { month: currentMonth };
            const employeeId = resolveCalendarEmployeeId();

            if (!employeeId && capabilities.can_view_all) {
                calendarDays.innerHTML = '<tr><td colspan="7" class="attendance-calendar-loading text-muted">No employees available to display.</td></tr>';
                setText(monthLabel, new Date(`${currentMonth}-01T00:00:00`).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' }));
                return;
            }

            if (employeeId) {
                params.employee_id = employeeId;
            }

            const { data } = await api.get('/attendance/calendar', { params });
            const payload = data.data;
            capabilities = payload.capabilities || capabilities;
            const subtitle = document.getElementById('attendanceSubtitle');

            if (subtitle) {
                const formatTrackingDate = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('en-IN', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                });

                if (payload.employee_attendance_start_date) {
                    const accessNote = payload.employee_portal_access_date
                        ? `Portal access from ${formatTrackingDate(payload.employee_portal_access_date)}. `
                        : '';
                    subtitle.textContent = `${accessNote}Attendance tracked from ${formatTrackingDate(payload.employee_attendance_start_date)}. Unmarked working days show as absent.`;
                } else if (payload.portal_start_date) {
                    subtitle.textContent = `Company portal starts ${formatTrackingDate(payload.portal_start_date)}. Days before portal access or the portal start date appear blank.`;
                } else {
                    subtitle.textContent = 'View attendance calendar and daily work hours.';
                }
            }

            renderCalendarShell(payload);
        } catch (error) {
            calendarDays.innerHTML = `<tr><td colspan="7" class="attendance-calendar-loading text-danger">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const renderRegularizationSection = (payload) => {
        const routes = window.HRMS_WEB_ROUTES || {};
        const regularizeUrl = `${routes.attendanceRegularizeIndex || '/attendance/regularize'}?date=${payload.date}`;
        const request = payload.regularization_request;

        if (request) {
            return `
                <div class="alert alert-warning mb-3">
                    <div class="fw-semibold mb-1">Regularization ${request.status_label}</div>
                    <div class="small">Requested: ${request.requested_punch_in_label || '—'} to ${request.requested_punch_out_label || '—'}</div>
                    <div class="small mt-1">${request.reason}</div>
                </div>
            `;
        }

        if (payload.can_request_regularization) {
            return `
                <div class="mb-3">
                    <a href="${regularizeUrl}" class="btn btn-sm btn-outline-primary">Request Regularization</a>
                </div>
            `;
        }

        return '';
    };

    const renderDayModal = (payload) => {
        setText(dayModalLabel, payload.date_label);
        setText(
            dayModalSubtitle,
            payload.awaiting_punch_out
                ? `${payload.employee.full_name} · Punch out pending`
                : `${payload.employee.full_name} · ${payload.worked_hours_label} worked / ${payload.required_hours_label} required`,
        );
        const regularizationSection = renderRegularizationSection(payload);

        const leaveApproverLine = payload.leave_approved_by_name
            ? `<div class="small mt-1">Approved by <strong>${payload.leave_approved_by_name}</strong>${payload.leave_approved_at_label ? ` on ${payload.leave_approved_at_label}` : ''}</div>`
            : '';

        const leaveSection = payload.status === 'on_leave'
            ? `
                <div class="alert alert-info mb-3">
                    <div class="fw-semibold mb-1">Approved Leave</div>
                    <div>${payload.leave_type_name || payload.status_label || 'On leave'}${payload.leave_session_label && payload.leave_session_label !== 'Full Day' ? ` · ${payload.leave_session_label}` : ''}</div>
                    ${leaveApproverLine}
                    ${payload.leave_request_id ? `<a href="${(window.HRMS_WEB_ROUTES?.leaveShow || '/leave')}/${payload.leave_request_id}" class="btn btn-sm btn-outline-primary mt-2">View leave request</a>` : ''}
                </div>
            `
            : '';

        const joiningSection = payload.is_joining_date
            ? `
                <div class="alert alert-info mb-3">
                    <div class="fw-semibold mb-1">Joining date</div>
                    <div>${payload.employee?.joining_date_label || payload.date_label || '—'}</div>
                </div>
            `
            : '';

        const holidayEditUrl = capabilities.can_manage_attendance_masters && payload.holiday?.id
            ? `${(window.HRMS_WEB_ROUTES?.holidayEdit || '/masters/attendance/holidays')}/${payload.holiday.id}/edit`
            : null;

        const holidaySection = payload.status === 'holiday'
            ? `
                <div class="alert alert-warning mb-3">
                    <div class="fw-semibold mb-1">${payload.holiday?.name || payload.holiday_name || payload.status_label || 'Holiday'}</div>
                    <div>${holidayTypeLabel(payload.holiday?.type)}${payload.holiday?.date_label ? ` · ${payload.holiday.date_label}` : ''}</div>
                    <div class="small text-muted mt-1">${payload.day_message || 'No attendance is required on this day.'}</div>
                    ${holidayEditUrl ? `<a href="${holidayEditUrl}" class="btn btn-sm btn-outline-primary mt-2">Edit holiday</a>` : ''}
                </div>
            `
            : '';

        if (!payload.punches.length) {
            const message = payload.status === 'holiday'
                ? null
                : payload.day_message
                || (payload.status === 'before_portal' ? 'Attendance tracking had not started on this date.' : null)
                || (payload.status === 'weekly_off' ? 'Weekly off day.' : null)
                || (payload.status === 'regularization_pending' ? 'Regularization request is pending approval.' : null)
                || (payload.status === 'on_leave' ? 'Approved leave for this day.' : null)
                || 'No attendance recorded for this day.';
            dayModalBody.innerHTML = `
                ${regularizationSection}
                ${joiningSection}
                ${holidaySection}
                ${leaveSection}
                ${message ? `<div class="text-center text-muted py-4">${message}</div>` : ''}
            `;
            return;
        }

        const segments = payload.segments.map((segment) => `
            <div class="attendance-segment-card">
                <div class="fw-semibold">${segment.duration_label}</div>
                <div class="small text-muted">
                    ${formatDateTime(segment.punch_in_at)}
                    ${segment.punch_out_at ? ` → ${formatDateTime(segment.punch_out_at)}` : ''}
                </div>
            </div>
        `).join('');

        const punches = payload.punches.map((punch) => {
            const selfieBlock = punch.selfie_url
                ? `<a href="${punch.selfie_url}" target="_blank" rel="noopener" class="small">Open selfie</a>`
                : '<span class="small text-muted">Regularized</span>';
            const imageBlock = punch.selfie_url
                ? `<div class="col-md-4"><img src="${punch.selfie_url}" alt="${punch.punch_label} selfie" class="attendance-selfie-thumb"></div>`
                : '';

            return `
            <div class="attendance-punch-card">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <div>
                        <span class="badge ${punch.punch_type === 'in' ? 'text-bg-success' : 'text-bg-warning'}">${punch.punch_label}</span>
                        ${punch.is_regularized ? '<span class="badge text-bg-info ms-1">Regularized</span>' : ''}
                        <span class="ms-2 fw-semibold">${formatDateTime(punch.punched_at)}</span>
                    </div>
                    ${selfieBlock}
                </div>
                <div class="row g-3 align-items-start">
                    ${imageBlock}
                    <div class="${imageBlock ? 'col-md-8' : 'col-12'}">
                        <div class="small text-muted mb-1">Location</div>
                        <div class="mb-2">${punch.location_label}</div>
                        ${punch.latitude || punch.longitude ? `<a href="https://www.google.com/maps?q=${punch.latitude},${punch.longitude}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">View on map</a>` : ''}
                    </div>
                </div>
            </div>
        `;
        }).join('');

        const statusSection = payload.awaiting_punch_out
            ? `
                <div class="attendance-day-overview mb-4">
                    <div><span class="text-muted">Punch In:</span> <strong>${payload.current_punch_in_label || payload.punch_in_label || '—'}</strong></div>
                    <div><span class="text-muted">Expected Clock Out:</span> <strong>${payload.expected_clock_out_label || '—'}</strong></div>
                    <div class="small text-muted mt-1">Expected time to complete full-day working hours based on your clock-in time.</div>
                </div>
            `
            : `
                <div class="attendance-day-status ${statusClass(payload.status)} mb-3">
                    Status: <strong>${payload.status_label || payload.status}</strong>
                    · ${payload.worked_hours_label} worked
                </div>
                <div class="attendance-day-overview mb-4">
                    <div><span class="text-muted">First Punch In:</span> <strong>${payload.punch_in_label || '—'}</strong></div>
                    <div><span class="text-muted">Last Punch Out:</span> <strong>${payload.punch_out_label || '—'}</strong></div>
                </div>
            `;

        dayModalBody.innerHTML = `
            ${regularizationSection}
            ${joiningSection}
            ${holidaySection}
            ${leaveSection}
            ${statusSection}
            ${segments ? `<h6 class="mb-2">Work Sessions</h6><div class="d-flex flex-column gap-2 mb-4">${segments}</div>` : ''}
            <h6 class="mb-2">Punch Records</h6>
            <div class="d-flex flex-column gap-3">${punches}</div>
        `;
    };

    const openDayModal = async (date) => {
        if (!dayModal) {
            return;
        }

        dayModalBody.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
        dayModal.show();

        try {
            const params = {};
            const employeeId = resolveCalendarEmployeeId();

            if (employeeId) {
                params.employee_id = employeeId;
            }

            const { data } = await api.get(`/attendance/day/${date}`, { params });
            renderDayModal(data.data);
        } catch (error) {
            dayModalBody.innerHTML = `<div class="text-center text-danger py-4">${getErrorMessage(error)}</div>`;
        }
    };

    prevMonthBtn?.addEventListener('click', () => {
        const date = new Date(`${currentMonth}-01T00:00:00`);
        date.setMonth(date.getMonth() - 1);
        currentMonth = currentMonthKey(date);
        loadCalendar();
    });

    nextMonthBtn?.addEventListener('click', () => {
        const date = new Date(`${currentMonth}-01T00:00:00`);
        date.setMonth(date.getMonth() + 1);
        currentMonth = currentMonthKey(date);
        loadCalendar();
    });

    filterStatus?.addEventListener('change', loadCalendar);
    filterReset?.addEventListener('click', () => {
        ensureEmployeeSelection();
        if (filterStatus) {
            filterStatus.value = '';
        }
        loadCalendar();
    });

    const openDayFromTarget = (target) => {
        const dayCell = target.closest('.attendance-day--interactive[data-date]');

        if (!dayCell
            || dayCell.classList.contains('attendance-day--empty')
            || dayCell.classList.contains('attendance-day--blank')) {
            return;
        }

        openDayModal(dayCell.dataset.date);
    };

    calendarDays?.addEventListener('click', (event) => {
        openDayFromTarget(event.target);
    });

    calendarDays?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const dayCell = event.target.closest('.attendance-day--interactive[data-date]');

        if (!dayCell) {
            return;
        }

        event.preventDefault();
        openDayFromTarget(dayCell);
    });

    try {
        const statusResponse = await api.get('/attendance/status');
        capabilities = statusResponse.data.data.capabilities || capabilities;
    } catch (error) {
        console.error(getErrorMessage(error));
    }

    await loadFilters();
    await loadCalendar();
});
