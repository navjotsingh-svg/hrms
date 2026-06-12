import api, { getErrorMessage } from './api';

const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const debounce = (callback, delay = 250) => {
    let timeoutId = null;

    return (...args) => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => {
            callback(...args);
        }, delay);
    };
};

export const formatEmployeeLabel = (employee) => {
    const name = employee.full_name
        || employee.employee_name
        || `${employee.first_name || ''} ${employee.last_name || ''}`.trim()
        || 'Employee';

    const code = employee.employee_code || employee.employee_id;

    return code ? `${name} (${code})` : name;
};

export const employeeSearchName = (employee) => String(
    employee?.full_name
    || employee?.employee_name
    || `${employee?.first_name || ''} ${employee?.last_name || ''}`.trim(),
).toLowerCase();

export const employeeSearchCode = (employee) => String(
    employee?.employee_code || employee?.employee_id || '',
).toLowerCase();

export const matchesEmployeeSearch = (employee, term) => {
    const needle = term.trim().toLowerCase();

    if (!needle) {
        return true;
    }

    return employeeSearchName(employee).includes(needle)
        || employeeSearchCode(employee).includes(needle);
};

export const filterEmployeeOptions = (items, term = '') => {
    const needle = term.trim().toLowerCase();

    if (!needle) {
        return items;
    }

    return items.filter((item) => matchesEmployeeSearch(item.employee, needle));
};

