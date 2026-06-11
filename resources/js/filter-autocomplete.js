import api, { getErrorMessage } from './api';

const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const debounce = (callback, delay = 300) => {
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

export const initFilterAutocomplete = ({
    input,
    menu,
    field,
    minLength = 1,
    onSelect = null,
}) => {
    if (!input || !menu) {
        return;
    }

    let activeIndex = -1;
    let suggestions = [];

    const hideMenu = () => {
        menu.classList.add('d-none');
        menu.innerHTML = '';
        activeIndex = -1;
        suggestions = [];
    };

    const showMenu = () => {
        menu.classList.remove('d-none');
    };

    const renderSuggestions = (items) => {
        suggestions = items;
        activeIndex = -1;

        if (!items.length) {
            menu.innerHTML = '<div class="filter-autocomplete-empty">No matches found</div>';
            showMenu();
            return;
        }

        menu.innerHTML = items.map((value, index) => `
            <button
                type="button"
                class="filter-autocomplete-item"
                data-suggestion-index="${index}"
                role="option"
            >
                ${escapeHtml(value)}
            </button>
        `).join('');

        showMenu();
    };

    const fetchSuggestions = debounce(async () => {
        const term = input.value.trim();

        if (term.length < minLength) {
            hideMenu();
            return;
        }

        try {
            const { data } = await api.get('/companies/suggestions', {
                params: { field, q: term },
            });

            renderSuggestions(data.data.suggestions || []);
        } catch (error) {
            menu.innerHTML = `<div class="filter-autocomplete-empty">${getErrorMessage(error, 'Unable to load suggestions.')}</div>`;
            showMenu();
        }
    });

    const selectSuggestion = (value) => {
        input.value = value;
        hideMenu();

        if (typeof onSelect === 'function') {
            onSelect(value);
        }
    };

    const setActiveItem = (index) => {
        const items = menu.querySelectorAll('.filter-autocomplete-item');

        items.forEach((item, itemIndex) => {
            item.classList.toggle('active', itemIndex === index);
        });

        activeIndex = index;
    };

    input.addEventListener('input', () => {
        fetchSuggestions();
    });

    input.addEventListener('focus', () => {
        if (input.value.trim().length >= minLength) {
            fetchSuggestions();
        }
    });

    input.addEventListener('keydown', (event) => {
        const items = menu.querySelectorAll('.filter-autocomplete-item');

        if (!items.length || menu.classList.contains('d-none')) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            const nextIndex = activeIndex < items.length - 1 ? activeIndex + 1 : 0;
            setActiveItem(nextIndex);
            items[nextIndex]?.scrollIntoView({ block: 'nearest' });
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
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
            hideMenu();
        }
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
        if (!input.contains(event.target) && !menu.contains(event.target)) {
            hideMenu();
        }
    });

    return { hideMenu };
};
