import api, { getErrorMessage } from './api';
import { renderAvatarHtml } from './avatar';

const PEOPLE_AVATAR_COLORS = ['#8b5a3c', '#6b4c35', '#2f6b4f', '#7c5c8a', '#3b6f8f', '#8b4f5c'];

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
                    <div class="org-chart-card-title">${node.name} (${node.employee_code})${node.is_company_admin ? ' <span class="badge text-bg-light border ms-1">Admin</span>' : ''}</div>
                </div>
                <div class="org-chart-card-field">
                    <span class="org-chart-card-label">Designation</span>
                    <span class="org-chart-card-value">${node.designation || '—'}</span>
                </div>
                <div class="org-chart-card-field">
                    <span class="org-chart-card-label">Department</span>
                    <span class="org-chart-card-value">${node.department || '—'}</span>
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

export const renderOrgSubtree = (node) => `
    <div class="org-chart-subtree">
        ${node.type === 'department' ? renderOrgDepartmentCard(node) : renderOrgEmployeeCard(node)}
    </div>
`;

export const renderOrgChart = (payload, rootId = 'orgChartRoot') => {
    const root = document.getElementById(rootId);

    if (!root) {
        return;
    }

    const nodes = payload.nodes || [];

    if (!nodes.length) {
        root.innerHTML = '<div class="text-center text-muted py-5">No company admins with teams to display in the organization chart.</div>';
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
                        ${nodes.map((node) => `<div class="org-chart-branch org-chart-root-branch">${renderOrgSubtree(node)}</div>`).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
};

export const bindOrgChartInteractions = (rootId = 'orgChartRoot') => {
    document.getElementById(rootId)?.addEventListener('click', (event) => {
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
};

export const loadOrgChart = async (rootId = 'orgChartRoot') => {
    const root = document.getElementById(rootId);

    if (!root) {
        return;
    }

    try {
        const { data } = await api.get('/people/org-chart');
        renderOrgChart(data.data || {}, rootId);
    } catch (error) {
        root.innerHTML = `<div class="text-center text-danger py-5">${getErrorMessage(error)}</div>`;
    }
};
