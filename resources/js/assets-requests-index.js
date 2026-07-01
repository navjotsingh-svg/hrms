import api, { getErrorMessage } from './api';
import { showAutoDismissAlert } from './form-utils';
import {
    bindAssetItemReviewHandlers,
    renderAssetItemsTable,
} from './asset-item-review';
import { composeActionGroup, renderCancelIconButton, renderViewLink } from './action-icons';
import { cancelRequest } from './request-review';

const routes = () => window.HRMS_WEB_ROUTES || {};

const requestStatusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'company-status-pill--rejected',
    cancelled: 'company-status-pill--cancelled',
    partially_reviewed: 'company-status-pill--inactive',
}[status] || '');

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('assetRequestsTableBody');
    const alertBox = document.getElementById('assetRequestsAlert');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const paginationInfo = document.getElementById('assetRequestsPaginationInfo');
    const paginationList = document.getElementById('assetRequestsPaginationList');
    const pendingContainer = document.getElementById('assetPendingContainer');
    const pendingBadge = document.getElementById('assetPendingBadge');
    const pendingCard = document.getElementById('assetPendingCard');
    let currentPage = 1;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        showAutoDismissAlert(alertBox, message, type);
    };

    const renderRow = (item, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
        return `<tr>
            <td>${serial}</td>
            <td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>
            <td>${item.assets_label || item.asset_type?.name || '—'}</td>
            <td><span class="company-status-pill ${requestStatusClass(item.status)}">${item.status_label}</span></td>
            <td>${composeActionGroup({
                view: renderViewLink(`${routes().assetRequestsShow || '/asset-requests'}/${item.id}`, 'View asset request'),
                cancel: item.can_cancel
                    ? renderCancelIconButton('data-cancel-asset', item.id, 'Cancel asset request')
                    : '',
            })}</td>
        </tr>`;
    };

    const renderPending = (requests) => {
        if (!pendingContainer) {
            return;
        }

        if (!requests.length) {
            pendingCard?.classList.add('d-none');
            return;
        }

        pendingCard?.classList.remove('d-none');
        pendingBadge?.classList.remove('d-none');
        if (pendingBadge) {
            pendingBadge.textContent = String(requests.length);
        }

        pendingContainer.innerHTML = requests.map((item) => {
            const viewLink = renderViewLink(`${routes().assetRequestsShow || '/asset-requests'}/${item.id}`, 'View request');

            return `
                <div class="border rounded p-3 mb-3 asset-pending-request" data-request-id="${item.id}">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${item.employee?.full_name || 'Employee'}</div>
                            <div class="small text-muted">${item.created_at_label || ''}</div>
                            <div class="small mt-2">${item.reason || ''}</div>
                        </div>
                        ${composeActionGroup({ view: viewLink })}
                    </div>
                    ${renderAssetItemsTable(item, { showCheckboxes: item.can_review })}
                </div>
            `;
        }).join('');
    };

    const loadPending = async () => {
        if (!pendingContainer) {
            return;
        }

        try {
            const { data } = await api.get('/asset-requests/pending');
            renderPending(data.data.asset_requests || []);
        } catch {
            pendingContainer.innerHTML = '<div class="text-danger">Unable to load pending asset requests.</div>';
        }
    };

    const loadRequests = async (page = 1) => {
        currentPage = page;
        const params = { page, per_page: 10 };

        if (filterStatus?.value) {
            params.status = filterStatus.value;
        }

        try {
            const { data } = await api.get('/asset-requests', { params });
            const requests = data.data.asset_requests || [];
            const pagination = data.data.pagination;

            tableBody.innerHTML = requests.length
                ? requests.map((item, i) => renderRow(item, i, pagination)).join('')
                : '<tr><td colspan="5" class="text-center text-muted py-5">No asset requests found.</td></tr>';

            paginationInfo.textContent = pagination?.total
                ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
                : 'No asset requests found';

            paginationList.innerHTML = pagination?.last_page
                ? Array.from({ length: pagination.last_page }, (_, i) => {
                    const p = i + 1;
                    return `<li class="page-item ${p === pagination.current_page ? 'active' : ''}"><button type="button" class="page-link" data-page="${p}">${p}</button></li>`;
                }).join('')
                : '';
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const handleCancel = async (id) => {
        try {
            const message = await cancelRequest(`asset:${id}`);
            if (message) {
                showAlert(message);
                await Promise.all([loadRequests(currentPage), loadPending()]);
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    filterStatus?.addEventListener('change', () => loadRequests(1));
    filterReset?.addEventListener('click', () => {
        if (filterStatus) filterStatus.value = '';
        loadRequests(1);
    });
    paginationList?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (btn) loadRequests(Number(btn.dataset.page));
    });
    tableBody?.addEventListener('click', async (event) => {
        const cancel = event.target.closest('[data-cancel-asset]');
        if (cancel) {
            await handleCancel(cancel.dataset.cancelAsset);
        }
    });

    bindAssetItemReviewHandlers(pendingContainer, {
        onSuccess: async (message) => {
            showAlert(message);
            await Promise.all([loadRequests(currentPage), loadPending()]);
        },
        onError: (error) => showAlert(getErrorMessage(error), 'danger'),
    });

    const urlStatus = new URLSearchParams(window.location.search).get('status');
    if (urlStatus && filterStatus) {
        filterStatus.value = urlStatus;
    }

    await Promise.all([loadRequests(), loadPending()]);
});
