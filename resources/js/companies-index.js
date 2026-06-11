import api, { getErrorMessage } from './api';
import { initFilterAutocomplete } from './filter-autocomplete';
import { consumePageFlashMessage } from './form-utils';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};
const DEFAULT_PER_PAGE = 10;
const PER_PAGE_OPTIONS = [10, 25, 50];

const cellIcons = {
    email: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/></svg>`,
    phone: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58z"/></svg>`,
    location: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg>`,
};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const actionIcons = {
    view: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>`,
    edit: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>`,
    delete: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>`,
};

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('companiesTableBody');
    const alertBox = document.getElementById('companiesAlert');
    const paginationWrap = document.getElementById('companiesPagination');
    const paginationInfo = document.getElementById('companiesPaginationInfo');
    const paginationList = document.getElementById('companiesPaginationList');
    const perPageSelect = document.getElementById('companiesPerPage');
    const filterName = document.getElementById('filterName');
    const filterCity = document.getElementById('filterCity');
    const filterNameSuggestions = document.getElementById('filterNameSuggestions');
    const filterCitySuggestions = document.getElementById('filterCitySuggestions');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const tableWrap = document.getElementById('companiesTableWrap');
    const filterLoading = document.getElementById('companiesFilterLoading');
    const statTotal = document.getElementById('companiesStatTotal');
    const statActive = document.getElementById('companiesStatActive');
    const statInactive = document.getElementById('companiesStatInactive');
    const routes = webRoutes();

    let currentPage = 1;
    let currentPerPage = DEFAULT_PER_PAGE;
    let isLoading = false;
    let hasLoadedOnce = false;
    let loadRequestId = 0;
    let appliedFilters = {
        name: '',
        city: '',
    };

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

    const getFilters = () => ({
        name: appliedFilters.name,
        city: appliedFilters.city,
        status: filterStatus?.value || '',
    });

    const setLoading = (loading, initial = false) => {
        isLoading = loading;

        if (!loading) {
            tableWrap?.classList.remove('companies-table-wrap--refreshing');
            filterLoading?.classList.add('d-none');
            return;
        }

        if (initial) {
            tableWrap?.classList.remove('companies-table-wrap--refreshing');
            filterLoading?.classList.add('d-none');
            tableBody.innerHTML = Array.from({ length: 5 }, () => `
                <tr class="companies-row-skeleton">
                    <td><span class="companies-skeleton companies-skeleton-serial"></span></td>
                    <td>
                        <div class="companies-skeleton-lines">
                            <span class="companies-skeleton companies-skeleton-line"></span>
                            <span class="companies-skeleton companies-skeleton-line companies-skeleton-line--short"></span>
                        </div>
                    </td>
                    <td>
                        <div class="companies-skeleton-lines">
                            <span class="companies-skeleton companies-skeleton-line"></span>
                            <span class="companies-skeleton companies-skeleton-line companies-skeleton-line--short"></span>
                        </div>
                    </td>
                    <td><span class="companies-skeleton companies-skeleton-line"></span></td>
                    <td><span class="companies-skeleton companies-skeleton-pill"></span></td>
                    <td><span class="companies-skeleton companies-skeleton-actions"></span></td>
                </tr>
            `).join('');
            return;
        }

        tableWrap?.classList.add('companies-table-wrap--refreshing');
        filterLoading?.classList.remove('d-none');
    };

    const renderSummary = (summary) => {
        if (!summary) {
            return;
        }

        if (statTotal) {
            statTotal.textContent = summary.total ?? 0;
        }

        if (statActive) {
            statActive.textContent = summary.active ?? 0;
        }

        if (statInactive) {
            statInactive.textContent = summary.inactive ?? 0;
        }
    };

    const renderCompanyCell = (company) => {
        const showUrl = `${routes.companyShow || '/companies'}/${company.id}`;

        return `
            <div class="companies-company-info">
                <a href="${showUrl}" class="companies-company-name">${escapeHtml(company.name)}</a>
                ${company.industry ? `<span class="companies-company-meta">${escapeHtml(company.industry)}</span>` : ''}
            </div>
        `;
    };

    const renderContactCell = (company) => `
        <div class="companies-contact-cell">
            <div class="companies-cell-meta">
                <span class="companies-cell-icon">${cellIcons.email}</span>
                <span class="companies-cell-text">${escapeHtml(company.email)}</span>
            </div>
            <div class="companies-cell-meta ${company.phone ? '' : 'companies-cell-meta--muted'}">
                <span class="companies-cell-icon">${cellIcons.phone}</span>
                <span class="companies-cell-text">${escapeHtml(company.phone || 'No phone listed')}</span>
            </div>
        </div>
    `;

    const renderLocationCell = (company) => {
        const location = [company.city, company.state].filter(Boolean).join(', ') || 'Not specified';

        return `
            <div class="companies-cell-meta ${company.city ? '' : 'companies-cell-meta--muted'}">
                <span class="companies-cell-icon">${cellIcons.location}</span>
                <span class="companies-cell-text">${escapeHtml(location)}</span>
            </div>
        `;
    };

    const renderStatusToggle = (company) => {
        const isActive = company.status === 'active';
        const switchId = `company-status-${company.id}`;
        const pillClass = isActive ? 'company-status-pill--active' : 'company-status-pill--inactive';

        return `
            <div class="company-status-cell">
                <div class="form-check form-switch company-status-switch mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="${switchId}"
                        data-status-toggle="${company.id}"
                        ${isActive ? 'checked' : ''}
                    >
                    <label
                        class="form-check-label company-status-pill ${pillClass}"
                        for="${switchId}"
                        data-status-label="${company.id}"
                    >
                        ${isActive ? 'Active' : 'Inactive'}
                    </label>
                </div>
            </div>
        `;
    };

    const renderActions = (company) => {
        const showUrl = `${routes.companyShow || '/companies'}/${company.id}`;
        const editUrl = `${routes.companyEdit || '/companies'}/${company.id}/edit`;

        return `
            <div class="table-action-group">
                <a href="${showUrl}" class="table-action-btn table-action-btn--view" title="View" aria-label="View ${escapeHtml(company.name)}">
                    ${actionIcons.view}
                </a>
                <a href="${editUrl}" class="table-action-btn table-action-btn--edit" title="Edit" aria-label="Edit ${escapeHtml(company.name)}">
                    ${actionIcons.edit}
                </a>
                <button type="button" class="table-action-btn table-action-btn--delete" title="Delete" aria-label="Delete ${escapeHtml(company.name)}" data-delete-company="${company.id}">
                    ${actionIcons.delete}
                </button>
            </div>
        `;
    };

    const getSerialNumber = (index, pagination) => {
        const currentPageNumber = pagination?.current_page || 1;
        const perPage = pagination?.per_page || 10;

        return ((currentPageNumber - 1) * perPage) + index + 1;
    };

    const renderRow = (company, index, pagination) => `
        <tr class="companies-data-row">
            <td class="companies-td-serial">
                <span class="companies-serial">${getSerialNumber(index, pagination)}</span>
            </td>
            <td>${renderCompanyCell(company)}</td>
            <td>${renderContactCell(company)}</td>
            <td>${renderLocationCell(company)}</td>
            <td>${renderStatusToggle(company)}</td>
            <td class="companies-td-actions">${renderActions(company)}</td>
        </tr>
    `;

    const renderEmptyState = (hasFilters) => {
        const createUrl = routes.companiesCreate || '/companies/create';

        if (hasFilters) {
            return `
                <tr>
                    <td colspan="6">
                        <div class="companies-empty-state">
                            <div class="companies-empty-icon companies-empty-icon--search" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>
                            </div>
                            <h3 class="companies-empty-title">No matching companies</h3>
                            <p class="companies-empty-text">Try adjusting your filters or reset to see all companies.</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        return `
            <tr>
                <td colspan="6">
                    <div class="companies-empty-state">
                        <div class="companies-empty-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16"><path d="M4 16s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-5.95a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/><path d="M2 1a2 2 0 0 0-2 2v9.5A1.5 1.5 0 0 0 1.5 14h.653a5.4 5.4 0 0 1 1.066-2H1V3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v9h-2.773c.64-1.077 1.06-2.354 1.06-3.773 0-1.208-.347-2.334-.936-3.285A2.6 2.6 0 0 0 11.5 6.5c0 .524.08 1.029.228 1.5H9.5a.5.5 0 0 1-.5-.5V6a.5.5 0 0 1 .5-.5h1.064A4.5 4.5 0 0 0 8 3.5 4.5 4.5 0 0 0 3.436 6H4.5a.5.5 0 0 1 .5.5V9a.5.5 0 0 1-.5.5H2.436A4.5 4.5 0 0 0 4 11.5c0 .653-.14 1.274-.393 1.836h8.786A1.5 1.5 0 0 0 14 11.5V3a2 2 0 0 0-2-2z"/></svg>
                        </div>
                        <h3 class="companies-empty-title">Build your company directory</h3>
                        <p class="companies-empty-text">Add your first company to start managing HR operations across the platform.</p>
                        <a href="${createUrl}" class="btn btn-primary companies-empty-action">+ Add Company</a>
                    </div>
                </td>
            </tr>
        `;
    };

    const renderPaginationInfo = (pagination) => {
        if (!pagination?.total) {
            return 'No companies found';
        }

        return `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total} companies`;
    };

    const renderPageButton = (page, currentPage) => `
        <li class="page-item ${page === currentPage ? 'active' : ''}">
            <button
                type="button"
                class="page-link"
                data-page="${page}"
                ${page === currentPage ? 'aria-current="page"' : ''}
            >
                ${page}
            </button>
        </li>
    `;

    const renderEllipsis = () => `
        <li class="page-item disabled">
            <span class="page-link">...</span>
        </li>
    `;

    const buildNumberedPages = (pagination) => {
        const totalPages = pagination.last_page;
        const currentPage = pagination.current_page;

        if (totalPages <= 1) {
            return renderPageButton(1, currentPage);
        }

        if (totalPages <= 10) {
            return Array.from({ length: totalPages }, (_, index) => renderPageButton(index + 1, currentPage)).join('');
        }

        const items = [renderPageButton(1, currentPage)];
        const start = Math.max(2, currentPage - 1);
        const end = Math.min(totalPages - 1, currentPage + 1);

        if (start > 2) {
            items.push(renderEllipsis());
        }

        for (let page = start; page <= end; page += 1) {
            items.push(renderPageButton(page, currentPage));
        }

        if (end < totalPages - 1) {
            items.push(renderEllipsis());
        }

        items.push(renderPageButton(totalPages, currentPage));

        return items.join('');
    };

    const renderPagination = (pagination) => {
        if (!paginationList || !paginationInfo) {
            return;
        }

        if (!pagination || !pagination.total) {
            paginationInfo.textContent = 'No companies found';
            paginationList.innerHTML = '';
            return;
        }

        paginationInfo.textContent = renderPaginationInfo(pagination);

        if (perPageSelect) {
            perPageSelect.value = String(pagination.per_page || currentPerPage);
        }

        const numberedPages = buildNumberedPages(pagination);

        paginationList.innerHTML = `
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <button
                    type="button"
                    class="page-link"
                    data-page="${pagination.current_page - 1}"
                    ${pagination.current_page === 1 ? 'disabled' : ''}
                >
                    Previous
                </button>
            </li>
            ${numberedPages}
            <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
                <button
                    type="button"
                    class="page-link"
                    data-page="${pagination.current_page + 1}"
                    ${pagination.current_page === pagination.last_page ? 'disabled' : ''}
                >
                    Next
                </button>
            </li>
        `;

        currentPerPage = pagination.per_page;
    };

    const loadCompanies = async (page = 1) => {
        const requestId = ++loadRequestId;

        currentPage = page;
        setLoading(true, !hasLoadedOnce);

        const filters = getFilters();
        const params = {
            page,
            per_page: currentPerPage,
        };

        if (filters.name) {
            params.name = filters.name;
        }

        if (filters.city) {
            params.city = filters.city;
        }

        if (filters.status) {
            params.status = filters.status;
        }

        try {
            const { data } = await api.get('/companies', { params });

            if (requestId !== loadRequestId) {
                return;
            }

            const companies = data.data.companies || [];
            const pagination = data.data.pagination;
            const summary = data.data.summary;
            const hasFilters = Boolean(filters.name || filters.city || filters.status);

            if (companies.length === 0) {
                tableBody.innerHTML = renderEmptyState(hasFilters);
            } else {
                tableBody.innerHTML = companies.map((company, index) => renderRow(company, index, pagination)).join('');
            }

            renderSummary(summary);
            renderPagination(pagination);
            hasLoadedOnce = true;
        } catch (error) {
            if (requestId !== loadRequestId) {
                return;
            }

            tableBody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="companies-empty-state companies-empty-state--error">
                            <div class="companies-empty-icon companies-empty-icon--error" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
                            </div>
                            <h3 class="companies-empty-title">Unable to load companies</h3>
                            <p class="companies-empty-text">${escapeHtml(getErrorMessage(error))}</p>
                        </div>
                    </td>
                </tr>
            `;

            if (paginationList) {
                paginationList.innerHTML = '';
            }

            if (paginationInfo) {
                paginationInfo.textContent = 'Unable to load companies';
            }
        } finally {
            if (requestId === loadRequestId) {
                setLoading(false);
            }
        }
    };

    const setStatusLabel = (companyId, status) => {
        const label = tableBody.querySelector(`[data-status-label="${companyId}"]`);

        if (label) {
            label.textContent = status === 'active' ? 'Active' : 'Inactive';
            label.classList.remove('company-status-pill--active', 'company-status-pill--inactive');
            label.classList.add(status === 'active' ? 'company-status-pill--active' : 'company-status-pill--inactive');
        }
    };

    initFilterAutocomplete({
        input: filterName,
        menu: filterNameSuggestions,
        field: 'name',
        onSelect: (value) => {
            appliedFilters.name = value;
            loadCompanies(1);
        },
    });

    initFilterAutocomplete({
        input: filterCity,
        menu: filterCitySuggestions,
        field: 'city',
        onSelect: (value) => {
            appliedFilters.city = value;
            loadCompanies(1);
        },
    });

    filterStatus?.addEventListener('change', () => loadCompanies(1));

    filterReset?.addEventListener('click', () => {
        if (filterName) {
            filterName.value = '';
        }

        if (filterCity) {
            filterCity.value = '';
        }

        if (filterStatus) {
            filterStatus.value = '';
        }

        appliedFilters = { name: '', city: '' };
        loadCompanies(1);
    });

    perPageSelect?.addEventListener('change', () => {
        currentPerPage = Number(perPageSelect.value) || DEFAULT_PER_PAGE;
        loadCompanies(1);
    });

    paginationWrap?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');

        if (!button || button.disabled) {
            return;
        }

        const page = Number(button.dataset.page);

        if (!page || page < 1 || page === currentPage) {
            return;
        }

        loadCompanies(page);
    });

    tableBody.addEventListener('change', async (event) => {
        const toggle = event.target.closest('[data-status-toggle]');

        if (!toggle) {
            return;
        }

        const companyId = toggle.dataset.statusToggle;
        const status = toggle.checked ? 'active' : 'inactive';
        const previousChecked = !toggle.checked;

        toggle.disabled = true;

        try {
            const { data } = await api.patch(`/companies/${companyId}/status`, { status });
            showAlert(data.message || 'Company status updated successfully.');
            await loadCompanies(currentPage);
        } catch (error) {
            toggle.checked = previousChecked;
            setStatusLabel(companyId, previousChecked ? 'active' : 'inactive');
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            toggle.disabled = false;
        }
    });

    tableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-company]');

        if (!button) {
            return;
        }

        const companyId = button.dataset.deleteCompany;

        if (!window.confirm('Delete this company?')) {
            return;
        }

        try {
            const { data } = await api.delete(`/companies/${companyId}`);
            showAlert(data.message || 'Company deleted successfully.');
            await loadCompanies(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    const flash = consumePageFlashMessage();

    if (flash?.message) {
        showAlert(flash.message, flash.type || 'success');
    }

    await loadCompanies();
});
