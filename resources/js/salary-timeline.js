const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const formatCurrency = (value) => new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
}).format(Number(value) || 0);

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatBool = (value) => (value ? 'Yes' : 'No');

const subtractDay = (dateStr) => {
    if (!dateStr) {
        return null;
    }

    const date = new Date(`${dateStr}T00:00:00`);
    date.setDate(date.getDate() - 1);

    return date.toISOString().slice(0, 10);
};

const revisionAsSalary = (revision = {}) => ({
    annual_ctc: revision.annual_ctc,
    basic_salary: revision.basic_salary,
    hra_percent: revision.hra_percent,
    special_allowance_percent: revision.special_allowance_percent,
    hra: revision.hra,
    special_allowance: revision.special_allowance,
    conveyance_allowance: revision.conveyance_allowance,
    medical_allowance: revision.medical_allowance,
    other_allowance: revision.other_allowance,
    monthly_gross: revision.monthly_gross,
    pf_applicable: revision.pf_applicable,
    esi_applicable: revision.esi_applicable,
    professional_tax_applicable: revision.professional_tax_applicable,
    salary_effective_from: revision.salary_effective_from,
    salary_payout_from: revision.salary_payout_from || revision.salary_effective_from,
});

const formatPeriod = (from, to) => {
    if (!from) {
        return '—';
    }

    return `${formatDate(from)} to ${to ? formatDate(to) : 'Present'}`;
};

const revisionTimelineTitle = (revisionType) => {
    if (revisionType === 'increment') {
        return 'Salary Increment';
    }

    if (revisionType === 'correction') {
        return 'Salary Correction';
    }

    return 'Salary Revised';
};

const renderSalarySnapshotRows = (salary = {}) => [
    ['Annual CTC', formatCurrency(salary.annual_ctc)],
    ['Monthly Gross', formatCurrency(salary.monthly_gross)],
    ['Basic Salary', formatCurrency(salary.basic_salary)],
    ['HRA', `${formatCurrency(salary.hra)} (${salary.hra_percent ?? 0}%)`],
    ['Special Allowance', `${formatCurrency(salary.special_allowance)} (${salary.special_allowance_percent ?? 0}%)`],
    ['Conveyance', formatCurrency(salary.conveyance_allowance)],
    ['Medical', formatCurrency(salary.medical_allowance)],
    ['Other Allowance', formatCurrency(salary.other_allowance)],
    ['PF / ESI / PT', `${formatBool(salary.pf_applicable)} / ${formatBool(salary.esi_applicable)} / ${formatBool(salary.professional_tax_applicable)}`],
];

const renderSnapshotBlock = (title, salary, periodFrom, periodTo, payoutFrom) => `
    <div class="salary-timeline-snapshot">
        <div class="salary-timeline-snapshot-head">
            <strong>${escapeHtml(title)}</strong>
            <span class="salary-timeline-period">${formatPeriod(periodFrom, periodTo)}</span>
        </div>
        <div class="salary-timeline-meta-row">
            <span>Payout from <strong>${formatDate(payoutFrom)}</strong></span>
        </div>
        <dl class="salary-timeline-dl">
            ${renderSalarySnapshotRows(salary).map(([label, value]) => `
                <div class="salary-timeline-dl-row">
                    <dt>${label}</dt>
                    <dd>${value}</dd>
                </div>
            `).join('')}
        </dl>
    </div>
`;

