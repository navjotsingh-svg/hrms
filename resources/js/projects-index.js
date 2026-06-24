import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { consumePageFlashMessage } from './form-utils';
import {
    bindEmployeeSearchSelect,
    formatEmployeeLabel,
    matchesEmployeeSearch,
} from './employee-autocomplete';

const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('projectsTableBody');
    const alertBox = document.getElementById('projectsAlert');
    const paginationInfo = document.getElementById('projectsPaginationInfo');
    const paginationList = document.getElementById('projectsPaginationList');
    const filterSearch = document.getElementById('filterSearch');
    const filterStatus = document.getElementById('filterStatus');
    const filterReset = document.getElementById('filterReset');
    const openProjectModalBtn = document.getElementById('openProjectModalBtn');
    const projectModalEl = document.getElementById('projectModal');
    const projectForm = document.getElementById('projectForm');
    const projectFormAlert = document.getElementById('projectFormAlert');
    const projectModalLabel = document.getElementById('projectModalLabel');
    const projectName = document.getElementById('projectName');
    const projectStatus = document.getElementById('projectStatus');
    const projectStartDate = document.getElementById('projectStartDate');
    const projectEndDate = document.getElementById('projectEndDate');
    const projectDescription = document.getElementById('projectDescription');
    const projectAssigneeChips = document.getElementById('projectAssigneeChips');
    const projectAssigneeHelp = document.getElementById('projectAssigneeHelp');
    const projectFormSubmit = document.getElementById('projectFormSubmit');

    let currentPage = 1;
    let searchTimeout = null;
    let editingProjectId = null;
    let employeeOptions = [];
    let autoAssignEmployees = [];
    let manualAssigneeIds = new Set();
    let assignerRole = '';
    let assigneeSelectController = null;
    let assigneeLookup = new Map();

    if (!tableBody || !projectModalEl) {
        return;
    }

    const projectModal = Modal.getOrCreateInstance(projectModalEl);

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const showFormAlert = (message) => {
        if (!projectFormAlert) {
            return;
        }

        projectFormAlert.textContent = message;
        projectFormAlert.classList.remove('d-none');
    };

    const hideFormAlert = () => {
        projectFormAlert?.classList.add('d-none');
    };

    const rebuildAssigneeLookup = (extraEmployees = []) => {
        assigneeLookup = new Map();

        [...employeeOptions, ...autoAssignEmployees, ...extraEmployees].forEach((employee) => {
            assigneeLookup.set(Number(employee.id), employee);
        });
    };

    const autoAssignIds = () => new Set(autoAssignEmployees.map((employee) => Number(employee.id)));

    const availableForDropdown = () => employeeOptions.filter((employee) => {
        const id = Number(employee.id);

        return !manualAssigneeIds.has(id) && !autoAssignIds().has(id);
    });

    const updateAssigneeHelp = () => {
        if (!projectAssigneeHelp) {
            return;
        }

        if (assignerRole === 'team_lead') {
            projectAssigneeHelp.textContent = employeeOptions.length
                ? 'You are assigned automatically. Search by employee name to add team members.'
                : 'You are assigned automatically. No subordinates were found — set reporting managers on employee records.';
        } else if (assignerRole === 'department_head') {
            projectAssigneeHelp.textContent = employeeOptions.length
                ? 'Search by employee name to add subordinates to this project.'
                : 'No subordinates were found. Ensure team members have the correct reporting manager set.';
        } else if (assignerRole === 'unknown') {
            projectAssigneeHelp.textContent = 'Your account is not linked to an employee profile, so subordinates cannot be loaded.';
        } else {
            projectAssigneeHelp.textContent = 'Search by employee name to add employees to this project.';
        }
    };

    const renderAssigneeChips = () => {
        if (!projectAssigneeChips) {
            return;
        }

        const manualChips = [...manualAssigneeIds].map((employeeId) => {
            const employee = assigneeLookup.get(Number(employeeId));

            if (!employee) {
                return '';
            }

            return `
                <span class="badge text-bg-light border d-inline-flex align-items-center gap-1 py-2 px-2">
                    ${escapeHtml(formatEmployeeLabel(employee))}
                    <button type="button" class="btn-close btn-close-sm" aria-label="Remove ${escapeHtml(formatEmployeeLabel(employee))}" data-remove-assignee="${employee.id}"></button>
                </span>
            `;
        }).filter(Boolean);

        if (!manualChips.length) {
            projectAssigneeChips.innerHTML = assignerRole === 'team_lead'
                ? '<span class="text-muted small">Add team members above. You will be assigned when the project is saved.</span>'
                : '<span class="text-muted small">No assignees selected yet.</span>';
            return;
        }

        projectAssigneeChips.innerHTML = manualChips.join('');
    };

    const setManualAssignees = (employeeIds = []) => {
        const autoIds = autoAssignIds();
        manualAssigneeIds = new Set(
            employeeIds
                .map(Number)
                .filter((id) => id && !autoIds.has(id)),
        );
        renderAssigneeChips();
        assigneeSelectController?.clearSelection?.();
    };

    const initAssigneeDropdown = () => {
        assigneeSelectController = bindEmployeeSearchSelect({
            inputId: 'projectAssigneeInput',
            hiddenId: 'projectAssigneeHidden',
            fetchSuggestions: async (term) => availableForDropdown()
                .filter((employee) => matchesEmployeeSearch(employee, term))
                .map((employee) => ({
                    id: employee.id,
                    label: formatEmployeeLabel(employee),
                    employee,
                })),
            onSelect: (item) => {
                manualAssigneeIds.add(Number(item.id));
                renderAssigneeChips();
                assigneeSelectController?.clearSelection?.();
            },
        });
    };

    const loadEmployees = async () => {
        try {
            const response = await api.get('/projects/employee-options');
            const payload = response.data?.data || {};
            employeeOptions = payload.employees || [];
            autoAssignEmployees = payload.auto_assign || [];
            assignerRole = payload.assigner_role || '';
            rebuildAssigneeLookup();
            updateAssigneeHelp();
            renderAssigneeChips();
        } catch (error) {
            employeeOptions = [];
            autoAssignEmployees = [];
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const resetProjectForm = () => {
        editingProjectId = null;
        hideFormAlert();
        projectForm?.reset();
        manualAssigneeIds = new Set();
        assigneeSelectController?.clearSelection?.();
        renderAssigneeChips();
        projectModalLabel.textContent = 'Add Project';
        projectFormSubmit.textContent = 'Save Project';
        projectStatus.value = 'active';
    };

    const openCreateModal = async () => {
        resetProjectForm();
        await loadEmployees();
        projectModal.show();
    };

    const openEditModal = async (project) => {
        resetProjectForm();
        editingProjectId = project.id;
        projectModalLabel.textContent = 'Edit Project';
        projectFormSubmit.textContent = 'Update Project';
        projectName.value = project.name || '';
        projectStatus.value = project.status || 'active';
        projectStartDate.value = project.start_date || '';
        projectEndDate.value = project.end_date || '';
        projectDescription.value = project.description || '';
        await loadEmployees();
        rebuildAssigneeLookup(project.employees || []);
        setManualAssignees((project.employees || []).map((employee) => employee.id));
        projectModal.show();
    };

    const formatTimeline = (project) => {
        const start = project.start_date || '—';
        const end = project.end_date || 'Open';

        return `${start} → ${end}`;
    };

    const renderAssigneesSummary = (project) => {
        const employees = project.employees || [];

        if (!employees.length) {
            return '—';
        }

        const labels = employees.slice(0, 3).map((employee) => escapeHtml(employee.full_name || 'Employee'));
        const extra = employees.length > 3 ? ` +${employees.length - 3} more` : '';

        return `${labels.join(', ')}${extra}`;
    };

    const renderStatusPill = (status) => {
        const isActive = status === 'active';

        return `<span class="company-status-pill ${isActive ? 'company-status-pill--active' : 'company-status-pill--inactive'}">${isActive ? 'Active' : 'Closed'}</span>`;
    };

    const renderRow = (project, index, pagination) => {
        const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;

        return `
            <tr class="companies-data-row">
                <td class="companies-td-serial"><span class="companies-serial">${serial}</span></td>
                <td>
                    <div class="companies-company-info">
                        <span class="companies-company-name">${escapeHtml(project.name)}</span>
                        ${project.description ? `<span class="companies-company-meta">${escapeHtml(project.description)}</span>` : ''}
                    </div>
                </td>
                <td>${escapeHtml(formatTimeline(project))}</td>
                <td>${renderAssigneesSummary(project)}</td>
                <td>${renderStatusPill(project.status)}</td>
                <td class="companies-td-actions">
                    <div class="table-action-group">
                        <button type="button" class="table-action-btn table-action-btn--edit" title="Edit" aria-label="Edit ${escapeHtml(project.name)}" data-edit-project="${project.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>
                        </button>
                        <button type="button" class="table-action-btn table-action-btn--delete" title="Delete" aria-label="Delete ${escapeHtml(project.name)}" data-delete-project="${project.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    };

    const renderPagination = (pagination) => {
        if (!paginationList || !paginationInfo) {
            return;
        }

        if (!pagination?.total) {
            paginationInfo.textContent = 'No projects found';
            paginationList.innerHTML = '';
            return;
        }

        paginationInfo.textContent = `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total} projects`;

        paginationList.innerHTML = Array.from({ length: pagination.last_page }, (_, index) => {
            const page = index + 1;

            return `
                <li class="page-item ${page === pagination.current_page ? 'active' : ''}">
                    <button type="button" class="page-link" data-page="${page}">${page}</button>
                </li>
            `;
        }).join('');
    };

    let cachedProjects = [];

    const loadProjects = async (page = 1) => {
        currentPage = page;

        const params = { page, per_page: 10 };

        if (filterSearch?.value.trim()) {
            params.search = filterSearch.value.trim();
        }

        if (filterStatus?.value) {
            params.status = filterStatus.value;
        }

        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">Loading projects...</td></tr>';

        try {
            const response = await api.get('/projects', { params });
            const payload = response.data?.data || {};
            cachedProjects = payload.projects || [];
            const pagination = payload.pagination || {};

            if (!cachedProjects.length) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No projects found.</td></tr>';
            } else {
                tableBody.innerHTML = cachedProjects.map((project, index) => renderRow(project, index, pagination)).join('');
            }

            renderPagination(pagination);
        } catch (error) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-5">Unable to load projects.</td></tr>';
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    projectForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideFormAlert();

        const hasManualAssignees = manualAssigneeIds.size > 0;
        const hasAutoAssignees = autoAssignEmployees.length > 0;

        if (!hasManualAssignees && !hasAutoAssignees) {
            showFormAlert('Select at least one assignee.');
            return;
        }

        if (assignerRole === 'department_head' && !hasManualAssignees) {
            showFormAlert('Select at least one employee to assign.');
            return;
        }

        const payload = {
            name: projectName.value.trim(),
            description: projectDescription.value.trim() || null,
            start_date: projectStartDate.value,
            end_date: projectEndDate.value || null,
            status: projectStatus.value,
            employee_ids: [...manualAssigneeIds],
        };

        projectFormSubmit.disabled = true;

        try {
            if (editingProjectId) {
                await api.put(`/projects/${editingProjectId}`, payload);
                showAlert('Project updated successfully.');
            } else {
                await api.post('/projects', payload);
                showAlert('Project created successfully.');
            }

            projectModal.hide();
            await loadProjects(editingProjectId ? currentPage : 1);
        } catch (error) {
            showFormAlert(getErrorMessage(error));
        } finally {
            projectFormSubmit.disabled = false;
        }
    });

    projectAssigneeChips?.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-assignee]');

        if (!removeButton) {
            return;
        }

        manualAssigneeIds.delete(Number(removeButton.dataset.removeAssignee));
        renderAssigneeChips();
    });

    tableBody.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit-project]');
        const deleteButton = event.target.closest('[data-delete-project]');

        if (editButton) {
            const projectId = Number(editButton.dataset.editProject);
            const project = cachedProjects.find((item) => item.id === projectId);

            if (project) {
                openEditModal(project);
            }

            return;
        }

        if (deleteButton) {
            const projectId = Number(deleteButton.dataset.deleteProject);
            const project = cachedProjects.find((item) => item.id === projectId);

            if (!project || !window.confirm(`Delete project "${project.name}"?`)) {
                return;
            }

            try {
                await api.delete(`/projects/${projectId}`);
                showAlert('Project deleted successfully.');
                await loadProjects(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        }
    });

    paginationList?.addEventListener('click', (event) => {
        const pageButton = event.target.closest('[data-page]');

        if (pageButton) {
            loadProjects(Number(pageButton.dataset.page));
        }
    });

    filterSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadProjects(1), 300);
    });

    filterStatus?.addEventListener('change', () => loadProjects(1));
    filterReset?.addEventListener('click', () => {
        if (filterSearch) {
            filterSearch.value = '';
        }

        if (filterStatus) {
            filterStatus.value = '';
        }

        loadProjects(1);
    });

    openProjectModalBtn?.addEventListener('click', openCreateModal);
    projectModalEl.addEventListener('hidden.bs.modal', resetProjectForm);

    const flash = consumePageFlashMessage();

    if (flash?.message) {
        showAlert(flash.message, flash.type || 'success');
    }

    await loadEmployees();
    initAssigneeDropdown();
    renderAssigneeChips();
    await loadProjects();
});
