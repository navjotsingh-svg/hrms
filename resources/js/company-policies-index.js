import { Modal } from 'bootstrap';
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
    const uploadBtn = document.getElementById('companyPolicyUploadBtn');
    const form = document.getElementById('companyPolicyForm');
    const modalEl = document.getElementById('companyPolicyModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    const modalTitle = document.getElementById('companyPolicyModalTitle');
    const policyIdInput = document.getElementById('companyPolicyId');
    const titleInput = document.getElementById('companyPolicyTitle');
    const categoryInput = document.getElementById('companyPolicyCategory');
    const descriptionInput = document.getElementById('companyPolicyDescription');
    const statusInput = document.getElementById('companyPolicyStatus');
    const fileInput = document.getElementById('companyPolicyFile');
    const fileRequiredMark = document.getElementById('companyPolicyFileRequired');
    const fileHelp = document.getElementById('companyPolicyFileHelp');
    const saveBtn = document.getElementById('companyPolicySaveBtn');

    if (!root || !tableBody) {
        return;
    }

    const canManage = root.dataset.canManage === '1';
    const columnCount = canManage ? 7 : 6;

    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);
    let searchTimeout = null;
    let categories = [];
    let policiesById = new Map();

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

    const fillCategoryOptions = (selectEl, includeAll = false) => {
        if (!selectEl) {
            return;
        }

        const options = categories.map((item) => (
            `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`
        )).join('');

        selectEl.innerHTML = includeAll
            ? `<option value="">All categories</option>${options}`
            : options;
    };

    const downloadPolicy = async (policy) => {
        const response = await api.get(`/company-policies/${policy.id}/download`, { responseType: 'blob' });
        const mime = policy.mime_type || response.data?.type || 'application/octet-stream';
        const blob = new Blob([response.data], { type: mime });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^"]+)"?/i);
        link.href = url;
        link.download = match?.[1] || policy.original_name || `policy-${policy.id}`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    };

    const openCreateModal = () => {
        if (policyIdInput) policyIdInput.value = '';
        if (titleInput) titleInput.value = '';
        if (descriptionInput) descriptionInput.value = '';
        if (statusInput) statusInput.value = 'published';
        if (fileInput) fileInput.value = '';
        fillCategoryOptions(categoryInput, false);
        if (modalTitle) modalTitle.textContent = 'Upload Policy';
        if (fileRequiredMark) fileRequiredMark.classList.remove('d-none');
        if (fileHelp) fileHelp.textContent = 'PDF, Word, Excel, or image up to 10 MB.';
        modal?.show();
    };

    const openEditModal = (policy) => {
        if (policyIdInput) policyIdInput.value = String(policy.id);
        if (titleInput) titleInput.value = policy.title || '';
        if (descriptionInput) descriptionInput.value = policy.description || '';
        if (statusInput) statusInput.value = policy.status || 'published';
        if (fileInput) fileInput.value = '';
        fillCategoryOptions(categoryInput, false);
        if (categoryInput) categoryInput.value = policy.category || '';
        if (modalTitle) modalTitle.textContent = 'Edit Policy';
        if (fileRequiredMark) fileRequiredMark.classList.add('d-none');
        if (fileHelp) {
            fileHelp.textContent = `Current file: ${policy.original_name || '—'}. Upload a new file only if you want to replace it.`;
        }
        modal?.show();
    };

    const renderRows = (policies, pagination) => {
        policiesById = new Map(policies.map((policy) => [String(policy.id), policy]));

        if (!policies.length) {
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="text-center text-muted py-5">No policies found.</td></tr>`;
            return;
        }

        tableBody.innerHTML = policies.map((policy, index) => {
            const serial = getSerialNumber(index, pagination);
            const actions = canManage
                ? `
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-download-policy="${policy.id}">View</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-edit-policy="${policy.id}">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-delete-policy="${policy.id}">Delete</button>
                    </div>
                `
                : `<button type="button" class="btn btn-sm btn-outline-primary" data-download-policy="${policy.id}">View / Download</button>`;

            return `
                <tr>
                    <td>${serial}</td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(policy.title)}</div>
                        ${policy.description ? `<div class="small text-muted">${escapeHtml(policy.description)}</div>` : ''}
                    </td>
                    <td>${escapeHtml(policy.category_label || policy.category)}</td>
                    <td>
                        <div>${escapeHtml(policy.original_name || '—')}</div>
                        <div class="small text-muted">${escapeHtml(policy.file_size_label || '')}</div>
                    </td>
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
        fillCategoryOptions(filterCategory, true);
        fillCategoryOptions(categoryInput, false);

        const maxKb = Number(data.data?.max_file_kb) || 10240;
        const mimes = (data.data?.allowed_mimes || []).join(', ').toUpperCase();
        if (fileHelp) {
            fileHelp.textContent = `${mimes || 'PDF, Word, Excel, or image'} up to ${Math.round(maxKb / 1024)} MB.`;
        }
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

    uploadBtn?.addEventListener('click', openCreateModal);

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!titleInput?.value.trim() || !categoryInput?.value) {
            showAlert('Title and category are required.', 'warning');
            return;
        }

        const isEdit = Boolean(policyIdInput?.value);
        if (!isEdit && !fileInput?.files?.[0]) {
            showAlert('Please select a file to upload.', 'warning');
            return;
        }

        const payload = new FormData();
        payload.append('title', titleInput.value.trim());
        payload.append('category', categoryInput.value);
        payload.append('description', descriptionInput?.value?.trim() || '');
        payload.append('status', statusInput?.value || 'published');
        if (fileInput?.files?.[0]) {
            payload.append('file', fileInput.files[0]);
        }
        if (isEdit) {
            payload.append('_method', 'PUT');
        }

        saveBtn.disabled = true;
        const originalText = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';

        try {
            if (isEdit) {
                await api.post(`/company-policies/${policyIdInput.value}`, payload, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                showAlert('Policy updated successfully.');
            } else {
                await api.post('/company-policies', payload, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                showAlert('Policy uploaded successfully.');
            }

            modal?.hide();
            currentPage = 1;
            await loadPolicies();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = originalText;
        }
    });

    tableBody.addEventListener('click', async (event) => {
        const downloadBtn = event.target.closest('[data-download-policy]');
        const editBtn = event.target.closest('[data-edit-policy]');
        const deleteBtn = event.target.closest('[data-delete-policy]');

        if (downloadBtn) {
            const policy = policiesById.get(String(downloadBtn.dataset.downloadPolicy));
            if (!policy) {
                return;
            }

            try {
                await downloadPolicy(policy);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
            return;
        }

        if (editBtn) {
            const policy = policiesById.get(String(editBtn.dataset.editPolicy));
            if (policy) {
                openEditModal(policy);
            }
            return;
        }

        if (deleteBtn) {
            const id = deleteBtn.dataset.deletePolicy;
            if (!window.confirm('Delete this policy? Employees will no longer be able to view it.')) {
                return;
            }

            try {
                await api.delete(`/company-policies/${id}`);
                showAlert('Policy deleted successfully.');
                await loadPolicies();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
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
