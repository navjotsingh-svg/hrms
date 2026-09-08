import api, { getErrorMessage } from './api';
import { bindEmployeeSearchSelect } from './employee-autocomplete';
import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

document.addEventListener('DOMContentLoaded', async () => {
    const alertBox = document.getElementById('reportsAlert');
    const reportTypeSelect = document.getElementById('reportTypeSelect');
    const reportDescription = document.getElementById('reportDescription');
    const tableHead = document.getElementById('reportsTableHead');
    const tableBody = document.getElementById('reportsTableBody');
    const paginationInfo = document.getElementById('reportsPaginationInfo');
    const paginationList = document.getElementById('reportsPaginationList');
    const perPageSelect = document.getElementById('reportsPerPage');
    const generatedAtEl = document.getElementById('reportGeneratedAt');
    const previewTitle = document.getElementById('reportPreviewTitle');
    const loadBtn = document.getElementById('loadReportBtn');
    const exportBtn = document.getElementById('exportReportBtn');

    let catalog = [];
    let currentType = '';
    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect, 25);
    let optionsLoaded = false;

    const today = new Date();
    const monthStart = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;
    const todayStr = today.toISOString().slice(0, 10);

    document.getElementById('filterFromDate').value = monthStart;
    document.getElementById('filterToDate').value = todayStr;

    bindEmployeeSearchSelect({
        input: document.getElementById('filterEmployeeSearch'),
        hiddenInput: document.getElementById('filterEmployeeId'),
    });

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const statusOptionsFor = (type) => {
        const map = {
            'leave-requests': ['pending', 'approved', 'rejected', 'cancelled'],
            expenses: ['draft', 'pending', 'approved', 'rejected', 'cancelled'],
            regularization: ['pending', 'approved', 'rejected', 'cancelled'],
            'performance-reviews': ['not_started', 'in_progress', 'submitted'],
            goals: ['draft', 'active', 'completed', 'cancelled'],
            pips: ['draft', 'active', 'completed', 'failed', 'cancelled'],
            kpis: ['active', 'completed', 'cancelled'],
            employees: ['active', 'inactive'],
            attendance: [],
        };

        return map[type] || [];
    };

    const updateFilterVisibility = () => {
        const report = catalog.find((item) => item.key === currentType);
        const filters = report?.filters || [];

        document.querySelectorAll('.filter-field').forEach((el) => {
            const key = el.dataset.filter;
            el.classList.toggle('d-none', !filters.includes(key));
        });

        const statusSelect = document.getElementById('filterStatus');
        if (statusSelect) {
            const statuses = statusOptionsFor(currentType);
            statusSelect.innerHTML = '<option value="">All</option>' + statuses.map((s) => (
                `<option value="${s}">${escapeHtml(s.replace(/_/g, ' '))}</option>`
            )).join('');
        }

        reportDescription.textContent = report?.description || '';
    };

    const collectFilters = (page = 1) => {
        const params = { page, per_page: currentPerPage };

        const fromDate = document.getElementById('filterFromDate')?.value;
        const toDate = document.getElementById('filterToDate')?.value;
        const status = document.getElementById('filterStatus')?.value;
        const departmentId = document.getElementById('filterDepartmentId')?.value;
        const employeeId = document.getElementById('filterEmployeeId')?.value;
        const employmentType = document.getElementById('filterEmploymentType')?.value;
        const leaveTypeId = document.getElementById('filterLeaveTypeId')?.value;
        const payrollPeriodId = document.getElementById('filterPayrollPeriodId')?.value;
        const cycleId = document.getElementById('filterCycleId')?.value;
        const projectId = document.getElementById('filterProjectId')?.value;

        if (fromDate) params.from_date = fromDate;
        if (toDate) params.to_date = toDate;
        if (status) params.status = status;
        if (departmentId) params.department_id = departmentId;
        if (employeeId) params.employee_id = employeeId;
        if (employmentType) params.employment_type = employmentType;
        if (leaveTypeId) params.leave_type_id = leaveTypeId;
        if (payrollPeriodId) params.payroll_period_id = payrollPeriodId;
        if (cycleId) params.cycle_id = cycleId;
        if (projectId) params.project_id = projectId;

        return params;
    };

    const renderTable = (headings, rows) => {
        if (!headings?.length) {
            tableHead.innerHTML = '<tr><th class="text-muted fw-normal">No columns</th></tr>';
            tableBody.innerHTML = '<tr><td class="text-center text-muted py-4">No data.</td></tr>';
            return;
        }

        tableHead.innerHTML = `<tr>${headings.map((h) => `<th>${escapeHtml(h)}</th>`).join('')}</tr>`;

        if (!rows?.length) {
            tableBody.innerHTML = `<tr><td colspan="${headings.length}" class="text-center text-muted py-4">No records found for the selected filters.</td></tr>`;
            return;
        }

        tableBody.innerHTML = rows.map((row) => `
            <tr>${row.map((cell) => `<td>${escapeHtml(cell ?? '—')}</td>`).join('')}</tr>
        `).join('');
    };

    const renderPagination = (pagination) => {
        renderListPagination({
            infoEl: paginationInfo,
            listEl: paginationList,
            perPageSelectEl: perPageSelect,
            pagination,
            emptyMessage: 'No records',
        });
    };

    const loadOptions = async () => {
        if (optionsLoaded) return;

        const { data } = await api.get('/reports/options');
        const payload = data.data;

        const deptSelect = document.getElementById('filterDepartmentId');
        (payload.departments || []).forEach((dept) => {
            deptSelect.insertAdjacentHTML('beforeend', `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`);
        });

        const leaveSelect = document.getElementById('filterLeaveTypeId');
        (payload.leave_types || []).forEach((type) => {
            leaveSelect.insertAdjacentHTML('beforeend', `<option value="${type.id}">${escapeHtml(type.name)}</option>`);
        });

        const payrollSelect = document.getElementById('filterPayrollPeriodId');
        (payload.payroll_periods || []).forEach((period) => {
            payrollSelect.insertAdjacentHTML('beforeend', `<option value="${period.id}">${escapeHtml(period.label)} (${escapeHtml(period.status)})</option>`);
        });

        const cycleSelect = document.getElementById('filterCycleId');
        (payload.review_cycles || []).forEach((cycle) => {
            cycleSelect.insertAdjacentHTML('beforeend', `<option value="${cycle.id}">${escapeHtml(cycle.name)}</option>`);
        });

        const projectSelect = document.getElementById('filterProjectId');
        (payload.projects || []).forEach((project) => {
            projectSelect.insertAdjacentHTML('beforeend', `<option value="${project.id}">${escapeHtml(project.name)}</option>`);
        });

        optionsLoaded = true;
    };

    const loadReport = async (page = 1) => {
        if (!currentType) {
            showAlert('Please select a report type.', 'warning');
            return;
        }

        currentPage = page;
        loadBtn.disabled = true;

        try {
            const { data } = await api.get(`/reports/${currentType}`, { params: collectFilters(page) });
            const payload = data.data;

            previewTitle.textContent = payload.report?.name || 'Report Preview';
            generatedAtEl.textContent = payload.generated_at
                ? `Generated ${new Date(payload.generated_at).toLocaleString()} — live data`
                : '';

            renderTable(payload.headings, payload.rows);
            renderPagination(payload.pagination);
            exportBtn.disabled = false;
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            loadBtn.disabled = false;
        }
    };

    const loadCatalog = async () => {
        const { data } = await api.get('/reports/catalog');
        catalog = data.data.reports || [];

        catalog.forEach((report) => {
            reportTypeSelect.insertAdjacentHTML('beforeend', `<option value="${report.key}">${escapeHtml(report.name)}</option>`);
        });

        if (catalog.length === 1) {
            reportTypeSelect.value = catalog[0].key;
            currentType = catalog[0].key;
            updateFilterVisibility();
        }
    };

    reportTypeSelect?.addEventListener('change', () => {
        currentType = reportTypeSelect.value;
        updateFilterVisibility();
        exportBtn.disabled = true;
        generatedAtEl.textContent = 'Select filters and click Load Report.';
        tableBody.innerHTML = '<tr><td class="text-center text-muted py-4">Click Load Report to preview live data.</td></tr>';
    });

    loadBtn?.addEventListener('click', () => loadReport(1));
    exportBtn?.addEventListener('click', async () => {
        if (!currentType) return;

        exportBtn.disabled = true;

        try {
            const response = await api.get(`/reports/${currentType}/export`, {
                params: collectFilters(),
                responseType: 'blob',
            });

            const disposition = response.headers['content-disposition'] || '';
            const match = disposition.match(/filename="?([^";]+)"?/i);
            const filename = match?.[1] || `report-${currentType}.xlsx`;

            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
            window.URL.revokeObjectURL(url);

            showAlert('Excel file downloaded.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            exportBtn.disabled = false;
        }
    });

    bindPagination(paginationList, loadReport);
    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        loadReport(1);
    });

    try {
        await loadCatalog();
        await loadOptions();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
});
