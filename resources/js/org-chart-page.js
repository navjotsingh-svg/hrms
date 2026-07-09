import { bindOrgChartInteractions, loadOrgChart } from './org-chart';

document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('orgChartRoot')) {
        return;
    }

    bindOrgChartInteractions('orgChartRoot');
    await loadOrgChart('orgChartRoot');
});
