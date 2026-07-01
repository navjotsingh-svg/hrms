import api, { getErrorMessage } from './api';
import { renderAvatarHtml } from './avatar';

const PEOPLE_AVATAR_COLORS = ['#8b5a3c', '#6b4c35', '#2f6b4f', '#7c5c8a', '#3b6f8f', '#8b4f5c'];

let currentPage = 1;
let searchTimeout = null;
let orgChartLoaded = false;

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

const showAlert = (message, type = 'danger') => {
    const alertBox = document.getElementById('peopleAlert');

    if (!alertBox) {
        return;
    }

    alertBox.className = `alert alert-${type} alert-dismissible fade show`;
    alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    alertBox.classList.remove('d-none');
};

const renderSummaryRow = (employee) => `
    <tr>
        <td>
            <a href="${employee.profile_url}" class="people-summary-name text-decoration-none">
                ${renderAvatarHtml({
                    name: employee.name,
                    photoUrl: employee.profile_photo_url,
                    initials: employee.initials,
                    className: 'people-summary-avatar',
                    palette: PEOPLE_AVATAR_COLORS,
                })}
                <span>${employee.name}</span>
            </a>
        </td>
        <td>${employee.employee_code}</td>
        <td>${employee.department || '—'}</td>
    </tr>
`;

const renderPagination = (pagination) => {
    const info = document.getElementById('peoplePaginationInfo');
    const list = document.getElementById('peoplePaginationList');

    if (!info || !list) {
        return;
    }

    if (!pagination?.total) {
        info.textContent = 'No employees found.';
        list.innerHTML = '';
        return;
    }

    info.textContent = `Showing ${pagination.from}-${pagination.to} of ${pagination.total}`;

    const pages = [];
    const { current_page: current, last_page: last } = pagination;

    pages.push(`<li class="page-item ${current === 1 ? 'disabled' : ''}"><button class="page-link" data-page="${current - 1}" ${current === 1 ? 'disabled' : ''}>&laquo;</button></li>`);

    for (let page = 1; page <= last; page += 1) {
        if (page === 1 || page === last || Math.abs(page - current) <= 1) {
            pages.push(`<li class="page-item ${page === current ? 'active' : ''}"><button class="page-link" data-page="${page}">${page}</button></li>`);
        } else if (page === current - 2 || page === current + 2) {
            pages.push('<li class="page-item disabled"><span class="page-link">...</span></li>');
        }
    }

    pages.push(`<li class="page-item ${current === last ? 'disabled' : ''}"><button class="page-link" data-page="${current + 1}" ${current === last ? 'disabled' : ''}>&raquo;</button></li>`);
    list.innerHTML = pages.join('');
};

const loadSummary = async (page = 1) => {
    const tableBody = document.getElementById('peopleSummaryBody');
    const search = document.getElementById('peopleSearch')?.value?.trim() || '';

    if (!tableBody) {
        return;
    }

    try {
        const { data } = await api.get('/people/summary', {
            params: {
                page,
                search: search || undefined,
                per_page: 15,
            },
        });

        const employees = data.data.employees || [];
        const pagination = data.data.pagination;
        currentPage = pagination?.current_page || 1;

        if (!employees.length) {
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-5">No employees found.</td></tr>';
        } else {
            tableBody.innerHTML = employees.map(renderSummaryRow).join('');
        }

        renderPagination(pagination);
    } catch (error) {
        tableBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
    }
};

const orgToggleId = (node) => (node.type === 'department' ? `dept-${node.id ?? 'unassigned'}` : String(node.id));

const renderOrgDepartmentCard = (node) => {
    const hasChildren = (node.children || []).length > 0;
    const toggleId = orgToggleId(node);

    return `
        <div class="org-chart-node-wrap" data-org-dept="${toggleId}">
            <div class="org-chart-card org-chart-dept-card">
                <div class="org-chart-dept-title">${node.name}</div>
                ${hasChildren ? `
                <button type="button" class="org-chart-more-btn" data-org-toggle="${toggleId}" data-org-count="${node.direct_reports_count}" data-org-expanded="true">
                    Less (${node.direct_reports_count}) ^
                </button>
                ` : ''}
            </div>
            ${hasChildren ? `
            <div class="org-chart-children" data-org-children="${toggleId}">
                <div class="org-chart-children-row org-chart-roots-row">
                    ${node.children.map((child) => `<div class="org-chart-branch org-chart-root-branch">${renderOrgSubtree(child)}</div>`).join('')}
                </div>
            </div>
            ` : ''}
        </div>
    `;
};

