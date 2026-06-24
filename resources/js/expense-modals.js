import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';

export const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

export const expenseStatusClass = (status) => ({
    draft: 'company-status-pill--draft',
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'company-status-pill--rejected',
    cancelled: 'company-status-pill--cancelled',
    unpaid: 'company-status-pill--inactive',
    paid: 'company-status-pill--active',
}[status] || '');

const detailRow = (label, value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return `<div class="row py-2 border-bottom"><div class="col-sm-4 text-muted">${escapeHtml(label)}</div><div class="col-sm-8">${value}</div></div>`;
};

export const renderExpenseDetailHtml = (expense) => {
    const receipts = (expense.attachments || []).map((file) => (
        `<a href="${escapeHtml(file.file_url)}" target="_blank" rel="noopener">${escapeHtml(file.original_name || 'Receipt')}</a>`
    )).join('<br>') || '—';

    return `
        ${detailRow('Employee', escapeHtml(expense.employee?.full_name || '—'))}
        ${detailRow('Expense date', escapeHtml(expense.expense_date_label || '—'))}
        ${detailRow('Type', escapeHtml(expense.expense_type?.name || '—'))}
        ${detailRow('Amount', escapeHtml(expense.amount_label || '—'))}
        ${detailRow('Merchant', escapeHtml(expense.merchant || '—'))}
        ${detailRow('Reference #', escapeHtml(expense.reference_number || '—'))}
        ${detailRow('Description', escapeHtml(expense.description || '—'))}
        ${detailRow('Claim reimbursement', expense.claim_reimbursement ? 'Yes' : 'No')}
        ${detailRow('Approval status', `<span class="badge ${expenseStatusClass(expense.status)}">${escapeHtml(expense.status_label || '—')}</span>`)}
        ${detailRow('Payout status', `<span class="badge ${expenseStatusClass(expense.payout_status)}">${escapeHtml(expense.payout_status_label || '—')}</span>`)}
        ${detailRow('Submitted on', escapeHtml(expense.created_at_label || '—'))}
        ${detailRow('Reviewed by', escapeHtml(expense.reviewed_by?.name || '—'))}
        ${detailRow('Review notes', escapeHtml(expense.review_notes || '—'))}
        ${detailRow('Receipt', receipts)}
    `;
};

export const renderExpenseGroupDetailHtml = (group) => {
    const expenseRows = (group.expenses || []).map((expense) => {
        const receipt = expense.attachments?.length
            ? `<a href="${escapeHtml(expense.attachments[0].file_url)}" target="_blank" rel="noopener">View</a>`
            : '—';

        return `<tr>
            <td>${escapeHtml(expense.expense_date_label || '—')}</td>
            <td>${escapeHtml(expense.expense_type?.name || '—')}</td>
            <td>${escapeHtml(expense.amount_label || '—')}</td>
            <td>${receipt}</td>
        </tr>`;
    }).join('') || '<tr><td colspan="4" class="text-muted">No expenses in this group.</td></tr>';

    return `
        ${detailRow('Employee', escapeHtml(group.employee?.full_name || '—'))}
        ${detailRow('Period', `${escapeHtml(group.from_date_label || '—')} – ${escapeHtml(group.to_date_label || '—')}`)}
        ${detailRow('Purpose', escapeHtml(group.description || '—'))}
        ${detailRow('Total amount', escapeHtml(group.total_amount_label || '—'))}
        ${detailRow('Approved reimbursable', escapeHtml(group.approved_reimbursable_label || '—'))}
        ${detailRow('Travel advance', escapeHtml(group.travel_advance_label || '—'))}
        ${detailRow('Net adjustment', escapeHtml(group.net_adjustment_label || '—'))}
        ${detailRow('Status', `<span class="badge ${expenseStatusClass(group.status)}">${escapeHtml(group.status_label || '—')}</span>`)}
        ${detailRow('Reviewed by', escapeHtml(group.reviewed_by?.name || '—'))}
        ${detailRow('Review notes', escapeHtml(group.review_notes || '—'))}
        <div class="table-responsive mt-3">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Receipt</th></tr></thead>
                <tbody>${expenseRows}</tbody>
            </table>
        </div>
    `;
};

export const bindExpenseRequestViewModal = ({
    modalId = 'expenseRequestDetailModal',
    titleId = 'expenseRequestDetailTitle',
    bodyId = 'expenseRequestDetailBody',
    onError = null,
} = {}) => {
    const modalEl = document.getElementById(modalId);

    if (!modalEl) {
        return { openExpense: async () => {}, openExpenseGroup: async () => {} };
    }

    const modal = Modal.getOrCreateInstance(modalEl);
    const titleEl = document.getElementById(titleId);
    const bodyEl = document.getElementById(bodyId);

    const showError = (error) => {
        if (typeof onError === 'function') {
            onError(getErrorMessage(error));
        }
    };

    const openExpense = async (expenseId) => {
        try {
            const response = await api.get(`/expenses/${expenseId}`);
            const expense = response.data.data.expense;

            if (titleEl) {
                titleEl.textContent = expense.expense_type?.name
                    ? `Expense · ${expense.expense_type.name}`
                    : 'Expense Details';
            }

            if (bodyEl) {
                bodyEl.innerHTML = renderExpenseDetailHtml(expense);
            }

            modal.show();
        } catch (error) {
            showError(error);
        }
    };

    const openExpenseGroup = async (groupId) => {
        try {
            const response = await api.get(`/expense-groups/${groupId}`);
            const group = response.data.data.expense_group;

            if (titleEl) {
                titleEl.textContent = group.name || 'Expense Group Details';
            }

            if (bodyEl) {
                bodyEl.innerHTML = renderExpenseGroupDetailHtml(group);
            }

            modal.show();
        } catch (error) {
            showError(error);
        }
    };

    return { openExpense, openExpenseGroup, modalEl };
};

export const renderExpenseViewButton = (category, entityId, label = 'View request') => `
    <button type="button" class="table-action-btn table-action-btn--view" data-view-request="${category}:${entityId}" title="${label}" aria-label="${label}">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
    </button>
`;
