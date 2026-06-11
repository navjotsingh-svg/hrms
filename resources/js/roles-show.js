import api, { getErrorMessage } from './api';

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('roleShowRoot');

    if (!root) {
        return;
    }

    const roleId = root.dataset.roleId;
    const title = document.getElementById('roleTitle');
    const subtitle = document.getElementById('roleSubtitle');
    const roleName = document.getElementById('roleName');
    const roleDescription = document.getElementById('roleDescription');
    const roleLevelBadge = document.getElementById('roleLevelBadge');
    const roleStatusBadge = document.getElementById('roleStatusBadge');
    const roleMeta = document.getElementById('roleMeta');
    const permissionsWrap = document.getElementById('rolePermissionsWrap');

    try {
        const { data } = await api.get(`/roles/${roleId}`);
        const role = data.data.role;
        const permissions = role.permissions || [];
        const grouped = permissions.reduce((groups, permission) => {
            const module = permission.module || 'general';

            if (!groups[module]) {
                groups[module] = [];
            }

            groups[module].push(permission);
            return groups;
        }, {});

        if (title) {
            title.textContent = role.name;
        }

        if (subtitle) {
            subtitle.textContent = role.description || 'Company role permissions';
        }

        if (roleName) {
            roleName.textContent = role.name;
        }

        if (roleDescription) {
            roleDescription.textContent = role.description || 'No description provided.';
        }

        if (roleLevelBadge) {
            roleLevelBadge.textContent = `Level ${role.level}`;
        }

        if (roleStatusBadge) {
            roleStatusBadge.textContent = role.status === 'active' ? 'Active' : 'Inactive';
            roleStatusBadge.className = `badge rounded-pill ${role.status === 'active' ? 'text-bg-success' : 'text-bg-secondary'}`;
        }

        if (roleMeta) {
            roleMeta.textContent = role.is_system ? 'System role · read-only' : 'Custom role';
        }

        if (permissionsWrap) {
            const modules = Object.keys(grouped).sort();

            if (modules.length === 0) {
                permissionsWrap.innerHTML = '<p class="text-muted mb-0">No permissions assigned to this role.</p>';
            } else {
                permissionsWrap.innerHTML = modules.map((module) => `
                    <div class="mb-4">
                        <h6 class="text-uppercase small fw-semibold text-muted mb-2">${module.replace(/_/g, ' ')}</h6>
                        <div class="d-flex flex-wrap gap-2">
                            ${grouped[module].map((permission) => `
                                <span class="badge rounded-pill text-bg-light border px-3 py-2" title="${permission.description || ''}">
                                    ${permission.name}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        if (permissionsWrap) {
            permissionsWrap.innerHTML = `<p class="text-danger mb-0">${getErrorMessage(error)}</p>`;
        }
    }
});
