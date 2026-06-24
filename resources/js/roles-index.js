import api, { getErrorMessage } from './api';
import { Modal } from 'bootstrap';
import { renderDeleteButton, renderEditLink, renderActionGroup, DELETE_ICON } from './action-icons';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

document.addEventListener('DOMContentLoaded', async () => {
    const alertBox = document.getElementById('rolesAlert');
    const tableBody = document.getElementById('rolesTableBody');
    const createRoleBtn = document.getElementById('createRoleBtn');
    const createRoleModalEl = document.getElementById('createRoleModal');
    const createRoleForm = document.getElementById('createRoleForm');
    const routes = webRoutes();

    if (!tableBody) {
        return;
    }

    const createRoleModal = createRoleModalEl ? Modal.getOrCreateInstance(createRoleModalEl) : null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderStatusCell = (role) => {
        const isActive = role.status === 'active';
        const switchId = `role-status-${role.id}`;
        const disabled = role.is_custom ? '' : 'disabled';

        return `
            <div class="company-status-cell">
                <div class="form-check form-switch company-status-switch company-status-switch--solo mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="${switchId}"
                        data-status-toggle="${role.id}"
                        ${isActive ? 'checked' : ''}
                        ${disabled}
                        aria-label="Toggle status for ${escapeHtml(role.name)}"
                    >
                </div>
            </div>
        `;
    };

    const setStatusToggle = (roleId, status) => {
        const toggle = tableBody.querySelector(`[data-status-toggle="${roleId}"]`);

        if (toggle) {
            toggle.checked = status === 'active';
        }
    };

    const renderTable = (roles) => {
        if (roles.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No roles found.</td></tr>';

            return;
        }

        tableBody.innerHTML = roles.map((role, index) => {
            const showUrl = `${routes.roleShow || '/masters/roles'}/${role.id}`;
            const roleType = role.is_system
                ? '<span class="badge text-bg-light border ms-1">System</span>'
                : '<span class="badge text-bg-info ms-1">Custom</span>';
            const customized = role.uses_company_override
                ? '<span class="badge text-bg-warning ms-1">Customized</span>'
                : '';
            const actions = [
                renderEditLink(showUrl, `Configure ${role.name}`),
            ];

            if (role.is_custom) {
                if (role.is_deletable) {
                    actions.push(renderDeleteButton('data-delete-role', role.id, 'Delete role', role.name));
                } else {
                    actions.push(`<button type="button" class="table-action-btn table-action-btn--delete" title="Remove all users before deleting" aria-label="Delete ${escapeHtml(role.name)}" disabled>${DELETE_ICON}</button>`);
                }
            }

            return `
                <tr class="companies-data-row">
                    <td class="companies-td-serial"><span class="companies-serial">${index + 1}</span></td>
                    <td>
                        <div class="companies-company-info">
                            <a href="${showUrl}" class="companies-company-name">${escapeHtml(role.name)}</a>
                            <span class="companies-company-meta">${escapeHtml(role.description || role.slug)} ${roleType}${customized}</span>
                        </div>
                    </td>
                    <td>${role.effective_permissions_count ?? role.permissions_count ?? 0}</td>
                    <td>${role.users_count ?? 0}</td>
                    <td>${renderStatusCell(role)}</td>
                    <td class="companies-td-actions">
                        ${renderActionGroup(actions.join(''))}
                    </td>
                </tr>
            `;
        }).join('');
    };

    const loadRoles = async () => {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">Loading roles...</td></tr>';

        try {
            const { data } = await api.get('/roles');
            renderTable(data.data.roles || []);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-5">${escapeHtml(getErrorMessage(error))}</td></tr>`;
        }
    };

    createRoleBtn?.addEventListener('click', () => {
        createRoleModal?.show();
    });

    createRoleForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const name = document.getElementById('createRoleName')?.value?.trim();
        const description = document.getElementById('createRoleDescription')?.value?.trim();

        if (!name) {
            return;
        }

        try {
            const { data } = await api.post('/roles', {
                name,
                description: description || undefined,
                permission_slugs: [],
            });

            createRoleModal?.hide();
            createRoleForm.reset();
            showAlert(data.message || 'Role created successfully.');

            const roleId = data.data?.role?.id;
            const showUrl = `${routes.roleShow || '/masters/roles'}/${roleId}`;

            if (roleId) {
                window.location.href = showUrl;
            } else {
                loadRoles();
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    tableBody.addEventListener('change', async (event) => {
        const toggle = event.target.closest('[data-status-toggle]');

        if (!toggle || toggle.disabled) {
            return;
        }

        const roleId = toggle.dataset.statusToggle;
        const status = toggle.checked ? 'active' : 'inactive';
        const previousChecked = !toggle.checked;

        toggle.disabled = true;

        try {
            const { data } = await api.patch(`/roles/${roleId}`, { status });
            setStatusToggle(roleId, data.data.role?.status || status);
            showAlert(data.message || 'Role status updated successfully.');
        } catch (error) {
            toggle.checked = previousChecked;
            setStatusToggle(roleId, previousChecked ? 'active' : 'inactive');
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            toggle.disabled = false;
        }
    });

    tableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-role]');

        if (!button) {
            return;
        }

        const roleId = button.dataset.deleteRole;
        const roleName = button.dataset.deleteName || 'this role';

        if (!window.confirm(`Delete "${roleName}"? This cannot be undone.`)) {
            return;
        }

        button.disabled = true;

        try {
            const { data } = await api.delete(`/roles/${roleId}`);
            showAlert(data.message || 'Role deleted successfully.');
            loadRoles();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
            button.disabled = false;
        }
    });

    await loadRoles();
});
