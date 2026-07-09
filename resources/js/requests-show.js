import api, { getErrorMessage } from './api';
import { renderDateTimeStackFromLabel } from './datetime-utils';
import { bindBackButton, buildCategoryReturnUrl, showAutoDismissAlert } from './form-utils';
import { renderExpenseDetailHtml, renderExpenseGroupDetailHtml } from './expense-modals';
import {
    bindRequestReviewHandlers,
    mountRequestShowActions,
} from './request-review';
import {
    renderEmployeeNameBlock,
    renderHubRequestDetailHtml,
    renderRegularizationBatchDates,
    renderRegularizationPunchFields,
} from './request-display';
import {
    bindRequestAttachmentHandlers,
    bindRequestAttachmentLightbox,
    loadProfilePhotoPreviews,
} from './request-attachments';

document.addEventListener('DOMContentLoaded', async () => {
    const card = document.getElementById('requestShowCard');
    const alertBox = document.getElementById('requestShowAlert');
    const titleEl = document.getElementById('requestShowTitle');
    const subtitleEl = document.getElementById('requestShowSubtitle');
    const toolbarEl = document.getElementById('requestShowCardToolbar');
    const detailsEl = document.getElementById('requestShowCardDetails');
    const category = card?.dataset.category;
    const entityId = card?.dataset.entityId;

    if (!card || !category || !entityId) {
        return;
    }

    bindBackButton('requestShowBackBtn', buildCategoryReturnUrl(category));

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        showAutoDismissAlert(alertBox, message, type);
    };

    bindRequestAttachmentLightbox();
    bindRequestAttachmentHandlers(detailsEl, {
        onError: (error) => showAlert(getErrorMessage(error), 'danger'),
    });

    const renderPageHeader = (item) => {
        if (titleEl) {
            titleEl.textContent = item.title || item.category_label || 'Request Details';
        }

        if (subtitleEl) {
            subtitleEl.textContent = item.subtitle || '';
        }
    };

    const setCardBody = (html, actionItem) => {
        mountRequestShowActions(toolbarEl, actionItem);

        if (detailsEl) {
            detailsEl.innerHTML = html;
        }

        if (category === 'profile_photo') {
            loadProfilePhotoPreviews(detailsEl, {
                onError: (error) => showAlert(getErrorMessage(error), 'danger'),
            });
        }
    };

    const loadHubItem = async () => {
        const { data } = await api.get(`/request-hub/${category}/${entityId}`);
        const item = data.data?.request;

        if (!item) {
            throw new Error('Request not found.');
        }

        return item;
    };

    const renderRegularization = async () => {
        const { data } = await api.get(`/attendance-regularizations/${entityId}`);
        const item = data.data.regularization_request;
        const employeeName = item.employee?.full_name || 'Employee';
        const employeeCode = item.employee?.employee_code || '';
        const actionItem = {
            category: 'regularization',
            entity_id: item.id,
            status: item.status,
            can_review: item.can_review,
            can_cancel: item.can_cancel,
            review_kind: 'regularization',
            review_target: String(item.id),
        };

        renderPageHeader({
            category_label: 'Attendance Regularization',
            title: 'Attendance Regularization',
            subtitle: `${employeeName}${employeeCode ? ` · ${employeeCode}` : ''} · ${item.attendance_date_label || '—'}`,
        });

        setCardBody(`
            <div class="row g-4">
                <div class="col-md-6">
                    <span class="text-muted">Employee</span>
                    ${renderEmployeeNameBlock(employeeName, employeeCode)}
                </div>
                <div class="col-md-6"><span class="text-muted">Date</span><div>${item.attendance_date_label || '—'}</div></div>
                ${renderRegularizationPunchFields(item)}
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label || item.status}</div></div>
                <div class="col-md-6"><span class="text-muted">Submitted On</span><div>${renderDateTimeStackFromLabel(item.created_at_label)}</div></div>
                <div class="col-12"><span class="text-muted">Reason</span><div>${item.reason || '—'}</div></div>
                ${item.review_notes ? `<div class="col-12"><span class="text-muted">Review Notes</span><div>${item.review_notes}</div></div>` : ''}
            </div>
        `, actionItem);
    };

    const renderRegularizationBatch = async () => {
        const { data } = await api.get(`/attendance-regularizations/batch/${entityId}`);
        const group = data.data?.group;

        if (!group) {
            throw new Error('Regularization batch not found.');
        }

        const employeeName = group.employee?.full_name || 'Employee';
        const employeeCode = group.employee?.employee_code || '';
        const actionItem = {
            category: 'regularization',
            entity_id: group.request_ids?.[0],
            status: group.status,
            can_review: group.can_review,
            can_cancel: false,
            review_kind: 'regularization_batch',
            review_target: group.batch_id,
        };
        const singleDay = (group.dates || []).length === 1 ? group.dates[0] : null;

        renderPageHeader({
            category_label: 'Attendance Regularization',
            title: 'Attendance Regularization Batch',
            subtitle: `${employeeName}${employeeCode ? ` · ${employeeCode}` : ''} · ${group.day_count || 0} day(s)`,
        });

        setCardBody(`
            <div class="row g-4">
                <div class="col-md-6">
                    <span class="text-muted">Employee</span>
                    ${renderEmployeeNameBlock(employeeName, employeeCode)}
                </div>
                <div class="col-md-6"><span class="text-muted">Days</span><div>${group.day_count || 0}</div></div>
                ${singleDay ? renderRegularizationPunchFields(singleDay) : ''}
                <div class="col-12">
                    <span class="text-muted">Dates</span>
                    <ul class="mb-0 ps-3">${renderRegularizationBatchDates(group.dates || [])}</ul>
                </div>
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${group.status_label || group.status || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Submitted On</span><div>${renderDateTimeStackFromLabel(group.created_at_label)}</div></div>
                <div class="col-12"><span class="text-muted">Reason</span><div>${group.reason || '—'}</div></div>
                ${group.reviewed_at_label ? `<div class="col-12"><span class="text-muted">Reviewed</span><div>${group.reviewed_at_label}${group.reviewed_by_name ? ` by ${group.reviewed_by_name}` : ''}</div></div>` : ''}
            </div>
        `, actionItem);
    };

    const renderExpense = async () => {
        const { data } = await api.get(`/expenses/${entityId}`);
        const expense = data.data.expense;
        const actionItem = {
            category: 'expense',
            entity_id: expense.id,
            status: expense.status,
            can_review: expense.can_review,
            can_cancel: expense.can_cancel,
            review_kind: 'expense',
            review_target: String(expense.id),
        };

        renderPageHeader({
            category_label: 'Expense',
            title: expense.expense_type?.name || 'Expense Claim',
            subtitle: `${expense.employee?.full_name || 'Employee'}${expense.employee?.employee_code ? ` · ${expense.employee.employee_code}` : ''} · ${expense.amount_label || '—'}`,
        });

        setCardBody(renderExpenseDetailHtml(expense), actionItem);
    };

    const renderExpenseGroup = async () => {
        const { data } = await api.get(`/expense-groups/${entityId}`);
        const group = data.data.expense_group;
        const actionItem = {
            category: 'expense_group',
            entity_id: group.id,
            status: group.status,
            can_review: group.can_review,
            can_cancel: group.can_cancel,
            review_kind: 'expense_group',
            review_target: String(group.id),
        };

        renderPageHeader({
            category_label: 'Expense Group',
            title: group.name || 'Expense Group',
            subtitle: `${group.employee?.full_name || 'Employee'}${group.employee?.employee_code ? ` · ${group.employee.employee_code}` : ''} · ${group.total_amount_label || '—'}`,
        });

        setCardBody(renderExpenseGroupDetailHtml(group), actionItem);
    };

    const renderHubBackedRequest = async () => {
        const item = await loadHubItem();
        const actionItem = {
            category: item.category,
            entity_id: item.entity_id,
            status: item.status,
            can_review: item.can_review,
            can_cancel: item.can_cancel,
            review_kind: item.review_kind,
            review_target: item.review_target,
        };

        renderPageHeader({
            title: item.document_type_name || item.subject || item.category_label,
            subtitle: `${item.requester_name || 'Employee'}${item.requester_code ? ` · ${item.requester_code}` : ''}`,
        });

        setCardBody(renderHubRequestDetailHtml(item), actionItem);
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
            if (toolbarEl) {
                toolbarEl.innerHTML = '';
                toolbarEl.classList.add('d-none');
            }

            if (detailsEl) {
                detailsEl.innerHTML = `<div class="text-danger py-4 text-center">${getErrorMessage(error)}</div>`;
            }
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
