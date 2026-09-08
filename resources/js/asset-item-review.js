import api from './api';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';
import { promptRequestReviewRemarks } from './swal-utils';

export const itemStatusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'company-status-pill--rejected',
}[status] || '');

export const reviewAssetItems = async (requestId, itemIds, action, notes = undefined) => {
    let remarks = notes;

    if (remarks === undefined) {
        remarks = await promptRequestReviewRemarks({ action, count: itemIds.length });

        if (remarks === null) {
            return null;
        }
    }

    const payload = {
        action,
        item_ids: itemIds,
    };

    if (remarks?.trim()) {
        payload.notes = remarks.trim();
    }

    const { data } = await api.post(`/asset-requests/${requestId}/items/review`, payload);

    return data.message || (action === 'approve' ? 'Selected assets approved.' : 'Selected assets rejected.');
};

export const reviewSingleAssetItem = async (requestId, itemId, action) => {
    const remarks = await promptRequestReviewRemarks({ action, count: 1 });

    if (remarks === null) {
        return null;
    }

    const payload = remarks?.trim() ? { notes: remarks.trim() } : {};
    const endpoint = action === 'approve'
        ? `/asset-requests/${requestId}/items/${itemId}/approve`
        : `/asset-requests/${requestId}/items/${itemId}/reject`;

    const { data } = await api.patch(endpoint, payload);

    return data.message || (action === 'approve' ? 'Asset approved.' : 'Asset rejected.');
};

export const renderAssetItemsTable = (request, {
    showCheckboxes = false,
    approveAttr = 'data-approve-asset-item',
    rejectAttr = 'data-reject-asset-item',
    checkboxClass = 'asset-item-select',
} = {}) => {
    const items = request.items || [];

    if (!items.length) {
        return '<div class="text-muted small">No assets in this request.</div>';
    }

    const checkboxHeader = showCheckboxes
        ? '<th class="asset-item-checkbox-col"><input type="checkbox" class="form-check-input asset-item-select-all" aria-label="Select all pending assets"></th>'
        : '';

    const rows = items.map((item) => {
        const checkboxCell = showCheckboxes
            ? `<td class="asset-item-checkbox-col">${item.can_review
                ? `<input type="checkbox" class="form-check-input ${checkboxClass}" value="${item.id}" aria-label="Select ${item.asset_type?.name || 'asset'}">`
                : ''}</td>`
            : '';

        const reviewActions = item.can_review
            ? `<div class="table-action-group">
                ${renderApproveIconButton(approveAttr, `${request.id}:${item.id}`, `Approve ${item.asset_type?.name || 'asset'}`)}
                ${renderRejectIconButton(rejectAttr, `${request.id}:${item.id}`, `Reject ${item.asset_type?.name || 'asset'}`)}
            </div>`
            : '<span class="text-muted small">—</span>';

        return `<tr>
            ${checkboxCell}
            <td class="fw-semibold">${item.asset_type?.name || '—'}</td>
            <td><span class="company-status-pill ${itemStatusClass(item.status)}">${item.status_label}</span></td>
            <td class="small">${item.review_notes || '—'}</td>
            <td class="text-end">${reviewActions}</td>
        </tr>`;
    }).join('');

    const bulkToolbar = showCheckboxes && request.can_review && request.has_pending_items
        ? `<div class="d-flex flex-wrap gap-2 mb-3 asset-item-bulk-actions">
            <button type="button" class="btn btn-success btn-sm" data-bulk-approve-assets="${request.id}">Approve Selected</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bulk-reject-assets="${request.id}">Reject Selected</button>
        </div>`
        : '';

    return `
        <div class="asset-review-scope">
        ${bulkToolbar}
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        ${checkboxHeader}
                        <th>Asset</th>
                        <th>Status</th>
                        <th>Review Remarks</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
        </div>
    `;
};

export const getSelectedAssetItemIds = (root, checkboxClass = 'asset-item-select') => {
    const scope = root?.classList?.contains('asset-review-scope')
        ? root
        : root?.querySelector?.('.asset-review-scope') || root;

    return Array.from(scope.querySelectorAll(`.${checkboxClass}:checked`))
        .map((input) => Number(input.value))
        .filter(Boolean);
};

export const bindAssetItemReviewHandlers = (root, {
    onSuccess,
    onError,
    approveAttr = '[data-approve-asset-item]',
    rejectAttr = '[data-reject-asset-item]',
    bulkApproveAttr = '[data-bulk-approve-assets]',
    bulkRejectAttr = '[data-bulk-reject-assets]',
    checkboxClass = 'asset-item-select',
} = {}) => {
    root?.addEventListener('click', async (event) => {
        const approve = event.target.closest(approveAttr);
        const reject = event.target.closest(rejectAttr);
        const bulkApprove = event.target.closest(bulkApproveAttr);
        const bulkReject = event.target.closest(bulkRejectAttr);

        if (approve) {
            const [requestId, itemId] = approve.dataset.approveAssetItem.split(':');

            try {
                const message = await reviewSingleAssetItem(requestId, itemId, 'approve');
                if (message) {
                    onSuccess?.(message);
                }
            } catch (error) {
                onError?.(error);
            }

            return;
        }

        if (reject) {
            const [requestId, itemId] = reject.dataset.rejectAssetItem.split(':');

            try {
                const message = await reviewSingleAssetItem(requestId, itemId, 'reject');
                if (message) {
                    onSuccess?.(message);
                }
            } catch (error) {
                onError?.(error);
            }

            return;
        }

        if (bulkApprove || bulkReject) {
            const trigger = bulkApprove || bulkReject;
            const requestId = trigger.dataset[
                bulkApprove ? 'bulkApproveAssets' : 'bulkRejectAssets'
            ];
            const scope = trigger.closest('.asset-review-scope');
            const itemIds = getSelectedAssetItemIds(scope, checkboxClass);

            if (!itemIds.length) {
                onError?.(new Error('Select at least one pending asset to review.'));
                return;
            }

            try {
                const message = await reviewAssetItems(
                    requestId,
                    itemIds,
                    bulkApprove ? 'approve' : 'reject',
                );

                if (message) {
                    onSuccess?.(message);
                }
            } catch (error) {
                onError?.(error);
            }
        }
    });

    root?.addEventListener('change', (event) => {
        if (!event.target.classList.contains('asset-item-select-all')) {
            return;
        }

        const scope = event.target.closest('.asset-review-scope');
        const checked = event.target.checked;

        scope?.querySelectorAll(`.${checkboxClass}`).forEach((input) => {
            input.checked = checked;
        });
    });
};
