import api, { getErrorMessage } from './api';
import { bindEmployeeSearchSelect } from './employee-autocomplete';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

document.addEventListener('DOMContentLoaded', async () => {
    const config = window.analyticsReportConfig || {};
    const reportKey = config.reportKey;
    const activeFilters = config.filters || [];
    const exportType = config.exportType || 'csv';

    const alertBox = document.getElementById('analyticsReportAlert');
    const tableHead = document.getElementById('analyticsReportTableHead');
    const tableBody = document.getElementById('analyticsReportTableBody');
    const paginationInfo = document.getElementById('analyticsReportPaginationInfo');
    const paginationList = document.getElementById('analyticsReportPaginationList');
    const generatedAtEl = document.getElementById('analyticsReportGeneratedAt');
    const loadBtn = document.getElementById('loadAnalyticsReportBtn');
    const exportBtn = document.getElementById('exportAnalyticsReportBtn');
    const resetBtn = document.getElementById('filterReset');
    const reportPanel = document.getElementById('analyticsReportPanel');
    const chartsPanel = document.getElementById('analyticsChartsPanel');
    const chartsContainer = document.getElementById('analyticsChartsContainer');
    const tabButtons = Array.from(document.querySelectorAll('#analyticsReportTabs [data-analytics-tab]'));

    let currentPage = 1;
    let lastParams = null;

    const today = new Date();
    const monthStart = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;
    const todayStr = today.toISOString().slice(0, 10);
    const prevMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const prevMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
    const prevFrom = `${prevMonthStart.getFullYear()}-${String(prevMonthStart.getMonth() + 1).padStart(2, '0')}-01`;
    const prevTo = `${prevMonthEnd.getFullYear()}-${String(prevMonthEnd.getMonth() + 1).padStart(2, '0')}-${String(prevMonthEnd.getDate()).padStart(2, '0')}`;

    const fromDateEl = document.getElementById('filterFromDate');
    const toDateEl = document.getElementById('filterToDate');

    if (fromDateEl) {
        fromDateEl.value = reportKey.startsWith('regularization') ? prevFrom : monthStart;
    }
    if (toDateEl) {
        toDateEl.value = reportKey.startsWith('regularization') ? prevTo : todayStr;
    }

    bindEmployeeSearchSelect({
        inputId: 'filterEmployeeInput',
        hiddenId: 'filterEmployeeId',
        departmentInput: document.getElementById('filterDepartmentId'),
    });

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const updateFilterVisibility = () => {
        document.querySelectorAll('#analyticsReportFilters .filter-field').forEach((el) => {
            const key = el.dataset.filter;
            el.classList.toggle('d-none', !activeFilters.includes(key));
        });

        const statusSelect = document.getElementById('filterStatus');
        if (statusSelect && activeFilters.includes('status')) {
            const statusMap = {
                'regularization-summary': ['pending', 'approved', 'rejected', 'cancelled'],
                'regularization-details': ['pending', 'approved', 'rejected', 'cancelled'],
                'review-cycle-summary': ['not_started', 'in_progress', 'submitted'],
                'employee-master': ['active', 'inactive'],
            };

            const statuses = statusMap[reportKey] || ['active', 'inactive', 'all'];
            const defaultValue = reportKey.startsWith('regularization') || reportKey === 'review-cycle-summary' ? '' : 'active';

            statusSelect.innerHTML = (reportKey.startsWith('regularization') || reportKey === 'review-cycle-summary'
                ? '<option value="">All</option>'
                : '') + statuses.map((status) => (
                `<option value="${status}"${status === defaultValue ? ' selected' : ''}>${escapeHtml(status.replace(/_/g, ' '))}</option>`
            )).join('');

            if (!reportKey.startsWith('regularization') && reportKey !== 'review-cycle-summary' && statuses.includes('all')) {
                statusSelect.innerHTML += '<option value="all">All</option>';
            }
        }
    };

    const loadOptions = async () => {
        try {
            const { data } = await api.get('/analytics/options');
            const payload = data.data || data;

            const deptSelect = document.getElementById('filterDepartmentId');
            if (deptSelect && payload.departments) {
                deptSelect.innerHTML = '<option value="">All Departments</option>' + payload.departments.map((dept) => (
                    `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`
                )).join('');
            }

            const cycleSelect = document.getElementById('filterCycleId');
            if (cycleSelect && payload.review_cycles) {
                cycleSelect.innerHTML = '<option value="">Select review cycle…</option>' + payload.review_cycles.map((cycle) => (
                    `<option value="${cycle.id}">${escapeHtml(cycle.name)}</option>`
                )).join('');
            }

            const jobSelect = document.getElementById('filterJobId');
            if (jobSelect && payload.jobs) {
                jobSelect.innerHTML = '<option value="">All Jobs</option>' + payload.jobs.map((job) => (
                    `<option value="${job.id}">${escapeHtml(job.title)}</option>`
                )).join('');
            }

            const candidateStatusSelect = document.getElementById('filterCandidateStatus');
            if (candidateStatusSelect && payload.candidate_stages) {
                candidateStatusSelect.innerHTML = '<option value="">All</option>' + payload.candidate_stages.map((stage) => (
                    `<option value="${stage}">${escapeHtml(stage.replace(/_/g, ' '))}</option>`
                )).join('');
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const collectParams = () => {
        const params = {
            page: currentPage,
            per_page: document.getElementById('itemsPerPage')?.value || 25,
        };

        if (activeFilters.includes('from_date') && fromDateEl?.value) {
            params.from_date = fromDateEl.value;
        }
        if (activeFilters.includes('to_date') && toDateEl?.value) {
            params.to_date = toDateEl.value;
        }
        if (activeFilters.includes('status')) {
            const value = document.getElementById('filterStatus')?.value;
            if (value) params.status = value;
        }
        if (activeFilters.includes('employment_type')) {
            const value = document.getElementById('filterEmploymentType')?.value;
            if (value) params.employment_type = value;
        }
        if (activeFilters.includes('date_type')) {
            params.date_type = document.getElementById('filterDateType')?.value || 'expense_date';
        }
        if (activeFilters.includes('department_id')) {
            const value = document.getElementById('filterDepartmentId')?.value;
            if (value) params.department_id = value;
        }
        if (activeFilters.includes('employee_id')) {
            const value = document.getElementById('filterEmployeeId')?.value;
            if (value) params.employee_id = value;
        }
        if (activeFilters.includes('cycle_id')) {
            const value = document.getElementById('filterCycleId')?.value;
            if (value) params.cycle_id = value;
        }
        if (activeFilters.includes('job_id')) {
            const value = document.getElementById('filterJobId')?.value;
            if (value) params.job_id = value;
        }
        if (activeFilters.includes('candidate_status')) {
            const value = document.getElementById('filterCandidateStatus')?.value;
            if (value) params.candidate_status = value;
        }

        return params;
    };

    const renderBarChart = (container, items, unit = '') => {
        if (!container) return;

        if (!items?.length) {
            container.innerHTML = '<div class="text-muted small">No chart data for the selected filters.</div>';
            return;
        }

        const max = Math.max(...items.map((item) => Math.abs(Number(item.value) || 0)), 1);

        container.innerHTML = items.map((item) => {
            const value = Number(item.value) || 0;
            const width = Math.max(4, Math.round((Math.abs(value) / max) * 100));

            return `
                <div class="analytics-bar-row">
                    <div class="analytics-bar-label">${escapeHtml(item.label)}</div>
                    <div class="analytics-bar-track">
                        <div class="analytics-bar-fill ${value < 0 ? 'analytics-bar-fill--negative' : ''}" style="width:${width}%"></div>
                    </div>
                    <div class="analytics-bar-value">${Number.isInteger(value) ? value : value.toFixed(2)}${unit}</div>
                </div>
            `;
        }).join('');
    };

    const renderCharts = (charts) => {
        if (!chartsContainer) return;

        const sections = charts?.sections || [];

        if (!sections.length) {
            chartsContainer.innerHTML = '<div class="col-12 text-muted py-5 text-center">No chart data for the selected filters.</div>';
            return;
        }

        chartsContainer.innerHTML = sections.map((section, index) => `
            <div class="col-lg-6">
                <h2 class="h6">${escapeHtml(section.title)}</h2>
                <div id="analyticsChartSection${index}" class="analytics-chart-wrap"></div>
            </div>
        `).join('');

        sections.forEach((section, index) => {
            renderBarChart(document.getElementById(`analyticsChartSection${index}`), section.items || []);
        });
    };

    const renderTable = (payload) => {
        const headings = payload.headings || [];
        const rows = payload.rows || [];
        const pagination = payload.pagination || {};

        tableHead.innerHTML = `<tr>${headings.map((h) => `<th>${escapeHtml(h)}</th>`).join('')}</tr>`;

        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="100" class="text-center text-muted py-5">No records found for the selected filters.</td></tr>';
        } else {
            tableBody.innerHTML = rows.map((row) => (
                `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`
            )).join('');
        }

        if (pagination.total != null) {
            paginationInfo.textContent = pagination.total
                ? `Showing ${pagination.from}–${pagination.to} of ${pagination.total} rows`
                : 'No rows to display';
        }

        if (generatedAtEl && payload.generated_at) {
            generatedAtEl.textContent = `Generated ${new Date(payload.generated_at).toLocaleString()}`;
            generatedAtEl.classList.remove('d-none');
        }

        renderPagination(pagination);
        exportBtn.disabled = rows.length === 0;
    };

    const renderPagination = (pagination) => {
        if (!paginationList) return;

        const lastPage = pagination.last_page || 1;
        const current = pagination.current_page || 1;

        if (lastPage <= 1) {
            paginationList.innerHTML = '';
            return;
        }

        const items = [];
        const addItem = (page, label = null, disabled = false, active = false) => {
            items.push(`
                <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                    <button type="button" class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''}>${label ?? page}</button>
                </li>
            `);
        };

        addItem(current - 1, 'Previous', current <= 1);

        for (let page = 1; page <= lastPage; page += 1) {
            if (page === 1 || page === lastPage || Math.abs(page - current) <= 2) {
                addItem(page, page, false, page === current);
            } else if (Math.abs(page - current) === 3) {
                items.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
            }
        }

        addItem(current + 1, 'Next', current >= lastPage);
        paginationList.innerHTML = items.join('');

        paginationList.querySelectorAll('[data-page]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const page = Number(btn.dataset.page);
                if (!page || page === currentPage) return;
                currentPage = page;
                loadReport();
            });
        });
    };

    const loadReport = async () => {
        lastParams = collectParams();
        loadBtn.disabled = true;
        loadBtn.textContent = 'Loading…';

        try {
            const { data } = await api.get(`/analytics/reports/${reportKey}`, { params: lastParams });
            const payload = data.data || data;
            renderTable(payload);
            renderCharts(payload.charts);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            loadBtn.disabled = false;
            loadBtn.textContent = 'Load Report / Charts';
        }
    };

    const exportReport = async () => {
        if (!lastParams) {
            showAlert('Load the report before exporting.', 'warning');
            return;
        }

        exportBtn.disabled = true;

        try {
            const response = await api.get(`/analytics/reports/${reportKey}/export`, {
                params: lastParams,
                responseType: 'blob',
            });

            const blob = new Blob([response.data]);
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            const extension = exportType === 'excel' ? 'xlsx' : 'csv';
            link.href = url;
            link.download = `${reportKey}-${todayStr}.${extension}`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            exportBtn.disabled = false;
        }
    };

    updateFilterVisibility();
    await loadOptions();

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            tabButtons.forEach((tab) => tab.classList.toggle('active', tab === button));
            const tab = button.dataset.analyticsTab;
            reportPanel?.classList.toggle('d-none', tab !== 'report');
            chartsPanel?.classList.toggle('d-none', tab !== 'charts');
        });
    });

    loadBtn?.addEventListener('click', () => {
        currentPage = 1;
        loadReport();
    });

    exportBtn?.addEventListener('click', exportReport);

    resetBtn?.addEventListener('click', () => {
        if (fromDateEl) fromDateEl.value = reportKey.startsWith('regularization') ? prevFrom : monthStart;
        if (toDateEl) toDateEl.value = reportKey.startsWith('regularization') ? prevTo : todayStr;
        document.getElementById('filterStatus').value = 'active';
        document.getElementById('filterEmploymentType').value = '';
        document.getElementById('filterDepartmentId').value = '';
        document.getElementById('filterEmployeeId').value = '';
        document.getElementById('filterEmployeeInput').value = '';
        document.getElementById('filterCycleId').value = '';
        document.getElementById('filterJobId').value = '';
        document.getElementById('filterCandidateStatus').value = '';
        document.getElementById('filterDateType').value = 'expense_date';
        currentPage = 1;
    });

    document.getElementById('itemsPerPage')?.addEventListener('change', () => {
        currentPage = 1;
        if (lastParams) loadReport();
    });
});
