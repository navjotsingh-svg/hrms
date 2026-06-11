import api, { getErrorMessage } from './api';

document.addEventListener('DOMContentLoaded', async () => {
    const yearSelect = document.getElementById('balanceYear');
    const container = document.getElementById('leaveBalancePage');
    const currentYear = new Date().getFullYear();

    if (!container) return;

    if (yearSelect) {
        yearSelect.innerHTML = Array.from({ length: 3 }, (_, i) => currentYear - 1 + i)
            .map((year) => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`).join('');
    }

    const load = async () => {
        container.innerHTML = '<div class="text-muted">Loading...</div>';
        try {
            const { data } = await api.get('/leave-balances/me', { params: { year: yearSelect?.value || currentYear } });
            const balances = data.data.balances || [];
            if (!balances.length) {
                container.innerHTML = '<div class="text-muted">No leave balances for this year.</div>';
                return;
            }
            container.innerHTML = `<div class="row g-3">${balances.map((item) => {
                const unit = item.balance_unit === 'hours' ? 'hours' : 'days';
                const quotaUnit = item.leave_type?.quota_unit === 'hours' ? 'hours' : 'days';

                return `
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">${item.leave_type.name} <span class="text-muted">(${item.leave_type.code})</span></div>
                        <div class="small text-muted">Annual quota: ${item.leave_type.annual_quota ?? 'Unlimited'} ${quotaUnit}</div>
                        <div class="small">Allocated: <strong>${item.allocated}</strong> ${unit}</div>
                        ${item.is_comp_off ? `<div class="small">Comp off credited: <strong>${item.adjusted}</strong></div>` : `<div class="small">Adjusted: <strong>${item.adjusted}</strong></div>`}
                        <div class="small">Used: <strong>${item.used}</strong> ${unit}</div>
                        <div class="small">Pending: <strong>${item.pending}</strong> ${unit}</div>
                        <div class="small mt-2">Available: <strong class="text-primary">${item.available ?? 'Unlimited'}</strong>${item.available != null ? ` ${unit}` : ''}</div>
                    </div>
                </div>
            `;
            }).join('')}</div>`;
        } catch (error) {
            container.innerHTML = `<div class="text-danger">${getErrorMessage(error)}</div>`;
        }
    };

    yearSelect?.addEventListener('change', load);
    await load();
});