export const buildSalaryTimelineEntries = (revisions = [], currentSalary = {}) => {
    const sorted = [...revisions].sort(
        (left, right) => new Date(right.revised_at) - new Date(left.revised_at),
    );
    const entries = [];

    if (currentSalary?.annual_ctc) {
        entries.push({
            type: 'current',
            title: 'Current Salary',
            markerClass: 'salary-timeline-marker--current',
            periodFrom: currentSalary.salary_effective_from,
            periodTo: null,
            payoutFrom: currentSalary.salary_payout_from || currentSalary.salary_effective_from,
            salary: currentSalary,
            updatedAt: sorted[0]?.revised_at || null,
            updatedBy: sorted[0]?.revised_by || null,
            notes: null,
        });
    }

    sorted.forEach((revision, index) => {
        const previous = revisionAsSalary(revision);
        const next = index === 0 ? currentSalary : revisionAsSalary(sorted[index - 1]);
        const periodTo = subtractDay(next.salary_effective_from);

        entries.push({
            type: 'revision',
            title: revisionTimelineTitle(revision.revision_type),
            markerClass: revision.revision_type === 'increment'
                ? 'salary-timeline-marker--increment'
                : 'salary-timeline-marker--revision',
            updatedAt: revision.revised_at,
            updatedBy: revision.revised_by,
            notes: revision.revision_notes,
            previous,
            previousPeriodFrom: previous.salary_effective_from,
            previousPeriodTo: periodTo,
            previousPayoutFrom: previous.salary_payout_from,
            next,
            nextPeriodFrom: next.salary_effective_from,
            nextPeriodTo: index === 0 ? null : subtractDay(sorted[index - 1]?.salary_effective_from),
            nextPayoutFrom: next.salary_payout_from || next.salary_effective_from,
        });
    });

    return entries;
};

export const renderSalaryRevisionTimeline = (container, revisions = [], currentSalary = {}) => {
    if (!container) {
        return;
    }

    const entries = buildSalaryTimelineEntries(revisions, currentSalary);

    if (entries.length === 0) {
        container.innerHTML = `
            <div class="salary-timeline-empty text-center text-muted py-5">
                <div class="mb-2" aria-hidden="true">📋</div>
                <p class="mb-0">No salary timeline yet. Revisions will appear here with period and update details.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="salary-timeline">
            ${entries.map((entry) => {
                if (entry.type === 'current') {
                    return `
                        <article class="salary-timeline-item">
                            <div class="salary-timeline-marker ${entry.markerClass}" aria-hidden="true"></div>
                            <div class="salary-timeline-body">
                                <div class="salary-timeline-body-head">
                                    <div>
                                        <h5 class="salary-timeline-title mb-1">${escapeHtml(entry.title)}</h5>
                                        <p class="salary-timeline-subtitle mb-0">Active compensation structure</p>
                                    </div>
                                    <span class="badge text-bg-success">Active</span>
                                </div>
                                ${renderSnapshotBlock('Compensation', entry.salary, entry.periodFrom, entry.periodTo, entry.payoutFrom)}
                                ${entry.updatedAt ? `
                                    <div class="salary-timeline-footer">
                                        Last updated ${formatDateTime(entry.updatedAt)}
                                        ${entry.updatedBy?.name ? ` by ${escapeHtml(entry.updatedBy.name)}` : ''}
                                    </div>
                                ` : `
                                    <div class="salary-timeline-footer">Initial salary record</div>
                                `}
                            </div>
                        </article>
                    `;
                }

                return `
                    <article class="salary-timeline-item">
                        <div class="salary-timeline-marker ${entry.markerClass}" aria-hidden="true"></div>
                        <div class="salary-timeline-body">
                            <div class="salary-timeline-body-head">
                                <div>
                                    <h5 class="salary-timeline-title mb-1">${escapeHtml(entry.title)}</h5>
                                    <p class="salary-timeline-subtitle mb-0">Updated ${formatDateTime(entry.updatedAt)}${entry.updatedBy?.name ? ` by ${escapeHtml(entry.updatedBy.name)}` : ''}</p>
                                </div>
                            </div>
                            ${entry.notes ? `<div class="salary-timeline-notes">${escapeHtml(entry.notes)}</div>` : ''}
                            <div class="salary-timeline-change-grid">
                                ${renderSnapshotBlock('Before', entry.previous, entry.previousPeriodFrom, entry.previousPeriodTo, entry.previousPayoutFrom)}
                                ${renderSnapshotBlock('After', entry.next, entry.nextPeriodFrom, entry.nextPeriodTo, entry.nextPayoutFrom)}
                            </div>
                        </div>
                    </article>
                `;
            }).join('')}
        </div>
    `;
};

export const hasSalaryRevisionTimeline = (revisions = [], currentSalary = {}) => (
    revisions.length > 0 || Boolean(currentSalary?.annual_ctc)
);
