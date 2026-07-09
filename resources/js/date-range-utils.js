import { localDateInputValue } from './form-utils';

export const todayDateInput = () => localDateInputValue();

export const monthStartDateInput = () => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return localDateInputValue(new Date(today.getFullYear(), today.getMonth(), 1));
};

export const resolveClientDateRange = (preset, fromDate = '', toDate = '') => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (preset === 'custom') {
        return {
            preset: 'custom',
            from_date: fromDate,
            to_date: toDate,
        };
    }

    if (preset === 'yesterday') {
        const day = new Date(today);
        day.setDate(day.getDate() - 1);
        const date = localDateInputValue(day);

        return { preset, from_date: date, to_date: date };
    }

    if (preset === 'this_week') {
        const start = new Date(today);
        const weekday = start.getDay();
        const diff = weekday === 0 ? -6 : 1 - weekday;
        start.setDate(start.getDate() + diff);
        const weekEnd = new Date(start);
        weekEnd.setDate(weekEnd.getDate() + 6);

        return {
            preset,
            from_date: localDateInputValue(start),
            to_date: localDateInputValue(weekEnd),
        };
    }

    if (preset === 'today') {
        const date = localDateInputValue(today);

        return { preset, from_date: date, to_date: date };
    }

    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);

    return {
        preset: preset || 'this_month',
        from_date: localDateInputValue(monthStart),
        to_date: localDateInputValue(monthEnd),
    };
};

export const formatDisplayDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

export const buildRangeQueryParams = (range) => ({
    range: range.preset,
    ...(range.preset === 'custom'
        ? { from_date: range.from_date, to_date: range.to_date }
        : {}),
});
