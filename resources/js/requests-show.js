import api, { getErrorMessage } from './api';
import { renderExpenseDetailHtml, renderExpenseGroupDetailHtml } from './expense-modals';
import {
    bindRequestReviewHandlers,
    renderHeaderReviewActions,
    reviewToken,
} from './request-review';

document.addEventListener('DOMContentLoaded', async () => {
    const card = document.getElementById('requestShowCard');
    const alertBox = document.getElementById('requestShowAlert');
    const titleEl = document.getElementById('requestShowTitle');
    const subtitleEl = document.getElementById('requestShowSubtitle');
    const headerActions = document.getElementById('requestShowHeaderActions');
    const category = card?.dataset.category;
    const entityId = card?.dataset.entityId;

    if (!card || !category || !entityId) {
        return;
    }

    let currentItem = null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderHeader = (item) => {
        currentItem = item;

        if (titleEl) {
            titleEl.textContent = item.title || item.category_label || 'Request Details';
        }

        if (subtitleEl) {
            subtitleEl.textContent = item.subtitle || '';
        }

        if (headerActions) {
            if (item.can_review || item.can_cancel) {
                headerActions.innerHTML = renderHeaderReviewActions(item);
                headerActions.classList.remove('d-none');
            } else {
                headerActions.innerHTML = '';
                headerActions.classList.add('d-none');
            }
        }
    };

    const loadHubItem = async () => {
        const { data } = await api.get('/request-hub/pending', { params: { per_page: 50, page: 1 } });
        const requests = data.data?.requests || [];
        let item = requests.find((entry) => entry.category === category && String(entry.entity_id) === String(entityId));

        if (!item && category === 'regularization-batch') {
            item = requests.find((entry) => entry.batch_id === entityId);
        }

        if (!item) {
            throw new Error('Request not found or no longer pending.');
        }

        return item;
    };

    const renderRegularization = async () => {
        const { data } = await api.get(`/attendance-regularizations/${entityId}`);
        const item = data.data.regularization_request;

        renderHeader({
            category: 'regularization',
            entity_id: item.id,
            category_label: 'Attendance Regularization',
            title: 'Attendance Regularization',
            subtitle: `${item.employee?.full_name || 'Employee'} · ${item.attendance_date_label || '—'}`,
            can_review: item.can_review,
            can_cancel: item.can_cancel,
            review_kind: 'regularization',
            review_target: String(item.id),
        });

        card.querySelector('.content-card-body').innerHTML = `
            <div class="row g-4">
                <div class="col-md-6"><span class="text-muted">Employee</span><div class="fw-semibold">${item.employee?.full_name || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Date</span><div>${item.attendance_date_label || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Current Punch</span><div>${[item.original_punch_in_label, item.original_punch_out_label].filter(Boolean).join(' · ') || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Requested Punch</span><div>${[item.requested_punch_in_label, item.requested_punch_out_label].filter(Boolean).join(' · ') || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label || item.status}</div></div>
                <div class="col-md-6"><span class="text-muted">Submitted On</span><div>${item.created_at_label || '—'}</div></div>
                <div class="col-12"><span class="text-muted">Reason</span><div>${item.reason || '—'}</div></div>
                ${item.review_notes ? `<div class="col-12"><span class="text-muted">Review Notes</span><div>${item.review_notes}</div></div>` : ''}
            </div>
        `;
    };

    const renderRegularizationBatch = async () => {
        const { data } = await api.get('/attendance-regularizations/pending');
        const group = (data.data?.pending_groups || []).find((entry) => entry.batch_id === entityId);

        if (!group) {
            throw new Error('Regularization batch not found or no longer pending.');
        }

        renderHeader({
            category: 'regularization',
            entity_id: group.request_ids?.[0],
            category_label: 'Attendance Regularization',
            title: 'Attendance Regularization Batch',
            subtitle: `${group.employee?.full_name || 'Employee'} · ${group.day_count || 0} day(s)`,
            can_review: group.can_review,
            can_cancel: false,
            review_kind: 'regularization_batch',
            review_target: group.batch_id,
        });

        const dates = (group.dates || []).map((day) => `<li>${day.attendance_date_label || day.attendance_date}</li>`).join('');

        card.querySelector('.content-card-body').innerHTML = `
            <div class="row g-4">
                <div class="col-md-6"><span class="text-muted">Employee</span><div class="fw-semibold">${group.employee?.full_name || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Days</span><div>${group.day_count || 0}</div></div>
                <div class="col-12"><span class="text-muted">Dates</span><ul class="mb-0 ps-3">${dates || '<li class="text-muted">—</li>'}</ul></div>
                <div class="col-12"><span class="text-muted">Reason</span><div>${group.reason || '—'}</div></div>
            </div>
        `;
    };

    const renderExpense = async () => {
        const { data } = await api.get(`/expenses/${entityId}`);
        const expense = data.data.expense;

        renderHeader({
            category: 'expense',
            entity_id: expense.id,
            category_label: 'Expense',
            title: expense.expense_type?.name || 'Expense Claim',
            subtitle: `${expense.employee?.full_name || 'Employee'} · ${expense.amount_label || '—'}`,
            can_review: expense.can_review,
            can_cancel: expense.can_cancel,
            review_kind: 'expense',
            review_target: String(expense.id),
        });

        card.querySelector('.content-card-body').innerHTML = renderExpenseDetailHtml(expense);
    };

    const renderExpenseGroup = async () => {
        const { data } = await api.get(`/expense-groups/${entityId}`);
        const group = data.data.expense_group;

        renderHeader({
            category: 'expense_group',
            entity_id: group.id,
            category_label: 'Expense Group',
            title: group.name || 'Expense Group',
            subtitle: `${group.employee?.full_name || 'Employee'} · ${group.total_amount_label || '—'}`,
            can_review: group.can_review,
            can_cancel: group.can_cancel,
            review_kind: 'expense_group',
            review_target: String(group.id),
        });

        card.querySelector('.content-card-body').innerHTML = renderExpenseGroupDetailHtml(group);
    };

    const renderHubBackedRequest = async () => {
        const item = await loadHubItem();

        renderHeader({
            ...item,
            title: item.subject || item.category_label,
            subtitle: `${item.requester_name || 'Employee'}${item.requester_code ? ` · ${item.requester_code}` : ''}`,
        });

        card.querySelector('.content-card-body').innerHTML = `
            <div class="row g-4">
                <div class="col-md-6"><span class="text-muted">Request Type</span><div class="fw-semibold">${item.category_label || 'Request'}</div></div>
                <div class="col-md-6"><span class="text-muted">Requested By</span><div>${item.requester_name || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Employee ID</span><div>${item.requester_code || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Submitted On</span><div>${item.submitted_at_label || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label || item.status}</div></div>
                <div class="col-12"><span class="text-muted">Details</span><div>${item.detail || '—'}</div></div>
                ${item.reason ? `<div class="col-12"><span class="text-muted">Reason / Notes</span><div>${item.reason}</div></div>` : ''}
            </div>
        `;
    };

    const load = async () => {
        try {
            if (category === 'regularization') {
                await renderRegularization();
                return;
            }

            if (category === 'regularization-batch') {
                await renderRegularizationBatch();
                return;
            }

            if (category === 'expense') {
                await renderExpense();
                return;
            }

            if (category === 'expense_group') {
                await renderExpenseGroup();
                return;
            }

            await renderHubBackedRequest();
        } catch (error) {
            card.querySelector('.content-card-body').innerHTML = `<div class="text-danger py-4 text-center">${getErrorMessage(error)}</div>`;
            headerActions?.classList.add('d-none');
        }
    };

    bindRequestReviewHandlers(document, {
        onSuccess: async (message) => {
            showAlert(message);
            await load();
        },
        onError: (error) => {
            showAlert(getErrorMessage(error), 'danger');
        },
    });

    await load();
});