const renderOrgEmployeeCard = (node) => {
    const hasChildren = (node.children || []).length > 0;
    const toggleId = orgToggleId(node);

    return `
        <div class="org-chart-node-wrap" data-org-node="${node.id}">
            <div class="org-chart-card">
                <div class="org-chart-card-head">
                    ${renderAvatarHtml({
                        name: node.name,
                        photoUrl: node.profile_photo_url,
                        initials: node.initials,
                        className: 'org-chart-avatar',
                        palette: PEOPLE_AVATAR_COLORS,
                    })}
                    <div class="org-chart-card-title">${node.name} (${node.employee_code})</div>
                </div>
                <div class="org-chart-card-field">
                    <span class="org-chart-card-label">Designation</span>
                    <span class="org-chart-card-value">${node.designation || '—'}</span>
                </div>
                ${hasChildren ? `
                <button type="button" class="org-chart-more-btn" data-org-toggle="${toggleId}" data-org-count="${node.direct_reports_count}" data-org-expanded="true">
                    Less (${node.direct_reports_count}) ^
                </button>
                ` : ''}
            </div>
            ${hasChildren ? `
            <div class="org-chart-children" data-org-children="${toggleId}">
                <div class="org-chart-children-row">
                    ${node.children.map((child) => `<div class="org-chart-branch">${renderOrgSubtree(child)}</div>`).join('')}
                </div>
            </div>
            ` : ''}
        </div>
    `;
};

const renderOrgSubtree = (node) => `
    <div class="org-chart-subtree">
        ${node.type === 'department' ? renderOrgDepartmentCard(node) : renderOrgEmployeeCard(node)}
    </div>
`;

const renderOrgChart = (payload) => {
    const root = document.getElementById('peopleOrgChartRoot');

    if (!root) {
        return;
    }

    const nodes = payload.nodes || [];

    if (!nodes.length) {
        root.innerHTML = '<div class="text-center text-muted py-5">No employees found to display in the organization chart.</div>';
        return;
    }

    root.innerHTML = `
        <div class="org-chart">
            <div class="org-chart-level">
                <div class="org-chart-company-block">
                    <div class="org-chart-company-card">${payload.company?.name || 'Company'}</div>
                    <button type="button" class="org-chart-company-toggle" id="orgChartCompanyToggle" aria-expanded="true">Less ^</button>
                </div>
                <div class="org-chart-roots" id="orgChartRoots">
                    <div class="org-chart-children-row org-chart-roots-row">
                        ${nodes.map((node) => `<div class="org-chart-branch org-chart-dept-branch">${renderOrgDepartmentCard(node)}</div>`).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
};

const loadOrgChart = async () => {
    const root = document.getElementById('peopleOrgChartRoot');

    if (!root || orgChartLoaded) {
        return;
    }

    try {
        const { data } = await api.get('/people/org-chart');
        renderOrgChart(data.data || {});
        orgChartLoaded = true;
    } catch (error) {
        root.innerHTML = `<div class="text-center text-danger py-5">${getErrorMessage(error)}</div>`;
    }
};

const setSidebarPeopleActive = (view = 'summary') => {
    document.getElementById('sidebarPeopleSummaryLink')?.classList.toggle('active', view === 'summary');
    document.getElementById('sidebarPeopleOrgChartLink')?.classList.toggle('active', view === 'org-chart');
};

const activateTabFromHash = () => {
    if (window.location.hash === '#org-chart') {
        document.getElementById('people-org-chart-tab')?.click();
        setSidebarPeopleActive('org-chart');
    } else {
        setSidebarPeopleActive('summary');
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    const searchInput = document.getElementById('peopleSearch');
    const paginationList = document.getElementById('peoplePaginationList');
    const orgChartTab = document.getElementById('people-org-chart-tab');

    if (!document.getElementById('peopleSummaryBody')) {
        return;
    }

    await loadSummary();
    activateTabFromHash();

    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadSummary(1), 350);
    });

    paginationList?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');

        if (!button || button.disabled) {
            return;
        }

        loadSummary(Number(button.dataset.page));
    });

    orgChartTab?.addEventListener('shown.bs.tab', () => {
        window.location.hash = 'org-chart';
        setSidebarPeopleActive('org-chart');
        loadOrgChart();
    });

    document.getElementById('people-summary-tab')?.addEventListener('shown.bs.tab', () => {
        if (window.location.hash === '#org-chart') {
            history.replaceState(null, '', window.location.pathname);
        }

        setSidebarPeopleActive('summary');
    });

    if (document.getElementById('peopleOrgChartPane')?.classList.contains('active')) {
        await loadOrgChart();
    }

    document.getElementById('peopleOrgChartRoot')?.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-org-toggle]');

        if (toggle) {
            const nodeId = toggle.dataset.orgToggle;
            const count = toggle.dataset.orgCount || '0';
            const children = document.querySelector(`[data-org-children="${nodeId}"]`);
            const expanded = children?.classList.toggle('is-collapsed') === false;

            toggle.textContent = expanded ? `Less (${count}) ^` : `More (${count})`;
            toggle.dataset.orgExpanded = expanded ? 'true' : 'false';

            return;
        }

        const companyToggle = event.target.closest('#orgChartCompanyToggle');

        if (companyToggle) {
            const roots = document.getElementById('orgChartRoots');
            const collapsed = roots?.classList.toggle('is-collapsed');
            companyToggle.textContent = collapsed ? 'More v' : 'Less ^';
            companyToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    });
});