export const initEmployeeAutocomplete = ({
    input,
    menu,
    hiddenInput,
    wrap = null,
    toggleButton = null,
    minLength = 0,
    fetchSuggestions,
    onSelect = null,
    onClear = null,
}) => {
    if (!input || !menu || !hiddenInput) {
        return null;
    }

    let activeIndex = -1;
    let suggestions = [];
    let selectedLabel = '';
    let isOpen = false;
    let isLoading = false;

    const setOpen = (open) => {
        isOpen = open;
        wrap?.classList.toggle('is-open', open);
        toggleButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
        input.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const hideMenu = () => {
        menu.classList.add('d-none');
        menu.innerHTML = '';
        activeIndex = -1;
        suggestions = [];
        setOpen(false);
    };

    const showMenu = () => {
        menu.classList.remove('d-none');
        setOpen(true);
    };

    const searchTerm = () => {
        const value = input.value.trim();

        if (!value || value === selectedLabel) {
            return '';
        }

        return value;
    };

    const renderSuggestions = (items, selectedId = null) => {
        suggestions = items;
        activeIndex = -1;

        if (isLoading) {
            menu.innerHTML = '<div class="filter-autocomplete-empty">Loading employees...</div>';
            showMenu();
            return;
        }

        if (!items.length) {
            menu.innerHTML = '<div class="filter-autocomplete-empty">No employees found</div>';
            showMenu();
            return;
        }

        menu.innerHTML = items.map((item, index) => `
            <button
                type="button"
                class="filter-autocomplete-item employee-search-select-item${Number(selectedId) === Number(item.id) ? ' is-selected' : ''}"
                data-suggestion-index="${index}"
                role="option"
                aria-selected="${Number(selectedId) === Number(item.id) ? 'true' : 'false'}"
            >
                ${escapeHtml(item.label)}
            </button>
        `).join('');

        showMenu();
    };

    const selectSuggestion = (item) => {
        selectedLabel = item.label;
        input.value = item.label;
        hiddenInput.value = String(item.id);
        hideMenu();

        if (typeof onSelect === 'function') {
            onSelect(item);
        }
    };

    const clearSelection = () => {
        selectedLabel = '';
        hiddenInput.value = '';
        hideMenu();

        if (typeof onClear === 'function') {
            onClear();
        }
    };

    const setDisabled = (disabled) => {
        input.disabled = disabled;
        toggleButton.disabled = disabled;

        if (disabled) {
            hideMenu();
        }
    };

    const setSelection = (item) => {
        if (!item?.id) {
            selectedLabel = '';
            input.value = '';
            hiddenInput.value = '';
            hideMenu();
            return;
        }

        selectedLabel = item.label || formatEmployeeLabel(item);
        input.value = selectedLabel;
        hiddenInput.value = String(item.id);
        hideMenu();
    };

    const loadSuggestions = async (termOverride = undefined) => {
        const term = termOverride !== undefined ? termOverride : searchTerm();

        if (minLength > 0 && term.length < minLength) {
            hideMenu();
            return;
        }

        isLoading = true;

        if (isOpen) {
            renderSuggestions([], hiddenInput.value);
        }

        try {
            const items = await fetchSuggestions(term);
            isLoading = false;
            renderSuggestions(items, hiddenInput.value);
        } catch (error) {
            isLoading = false;
            menu.innerHTML = `<div class="filter-autocomplete-empty">${getErrorMessage(error, 'Unable to load employees.')}</div>`;
            showMenu();
        }
    };

    const debouncedLoad = debounce(() => {
        loadSuggestions();
    });

    const openMenu = () => {
        loadSuggestions('');
    };

    const setActiveItem = (index) => {
        const items = menu.querySelectorAll('.filter-autocomplete-item');

        items.forEach((item, itemIndex) => {
            item.classList.toggle('active', itemIndex === index);
        });

        activeIndex = index;
    };

    input.addEventListener('input', () => {
        if (!input.value.trim()) {
            clearSelection();
            openMenu();
            return;
        }

        debouncedLoad();
    });

    input.addEventListener('focus', () => {
        openMenu();
    });

    input.addEventListener('click', () => {
        if (!isOpen) {
            openMenu();
        }
    });

    input.addEventListener('keydown', (event) => {
        const items = menu.querySelectorAll('.filter-autocomplete-item');

        if (event.key === 'ArrowDown') {
            event.preventDefault();

            if (!items.length || menu.classList.contains('d-none')) {
                openMenu();
                return;
            }

            const nextIndex = activeIndex < items.length - 1 ? activeIndex + 1 : 0;
            setActiveItem(nextIndex);
            items[nextIndex]?.scrollIntoView({ block: 'nearest' });
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();

            if (!items.length || menu.classList.contains('d-none')) {
                return;
            }

            const nextIndex = activeIndex > 0 ? activeIndex - 1 : items.length - 1;
            setActiveItem(nextIndex);
            items[nextIndex]?.scrollIntoView({ block: 'nearest' });
            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            selectSuggestion(suggestions[activeIndex]);
            return;
        }

        if (event.key === 'Escape') {
            if (selectedLabel) {
                input.value = selectedLabel;
            }

            hideMenu();
        }
    });

    toggleButton?.addEventListener('click', (event) => {
        event.preventDefault();

        if (isOpen) {
            if (selectedLabel) {
                input.value = selectedLabel;
            }

            hideMenu();
            return;
        }

        input.focus();
        openMenu();
    });

    menu.addEventListener('click', (event) => {
        const button = event.target.closest('[data-suggestion-index]');

        if (!button) {
            return;
        }

        const index = Number(button.dataset.suggestionIndex);
        selectSuggestion(suggestions[index]);
    });

    document.addEventListener('mousedown', (event) => {
        const inside = wrap?.contains(event.target)
            || input.contains(event.target)
            || menu.contains(event.target)
            || toggleButton?.contains(event.target);

        if (!inside) {
            if (selectedLabel && input.value !== selectedLabel && !hiddenInput.value) {
                input.value = selectedLabel;
            } else if (selectedLabel && hiddenInput.value && input.value !== selectedLabel) {
                input.value = selectedLabel;
            }

            hideMenu();
        }
    });

    return {
        hideMenu,
        setSelection,
        clearSelection,
        openMenu,
        setDisabled,
        getSelectedId: () => (hiddenInput.value ? Number(hiddenInput.value) : null),
    };
};

export const bindEmployeeSearchSelect = ({
    inputId,
    hiddenId,
    departmentInput = null,
    excludeEmployeeId = null,
    fetchSuggestions = null,
    onSelect = null,
    onClear = null,
}) => {
    const input = document.getElementById(inputId);
    const hiddenInput = document.getElementById(hiddenId);

    if (!input || !hiddenInput) {
        return null;
    }

    return initEmployeeAutocomplete({
        input,
        menu: document.getElementById(`${inputId}Menu`),
        hiddenInput,
        wrap: document.getElementById(`${inputId}Wrap`),
        toggleButton: document.getElementById(`${inputId}Toggle`),
        fetchSuggestions: fetchSuggestions || (async (term) => {
            const params = {};

            if (departmentInput?.value) {
                params.department_id = departmentInput.value;
            }

            const items = await searchEmployees(term, params);

            if (!excludeEmployeeId) {
                return items;
            }

            return items.filter((item) => Number(item.id) !== Number(excludeEmployeeId));
        }),
        onSelect,
        onClear,
    });
};

export const searchEmployees = async (term, extraParams = {}) => {
    const params = {
        status: 'active',
        per_page: 50,
        ...extraParams,
    };

    if (term) {
        params.search = term;
    }

    const { data } = await api.get('/employees', { params });

    return (data.data.employees || []).map((employee) => ({
        id: employee.id,
        label: formatEmployeeLabel(employee),
        employee,
    }));
};
