const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

export const normalizeDateTimeLabel = (label) => String(label)
    .replace(/,\s*(?=\d{1,2}:\d{2}\s*[AP]M)/i, '\n');

export const formatDateTimeLabel = (value, { empty = '—', dateOnly = false } = {}) => {
    if (value == null || value === '') {
        return empty;
    }

    if (typeof value === 'string' && !value.includes('T') && /[AP]M/i.test(value)) {
        const normalized = normalizeDateTimeLabel(value);

        return dateOnly ? normalized.split('\n')[0] : normalized;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const datePart = date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

    if (dateOnly) {
        return datePart;
    }

    const timePart = date.toLocaleTimeString('en-IN', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });

    return `${datePart}\n${timePart}`;
};

export const renderDateTimeStack = (value, { className = 'dt-stack', empty = '—', dateOnly = false } = {}) => {
    const label = formatDateTimeLabel(value, { empty, dateOnly });

    if (!label || label === empty) {
        return empty;
    }

    return `<span class="${className}">${escapeHtml(label)}</span>`;
};

export const renderDateTimeStackFromLabel = (label, { className = 'dt-stack', empty = '—' } = {}) => {
    if (!label || label === empty) {
        return empty;
    }

    return `<span class="${className}">${escapeHtml(normalizeDateTimeLabel(label))}</span>`;
};
