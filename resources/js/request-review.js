import api from './api';
import { renderActionGroup, renderCancelIconButton, renderViewLink } from './action-icons';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';
import { confirmLeaveCancel, confirmRequestCancel } from './swal-utils';

export const reviewEndpoints = {
    leave: (id) => ({
        approve: `/leave-requests/${id}/approve`,
        reject: `/leave-requests/${id}/reject`,
    }),
    regularization: (id) => ({
        approve: `/attendance-regularizations/${id}/approve`,
        reject: `/attendance-regularizations/${id}/reject`,
    }),
    regularization_batch: (id) => ({
        approve: `/attendance-regularizations/batch/${id}/approve`,
        reject: `/attendance-regularizations/batch/${id}/reject`,
    }),
    document: (id) => ({
        approve: `/employee-documents/${id}/approve`,
        reject: `/employee-documents/${id}/reject`,
    }),
    payment_method: (id) => ({
        approve: `/employee-payment-methods/${id}/approve`,
        reject: `/employee-payment-methods/${id}/reject`,
    }),
    family_member: (id) => ({
        approve: `/employee-family-members/${id}/approve`,
        reject: `/employee-family-members/${id}/reject`,
    }),
    personal_section: (id) => ({
        approve: `/employee-personal-sections/${id}/approve`,
        reject: `/employee-personal-sections/${id}/reject`,
    }),
    compliance_field: (id) => ({
        approve: `/employee-compliance-fields/${id}/approve`,
        reject: `/employee-compliance-fields/${id}/reject`,
    }),
    expense: (id) => ({
        approve: `/expenses/${id}/approve`,
        reject: `/expenses/${id}/reject`,
    }),
    expense_group: (id) => ({
        approve: `/expense-groups/${id}/approve`,
        reject: `/expense-groups/${id}/reject`,
    }),
    job_requisition: (id) => ({
        approve: `/job-requisitions/${id}/approve`,
        reject: `/job-requisitions/${id}/reject`,
    }),
};

export const cancelEndpoints = {
    leave: (id) => `/leave-requests/${id}/cancel`,
    regularization: (id) => `/attendance-regularizations/${id}/cancel`,
    expense: (id) => `/expenses/${id}/cancel`,
    expense_group: (id) => `/expense-groups/${id}/cancel`,
};

export const reviewToken = (item) => `${item.review_kind}:${item.review_target}`;

export const renderRequestActions = (item, {
    includeView = true,
    approveAttr = 'data-approve-request',
    rejectAttr = 'data-reject-request',
    cancelAttr = 'data-cancel-request',
} = {}) => {
    const actions = [];

    if (includeView && item.view_url) {
        actions.push(renderViewLink(item.view_url, 'View request'));
    }

    if (item.can_review && item.review_kind && item.review_target) {
        actions.push(renderApproveIconButton(approveAttr, reviewToken(item), 'Approve request'));
        actions.push(renderRejectIconButton(rejectAttr, reviewToken(item), 'Reject request (decline)'));
    }

    if (item.can_cancel && cancelEndpoints[item.category]) {
        actions.push(renderCancelIconButton(cancelAttr, `${item.category}:${item.entity_id}`, 'Cancel request (withdraw)'));
    }

    return actions.length ? renderActionGroup(actions.join('')) : '<span class="text-muted">—</span>';
};

export const renderHeaderReviewActions = (item, {
    approveAttr = 'data-approve-request',
    rejectAttr = 'data-reject-request',
    cancelAttr = 'data-cancel-request',
} = {}) => {
    const actions = [];

    if (item.can_review && item.review_kind && item.review_target) {
        actions.push(renderApproveIconButton(approveAttr, reviewToken(item), 'Approve request'));
        actions.push(renderRejectIconButton(rejectAttr, reviewToken(item), 'Reject request (decline)'));
    }

    if (item.can_cancel && cancelEndpoints[item.category]) {
        actions.push(renderCancelIconButton(cancelAttr, `${item.category}:${item.entity_id}`, 'Cancel request (withdraw)'));
    }

    return actions.length ? renderActionGroup(actions.join('')) : '';
};

export const reviewSingleRequest = async (token, action, notes = null) => {
    const [kind, id] = token.split(':');
    const endpoints = reviewEndpoints[kind];

    if (!endpoints || !id) {
        throw new Error('Unsupported request type.');
    }

    if (action === 'approve') {
        const { data } = await api.patch(endpoints(id).approve);
        return data.message || 'Request approved.';
    }

    const reason = notes?.trim() || prompt('Rejection reason:')?.trim();
    if (!reason) {
        return null;
    }

    const { data } = await api.patch(endpoints(id).reject, { notes: reason });
    return data.message || 'Request rejected.';
};

export const bulkReviewRequests = async (items, action, notes = null) => {
    const payload = {
        action,
        items: items.map((item) => ({
            kind: item.review_kind,
            target: String(item.review_target),
        })),
    };

    if (action === 'reject') {
        const reason = notes?.trim() || prompt('Rejection reason for selected requests:')?.trim();
        if (!reason) {
            return null;
        }
        payload.notes = reason;
    }

    const { data } = await api.post('/request-hub/bulk-review', payload);
    return data;
};

export const cancelRequest = async (token) => {
    const [category, id] = token.split(':');
    const endpoint = cancelEndpoints[category]?.(id);

    if (!endpoint) {
        throw new Error('This request cannot be cancelled.');
    }

    const confirmed = category === 'leave'
        ? await confirmLeaveCancel()
        : await confirmRequestCancel();

    if (!confirmed) {
        return null;
    }

    const { data } = await api.patch(endpoint);
    return data.message || (category === 'leave'
        ? 'Leave request has been cancelled.'
        : 'Request has been cancelled.');
};

export const bindRequestReviewHandlers = (root, {
    onSuccess,
    onError,
    approveAttr = '[data-approve-request]',
    rejectAttr = '[data-reject-request]',
    cancelAttr = '[data-cancel-request]',
} = {}) => {
    root?.addEventListener('click', async (event) => {
        const approve = event.target.closest(approveAttr);
        const reject = event.target.closest(rejectAttr);
        const cancel = event.target.closest(cancelAttr);

        if (approve) {
            try {
                const message = await reviewSingleRequest(approve.dataset.approveRequest, 'approve');
                if (message) {
                    onSuccess?.(message);
                }
            } catch (error) {
                onError?.(error);
            }
            return;
        }

        if (reject) {
            try {
                const message = await reviewSingleRequest(reject.dataset.rejectRequest, 'reject');
                if (message) {
                    onSuccess?.(message);
                }
            } catch (error) {
                onError?.(error);
            }
            return;
        }

        if (cancel) {
            try {
                const message = await cancelRequest(cancel.dataset.cancelRequest);
                if (message) {
                    onSuccess?.(message);
                }
            } catch (error) {
                onError?.(error);
            }
        }
    });
};

export const renderSimplePagination = (pagination, label = 'requests') => {
    if (!pagination?.total) {
        return {
            info: `No pending ${label}`,
            pages: '',
        };
    }

    const pages = Array.from({ length: pagination.last_page }, (_, index) => {
        const page = index + 1;
        return `
            <li class="page-item ${page === pagination.current_page ? 'active' : ''}">
                <button type="button" class="page-link" data-page="${page}">${page}</button>
            </li>
        `;
    }).join('');

    return {
        info: `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total} ${label}`,
        pages,
    };
};
