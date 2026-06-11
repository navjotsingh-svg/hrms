import api, { getErrorMessage } from './api';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('rolesTableBody');
    const routes = webRoutes();

    if (!tableBody) {
        return;
    }

    const renderStatus = (status) => {
        const isActive = status === 'active';

        return `<span class="company-status-pill ${isActive ? 'company-status-pill--active' : 'company-status-pill--inactive'}">${isActive ? 'Active' : 'Inactive'}</span>`;
    };

    try {
        const { data } = await api.get('/roles');
        const roles = data.data.roles || [];

        if (roles.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No roles found.</td></tr>';
            return;
        }

        tableBody.innerHTML = roles.map((role, index) => {
            const showUrl = `${routes.roleShow || '/masters/roles'}/${role.id}`;

            return `
                <tr class="companies-data-row">
                    <td class="companies-td-serial"><span class="companies-serial">${index + 1}</span></td>
                    <td>
                        <div class="companies-company-info">
                            <a href="${showUrl}" class="companies-company-name">${role.name}</a>
                            <span class="companies-company-meta">${role.description || role.slug}</span>
                        </div>
                    </td>
                    <td><span class="badge text-bg-light border">${role.level}</span></td>
                    <td>${role.permissions_count ?? role.permissions?.length ?? 0} permissions</td>
                    <td>${renderStatus(role.status)}</td>
                    <td class="companies-td-actions">
                        <a href="${showUrl}" class="table-action-btn table-action-btn--view" title="View" aria-label="View ${role.name}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                        </a>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
    }
});
