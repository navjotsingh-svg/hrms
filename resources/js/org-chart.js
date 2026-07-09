import api, { getErrorMessage } from './api';
import { renderAvatarHtml } from './avatar';

const PEOPLE_AVATAR_COLORS = ['#8b5a3c', '#6b4c35', '#2f6b4f', '#7c5c8a', '#3b6f8f', '#8b4f5c'];
const ORG_CHART_MIN_SCALE = 0.35;
const ORG_CHART_MAX_SCALE = 1.25;
const ORG_CHART_ZOOM_STEP = 0.1;

const orgChartToolbarHtml = () => `
    <div class="org-chart-toolbar">
        <span class="org-chart-toolbar-hint">Scroll to explore the full hierarchy</span>
        <div class="org-chart-toolbar-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-org-chart-fit title="Fit entire chart in view">Fit to view</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-org-chart-zoom-out title="Zoom out">−</button>
            <span class="org-chart-scale-label" data-org-chart-scale>100%</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-org-chart-zoom-in title="Zoom in">+</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-org-chart-reset title="Reset zoom">100%</button>
        </div>
    </div>
`;

const getOrgChartElements = (rootId) => {
    const root = document.getElementById(rootId);

    if (!root) {
        return null;
    }

    return {
        root,
        viewport: root.querySelector('.org-chart-viewport'),
        stage: root.querySelector('.org-chart-stage'),
        chart: root.querySelector('.org-chart'),
        scaleLabel: root.querySelector('[data-org-chart-scale]'),
    };
};

const readOrgChartScale = (stage) => parseFloat(stage?.dataset.scale || '1') || 1;

const applyOrgChartScale = (elements, scale) => {
    if (!elements?.stage) {
        return 1;
    }

    const clamped = Math.min(ORG_CHART_MAX_SCALE, Math.max(ORG_CHART_MIN_SCALE, scale));

    elements.stage.style.zoom = clamped;
    elements.stage.dataset.scale = String(clamped);

    if (elements.scaleLabel) {
        elements.scaleLabel.textContent = `${Math.round(clamped * 100)}%`;
    }

    return clamped;
};

export const fitOrgChartToView = (rootId) => {
    const elements = getOrgChartElements(rootId);

    if (!elements?.viewport || !elements.stage || !elements.chart) {
        return;
    }

    applyOrgChartScale(elements, 1);

    const padding = 24;
    const chartWidth = elements.chart.scrollWidth;
    const chartHeight = elements.chart.scrollHeight;
    const viewportWidth = Math.max(elements.viewport.clientWidth - padding, 1);
    const viewportHeight = Math.max(elements.viewport.clientHeight - padding, 1);
    const scale = Math.min(viewportWidth / chartWidth, viewportHeight / chartHeight, 1);

    applyOrgChartScale(elements, scale);

    elements.viewport.scrollLeft = Math.max(0, (elements.chart.scrollWidth * scale - elements.viewport.clientWidth) / 2);
    elements.viewport.scrollTop = 0;
};

export const initOrgChartViewport = (rootId) => {
    const elements = getOrgChartElements(rootId);

    if (!elements) {
        return;
    }

    requestAnimationFrame(() => {
        fitOrgChartToView(rootId);
    });
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
        ${orgChartToolbarHtml()}
        <div class="org-chart-viewport">
            <div class="org-chart-stage" data-scale="1">
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
            </div>
        </div>
    `;
};

export const bindOrgChartInteractions = (rootId = 'orgChartRoot') => {
    document.getElementById(rootId)?.addEventListener('click', (event) => {
        const fitButton = event.target.closest('[data-org-chart-fit]');

        if (fitButton) {
            fitOrgChartToView(rootId);

            return;
        }

        const zoomInButton = event.target.closest('[data-org-chart-zoom-in]');

        if (zoomInButton) {
            const elements = getOrgChartElements(rootId);
            applyOrgChartScale(elements, readOrgChartScale(elements?.stage) + ORG_CHART_ZOOM_STEP);

            return;
        }

        const zoomOutButton = event.target.closest('[data-org-chart-zoom-out]');

        if (zoomOutButton) {
            const elements = getOrgChartElements(rootId);
            applyOrgChartScale(elements, readOrgChartScale(elements?.stage) - ORG_CHART_ZOOM_STEP);

            return;
        }

        const resetButton = event.target.closest('[data-org-chart-reset]');

        if (resetButton) {
            const elements = getOrgChartElements(rootId);
            applyOrgChartScale(elements, 1);
            elements?.viewport && (elements.viewport.scrollLeft = 0);
            elements?.viewport && (elements.viewport.scrollTop = 0);

            return;
        }

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
        initOrgChartViewport(rootId);
    } catch (error) {
        root.innerHTML = `<div class="text-center text-danger py-5">${getErrorMessage(error)}</div>`;
    }
};
