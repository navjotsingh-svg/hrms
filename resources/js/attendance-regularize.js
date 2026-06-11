import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { renderActionGroup, renderCancelIconButton } from './action-icons';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';
import { setSubmitLoading } from './form-utils';

const statusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'text-bg-danger',
    cancelled: 'text-bg-secondary',
}[status] || '');

const eligibleStatusClass = (status) => ({
    absent: 'regularize-eligible-card--absent',
    half_day: 'regularize-eligible-card--half-day',
    short_leave: 'regularize-eligible-card--short-leave',
    incomplete: 'regularize-eligible-card--incomplete',
}[status] || 'regularize-eligible-card--default');

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('regularizeTableBody');
    const alertBox = document.getElementById('regularizeAlert');
    const pendingContainer = document.getElementById('pendingRegularizeContainer');
    const eligibleDatesContainer = document.getElementById('eligibleDatesContainer');
    const eligibleEmployee = document.getElementById('eligibleEmployee');
    const filterStatus = document.getElementById('filterStatus');
    const filterYear = document.getElementById('filterYear');
    const filterReset = document.getElementById('filterReset');
    const paginationInfo = document.getElementById('regularizePaginationInfo');
    const paginationList = document.getElementById('regularizePaginationList');
    const regularizeForm = document.getElementById('regularizeForm');
    const regularizeModalEl = document.getElementById('regularizeModal');
    const regularizeModalTitle = document.getElementById('regularizeModalTitle');
    const regularizeSelectedDaySummary = document.getElementById('regularizeSelectedDaySummary');
    const regularizeSubmitBtn = document.getElementById('regularizeSubmitBtn');
    const regularizeModal = regularizeModalEl ? Modal.getOrCreateInstance(regularizeModalEl) : null;

    let currentPage = 1;
    const currentYear = new Date().getFullYear();
    let canViewAll = false;
    let eligibleDatesByKey = {};

    if (filterYear) {
        filterYear.innerHTML = Array.from({ length: 3 }, (_, i) => currentYear - 1 + i)
            .map((year) => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`).join('');
    }

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const formatTimes = (item) => {
        const parts = [];
        if (item.requested_punch_in_label) parts.push(`In ${item.requested_punch_in_label}`);
        if (item.requested_punch_out_label) parts.push(`Out ${item.requested_punch_out_label}`);
        return parts.join(' · ') || '—';
    };

    const openRegularizeModal = (item, employeeId = null) => {
        document.getElementById('attendance_date').value = item.date;
        document.getElementById('punch_in_time').value = item.suggested_punch_in || '';
        document.getElementById('punch_out_time').value = item.suggested_punch_out || '';
        document.getElementById('reason').value = '';

        const employeeField = document.getElementById('regularize_employee_id');
        if (employeeField) {
            employeeField.value = employeeId || eligibleEmployee?.value || '';
        }

        if (regularizeModalTitle) {
            regularizeModalTitle.textContent = `Regularize · ${item.date_label}`;
        }

        if (regularizeSelectedDaySummary) {
            regularizeSelectedDaySummary.innerHTML = `
                <div class="regularize-selected-day-status ${eligibleStatusClass(item.status)}">
                    <div class="fw-semibold">${item.status_label}</div>
                    <div class="small text-muted mt-1">
                        Recorded: In ${item.punch_in_label || '—'} · Out ${item.punch_out_label || '—'}
                        · Worked ${item.worked_hours_label} / Required ${item.required_hours_label}
                    </div>
                </div>
            `;
        }

        regularizeModal?.show();
    };

    const renderEligibleDates = (payload) => {
        if (!eligibleDatesContainer) return;

        const dates = payload.dates || [];
        eligibleDatesByKey = Object.fromEntries(dates.map((item) => [item.date, item]));

        if (!dates.length) {
            eligibleDatesContainer.innerHTML = '<div class="text-muted py-3">No dates need regularization.</div>';
            return;
        }

        eligibleDatesContainer.innerHTML = dates.map((item) => {
            if (item.has_pending_request) {
                return `
                    <div class="regularize-eligible-card regularize-eligible-card--pending ${eligibleStatusClass(item.status)}">
                        <div class="regularize-eligible-card-date">${item.date_label}</div>
                        <div class="regularize-eligible-card-status">${item.status_label}</div>
                        <div class="regularize-eligible-card-meta">In ${item.punch_in_label || '—'} · Out ${item.punch_out_label || '—'}</div>
                        <span class="badge text-bg-warning mt-2">Pending approval</span>
                    </div>
                `;
            }

            return `
                <button
                    type="button"
                    class="regularize-eligible-card ${eligibleStatusClass(item.status)}"
                    data-eligible-date="${item.date}"
                >
                    <div class="regularize-eligible-card-date">${item.date_label}</div>
                    <div class="regularize-eligible-card-status">${item.status_label}</div>
                    <div class="regularize-eligible-card-meta">
                        In ${item.punch_in_label || '—'} · Out ${item.punch_out_label || '—'}
                    </div>
                    <div class="regularize-eligible-card-meta">Worked ${item.worked_hours_label} / ${item.required_hours_label}</div>
                    <span class="regularize-eligible-card-action">Request regularization</span>
                </button>
            `;
        }).join('');
    };

    const loadEligibleEmployees = async () => {
        if (!eligibleEmployee) return;

        try {
            const { data } = await api.get('/employees', { params: { per_page: 100, status: 'active' } });
            const employees = data.data.employees || [];
            canViewAll = true;
            const defaultId = window.REGULARIZE_DEFAULT_EMPLOYEE_ID;
            eligibleEmployee.innerHTML = employees.map((employee) => `
                <option value="${employee.id}" ${String(employee.id) === String(defaultId) ? 'selected' : ''}>${employee.full_name} (${employee.employee_code})</option>
            `).join('');

            if (!eligibleEmployee.value && employees[0]) {
                eligibleEmployee.value = String(employees[0].id);
            }
        } catch (error) {
            eligibleEmployee.innerHTML = '<option value="">Unable to load employees</option>';
            console.error(getErrorMessage(error));
        }
    };

    const loadEligibleDates = async () => {
        if (!eligibleDatesContainer) return;

        eligibleDatesContainer.innerHTML = '<div class="text-muted py-3">Loading eligible dates...</div>';

        try {
            const params = {};
            if (eligibleEmployee?.value) {
                params.employee_id = eligibleEmployee.value;
            }

            const { data } = await api.get('/attendance-regularizations/eligible-dates', { params });
            renderEligibleDates(data.data || {});
        } catch (error) {
            eligibleDatesContainer.innerHTML = `<div class="text-danger py-3">${getErrorMessage(error)}</div>`;
        }
    };

    const renderPending = (requests) => {
        if (!pendingContainer) return;
        if (!requests.length) {
            pendingContainer.innerHTML = '<div class="text-muted">No pending regularization requests.</div>';
            return;
        }
        pendingContainer.innerHTML = requests.map((item) => `
            <div class="border rounded p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">${item.employee?.full_name || 'Employee'} · ${item.attendance_date_label}</div>
                        <div class="small text-muted">${formatTimes(item)}</div>
                        <div class="small mt-1">${item.reason}</div>
                    </div>
                    <div class="table-action-group">
                        ${item.can_review ? `
                            ${renderApproveIconButton('data-approve-regularize', item.id, 'Approve regularization')}
                            ${renderRejectIconButton('data-reject-regularize', item.id, 'Reject regularization')}
                        ` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    };

    const renderRow = (item, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
        const actions = [];
        if (item.can_cancel && item.status === 'pending') {
            actions.push(renderCancelIconButton('data-cancel-regularize', item.id, 'Cancel request'));
        }
        return `<tr>
            <td>${serial}</td>
            <td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>
            <td>${item.attendance_date_label || '—'}</td>
            <td>${formatTimes(item)}</td>
            <td class="text-truncate" style="max-width:220px">${item.reason}</td>
            <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
            <td>${actions.length ? renderActionGroup(actions.join('')) : '—'}</td>
        </tr>`;
    };

    const loadPending = async () => {
        if (!pendingContainer) return;
        try {
            const { data } = await api.get('/attendance-regularizations/pending');
            renderPending(data.data.regularization_requests || []);
        } catch {
            pendingContainer.innerHTML = '<div class="text-danger">Unable to load pending requests.</div>';
        }
    };

    const loadRequests = async (page = 1) => {
        currentPage = page;
        const params = { page, per_page: 10, year: filterYear?.value || currentYear };
        if (filterStatus?.value) params.status = filterStatus.value;
        try {
            const { data } = await api.get('/attendance-regularizations', { params });
            const requests = data.data.regularization_requests || [];
            const pagination = data.data.pagination;
            tableBody.innerHTML = requests.length
                ? requests.map((item, i) => renderRow(item, i, pagination)).join('')
                : '<tr><td colspan="7" class="text-center text-muted py-5">No regularization requests found.</td></tr>';
            paginationInfo.textContent = pagination?.total
                ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
                : 'No requests found';
            paginationList.innerHTML = pagination?.last_page
                ? Array.from({ length: pagination.last_page }, (_, i) => {
                    const p = i + 1;
                    return `<li class="page-item ${p === pagination.current_page ? 'active' : ''}"><button type="button" class="page-link" data-page="${p}">${p}</button></li>`;
                }).join('')
                : '';
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const handleReview = async (id, action) => {
        try {
            if (action === 'approve') {
                await api.patch(`/attendance-regularizations/${id}/approve`);
                showAlert('Regularization request approved.');
            } else {
                const notes = prompt('Rejection reason:');
                if (!notes?.trim()) return;
                await api.patch(`/attendance-regularizations/${id}/reject`, { notes: notes.trim() });
                showAlert('Regularization request rejected.');
            }
            await Promise.all([loadPending(), loadRequests(currentPage), loadEligibleDates()]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const handleCancel = async (id) => {
        if (!window.confirm('Cancel this regularization request?')) return;
        try {
            await api.patch(`/attendance-regularizations/${id}/cancel`);
            showAlert('Regularization request cancelled.');
            await Promise.all([loadPending(), loadRequests(currentPage), loadEligibleDates()]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    eligibleDatesContainer?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-eligible-date]');
        if (!button) return;

        const item = eligibleDatesByKey[button.dataset.eligibleDate];
        if (!item) return;

        openRegularizeModal(item, eligibleEmployee?.value || null);
    });

    pendingContainer?.addEventListener('click', (event) => {
        const approve = event.target.closest('[data-approve-regularize]');
        const reject = event.target.closest('[data-reject-regularize]');
        if (approve) handleReview(approve.dataset.approveRegularize, 'approve');
        if (reject) handleReview(reject.dataset.rejectRegularize, 'reject');
    });

    tableBody?.addEventListener('click', (event) => {
        const cancel = event.target.closest('[data-cancel-regularize]');
        if (cancel) handleCancel(cancel.dataset.cancelRegularize);
    });

    regularizeForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        setSubmitLoading(regularizeSubmitBtn, true, { submittingText: 'Submitting...' });
        try {
            const payload = {
                attendance_date: document.getElementById('attendance_date')?.value,
                punch_in_time: document.getElementById('punch_in_time')?.value || null,
                punch_out_time: document.getElementById('punch_out_time')?.value || null,
                reason: document.getElementById('reason')?.value?.trim(),
            };

            const employeeId = document.getElementById('regularize_employee_id')?.value;
            if (canViewAll && employeeId) {
                payload.employee_id = Number(employeeId);
            }

            await api.post('/attendance-regularizations', payload);
            showAlert('Regularization request submitted.');
            regularizeForm.reset();
            regularizeModal?.hide();
            await Promise.all([loadPending(), loadRequests(1), loadEligibleDates()]);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(regularizeSubmitBtn, false);
        }
    });

    eligibleEmployee?.addEventListener('change', loadEligibleDates);

    filterStatus?.addEventListener('change', () => loadRequests(1));
    filterYear?.addEventListener('change', () => loadRequests(1));
    filterReset?.addEventListener('click', () => {
        if (filterStatus) filterStatus.value = '';
        if (filterYear) filterYear.value = String(currentYear);
        loadRequests(1);
    });
    paginationList?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (btn) loadRequests(Number(btn.dataset.page));
    });

    if (eligibleEmployee) {
        await loadEligibleEmployees();
    }

    const urlDate = new URLSearchParams(window.location.search).get('date');
    await Promise.all([loadPending(), loadRequests(), loadEligibleDates()]);

    if (urlDate && eligibleDatesByKey[urlDate]) {
        openRegularizeModal(eligibleDatesByKey[urlDate], eligibleEmployee?.value || null);
    }
});
