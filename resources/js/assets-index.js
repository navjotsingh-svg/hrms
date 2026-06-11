import api, { getErrorMessage } from './api';
import { consumePageFlashMessage } from './form-utils';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('assetsTableBody');
    const alertBox = document.getElementById('assetsAlert');
    const paginationInfo = document.getElementById('assetsPaginationInfo');
    const paginationList = document.getElementById('assetsPaginationList');
    const filterSearch = document.getElementById('filterSearch');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const routes = webRoutes();

    let currentPage = 1;
    let searchTimeout = null;

    if (!tableBody) {
        return;
    }

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderStatusToggle = (assetType) => {
        const isActive = assetType.status === 'active';
        const switchId = `asset-status-${assetType.id}`;
        const pillClass = isActive ? 'company-status-pill--active' : 'company-status-pill--inactive';

        return `
            <div class="company-status-cell">
                <div class="form-check form-switch company-status-switch mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="${switchId}"
                        data-status-toggle="${assetType.id}"
                        ${isActive ? 'checked' : ''}
                    >
                    <label
                        class="form-check-label company-status-pill ${pillClass}"
                        for="${switchId}"
                        data-status-label="${assetType.id}"
                    >
                        ${isActive ? 'Active' : 'Inactive'}
                    </label>
                </div>
            </div>
        `;
    };

    const setStatusLabel = (assetTypeId, status) => {
        const label = tableBody.querySelector(`[data-status-label="${assetTypeId}"]`);

        if (label) {
            label.textContent = status === 'active' ? 'Active' : 'Inactive';
            label.classList.remove('company-status-pill--active', 'company-status-pill--inactive');
            label.classList.add(status === 'active' ? 'company-status-pill--active' : 'company-status-pill--inactive');
        }
    };

    const renderRow = (assetType, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
        const editUrl = `${routes.assetEdit || '/masters/assets'}/${assetType.id}/edit`;

        return `
            <tr class="companies-data-row">
                <td class="companies-td-serial"><span class="companies-serial">${serial}</span></td>
                <td>
                    <div class="companies-company-info">
                        <span class="companies-company-name">${assetType.name}</span>
                    </div>
                </td>
                <td>${renderStatusToggle(assetType)}</td>
                <td class="companies-td-actions">
                    <div class="table-action-group">
                        <a href="${editUrl}" class="table-action-btn table-action-btn--edit" title="Edit" aria-label="Edit ${assetType.name}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>
                        </a>
                        <button type="button" class="table-action-btn table-action-btn--delete" title="Delete" aria-label="Delete ${assetType.name}" data-delete-asset="${assetType.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    };

    const renderPagination = (pagination) => {
        if (!paginationList || !paginationInfo) {
            return;
        }

        if (!pagination?.total) {
            paginationInfo.textContent = 'No assets found';
            paginationList.innerHTML = '';
            return;
        }

        paginationInfo.textContent = `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total} assets`;

        paginationList.innerHTML = Array.from({ length: pagination.last_page }, (_, index) => {
            const page = index + 1;

            return `
                <li class="page-item ${page === pagination.current_page ? 'active' : ''}">
                    <button type="button" class="page-link" data-page="${page}">${page}</button>
                </li>
            `;
        }).join('');
    };

    const loadAssets = async (page = 1) => {
        currentPage = page;

        const params = { page, per_page: 10 };

        if (filterSearch?.value.trim()) {
            params.search = filterSearch.value.trim();
        }

        if (filterStatus?.value) {
            params.status = filterStatus.value;
        }

        try {
            const { data } = await api.get('/asset-types', { params });
            const assetTypes = data.data.asset_types || [];
            const pagination = data.data.pagination;

            if (assetTypes.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-5">No assets found.</td></tr>';
            } else {
                tableBody.innerHTML = assetTypes.map((assetType, index) => renderRow(assetType, index, pagination)).join('');
            }

            renderPagination(pagination);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    filterSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadAssets(1), 400);
    });

    filterStatus?.addEventListener('change', () => loadAssets(1));

    filterReset?.addEventListener('click', () => {
        if (filterSearch) {
            filterSearch.value = '';
        }

        if (filterStatus) {
            filterStatus.value = '';
        }

        loadAssets(1);
    });

    paginationList?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');

        if (!button) {
            return;
        }

        loadAssets(Number(button.dataset.page));
    });

    tableBody.addEventListener('change', async (event) => {
        const toggle = event.target.closest('[data-status-toggle]');

        if (!toggle) {
            return;
        }

        const assetTypeId = toggle.dataset.statusToggle;
        const status = toggle.checked ? 'active' : 'inactive';
        const previousChecked = !toggle.checked;

        toggle.disabled = true;

        try {
            const { data } = await api.patch(`/asset-types/${assetTypeId}/status`, { status });
            setStatusLabel(assetTypeId, data.data.asset_type.status);
            showAlert(data.message || 'Asset status updated successfully.');
        } catch (error) {
            toggle.checked = previousChecked;
            setStatusLabel(assetTypeId, previousChecked ? 'active' : 'inactive');
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            toggle.disabled = false;
        }
    });

    tableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-asset]');

        if (!button) {
            return;
        }

        if (!window.confirm('Delete this asset type?')) {
            return;
        }

        try {
            const { data } = await api.delete(`/asset-types/${button.dataset.deleteAsset}`);
            showAlert(data.message || 'Asset deleted successfully.');
            await loadAssets(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    const flash = consumePageFlashMessage();

    if (flash?.message) {
        showAlert(flash.message, flash.type || 'success');
    }

    await loadAssets();
});
