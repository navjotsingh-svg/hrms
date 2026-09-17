import api, { getErrorMessage } from './api';
import { bindPagination, bindPerPageSelect, getSerialNumber, readPerPage, renderListPagination } from './pagination';

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('companyPoliciesRoot');
    const tableBody = document.getElementById('companyPoliciesTableBody');
    const alertBox = document.getElementById('companyPoliciesAlert');
    const paginationInfo = document.getElementById('companyPoliciesPaginationInfo');
    const paginationList = document.getElementById('companyPoliciesPaginationList');
    const perPageSelect = document.getElementById('companyPoliciesPerPage');
    const filterSearch = document.getElementById('filterSearch');
    const filterCategory = document.getElementById('filterCategory');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');

    if (!root || !tableBody) {
        return;
    }

    const canManage = root.dataset.canManage === '1';
    const columnCount = canManage ? 7 : 6;

    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);
    let searchTimeout = null;
    let categories = [];

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderStatusPill = (status) => {
        const isPublished = status === 'published';

        return `<span class="company-status-pill ${isPublished ? 'company-status-pill--active' : 'company-status-pill--inactive'}">${isPublished ? 'Published' : 'Draft'}</span>`;
    };

    const consentLabel = (policy) => {
        if (!policy.requires_consent) {
            return 'Not required';
        }

        if (policy.has_consented) {
            return '<span class="text-success">Consented</span>';
        }

        return canManage ? 'Required' : '<span class="text-warning">Pending</span>';
    };

    const fillCategoryOptions = (selectEl) => {
        if (!selectEl) {
            return;
        }

        selectEl.innerHTML = [
            '<option value="">All categories</option>',
            ...categories.map((item) => `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`),
        ].join('');
    };

    const renderRows = (policies, pagination) => {
        if (!policies.length) {
            const emptyMessage = canManage
                ? 'No policies found.'
                : 'No published policies yet. Check back after HR publishes a policy page.';
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="text-center text-muted py-5">${emptyMessage}</td></tr>`;
            return;
        }

        tableBody.innerHTML = policies.map((policy, index) => {
            const serial = getSerialNumber(index, pagination);
            const viewUrl = `/company-policies/${policy.id}`;
            const editUrl = `/company-policies/${policy.id}/edit`;
            const actions = canManage
                ? `
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-secondary" href="${viewUrl}">View</a>
                        <a class="btn btn-sm btn-outline-primary" href="${editUrl}">Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-delete-policy="${policy.id}">Delete</button>
                    </div>
                `
                : `<a class="btn btn-sm btn-outline-primary" href="${viewUrl}">${policy.needs_consent ? 'View & Consent' : 'View'}</a>`;

            return `
                <tr>
                    <td>${serial}</td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(policy.title)}</div>
                        ${policy.description ? `<div class="small text-muted">${escapeHtml(policy.description)}</div>` : ''}
                        <div class="small text-muted">Version ${escapeHtml(policy.version)}</div>
                    </td>
                    <td>${escapeHtml(policy.category_label || policy.category)}</td>
                    <td>${consentLabel(policy)}</td>
                    <td>${escapeHtml(policy.updated_at_label || '—')}</td>
                    ${canManage ? `<td>${renderStatusPill(policy.status)}</td>` : ''}
                    <td>${actions}</td>
                </tr>
            `;
        }).join('');
    };

    const loadMeta = async () => {
        const { data } = await api.get('/company-policies/meta');
        categories = data.data?.categories || [];
        fillCategoryOptions(filterCategory);
    };

    const loadPolicies = async () => {
        tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="text-center text-muted py-5">Loading policies...</td></tr>`;

        try {
            const params = {
                page: currentPage,
                per_page: currentPerPage,
                search: filterSearch?.value?.trim() || undefined,
                category: filterCategory?.value || undefined,
                status: canManage ? (filterStatus?.value || undefined) : undefined,
            };

            const { data } = await api.get('/company-policies', { params });
            const policies = data.data?.policies || [];
            const pagination = data.data?.pagination;

            renderRows(policies, pagination);
            renderListPagination({
                infoEl: paginationInfo,
                listEl: paginationList,
                pagination,
                onPageChange: (page) => {
                    currentPage = page;
                    loadPolicies();
                },
            });
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="text-center text-danger py-5">${escapeHtml(getErrorMessage(error))}</td></tr>`;
        }
    };

    tableBody.addEventListener('click', async (event) => {
        const deleteBtn = event.target.closest('[data-delete-policy]');
        if (!deleteBtn) {
            return;
        }

        if (!window.confirm('Delete this policy page? Existing consents for it will also be removed.')) {
            return;
        }

        try {
            await api.delete(`/company-policies/${deleteBtn.dataset.deletePolicy}`);
            showAlert('Policy deleted successfully.');
            await loadPolicies();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    filterSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadPolicies();
        }, 300);
    });

    filterCategory?.addEventListener('change', () => {
        currentPage = 1;
        loadPolicies();
    });

    filterStatus?.addEventListener('change', () => {
        currentPage = 1;
        loadPolicies();
    });

    filterReset?.addEventListener('click', () => {
        if (filterSearch) filterSearch.value = '';
        if (filterCategory) filterCategory.value = '';
        if (filterStatus) filterStatus.value = '';
        currentPage = 1;
        loadPolicies();
    });

    bindPagination(paginationList, (page) => {
        currentPage = page;
        loadPolicies();
    });

    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        currentPage = 1;
        loadPolicies();
    });

    try {
        await loadMeta();
        await loadPolicies();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
});
