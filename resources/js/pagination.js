export const DEFAULT_PER_PAGE = 10;

export const PER_PAGE_OPTIONS = [10, 25, 50];

export const syncPerPageSelect = (selectEl, pagination, fallback = DEFAULT_PER_PAGE) => {
    if (!selectEl) {
        return;
    }

    selectEl.value = String(pagination?.per_page || fallback);
};

export const bindPerPageSelect = (selectEl, onPerPageChange) => {
    selectEl?.addEventListener('change', () => {
        onPerPageChange(Number(selectEl.value) || DEFAULT_PER_PAGE);
    });
};

export const readPerPage = (selectEl, fallback = DEFAULT_PER_PAGE) => Number(selectEl?.value) || fallback;

export const buildPaginationMeta = (total, page, perPage) => {
    const safePerPage = Math.max(1, perPage);
    const lastPage = Math.max(1, Math.ceil(total / safePerPage));
    const currentPage = Math.max(1, Math.min(page, lastPage));
    const offset = (currentPage - 1) * safePerPage;

    return {
        current_page: currentPage,
        last_page: lastPage,
        per_page: safePerPage,
        total,
        from: total ? offset + 1 : 0,
        to: total ? Math.min(offset + safePerPage, total) : 0,
    };
};

export const paginateArray = (items, page = 1, perPage = DEFAULT_PER_PAGE) => {
    const pagination = buildPaginationMeta(items.length, page, perPage);
    const offset = (pagination.current_page - 1) * pagination.per_page;

    return {
        items: items.slice(offset, offset + pagination.per_page),
        pagination,
    };
};

export const formatPaginationInfo = (pagination, itemLabel = 'items', emptyMessage = null) => {
    if (!pagination?.total) {
        return emptyMessage || `No ${itemLabel} found`;
    }

    return `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total} ${itemLabel}`;
};

const renderPageButton = (page, currentPage, dataAttr) => `
    <li class="page-item ${page === currentPage ? 'active' : ''}">
        <button
            type="button"
            class="page-link"
            ${dataAttr}="${page}"
            ${page === currentPage ? 'aria-current="page"' : ''}
        >
            ${page}
        </button>
    </li>
`;

const renderEllipsis = () => `
    <li class="page-item disabled">
        <span class="page-link">...</span>
    </li>
`;

export const buildNumberedPagesHtml = (pagination, dataAttr = 'data-page') => {
    const totalPages = pagination.last_page;
    const currentPage = pagination.current_page;

    if (totalPages <= 1) {
        return renderPageButton(1, currentPage, dataAttr);
    }

    if (totalPages <= 10) {
        return Array.from({ length: totalPages }, (_, index) => renderPageButton(index + 1, currentPage, dataAttr)).join('');
    }

    const items = [renderPageButton(1, currentPage, dataAttr)];
    const start = Math.max(2, currentPage - 1);
    const end = Math.min(totalPages - 1, currentPage + 1);

    if (start > 2) {
        items.push(renderEllipsis());
    }

    for (let page = start; page <= end; page += 1) {
        items.push(renderPageButton(page, currentPage, dataAttr));
    }

    if (end < totalPages - 1) {
        items.push(renderEllipsis());
    }

    items.push(renderPageButton(totalPages, currentPage, dataAttr));

    return items.join('');
};

export const renderListPagination = ({
    infoEl,
    listEl,
    perPageSelectEl = null,
    pagination,
    itemLabel = 'items',
    emptyMessage = null,
    dataAttr = 'data-page',
}) => {
    if (infoEl) {
        infoEl.textContent = formatPaginationInfo(pagination, itemLabel, emptyMessage);
    }

    syncPerPageSelect(perPageSelectEl, pagination);

    if (!listEl) {
        return;
    }

    if (!pagination?.total || pagination.last_page <= 1) {
        listEl.innerHTML = '';
        return;
    }

    const numberedPages = buildNumberedPagesHtml(pagination, dataAttr);

    listEl.innerHTML = `
        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
            <button
                type="button"
                class="page-link"
                ${dataAttr}="${pagination.current_page - 1}"
                ${pagination.current_page === 1 ? 'disabled' : ''}
            >
                Previous
            </button>
        </li>
        ${numberedPages}
        <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
            <button
                type="button"
                class="page-link"
                ${dataAttr}="${pagination.current_page + 1}"
                ${pagination.current_page === pagination.last_page ? 'disabled' : ''}
            >
                Next
            </button>
        </li>
    `;
};

export const bindPagination = (containerEl, onPage, dataAttr = 'data-page') => {
    containerEl?.addEventListener('click', (event) => {
        const button = event.target.closest(`[${dataAttr}]`);

        if (!button || button.disabled) {
            return;
        }

        const page = Number(button.getAttribute(dataAttr));

        if (page > 0) {
            onPage(page);
        }
    });
};

export const getSerialNumber = (index, pagination) => {
    const currentPageNumber = pagination?.current_page || 1;
    const perPage = pagination?.per_page || DEFAULT_PER_PAGE;

    return ((currentPageNumber - 1) * perPage) + index + 1;
};
