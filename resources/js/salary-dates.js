export const todayDateInput = () => new Date().toISOString().slice(0, 10);

/**
 * Next salary increment effective date: joining-date anniversary after current effective date.
 */
export const computeIncrementEffectiveDate = (joiningDate, currentEffectiveFrom = null) => {
    if (!joiningDate) {
        return todayDateInput();
    }

    const join = new Date(`${joiningDate}T00:00:00`);
    const reference = new Date(`${currentEffectiveFrom || joiningDate}T00:00:00`);

    const candidate = new Date(join);
    candidate.setFullYear(candidate.getFullYear() + 1);

    while (candidate <= reference) {
        candidate.setFullYear(candidate.getFullYear() + 1);
    }

    return candidate.toISOString().slice(0, 10);
};
