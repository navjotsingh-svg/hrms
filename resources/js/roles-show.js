import api, { getErrorMessage } from './api';
import { aiAdviseRole } from './ai-tools';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('roleShowRoot');

    if (!root) {
        return;
    }

    const roleId = root.dataset.roleId;
    const alertBox = document.getElementById('roleShowAlert');
    const title = document.getElementById('roleTitle');
    const subtitle = document.getElementById('roleSubtitle');
    const roleName = document.getElementById('roleName');
    const roleDescription = document.getElementById('roleDescription');
    const roleMeta = document.getElementById('roleMeta');
    const roleOverrideBadge = document.getElementById('roleOverrideBadge');
    const permissionsWrap = document.getElementById('rolePermissionsWrap');
    const saveBtn = document.getElementById('saveRolePermissionsBtn');
    const resetBtn = document.getElementById('resetRolePermissionsBtn');
    const deleteBtn = document.getElementById('deleteRoleBtn');
    const aiRoleAdviseBtn = document.getElementById('aiRoleAdviseBtn');
    const roleDetailsForm = document.getElementById('roleDetailsForm');
    const roleDetailsReadonly = document.getElementById('roleDetailsReadonly');
    const editRoleName = document.getElementById('editRoleName');
    const editRoleDescription = document.getElementById('editRoleDescription');
    const roleStatusToggle = document.getElementById('roleStatusToggle');
    const saveRoleDetailsBtn = document.getElementById('saveRoleDetailsBtn');
    const routes = webRoutes();

    let catalog = [];
    let selectedSlugs = new Set();
    let isEditable = false;
    let isCustom = false;
    let isDeletable = false;
    let usesCompanyOverride = false;
    let isSaving = false;
    let currentRole = null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const sortOperationTypes = (types) => {
        const order = ['view', 'manage', 'apply', 'approve', 'regularize', 'submit', 'export', 'participate', 'review', 'pip', 'requisition_create', 'requisition_approve', 'interview', 'careers'];

        return [...types].sort((a, b) => {
            const ai = order.indexOf(a);
            const bi = order.indexOf(b);

            if (ai === -1 && bi === -1) {
                return a.localeCompare(b);
            }

            if (ai === -1) {
                return 1;
            }

            if (bi === -1) {
                return -1;
            }

            return ai - bi;
        });
    };

    const columnsForGroup = (group) => {
        const types = new Set();

        (group.modules || []).forEach((module) => {
            (module.operations || []).forEach((operation) => {
                types.add(operation.type || operation.label);
            });
        });

        return sortOperationTypes(types);
    };

    const columnLabel = (type) => ({
        view: 'View',
        manage: 'Add / Edit',
        apply: 'Apply',
        approve: 'Approve',
        regularize: 'Regularize',
        submit: 'Submit',
        export: 'Export',
        participate: 'Participate',
        review: 'Review',
        pip: 'PIPs',
        requisition_create: 'Create Req.',
        requisition_approve: 'Approve Req.',
        interview: 'Interview',
        careers: 'Careers',
    }[type] || type.replace(/_/g, ' '));

    const syncRequiredPermissions = (slug, checked) => {
        catalog.forEach((group) => {
            (group.modules || []).forEach((module) => {
                (module.operations || []).forEach((operation) => {
                    if (operation.slug !== slug) {
                        return;
                    }

                    if (checked && Array.isArray(operation.requires)) {
                        operation.requires.forEach((requiredSlug) => selectedSlugs.add(requiredSlug));
                    }

                    if (!checked && Array.isArray(operation.requires) && operation.requires.includes(slug)) {
                        // no-op: requirements are one-way
                    }
                });
            });
        });
    };

    const renderMatrix = () => {
        if (!permissionsWrap) {
            return;
        }

        if (!catalog.length) {
            permissionsWrap.innerHTML = '<p class="text-muted mb-0">No permission catalog configured.</p>';

            return;
        }

        const disabled = !isEditable ? 'disabled' : '';

        permissionsWrap.innerHTML = catalog.map((group) => {
            const columns = columnsForGroup(group);

            if (!columns.length) {
                return '';
            }

            return `
            <div class="role-permission-group mb-4">
                <h6 class="role-permission-group-title">${escapeHtml(group.group)}</h6>
                <div class="table-responsive">
                    <table class="role-permission-matrix table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Menu / Module</th>
                                ${columns.map((type) => `<th class="text-center">${escapeHtml(columnLabel(type))}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${(group.modules || []).map((module) => `
                                <tr>
                                    <td>
                                        <div class="fw-semibold">${escapeHtml(module.label)}</div>
                                        ${module.description ? `<div class="small text-muted">${escapeHtml(module.description)}</div>` : ''}
                                    </td>
                                    ${columns.map((type) => {
                                        const operation = (module.operations || []).find((item) => item.type === type);

                                        if (!operation) {
                                            return '<td class="text-center"></td>';
                                        }

                                        const checked = selectedSlugs.has(operation.slug) ? 'checked' : '';

                                        return `<td class="text-center">
                                            <input
                                                type="checkbox"
                                                class="form-check-input role-permission-checkbox"
                                                data-slug="${escapeHtml(operation.slug)}"
                                                ${checked}
                                                ${disabled}
                                                aria-label="${escapeHtml(module.label)} ${escapeHtml(operation.label)}"
                                            >
                                        </td>`;
                                    }).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        }).filter(Boolean).join('');

        permissionsWrap.querySelectorAll('.role-permission-checkbox').forEach((input) => {
            input.addEventListener('change', () => {
                const slug = input.dataset.slug;

                if (input.checked) {
                    selectedSlugs.add(slug);
                    syncRequiredPermissions(slug, true);
                } else {
                    selectedSlugs.delete(slug);
                }

                renderMatrix();
            });
        });
    };

    const setStatusToggle = (status, { disabled = false } = {}) => {
        if (roleStatusToggle) {
            roleStatusToggle.checked = status === 'active';
            roleStatusToggle.disabled = disabled;
        }
    };

    const toggleActionButtons = () => {
        if (saveBtn) {
            saveBtn.classList.toggle('d-none', !isEditable);
        }

        if (resetBtn) {
            resetBtn.classList.toggle('d-none', !isEditable || !usesCompanyOverride);
        }

        if (aiRoleAdviseBtn) {
            aiRoleAdviseBtn.classList.toggle('d-none', !isEditable);
        }

        if (deleteBtn) {
            deleteBtn.classList.toggle('d-none', !isDeletable);
            deleteBtn.disabled = !isDeletable;
            deleteBtn.title = isCustom && !isDeletable
                ? 'Remove all users from this role before deleting it'
                : 'Delete this custom role';
        }

        if (roleDetailsForm) {
            roleDetailsForm.classList.toggle('d-none', !isCustom);
        }

        if (roleDetailsReadonly) {
            roleDetailsReadonly.classList.toggle('d-none', isCustom);
        }

        if (saveRoleDetailsBtn) {
            saveRoleDetailsBtn.classList.toggle('d-none', !isCustom);
        }

        setStatusToggle(currentRole?.status || 'active', { disabled: !isCustom });
    };

    const fillRoleDetailsForm = (role) => {
        if (editRoleName) {
            editRoleName.value = role.name || '';
        }

        if (editRoleDescription) {
            editRoleDescription.value = role.description || '';
        }
    };

    const loadRole = async () => {
        try {
            const { data } = await api.get(`/roles/${roleId}`);
            const role = data.data.role;
            currentRole = role;

            catalog = data.data.permission_catalog || [];
            selectedSlugs = new Set(data.data.effective_permission_slugs || []);
            isEditable = Boolean(data.data.is_editable);
            isCustom = Boolean(role.is_custom);
            isDeletable = Boolean(role.is_deletable);
            usesCompanyOverride = Boolean(data.data.uses_company_override);

            if (title) {
                title.textContent = role.name;
            }

            if (subtitle) {
                subtitle.textContent = isEditable
                    ? 'Choose which menus this role can access and what actions they can perform.'
                    : 'This role has full access and cannot be restricted.';
            }

            if (roleName) {
                roleName.textContent = role.name;
            }

            if (roleDescription) {
                roleDescription.textContent = role.description || 'No description provided.';
            }

            if (roleMeta) {
                if (!isEditable) {
                    roleMeta.textContent = 'Company Administrator · full access to all modules';
                } else if (role.is_system) {
                    roleMeta.textContent = 'System role · customize permissions for your company';
                } else {
                    const usersCount = role.users_count ?? 0;
                    roleMeta.textContent = usersCount > 0
                        ? `Custom role · ${usersCount} user(s) assigned · remove users before deleting`
                        : 'Custom role · editable name, status, and permissions';
                }
            }

            if (roleOverrideBadge) {
                roleOverrideBadge.textContent = usesCompanyOverride ? 'Using company customization' : 'Using system defaults';
            }

            fillRoleDetailsForm(role);
            toggleActionButtons();
            renderMatrix();
        } catch (error) {
            if (permissionsWrap) {
                permissionsWrap.innerHTML = `<p class="text-danger mb-0">${escapeHtml(getErrorMessage(error))}</p>`;
            }
        }
    };

    roleDetailsForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!isCustom || isSaving) {
            return;
        }

        const name = editRoleName?.value?.trim();
        const description = editRoleDescription?.value?.trim();

        if (!name) {
            return;
        }

        isSaving = true;

        if (saveRoleDetailsBtn) {
            saveRoleDetailsBtn.disabled = true;
        }

        try {
            const { data } = await api.patch(`/roles/${roleId}`, {
                name,
                description: description || null,
            });

            const role = data.data.role;
            currentRole = role;

            if (title) {
                title.textContent = role.name;
            }

            if (roleName) {
                roleName.textContent = role.name;
            }

            if (roleDescription) {
                roleDescription.textContent = role.description || 'No description provided.';
            }

            showAlert(data.message || 'Role updated successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            isSaving = false;

            if (saveRoleDetailsBtn) {
                saveRoleDetailsBtn.disabled = false;
            }
        }
    });

    deleteBtn?.addEventListener('click', async () => {
        if (!isDeletable || isSaving) {
            return;
        }

        const roleLabel = currentRole?.name || 'this role';

        if (!window.confirm(`Delete "${roleLabel}"? This cannot be undone.`)) {
            return;
        }

        isSaving = true;
        deleteBtn.disabled = true;

        try {
            const { data } = await api.delete(`/roles/${roleId}`);

            showAlert(data.message || 'Role deleted successfully.');
            window.location.href = routes.rolesIndex || '/masters/roles';
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
            deleteBtn.disabled = false;
            isSaving = false;
        }
    });

    roleStatusToggle?.addEventListener('change', async () => {
        if (!isCustom || isSaving || !currentRole) {
            return;
        }

        const status = roleStatusToggle.checked ? 'active' : 'inactive';
        const previousStatus = currentRole.status === 'inactive' ? 'inactive' : 'active';
        const previousChecked = previousStatus === 'active';

        roleStatusToggle.disabled = true;
        setStatusToggle(status);

        try {
            const { data } = await api.patch(`/roles/${roleId}`, { status });
            currentRole = data.data.role;
            showAlert(data.message || 'Role status updated.');
            setStatusToggle(currentRole.status, { disabled: false });
        } catch (error) {
            roleStatusToggle.checked = previousChecked;
            setStatusToggle(previousStatus, { disabled: false });
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            if (isCustom) {
                roleStatusToggle.disabled = false;
            }
        }
    });

    saveBtn?.addEventListener('click', async () => {
        if (!isEditable || isSaving) {
            return;
        }

        isSaving = true;
        saveBtn.disabled = true;

        try {
            const { data } = await api.patch(`/roles/${roleId}/permissions`, {
                permission_slugs: [...selectedSlugs],
            });

            showAlert(data.message || 'Permissions saved.');
            usesCompanyOverride = true;

            if (roleOverrideBadge) {
                roleOverrideBadge.textContent = 'Using company customization';
            }

            toggleActionButtons();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            isSaving = false;
            saveBtn.disabled = false;
        }
    });

    resetBtn?.addEventListener('click', async () => {
        if (!isEditable || isSaving) {
            return;
        }

        if (!window.confirm('Reset this role to system default permissions for your company?')) {
            return;
        }

        isSaving = true;
        resetBtn.disabled = true;

        try {
            const { data } = await api.delete(`/roles/${roleId}/permissions`);

            showAlert(data.message || 'Permissions reset.');
            selectedSlugs = new Set(data.data.role?.effective_permission_slugs || []);
            usesCompanyOverride = false;

            if (roleOverrideBadge) {
                roleOverrideBadge.textContent = 'Using system defaults';
            }

            toggleActionButtons();
            renderMatrix();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            isSaving = false;
            resetBtn.disabled = false;
        }
    });

    aiRoleAdviseBtn?.addEventListener('click', async () => {
        const roleName = currentRole?.name || editRoleName?.value?.trim() || 'Custom role';
        const description = currentRole?.description || editRoleDescription?.value?.trim() || '';

        aiRoleAdviseBtn.disabled = true;
        aiRoleAdviseBtn.textContent = 'AI working…';

        try {
            const result = await aiAdviseRole({ role_name: roleName, description });
            const slugs = result.recommended_permission_slugs || [];

            slugs.forEach((slug) => {
                selectedSlugs.add(slug);
                syncRequiredPermissions(slug, true);
            });

            renderMatrix();
            showAlert(result.summary || 'AI permission suggestions applied. Review and save.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            aiRoleAdviseBtn.disabled = false;
            aiRoleAdviseBtn.textContent = 'AI suggest permissions';
        }
    });

    await loadRole();
});
