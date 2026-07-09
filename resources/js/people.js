import api, { getErrorMessage } from './api';

import { renderAvatarHtml } from './avatar';

import { bindOrgChartInteractions, renderOrgChart } from './org-chart';

import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';



const PEOPLE_AVATAR_COLORS = ['#8b5a3c', '#6b4c35', '#2f6b4f', '#7c5c8a', '#3b6f8f', '#8b4f5c'];



let currentPage = 1;

let searchTimeout = null;

let orgChartLoaded = false;



const showAlert = (message, type = 'danger') => {

    const alertBox = document.getElementById('peopleAlert');



    if (!alertBox) {

        return;

    }



    alertBox.className = `alert alert-${type} alert-dismissible fade show`;

    alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;

    alertBox.classList.remove('d-none');

};



const renderSummaryRow = (employee) => `

    <tr>

        <td>

            <a href="${employee.profile_url}" class="people-summary-name text-decoration-none">

                ${renderAvatarHtml({

                    name: employee.name,

                    photoUrl: employee.profile_photo_url,

                    initials: employee.initials,

                    className: 'people-summary-avatar',

                    palette: PEOPLE_AVATAR_COLORS,

                })}

                <span>${employee.name}</span>

            </a>

        </td>

        <td>${employee.employee_code}</td>

        <td>${employee.department || '—'}</td>

    </tr>

`;



const renderPagination = (pagination) => {

    const info = document.getElementById('peoplePaginationInfo');

    const list = document.getElementById('peoplePaginationList');

    const perPageSelect = document.getElementById('peoplePerPage');



    renderListPagination({

        infoEl: info,

        listEl: list,

        perPageSelectEl: perPageSelect,

        pagination,

        itemLabel: 'employees',

        emptyMessage: 'No employees found.',

    });

};



const loadSummary = async (page = 1) => {

    const tableBody = document.getElementById('peopleSummaryBody');

    const search = document.getElementById('peopleSearch')?.value?.trim() || '';

    const perPageSelect = document.getElementById('peoplePerPage');

    const perPage = readPerPage(perPageSelect, 15);



    if (!tableBody) {

        return;

    }



    try {

        const { data } = await api.get('/people/summary', {

            params: {

                page,

                search: search || undefined,

                per_page: perPage,

            },

        });



        const employees = data.data.employees || [];

        const pagination = data.data.pagination;

        currentPage = pagination?.current_page || 1;



        if (!employees.length) {

            tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-5">No employees found.</td></tr>';

        } else {

            tableBody.innerHTML = employees.map(renderSummaryRow).join('');

        }



        renderPagination(pagination);

    } catch (error) {

        tableBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;

    }

};



const loadPeopleOrgChart = async () => {

    const root = document.getElementById('peopleOrgChartRoot');



    if (!root || orgChartLoaded) {

        return;

    }



    try {

        const { data } = await api.get('/people/org-chart');

        renderOrgChart(data.data || {}, 'peopleOrgChartRoot');

        orgChartLoaded = true;

    } catch (error) {

        root.innerHTML = `<div class="text-center text-danger py-5">${getErrorMessage(error)}</div>`;

    }

};



const setSidebarPeopleActive = (view = 'summary') => {

    document.getElementById('sidebarPeopleSummaryLink')?.classList.toggle('active', view === 'summary');

    document.getElementById('sidebarPeopleOrgChartLink')?.classList.toggle('active', view === 'org-chart');

};



const activateTabFromHash = () => {

    if (window.location.hash === '#org-chart') {

        document.getElementById('people-org-chart-tab')?.click();

        setSidebarPeopleActive('org-chart');

    } else {

        setSidebarPeopleActive('summary');

    }

};



document.addEventListener('DOMContentLoaded', async () => {

    const searchInput = document.getElementById('peopleSearch');

    const paginationList = document.getElementById('peoplePaginationList');

    const perPageSelect = document.getElementById('peoplePerPage');

    const orgChartTab = document.getElementById('people-org-chart-tab');



    if (!document.getElementById('peopleSummaryBody')) {

        return;

    }



    bindOrgChartInteractions('peopleOrgChartRoot');

    await loadSummary();

    activateTabFromHash();



    searchInput?.addEventListener('input', () => {

        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => loadSummary(1), 350);

    });



    bindPagination(paginationList, loadSummary);

    bindPerPageSelect(perPageSelect, () => loadSummary(1));



    orgChartTab?.addEventListener('shown.bs.tab', () => {

        window.location.hash = 'org-chart';

        setSidebarPeopleActive('org-chart');

        loadPeopleOrgChart();

    });



    document.getElementById('people-summary-tab')?.addEventListener('shown.bs.tab', () => {

        if (window.location.hash === '#org-chart') {

            history.replaceState(null, '', window.location.pathname);

        }



        setSidebarPeopleActive('summary');

    });



    if (document.getElementById('peopleOrgChartPane')?.classList.contains('active')) {

        await loadPeopleOrgChart();

    }

});


