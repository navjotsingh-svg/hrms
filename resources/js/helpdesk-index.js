import api, { getErrorMessage } from './api';
import { renderDateTimeStackFromLabel } from './datetime-utils';
import { composeActionGroup, renderViewLink } from './action-icons';
import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';

const routes = () => window.HRMS_WEB_ROUTES || {};

const statusClass = (status) => ({
    open: 'company-status-pill--inactive',
    in_progress: 'company-status-pill--warning',
    resolved: 'company-status-pill--active',
    closed: 'company-status-pill--cancelled',
}[status] || '');

const priorityClass = (priority) => ({
    low: 'text-muted',
    medium: 'text-primary',
    high: 'text-warning',
    urgent: 'text-danger',
}[priority] || '');

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('helpdeskTableBody');
    const alertBox = document.getElementById('helpdeskAlert');
    const filterStatus = document.getElementById('filterStatus');
    const filterCategory = document.getElementById('filterCategory');
    const filterPriority = document.getElementById('filterPriority');
    const filterSearch = document.getElementById('filterSearch');
    const filterReset = document.getElementById('filterReset');
    const paginationInfo = document.getElementById('helpdeskPaginationInfo');
    const paginationList = document.getElementById('helpdeskPaginationList');
    const perPageSelect = document.getElementById('helpdeskPerPage');
    const openCount = document.getElementById('helpdeskOpenCount');
    const addCategoryBtn = document.getElementById('helpdeskIndexAddCategoryBtn');
    const categoryForm = document.getElementById('helpdeskCategoryForm');
    const categoryNameInput = document.getElementById('helpdeskCategoryName');
    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);
    let meta = null;
    const pageRoot = document.getElementById('helpdeskPageRoot');
    let canManage = pageRoot?.dataset.canManage === '1';
    let categoryModal = null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const fillSelect = (select, items, includeAll = true, selectedValue = null) => {
        if (!select) return;
        const current = selectedValue ?? select.value;
        select.innerHTML = includeAll ? '<option value="">All</option>' : '';
        (items || []).forEach((item) => {
            select.insertAdjacentHTML('beforeend', `<option value="${item.value}">${item.label}</option>`);
        });
        if (current) {
            select.value = String(current);
        }
    };

    const loadMeta = async (selectedCategoryId = null) => {
        const { data } = await api.get('/helpdesk-tickets/meta');
        meta = data.data;
        fillSelect(filterStatus, meta.statuses);
        fillSelect(filterCategory, meta.categories, true, selectedCategoryId);
        fillSelect(filterPriority, meta.priorities);
    };

    if (canManage && addCategoryBtn) {
        categoryModal = window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('helpdeskCategoryModal'));

        addCategoryBtn.addEventListener('click', () => {
            categoryNameInput.value = '';
            categoryModal?.show();
        });

        categoryForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const saveBtn = document.getElementById('helpdeskCategorySaveBtn');
            saveBtn.disabled = true;

            try {
                const { data } = await api.post('/helpdesk-categories', {
                    name: categoryNameInput.value.trim(),
                });
                categoryModal?.hide();
                await loadMeta(data.data.category?.id);
                showAlert('Category created.');
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            } finally {
                saveBtn.disabled = false;
            }
        });
    }

    const loadSummary = async () => {
        if (!openCount) return;
        try {
            const { data } = await api.get('/helpdesk-tickets/summary');
            openCount.textContent = data.data.open_count || 0;
        } catch {
            openCount.textContent = '0';
        }
    };

    const renderRow = (item) => {
        const employeeCell = canManage
            ? `<td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>`
            : '';
        return `<tr>
            <td><span class="fw-semibold">${item.ticket_number}</span></td>
            <td>${item.subject}</td>
            <td>${item.category_label}</td>
            <td><span class="${priorityClass(item.priority)}">${item.priority_label}</span></td>
            <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
            ${employeeCell}
            <td>${renderDateTimeStackFromLabel(item.updated_at_label)}</td>
            <td>${composeActionGroup({
                view: renderViewLink(`${routes().helpdeskShow || '/helpdesk'}/${item.id}`, 'View ticket'),
            })}</td>
        </tr>`;
    };

    const load = async (page = 1) => {
        currentPage = page;
        const params = {
            page,
            per_page: currentPerPage,
            status: filterStatus?.value || undefined,
            helpdesk_category_id: filterCategory?.value || undefined,
            priority: filterPriority?.value || undefined,
            search: filterSearch?.value?.trim() || undefined,
        };

        const { data } = await api.get('/helpdesk-tickets', { params });
        const tickets = data.data.tickets || [];
        const pagination = data.data.pagination;

        if (!tickets.length) {
            const cols = canManage ? 8 : 7;
            tableBody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted py-5">No tickets found.</td></tr>`;
        } else {
            tableBody.innerHTML = tickets.map((item) => renderRow(item)).join('');
        }

        renderListPagination({
            infoEl: paginationInfo,
            listEl: paginationList,
            perPageSelectEl: perPageSelect,
            pagination,
            itemLabel: 'tickets',
            emptyMessage: 'No records',
        });
    };

    bindPagination(paginationList, (page) => load(page).catch((e) => showAlert(getErrorMessage(e), 'danger')));
    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        load(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
    });

    filterStatus?.addEventListener('change', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
    filterCategory?.addEventListener('change', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
    filterPriority?.addEventListener('change', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
    filterSearch?.addEventListener('input', () => {
        window.clearTimeout(window.helpdeskSearchTimer);
        window.helpdeskSearchTimer = window.setTimeout(() => {
            load(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
        }, 300);
    });
    filterReset?.addEventListener('click', () => {
        if (filterStatus) filterStatus.value = '';
        if (filterCategory) filterCategory.value = '';
        if (filterPriority) filterPriority.value = '';
        if (filterSearch) filterSearch.value = '';
        load(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
    });

    try {
        await loadMeta();
        await loadSummary();
        await load(1);
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
});
