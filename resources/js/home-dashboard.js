import { Modal } from 'bootstrap';
import Chart from 'chart.js/auto';
import api, { getErrorMessage } from './api';

const chartInstances = new Map();

const CHART_COLORS = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#8b5cf6', '#64748b', '#14b8a6', '#ec4899'];

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('homeDashboardRoot');
    const empty = document.getElementById('homeDashboardEmpty');
    const alertBox = document.getElementById('homeDashboardAlert');
    const manageBtn = document.getElementById('homeDashboardManageBtn');
    const widgetModalEl = document.getElementById('homeDashboardWidgetModal');
    const widgetOptions = document.getElementById('homeDashboardWidgetOptions');
    const saveWidgetsBtn = document.getElementById('homeDashboardSaveWidgetsBtn');
    const widgetModal = widgetModalEl ? Modal.getOrCreateInstance(widgetModalEl) : null;

    if (!root) return;

    let availableWidgets = [];
    let activeWidgets = [];

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const destroyCharts = () => {
        chartInstances.forEach((chart) => chart.destroy());
        chartInstances.clear();
    };

    const renderChart = (canvas, widget) => {
        const catalog = widget.catalog || {};
        const data = widget.data || { labels: [], series: [] };
        const type = catalog.chart_type === 'bar' ? 'bar' : 'doughnut';
        const chartLabel = catalog.label || 'Count';

        const chart = new Chart(canvas, {
            type,
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: chartLabel,
                    data: data.series || [],
                    backgroundColor: CHART_COLORS,
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: type !== 'bar',
                        position: type === 'bar' ? 'top' : 'bottom',
                    },
                },
                scales: type === 'bar' ? {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                } : {},
            },
        });

        chartInstances.set(widget.key, chart);
    };

    const renderWidgets = () => {
        destroyCharts();

        if (!activeWidgets.length) {
            root.innerHTML = '';
            empty?.classList.remove('d-none');
            return;
        }

        empty?.classList.add('d-none');
        root.innerHTML = activeWidgets.map((widget) => {
            const catalog = widget.catalog || {};
            return `
                <div class="col-lg-6">
                    <div class="content-card h-100">
                        <div class="content-card-body">
                            <h2 class="h6 mb-1">${catalog.label || widget.key}</h2>
                            <p class="text-muted small mb-3">${catalog.description || ''}</p>
                            <div class="home-dashboard-chart-wrap">
                                <canvas id="homeChart-${widget.key}" aria-label="${catalog.label || widget.key}"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        activeWidgets.forEach((widget) => {
            const canvas = document.getElementById(`homeChart-${widget.key}`);
            if (canvas) {
                renderChart(canvas, widget);
            }
        });
    };

    const load = async () => {
        try {
            const { data } = await api.get('/home/dashboard');
            availableWidgets = data.data?.available_widgets || [];
            activeWidgets = data.data?.widgets || [];
            renderWidgets();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const openManageModal = () => {
        if (!widgetOptions) return;

        const selected = new Set(activeWidgets.map((widget) => widget.key));

        widgetOptions.innerHTML = availableWidgets.map((widget) => `
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" value="${widget.key}" id="widget-${widget.key}" ${selected.has(widget.key) ? 'checked' : ''}>
                <label class="form-check-label" for="widget-${widget.key}">
                    <span class="fw-semibold">${widget.label}</span>
                    <span class="d-block small text-muted">${widget.description || ''}</span>
                </label>
            </div>
        `).join('');

        widgetModal?.show();
    };

    manageBtn?.addEventListener('click', openManageModal);

    saveWidgetsBtn?.addEventListener('click', async () => {
        const widgets = Array.from(widgetOptions?.querySelectorAll('input[type="checkbox"]:checked') || [])
            .map((input) => input.value);

        if (!widgets.length) {
            showAlert('Select at least one widget.', 'warning');
            return;
        }

        saveWidgetsBtn.disabled = true;

        try {
            const { data } = await api.put('/home/dashboard/widgets', { widgets });
            activeWidgets = data.data?.widgets || [];
            widgetModal?.hide();
            renderWidgets();
            showAlert(data.message || 'Dashboard updated.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            saveWidgetsBtn.disabled = false;
        }
    });

    await load();
});
