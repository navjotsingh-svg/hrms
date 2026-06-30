import api from './api';
import { composeActionGroup, renderCancelIconButton, renderViewLink } from './action-icons';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';
import { confirmLeaveCancel, confirmRequestCancel, promptRequestReviewRemarks } from './swal-utils';

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
    profile_photo: (id) => ({
        approve: `/employee-profile-photos/${id}/approve`,
        reject: `/employee-profile-photos/${id}/reject`,
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

export const canShowRequestReviewActions = (item) => (
    item?.status === 'pending'
    && item?.can_review
    && item?.review_kind
    && item?.review_target
);

export const canShowRequestCancelAction = (item) => (
    item?.status === 'pending'
    && item?.can_cancel
    && cancelEndpoints[item.category]
);

export const buildReviewPayload = (kind, action, notes) => {
    const trimmed = notes?.trim() || '';

    if (kind === 'job_requisition') {
        return action === 'reject'
            ? { reason: trimmed }
            : (trimmed ? { notes: trimmed } : {});
    }

    if (kind === 'expense' || kind === 'expense_group') {
        return action === 'approve'
            ? (trimmed ? { review_notes: trimmed } : {})
            : { notes: trimmed };
    }

    return trimmed ? { notes: trimmed } : {};
};

export const renderRequestActions = (item, {
    includeView = true,
    includeReview = false,
    viewOverride = null,
    approveAttr = 'data-approve-request',
    rejectAttr = 'data-reject-request',
    cancelAttr = 'data-cancel-request',
} = {}) => composeActionGroup({
    view: includeView && (viewOverride || (item.view_url ? renderViewLink(item.view_url, 'View request') : '')),
    approve: includeReview && canShowRequestReviewActions(item)
        ? renderApproveIconButton(approveAttr, reviewToken(item), 'Approve request')
        : '',
    reject: includeReview && canShowRequestReviewActions(item)
        ? renderRejectIconButton(rejectAttr, reviewToken(item), 'Reject request (decline)')
        : '',
    cancel: canShowRequestCancelAction(item)
        ? renderCancelIconButton(cancelAttr, `${item.category}:${item.entity_id}`, 'Cancel request (withdraw)')
        : '',
});

export const renderHeaderReviewActions = (item, {
    approveAttr = 'data-approve-request',
    rejectAttr = 'data-reject-request',
    cancelAttr = 'data-cancel-request',
} = {}) => {
    const parts = {
        approve: canShowRequestReviewActions(item)
            ? renderApproveIconButton(approveAttr, reviewToken(item), 'Approve request')
            : '',
        reject: canShowRequestReviewActions(item)
            ? renderRejectIconButton(rejectAttr, reviewToken(item), 'Reject request (decline)')
            : '',
        cancel: canShowRequestCancelAction(item)
            ? renderCancelIconButton(cancelAttr, `${item.category}:${item.entity_id}`, 'Cancel request (withdraw)')
            : '',
    };

    return Object.values(parts).some(Boolean) ? composeActionGroup(parts) : '';
};

export const renderDetailReviewActionsBar = (item, {
    approveAttr = 'data-approve-request',
    rejectAttr = 'data-reject-request',
    cancelAttr = 'data-cancel-request',
} = {}) => {
    const buttons = [];

    if (canShowRequestReviewActions(item)) {
        buttons.push(`<button type="button" class="btn btn-success btn-sm" ${approveAttr}="${reviewToken(item)}">Approve</button>`);
        buttons.push(`<button type="button" class="btn btn-outline-danger btn-sm" ${rejectAttr}="${reviewToken(item)}">Reject</button>`);
    }

    if (canShowRequestCancelAction(item)) {
        buttons.push(`<button type="button" class="btn btn-outline-secondary btn-sm" ${cancelAttr}="${item.category}:${item.entity_id}">Cancel</button>`);
    }

    if (!buttons.length) {
        return '';
    }

    return `
        <div class="request-show-actions d-flex flex-wrap gap-2 justify-content-end">
            ${buttons.join('')}
        </div>
    `;
};

export const renderRequestShowCardLayout = (contentHtml, actionItem) => contentHtml;

export const mountRequestShowActions = (toolbarEl, actionItem) => {
    if (!toolbarEl) {
        return;
    }

    const actions = renderDetailReviewActionsBar(actionItem);

    toolbarEl.innerHTML = actions;
    toolbarEl.classList.toggle('d-none', !actions);
};

export const reviewSingleRequest = async (token, action, notes = undefined) => {
    const [kind, id] = token.split(':');
    const endpoints = reviewEndpoints[kind];

    if (!endpoints || !id) {
        throw new Error('Unsupported request type.');
    }

    let remarks = notes;

    if (remarks === undefined) {
        remarks = await promptRequestReviewRemarks({ action, count: 1 });

        if (remarks === null) {
            return null;
        }
    }

    const payload = buildReviewPayload(kind, action, remarks);
    const endpoint = action === 'approve' ? endpoints(id).approve : endpoints(id).reject;
    const { data } = await api.patch(endpoint, payload);

    return data.message || (action === 'approve' ? 'Request approved.' : 'Request rejected.');
};

export const bulkReviewRequests = async (items, action, notes = undefined) => {
    let remarks = notes;

    if (remarks === undefined) {
        remarks = await promptRequestReviewRemarks({ action, count: items.length });

        if (remarks === null) {
            return null;
        }
    }

    const payload = {
        action,
        items: items.map((item) => ({
            kind: item.review_kind,
            target: String(item.review_target),
        })),
    };

    if (remarks?.trim()) {
        payload.notes = remarks.trim();
    } else if (action === 'reject') {
        throw new Error('Rejection reason is required.');
    }

    const { data } = await api.post('/request-hub/bulk-review', payload);

    return data;
};

export const patchProfileReviewAction = async (url, kind, action) => {
    const remarks = await promptRequestReviewRemarks({ action, count: 1 });

    if (remarks === null) {
        return null;
    }

    const payload = buildReviewPayload(kind, action, remarks);
    const { data } = await api.patch(url, payload);

    return data.message || (action === 'approve' ? 'Request approved.' : 'Request rejected.');
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
