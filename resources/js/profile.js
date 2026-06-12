import { Modal, Tab } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { applyBackendErrors, bindUploadProgress, setFieldError, setSubmitLoading } from './form-utils';
import { initRichTextEditor } from './rich-text-editor';
import { renderReviewIconActionGroup, renderViewDocumentIconButton, renderDeleteDocumentIconButton, renderReviewIconActions } from './review-actions';

const PROFILE_TAB_HASHES = {
    work: 'profile-work-tab',
    personal: 'profile-personal-tab',
    salary: 'profile-salary-tab',
    bank: 'profile-bank-tab',
    compliances: 'profile-compliances-tab',
    documents: 'profile-documents-tab',
    other: 'profile-other-tab',
};

let profileCanEditWithoutApproval = false;
let profileCanManageSalary = false;
let profileCanManageAssets = false;
const assetDescriptionEditors = new Map();

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
let profileCanDeleteDocuments = false;

const usesSectionPayload = (section) => section && (profileCanEditWithoutApproval || section.can_resubmit);

const canSubmitProfileSection = (section) => profileCanEditWithoutApproval || !section || section.can_resubmit;

const profileSubmitLabel = (section, labels = {}) => {
    const {
        save = 'Save',
        initial = 'Submit for Approval',
        change = 'Submit Changes for Approval',
        resubmit = 'Re-submit for Approval',
    } = labels;

    if (profileCanEditWithoutApproval) {
        return save;
    }

    if (section?.status === 'approved' && section.can_resubmit) {
        return change;
    }

    if (section?.status === 'rejected') {
        return resubmit;
    }

    return initial;
};

const EMPLOYMENT_LABELS = {
    full_time: 'Full Time',
    part_time: 'Part Time',
    contract: 'Contract',
    intern: 'Intern',
};

const PROBATION_STATUS_LABELS = {
    on_probation: 'On Probation',
    confirmed: 'Confirmed',
    extended: 'Extended',
    not_applicable: 'Not Applicable',
};

const GENDER_LABELS = {
    male: 'Male',
    female: 'Female',
    other: 'Other',
};

const PAYMENT_MODE_LABELS = {
    bank_transfer: 'Bank Transfer',
    cash: 'Cash',
    cheque: 'Cheque',
};

const DOCUMENT_STATUS_LABELS = {
    pending: 'Pending Review',
    approved: 'Approved',
    rejected: 'Rejected',
};

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

    return new Date(value).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatReviewCell = (item) => {
    if (item.status === 'pending' || !item.reviewed_by?.name) {
        return '<span class="text-muted">—</span>';
    }

    const actionLabel = item.status === 'approved' ? 'Approved by' : 'Rejected by';

    return `
        <div>
            <span class="small text-muted">${actionLabel}</span>
            <div>${item.reviewed_by.name}</div>
            <div class="small text-muted">${formatDateTime(item.reviewed_at)}</div>
        </div>
    `;
};

const todayDateInputValue = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const FAMILY_DOB_MIN = '1900-01-01';

const applyFamilyDobConstraints = (input) => {
    if (!input) {
        return;
    }

    input.min = FAMILY_DOB_MIN;
    input.max = todayDateInputValue();

    if (input.value && (input.value > input.max || input.value < input.min)) {
        input.value = '';
    }
};

const isFutureDateInput = (value) => Boolean(value && value > todayDateInputValue());

const isValidTenDigitPhone = (value) => !value || /^\d{10}$/.test(value);

const isValidFamilyDob = (value) => {
    if (!value) {
        return true;
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }

    return value >= FAMILY_DOB_MIN && value <= todayDateInputValue();
};

const normalizePhoneInput = (input) => {
    if (!input) {
        return;
    }

    input.value = input.value.replace(/\D/g, '').slice(0, 10);
};

const getInitials = (name = '') => {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
};

const renderDl = (container, rows) => {
    if (!container) {
        return;
    }

    container.innerHTML = rows.map(([label, value]) => `
        <div class="profile-dl-row">
            <dt>${label}</dt>
            <dd>${value ?? '—'}</dd>
        </div>
    `).join('');
};

const statusBadge = (status, type = 'status') => {
    const label = type === 'document'
        ? (DOCUMENT_STATUS_LABELS[status] || status)
        : (status === 'active' ? 'Active' : 'Inactive');
    const className = type === 'document'
        ? `profile-status-badge profile-status-badge--${status}`
        : `profile-status-badge profile-status-badge--${status === 'active' ? 'active' : 'inactive'}`;

    return `<span class="${className}">${label}</span>`;
};

const yesNo = (value) => (value ? 'Yes' : 'No');

const setVisible = (showEl, hideEl, visible) => {
    showEl?.classList.toggle('d-none', !visible);
    hideEl?.classList.toggle('d-none', visible);
};

const activateProfileTabFromHash = () => {
    const hash = window.location.hash.replace('#', '').toLowerCase();
    const tabId = PROFILE_TAB_HASHES[hash];

    if (!tabId) {
        return;
    }

    const tabTrigger = document.getElementById(tabId);

    if (tabTrigger) {
        Tab.getOrCreateInstance(tabTrigger).show();
    }
};

const populateProfileHeader = (user, employee = null) => {
    const name = employee?.full_name || user.name;
    document.getElementById('profileDisplayName').textContent = name || '—';
    document.getElementById('profileDisplayEmail').textContent = user.email || '—';
    document.getElementById('profileDisplayRole').textContent = employee?.role?.name || user.role?.name || '—';
    document.getElementById('profileDisplayCompany').textContent = user.company?.name
        ? `Company · ${user.company.name}`
        : '';
    document.getElementById('profileAvatarInitials').textContent = getInitials(name);
};

const renderWorkTab = (employee) => {
    const departments = employee.departments?.length
        ? employee.departments.map((department) => department.name).join(', ')
        : (employee.department?.name || '—');

    renderDl(document.getElementById('profileWorkJob'), [
        ['Employee Code', employee.employee_code],
        ['Designation', employee.designation],
        ['Employment Type', EMPLOYMENT_LABELS[employee.employment_type] || employee.employment_type],
        ['Joining Date', formatDate(employee.joining_date)],
        ['Status', statusBadge(employee.status)],
    ]);

    renderDl(document.getElementById('profileWorkOrg'), [
        ['Company', employee.company?.name],
        ['Departments', departments],
        ['System Role', employee.role?.name],
        ['Reporting Manager', employee.manager?.full_name
            ? `${employee.manager.full_name}${employee.manager.designation ? ` (${employee.manager.designation})` : ''}`
            : '—'],
        ['Shift', employee.shift
            ? `${employee.shift.name} (${employee.shift.time_range || '—'})`
            : '—'],
    ]);

    renderDl(document.getElementById('profileWorkProbation'), [
        ['Probation Applicable', yesNo(employee.probation_applicable)],
        ['Period (Months)', employee.probation_applicable ? employee.probation_period_months : '—'],
        ['End Date', employee.probation_applicable ? formatDate(employee.probation_end_date) : '—'],
        ['Status', employee.probation_applicable
            ? (PROBATION_STATUS_LABELS[employee.probation_status] || employee.probation_status)
            : 'Not Applicable'],
    ]);
};

const getProfileSalaryValue = (id) => document.getElementById(id)?.value?.trim() ?? '';

const getProfileMonthlyCtc = () => {
    const annualCtc = parseFloat(getProfileSalaryValue('profile_salary_annual_ctc')) || 0;

    return annualCtc > 0 ? annualCtc / 12 : 0;
};

const getProfileHraAmount = () => getProfileMonthlyCtc() * (parseFloat(getProfileSalaryValue('profile_salary_hra_percent')) || 0) / 100;

const getProfileSpecialAllowanceAmount = () => getProfileMonthlyCtc() * (parseFloat(getProfileSalaryValue('profile_salary_special_allowance_percent')) || 0) / 100;

const calculateProfileMonthlyGross = () => {
    const basic = parseFloat(getProfileSalaryValue('profile_salary_basic_salary')) || 0;
    const fixed = ['profile_salary_conveyance_allowance', 'profile_salary_medical_allowance', 'profile_salary_other_allowance']
        .reduce((sum, id) => sum + (parseFloat(getProfileSalaryValue(id)) || 0), 0);

    return basic + getProfileHraAmount() + getProfileSpecialAllowanceAmount() + fixed;
};

const updateProfileSalaryPreview = () => {
    const annual = parseFloat(getProfileSalaryValue('profile_salary_annual_ctc')) || 0;
    const monthly = calculateProfileMonthlyGross();

    const annualEl = document.getElementById('profileSummaryAnnualCtc');
    const monthlyEl = document.getElementById('profileSummaryMonthlyGross');
    const hraEl = document.getElementById('profileHraAmountPreview');
    const specialEl = document.getElementById('profileSpecialAllowanceAmountPreview');

    if (annualEl) {
        annualEl.textContent = formatCurrency(annual);
    }

    if (monthlyEl) {
        monthlyEl.textContent = formatCurrency(monthly);
    }

    if (hraEl) {
        hraEl.textContent = formatCurrency(getProfileHraAmount());
    }

    if (specialEl) {
        specialEl.textContent = formatCurrency(getProfileSpecialAllowanceAmount());
    }
};

const buildSalaryDisplayRows = (salary = {}) => [
    ['Annual CTC', salary.annual_ctc ? formatCurrency(salary.annual_ctc) : '—'],
    ['Basic Salary', salary.basic_salary ? formatCurrency(salary.basic_salary) : '—'],
    ['HRA', salary.hra ? `${formatCurrency(salary.hra)} (${salary.hra_percent || 0}% of monthly CTC)` : '—'],
    ['Special Allowance', salary.special_allowance ? `${formatCurrency(salary.special_allowance)} (${salary.special_allowance_percent || 0}% of monthly CTC)` : '—'],
    ['Conveyance', salary.conveyance_allowance ? formatCurrency(salary.conveyance_allowance) : '—'],
    ['Medical', salary.medical_allowance ? formatCurrency(salary.medical_allowance) : '—'],
    ['Other Allowance', salary.other_allowance ? formatCurrency(salary.other_allowance) : '—'],
    ['Monthly Gross', salary.monthly_gross ? formatCurrency(salary.monthly_gross) : '—'],
    ['Effective From', formatDate(salary.salary_effective_from)],
    ['PF Applicable', yesNo(salary.pf_applicable)],
    ['ESI Applicable', yesNo(salary.esi_applicable)],
    ['Professional Tax', yesNo(salary.professional_tax_applicable)],
];

const fillProfileSalaryForm = (salary = {}) => {
    const set = (id, value) => {
        const input = document.getElementById(id);
        if (input) {
            input.value = value ?? '';
        }
    };

    set('profile_salary_annual_ctc', salary.annual_ctc ?? '');
    set('profile_salary_basic_salary', salary.basic_salary ?? '');
    set('profile_salary_hra_percent', salary.hra_percent ?? 40);
    set('profile_salary_special_allowance_percent', salary.special_allowance_percent ?? 0);
    set('profile_salary_conveyance_allowance', salary.conveyance_allowance ?? 0);
    set('profile_salary_medical_allowance', salary.medical_allowance ?? 0);
    set('profile_salary_other_allowance', salary.other_allowance ?? 0);
    set('profile_salary_effective_from', salary.salary_effective_from ?? '');

    const pf = document.getElementById('profile_salary_pf_applicable');
    const esi = document.getElementById('profile_salary_esi_applicable');
    const pt = document.getElementById('profile_salary_professional_tax_applicable');

    if (pf) {
        pf.checked = salary.pf_applicable !== false;
    }

    if (esi) {
        esi.checked = Boolean(salary.esi_applicable);
    }

    if (pt) {
        pt.checked = salary.professional_tax_applicable !== false;
    }

    updateProfileSalaryPreview();
};

const renderSalaryRevisionsTable = (revisions = []) => {
    const tableBody = document.getElementById('profileSalaryRevisionsBody');

    if (!tableBody) {
        return;
    }

    if (revisions.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No previous revisions.</td></tr>';
        return;
    }

    tableBody.innerHTML = revisions.map((revision) => `
        <tr>
            <td>${formatDateTime(revision.revised_at)}</td>
            <td>${formatCurrency(revision.annual_ctc)}</td>
            <td>${formatCurrency(revision.monthly_gross)}</td>
            <td>${formatDate(revision.salary_effective_from)}</td>
            <td>${revision.revised_by?.name || '—'}</td>
            <td>${revision.revision_notes || '<span class="text-muted">—</span>'}</td>
        </tr>
    `).join('');
};

const renderSalaryTab = (employee) => {
    const salary = employee.salary || {};
    const hasSalary = Boolean(salary.annual_ctc);
    const desc = document.getElementById('profileSalaryTabDesc');
    const manageSection = document.getElementById('profileSalaryManageSection');

    setVisible(
        document.getElementById('profileSalaryContent'),
        document.getElementById('profileSalaryEmpty'),
        hasSalary || profileCanManageSalary,
    );

    if (!hasSalary && !profileCanManageSalary) {
        return;
    }

    if (desc) {
        desc.textContent = profileCanManageSalary
            ? 'View current compensation or update and revise salary for this employee.'
            : 'View your current compensation structure as recorded by HR.';
    }

    renderDl(document.getElementById('profileSalaryDisplay'), buildSalaryDisplayRows(salary));
    updateProfileSalaryPreview();
    document.getElementById('profileSummaryAnnualCtc').textContent = formatCurrency(salary.annual_ctc || 0);
    document.getElementById('profileSummaryMonthlyGross').textContent = formatCurrency(salary.monthly_gross || 0);

    manageSection?.classList.toggle('d-none', !profileCanManageSalary);

    if (profileCanManageSalary) {
        fillProfileSalaryForm(salary);
        renderSalaryRevisionsTable(employee.salary_revisions || []);
    }
};

const renderAssetStatus = (isAssigned) => {
    const label = isAssigned ? 'Available' : 'Not Available';
    const className = isAssigned ? 'profile-asset-status--available' : 'profile-asset-status--unavailable';

    return `<span class="profile-asset-status ${className}">${label}</span>`;
};

const buildProfileAssetDescriptionView = (asset) => {
    if (!asset.is_assigned || !asset.description) {
        return '';
    }

    return `
        <div class="profile-asset-details-box">
            <div class="profile-asset-details-box-label">Details given</div>
            <div class="profile-asset-details-box-body">${asset.description}</div>
        </div>
    `;
};

const buildProfileAssetListItem = (asset) => `
    <li class="profile-asset-item">
        <div class="profile-asset-item-header">
            <div class="profile-asset-item-title">
                <span class="profile-asset-icon" aria-hidden="true">i</span>
                <span class="profile-asset-name">${escapeHtml(asset.name)}</span>
            </div>
            ${renderAssetStatus(asset.is_assigned)}
        </div>
        ${buildProfileAssetDescriptionView(asset)}
    </li>
`;

const buildProfileAssetEditItem = (asset) => `
    <li class="profile-asset-edit-item">
        <div class="profile-asset-edit-item-head">
            <div class="form-check mb-0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="profile_asset_${asset.asset_type_id}"
                    data-asset-type-id="${asset.asset_type_id}"
                    ${asset.is_assigned ? 'checked' : ''}
                >
                <label class="form-check-label" for="profile_asset_${asset.asset_type_id}">${escapeHtml(asset.name)}</label>
            </div>
        </div>
        <div class="profile-asset-description-wrap ${asset.is_assigned ? '' : 'd-none'}" data-asset-description-wrap="${asset.asset_type_id}">
            <label class="form-label profile-asset-description-field-label" for="profile_asset_description_${asset.asset_type_id}">
                Details of what has been given
            </label>
            <div id="profile_asset_editor_${asset.asset_type_id}" class="profile-asset-editor"></div>
            <textarea id="profile_asset_description_${asset.asset_type_id}" class="d-none">${escapeHtml(asset.description || '')}</textarea>
        </div>
    </li>
`;

const toggleAssetDescriptionField = (assetTypeId, visible) => {
    document
        .querySelector(`[data-asset-description-wrap="${assetTypeId}"]`)
        ?.classList.toggle('d-none', !visible);
};

const initAssetDescriptionEditors = (assets) => {
    assetDescriptionEditors.clear();

    assets.forEach((asset) => {
        const container = document.getElementById(`profile_asset_editor_${asset.asset_type_id}`);
        const textarea = document.getElementById(`profile_asset_description_${asset.asset_type_id}`);

        if (!container || !textarea) {
            return;
        }

        const editor = initRichTextEditor({
            container,
            textarea,
            placeholder: 'Serial number, model, accessories, condition, handover notes...',
        });

        if (editor) {
            assetDescriptionEditors.set(asset.asset_type_id, editor);
        }
    });
};

const bindAssetAssignmentEditors = () => {
    document.querySelectorAll('#profileAssetEditList [data-asset-type-id]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            toggleAssetDescriptionField(Number(checkbox.dataset.assetTypeId), checkbox.checked);
        });
    });
};

const renderOtherTab = (employee) => {
    const assets = employee.assets || [];
    const hasAssets = assets.length > 0;
    const desc = document.getElementById('profileOtherTabDesc');
    const manageSection = document.getElementById('profileOtherManageSection');
    const assetList = document.getElementById('profileAssetList');
    const assetEditList = document.getElementById('profileAssetEditList');

    setVisible(
        document.getElementById('profileOtherContent'),
        document.getElementById('profileOtherEmpty'),
        hasAssets || profileCanManageAssets,
    );

    if (!hasAssets && !profileCanManageAssets) {
        return;
    }

    if (desc) {
        desc.textContent = profileCanManageAssets
            ? 'View or update assets assigned to this employee.'
            : 'View assets assigned to you by HR.';
    }

    if (assetList) {
        assetList.innerHTML = hasAssets
            ? assets.map((asset) => buildProfileAssetListItem(asset)).join('')
            : '<li class="text-muted small">No asset types configured yet.</li>';
    }

    manageSection?.classList.toggle('d-none', !profileCanManageAssets);

    if (profileCanManageAssets && assetEditList) {
        assetEditList.innerHTML = hasAssets
            ? assets.map((asset) => buildProfileAssetEditItem(asset)).join('')
            : '<li class="text-muted small">Add asset types from Masters → Assets first.</li>';

        if (hasAssets) {
            initAssetDescriptionEditors(assets);
            bindAssetAssignmentEditors();
        }
    }
};

const collectProfileAssetsPayload = () => {
    const checkboxes = document.querySelectorAll('#profileAssetEditList [data-asset-type-id]');

    return Array.from(checkboxes).map((checkbox) => {
        const assetTypeId = Number(checkbox.dataset.assetTypeId);
        const editor = assetDescriptionEditors.get(assetTypeId);
        editor?.sync?.();

        const textarea = document.getElementById(`profile_asset_description_${assetTypeId}`);

        return {
            asset_type_id: assetTypeId,
            is_assigned: checkbox.checked,
            description: checkbox.checked ? (textarea?.value.trim() || null) : null,
        };
    });
};

const collectProfileSalaryPayload = () => ({
    annual_ctc: parseFloat(getProfileSalaryValue('profile_salary_annual_ctc')) || 0,
    basic_salary: parseFloat(getProfileSalaryValue('profile_salary_basic_salary')) || 0,
    hra_percent: parseFloat(getProfileSalaryValue('profile_salary_hra_percent')) || 0,
    special_allowance_percent: parseFloat(getProfileSalaryValue('profile_salary_special_allowance_percent')) || 0,
    conveyance_allowance: parseFloat(getProfileSalaryValue('profile_salary_conveyance_allowance')) || 0,
    medical_allowance: parseFloat(getProfileSalaryValue('profile_salary_medical_allowance')) || 0,
    other_allowance: parseFloat(getProfileSalaryValue('profile_salary_other_allowance')) || 0,
    pf_applicable: document.getElementById('profile_salary_pf_applicable')?.checked ?? false,
    esi_applicable: document.getElementById('profile_salary_esi_applicable')?.checked ?? false,
    professional_tax_applicable: document.getElementById('profile_salary_professional_tax_applicable')?.checked ?? true,
    salary_effective_from: getProfileSalaryValue('profile_salary_effective_from'),
    revision_notes: getProfileSalaryValue('profile_salary_revision_notes') || null,
});

const PERSONAL_SECTION_LABELS = {
    family: 'Family Details',
    address: 'Address',
    emergency_contact: 'Emergency Contact',
};

const getPersonalSectionByType = (sections, type) => (sections || []).find((section) => section.section_type === type);

const renderPersonalDisplayInfo = (employee) => {
    renderDl(document.getElementById('profilePersonalDisplayInfo'), [
        ['Full Name', employee.full_name],
        ['Work Email', employee.email],
        ['Personal Email', employee.personal_email || '—'],
        ['Mobile Number', employee.phone || '—'],
        ['Gender', GENDER_LABELS[employee.gender] || employee.gender || '—'],
        ['Date of Birth', formatDate(employee.date_of_birth)],
    ]);
};

const renderSectionStatusBadge = (element, section) => {
    if (!element) {
        return;
    }

    if (!section?.status) {
        element.innerHTML = '<span class="text-muted small">Not submitted</span>';
        return;
    }

    element.innerHTML = statusBadge(section.status, 'document');
};

const renderSectionReviewNotes = (container, section) => {
    if (!container) {
        return;
    }

    if (section?.status === 'rejected' && section.notes) {
        container.innerHTML = `<div class="alert alert-danger py-2 small mb-3">Review notes: ${section.notes}</div>`;
        return;
    }

    container.innerHTML = '';
};

const resetFamilyForm = (member = null) => {
    const list = document.getElementById('profileFamilyMembersList');
    const resubmitInput = document.getElementById('profileFamilyResubmitId');
    const cancelBtn = document.getElementById('profileFamilyResubmitCancel');
    const submitBtn = document.getElementById('profileFamilySectionSubmit');
    const addBtn = document.getElementById('profileAddFamilyMember');
    const hint = document.getElementById('profileFamilySectionHint');

    if (!list) {
        return;
    }

    list.innerHTML = '';
    const row = createFamilyMemberRow(member || {});

    if (row) {
        list.appendChild(row);
    }

    updateFamilyMemberRemoveButtons();

    if (resubmitInput) {
        resubmitInput.value = member?.id ? String(member.id) : '';
    }

    const isResubmit = Boolean(member?.id);

    cancelBtn?.classList.toggle('d-none', !isResubmit);
    addBtn?.classList.toggle('d-none', isResubmit);

    if (submitBtn) {
        submitBtn.textContent = profileCanEditWithoutApproval
            ? (isResubmit ? 'Save Changes' : 'Save Family Member')
            : (isResubmit
                ? (member.status === 'approved' ? 'Submit Changes for Approval' : 'Re-submit for Approval')
                : 'Submit for Approval');
    }

    if (hint) {
        hint.textContent = profileCanEditWithoutApproval
            ? (isResubmit
                ? 'Update this family member and save directly for the employee.'
                : 'Add family members for this employee. Changes save immediately.')
            : (isResubmit
                ? 'Update this relation and submit for HR approval again.'
                : 'Add new family members below. Submitted relations appear in the listing above only.');
    }
};

const setPersonalSectionFormState = (form, submitBtn, section) => {
    const isLocked = !profileCanEditWithoutApproval && Boolean(section?.is_locked);
    const canSubmit = canSubmitProfileSection(section);

    form?.classList.toggle('d-none', !canSubmit);
    form?.querySelectorAll('input, select, textarea, button[type="button"]').forEach((element) => {
        if (element.id === 'profileAddFamilyMember') {
            element.disabled = isLocked || !canSubmit;
            return;
        }

        element.disabled = isLocked || !canSubmit;
    });

    if (submitBtn) {
        submitBtn.disabled = isLocked || !canSubmit;
        submitBtn.textContent = profileSubmitLabel(section);
    }
};

const updateFamilyMemberRemoveButtons = () => {
    document.querySelectorAll('[data-family-member-row]').forEach((row, index) => {
        const removeBtn = row.querySelector('[data-remove-family-member]');

        if (removeBtn) {
            removeBtn.classList.toggle('d-none', index === 0);
        }
    });
};

const createFamilyMemberRow = (member = {}) => {
    const template = document.getElementById('profileFamilyMemberRowTemplate');
    const row = template?.content.firstElementChild?.cloneNode(true);

    if (!row) {
        return null;
    }

    row.querySelector('[data-family-name]').value = member.name || '';
    row.querySelector('[data-family-relation]').value = member.relation || '';
    row.querySelector('[data-family-phone]').value = member.phone || '';
    row.querySelector('[data-family-dob]').value = member.date_of_birth || '';
    applyFamilyDobConstraints(row.querySelector('[data-family-dob]'));

    return row;
};

const collectFamilyMembersFromForm = () => {
    const resubmitId = document.getElementById('profileFamilyResubmitId')?.value;
    const members = Array.from(document.querySelectorAll('[data-family-member-row]')).map((row) => ({
        name: row.querySelector('[data-family-name]')?.value.trim() || '',
        relation: row.querySelector('[data-family-relation]')?.value.trim() || '',
        phone: (() => {
            const value = row.querySelector('[data-family-phone]')?.value.trim() || '';
            return value === '' ? null : value;
        })(),
        date_of_birth: row.querySelector('[data-family-dob]')?.value || null,
    })).filter((member) => member.name || member.relation);

    if (resubmitId) {
        return members.map((member) => ({
            ...member,
            id: Number(resubmitId),
        }));
    }

    return members;
};

const formatAddressBlock = (address = {}) => [
    address.address_line_1,
    address.address_line_2,
    address.city,
    address.state,
    address.postal_code,
    address.country,
].filter(Boolean).join(', ') || '—';

const getApprovedFamilyMembers = (members = []) => members.filter((member) => member.status === 'approved');

const renderFamilyMembersTable = (employee) => {
    const tableBody = document.getElementById('profileFamilyMembersTableBody');
    const members = employee.family_members || [];

    if (!tableBody) {
        return;
    }

    if (members.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No family members submitted yet.</td></tr>';
        return;
    }

    tableBody.innerHTML = members.map((member) => `
        <tr>
            <td>${member.name}</td>
            <td>${member.relation}</td>
            <td>${member.phone || '—'}</td>
            <td>${formatDate(member.date_of_birth)}</td>
            <td>${statusBadge(member.status, 'document')}</td>
            <td>${member.status === 'rejected' && member.notes
        ? `<span class="text-danger small">${member.notes}</span>`
        : '<span class="text-muted">—</span>'}</td>
            <td>${formatDateTime(member.submitted_at)}</td>
            <td>${formatReviewCell(member)}</td>
            <td class="text-end">
                ${member.can_resubmit
        ? `<button type="button" class="btn btn-sm btn-outline-primary" data-resubmit-family-member="${member.id}">${member.status === 'approved' ? 'Change' : 'Re-submit'}</button>`
        : '<span class="text-muted">—</span>'}
            </td>
        </tr>
    `).join('');
};

const renderPendingFamilyMemberApprovals = (members = []) => {
    const section = document.getElementById('profileFamilyApprovalsSection');
    const tableBody = document.getElementById('profilePendingFamilyBody');
    const countBadge = document.getElementById('profilePendingFamilyCount');

    if (!section || !tableBody) {
        return;
    }

    section.classList.toggle('d-none', members.length === 0);
    countBadge.textContent = `${members.length} pending`;

    if (members.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No pending family members.</td></tr>';
        return;
    }

    tableBody.innerHTML = members.map((member) => `
        <tr>
            <td>
                <strong>${member.employee?.full_name || '—'}</strong>
                <div class="small text-muted">${member.employee?.employee_code || ''}</div>
            </td>
            <td>${member.name || '—'}</td>
            <td>${member.relation || '—'}</td>
            <td>${member.submitted_by?.name || '—'}${member.submitted_by?.role ? `<div class="small text-muted">${member.submitted_by.role}</div>` : ''}</td>
            <td>${formatDateTime(member.submitted_at)}</td>
            <td class="text-end">${renderReviewIconActionGroup('data-approve-family-member', 'data-reject-family-member', member.id)}</td>
        </tr>
    `).join('');
};

const loadPendingFamilyMemberApprovals = async (canReview) => {
    if (!canReview) {
        renderPendingFamilyMemberApprovals([]);
        return;
    }

    try {
        const { data } = await api.get('/employee-family-members/pending');
        renderPendingFamilyMemberApprovals(data.data.family_members || []);
    } catch (error) {
        console.error(getErrorMessage(error));
    }
};

const renderAddressDetailsView = (employee, section) => {
    const container = document.getElementById('profileAddressApprovedView');
    let permanent = null;
    let temporary = null;
    let sameAsPermanent = false;
    let heading = '';

    if (!container) {
        return;
    }

    if (section?.status === 'pending') {
        permanent = section.payload?.permanent || {};
        temporary = section.payload?.temporary || {};
        sameAsPermanent = Boolean(section.payload?.same_as_permanent);
        heading = 'Submitted for Review';
    } else if (section?.status === 'rejected') {
        permanent = section.payload?.permanent || {};
        temporary = section.payload?.temporary || {};
        sameAsPermanent = Boolean(section.payload?.same_as_permanent);
        heading = 'Rejected Submission';
    } else if (employee.address_line_1) {
        permanent = {
            address_line_1: employee.address_line_1,
            address_line_2: employee.address_line_2,
            city: employee.city,
            state: employee.state,
            country: employee.country,
            postal_code: employee.postal_code,
        };
        temporary = {
            address_line_1: employee.temp_address_line_1,
            address_line_2: employee.temp_address_line_2,
            city: employee.temp_city,
            state: employee.temp_state,
            country: employee.temp_country,
            postal_code: employee.temp_postal_code,
        };
        sameAsPermanent = !employee.temp_address_line_1 && !employee.temp_city && !employee.temp_state;
        heading = 'Approved Details';
    }

    if (!permanent?.address_line_1) {
        container.innerHTML = '<p class="text-muted small mb-0">No address submitted yet.</p>';
        return;
    }

    container.innerHTML = `
        <p class="small fw-semibold mb-2">${heading}</p>
        <div class="table-responsive">
            <table class="table profile-documents-table mb-0">
                <thead>
                    <tr>
                        <th>Address Type</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Permanent</td>
                        <td>${formatAddressBlock(permanent)}</td>
                    </tr>
                    <tr>
                        <td>Current</td>
                        <td>${sameAsPermanent ? 'Same as permanent address' : formatAddressBlock(temporary)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        ${section && section.status !== 'pending' ? `<div class="mt-3">${formatReviewCell(section)}</div>` : ''}
    `;
};

const fillAddressSectionForm = (section, employee) => {
    const permanent = usesSectionPayload(section)
        ? (section.payload?.permanent || {})
        : {
            address_line_1: employee.address_line_1,
            address_line_2: employee.address_line_2,
            city: employee.city,
            state: employee.state,
            country: employee.country,
            postal_code: employee.postal_code,
        };
    const temporary = usesSectionPayload(section)
        ? (section.payload?.temporary || {})
        : {
            address_line_1: employee.temp_address_line_1,
            address_line_2: employee.temp_address_line_2,
            city: employee.temp_city,
            state: employee.temp_state,
            country: employee.temp_country,
            postal_code: employee.temp_postal_code,
        };
    const sameAsPermanent = usesSectionPayload(section)
        ? Boolean(section.payload?.same_as_permanent)
        : !employee.temp_address_line_1 && !employee.temp_city && !employee.temp_state;

    const set = (id, value) => {
        const input = document.getElementById(id);
        if (input) {
            input.value = value ?? '';
        }
    };

    set('profile_permanent_address_line_1', permanent.address_line_1);
    set('profile_permanent_address_line_2', permanent.address_line_2);
    set('profile_permanent_city', permanent.city);
    set('profile_permanent_state', permanent.state);
    set('profile_permanent_country', permanent.country || 'India');
    set('profile_permanent_postal_code', permanent.postal_code);
    set('profile_temp_address_line_1', temporary.address_line_1);
    set('profile_temp_address_line_2', temporary.address_line_2);
    set('profile_temp_city', temporary.city);
    set('profile_temp_state', temporary.state);
    set('profile_temp_country', temporary.country || 'India');
    set('profile_temp_postal_code', temporary.postal_code);

    const sameCheckbox = document.getElementById('profile_same_as_permanent');
    if (sameCheckbox) {
        sameCheckbox.checked = sameAsPermanent;
    }

    toggleTemporaryAddressFields();
};

const toggleTemporaryAddressFields = () => {
    const sameAsPermanent = document.getElementById('profile_same_as_permanent')?.checked;
    const fields = document.getElementById('profileTemporaryAddressFields');

    fields?.querySelectorAll('input').forEach((input) => {
        input.disabled = Boolean(sameAsPermanent);
    });

    if (sameAsPermanent) {
        fields?.classList.add('opacity-50');
    } else {
        fields?.classList.remove('opacity-50');
    }
};

const renderEmergencyApprovedView = (employee, section = null) => {
    const container = document.getElementById('profileEmergencyApprovedView');

    if (!container) {
        return;
    }

    if (!employee.emergency_contact_name && !section) {
        container.innerHTML = '<p class="text-muted small mb-0">No approved emergency contact yet.</p>';
        return;
    }

    if (section && section.status !== 'approved') {
        const contact = getEmergencyContactDetails(section, employee);

        container.innerHTML = `
            <p class="small fw-semibold mb-2">${section.status === 'pending' ? 'Submitted for Review' : 'Rejected Submission'}</p>
            <dl class="profile-dl mb-0">
                <div class="profile-dl-row"><dt>Name</dt><dd>${contact.name || '—'}</dd></div>
                <div class="profile-dl-row"><dt>Relation</dt><dd>${contact.relation || '—'}</dd></div>
                <div class="profile-dl-row"><dt>Mobile</dt><dd>${contact.phone || '—'}</dd></div>
            </dl>
            ${section.status !== 'pending' ? `<div class="mt-3">${formatReviewCell(section)}</div>` : ''}
        `;
        return;
    }

    container.innerHTML = `
        <dl class="profile-dl">
            <div class="profile-dl-row"><dt>Name</dt><dd>${employee.emergency_contact_name}</dd></div>
            <div class="profile-dl-row"><dt>Relation</dt><dd>${employee.emergency_contact_relation || '—'}</dd></div>
            <div class="profile-dl-row"><dt>Mobile</dt><dd>${employee.emergency_contact_phone || '—'}</dd></div>
        </dl>
        ${section && section.status === 'approved' ? `<div class="mt-3">${formatReviewCell(section)}</div>` : ''}
    `;
};

const getEmergencyContactDetails = (section, employee) => {
    if (section?.payload?.name) {
        return {
            name: section.payload.name,
            relation: section.payload.relation || '',
            phone: section.payload.phone || '',
        };
    }

    if (section?.payload?.family_member_id) {
        const member = (employee.family_members || []).find(
            (item) => Number(item.id) === Number(section.payload.family_member_id),
        );

        if (member) {
            return {
                name: member.name,
                relation: member.relation || '',
                phone: member.phone || '',
            };
        }
    }

    return {
        name: employee.emergency_contact_name || '',
        relation: employee.emergency_contact_relation || '',
        phone: employee.emergency_contact_phone || '',
    };
};

const fillEmergencySectionForm = (section, employee) => {
    const contact = usesSectionPayload(section)
        ? getEmergencyContactDetails(section, employee)
        : {
            name: employee.emergency_contact_name || '',
            relation: employee.emergency_contact_relation || '',
            phone: employee.emergency_contact_phone || '',
        };

    const set = (id, value) => {
        const input = document.getElementById(id);
        if (input) {
            input.value = value ?? '';
        }
    };

    set('profile_emergency_contact_name', contact.name);
    set('profile_emergency_contact_relation', contact.relation);
    set('profile_emergency_contact_phone', contact.phone);
};

const renderPersonalTab = (employee) => {
    const sections = employee.personal_sections || [];
    const addressSection = getPersonalSectionByType(sections, 'address');
    const emergencySection = getPersonalSectionByType(sections, 'emergency_contact');

    renderPersonalDisplayInfo(employee);
    renderFamilyMembersTable(employee);
    resetFamilyForm();

    renderSectionStatusBadge(document.getElementById('profileAddressSectionStatus'), addressSection);
    renderSectionReviewNotes(document.getElementById('profileAddressSectionNotes'), addressSection);
    renderAddressDetailsView(employee, addressSection);
    fillAddressSectionForm(addressSection, employee);
    setPersonalSectionFormState(
        document.getElementById('profileAddressSectionForm'),
        document.getElementById('profileAddressSectionSubmit'),
        addressSection,
    );

    renderSectionStatusBadge(document.getElementById('profileEmergencySectionStatus'), emergencySection);
    renderSectionReviewNotes(document.getElementById('profileEmergencySectionNotes'), emergencySection);
    renderEmergencyApprovedView(employee, emergencySection);
    fillEmergencySectionForm(emergencySection, employee);

    setPersonalSectionFormState(
        document.getElementById('profileEmergencySectionForm'),
        document.getElementById('profileEmergencySectionSubmit'),
        emergencySection,
    );

    document.getElementById('profileSubmissionPolicyAlert')?.classList.toggle('d-none', profileCanEditWithoutApproval);
};

const renderPendingPersonalSectionApprovals = (sections = []) => {
    const section = document.getElementById('profilePersonalApprovalsSection');
    const tableBody = document.getElementById('profilePendingPersonalBody');
    const countBadge = document.getElementById('profilePendingPersonalCount');

    if (!section || !tableBody) {
        return;
    }

    section.classList.toggle('d-none', sections.length === 0);
    countBadge.textContent = `${sections.length} pending`;

    if (sections.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No pending personal sections.</td></tr>';
        return;
    }

    tableBody.innerHTML = sections.map((item) => `
        <tr>
            <td>
                <strong>${item.employee?.full_name || '—'}</strong>
                <div class="small text-muted">${item.employee?.employee_code || ''}</div>
            </td>
            <td>${item.section_label || PERSONAL_SECTION_LABELS[item.section_type] || item.section_type}</td>
            <td>${item.summary || '—'}</td>
            <td>${item.submitted_by?.name || '—'}${item.submitted_by?.role ? `<div class="small text-muted">${item.submitted_by.role}</div>` : ''}</td>
            <td>${formatDateTime(item.submitted_at)}</td>
            <td class="text-end">${renderReviewIconActionGroup('data-approve-personal-section', 'data-reject-personal-section', item.id)}</td>
        </tr>
    `).join('');
};

const loadPendingPersonalSectionApprovals = async (canReview) => {
    if (!canReview) {
        renderPendingPersonalSectionApprovals([]);
        return;
    }

    try {
        const { data } = await api.get('/employee-personal-sections/pending');
        renderPendingPersonalSectionApprovals(data.data.personal_sections || []);
    } catch (error) {
        console.error(getErrorMessage(error));
    }
};

const collectAddressPayload = () => {
    const sameAsPermanent = document.getElementById('profile_same_as_permanent')?.checked || false;
    const get = (id) => document.getElementById(id)?.value.trim() || null;

    return {
        same_as_permanent: sameAsPermanent,
        permanent: {
            address_line_1: get('profile_permanent_address_line_1'),
            address_line_2: get('profile_permanent_address_line_2'),
            city: get('profile_permanent_city'),
            state: get('profile_permanent_state'),
            country: get('profile_permanent_country'),
            postal_code: get('profile_permanent_postal_code'),
        },
        temporary: sameAsPermanent ? {} : {
            address_line_1: get('profile_temp_address_line_1'),
            address_line_2: get('profile_temp_address_line_2'),
            city: get('profile_temp_city'),
            state: get('profile_temp_state'),
            country: get('profile_temp_country'),
            postal_code: get('profile_temp_postal_code'),
        },
    };
};

const setContactInfoFormAccess = (canUpdateContactInfo) => {
    const emailInput = document.querySelector('#profileForm #email');

    if (emailInput) {
        emailInput.readOnly = !canUpdateContactInfo;
        emailInput.classList.toggle('bg-light', !canUpdateContactInfo);
    }
};

const PAYMENT_MODE_OPTIONS = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cash', label: 'Cash' },
    { value: 'cheque', label: 'Cheque' },
];

const toggleBankFields = () => {
    const mode = document.getElementById('profile_payment_mode')?.value;
    const fields = document.getElementById('profileBankFields');
    const note = document.getElementById('profileBankNonTransferNote');
    const bankFieldIds = [
        'profile_bank_name',
        'profile_bank_branch',
        'profile_bank_address',
        'profile_account_holder_name',
        'profile_account_number',
        'profile_ifsc_code',
    ];

    if (fields) {
        fields.classList.toggle('d-none', mode !== 'bank_transfer');
    }

    if (note) {
        note.classList.toggle('d-none', !mode || mode === 'bank_transfer');
    }

    if (mode !== 'bank_transfer') {
        bankFieldIds.forEach((id) => {
            const input = document.getElementById(id);
            if (input) {
                input.value = '';
            }
        });
    }
};

const getPaymentMethodByMode = (methods, mode) => methods.find((method) => method.payment_mode === mode);

const getSubmittablePaymentModes = (methods) => {
    if (profileCanEditWithoutApproval) {
        return PAYMENT_MODE_OPTIONS;
    }

    return PAYMENT_MODE_OPTIONS.filter((option) => {
        const existing = getPaymentMethodByMode(methods, option.value);
        return !existing || existing.can_resubmit;
    });
};

const paymentMethodOptionSuffix = (existing) => {
    if (!existing?.can_resubmit) {
        return '';
    }

    return existing.status === 'approved' ? ' (Change)' : ' (Re-submit)';
};

const fillPaymentMethodFormFields = (method) => {
    const set = (id, value) => {
        const input = document.getElementById(id);
        if (input) {
            input.value = value ?? '';
        }
    };

    if (!method || method.payment_mode !== 'bank_transfer') {
        return;
    }

    set('profile_bank_name', method.bank_name);
    set('profile_bank_branch', method.bank_branch);
    set('profile_bank_address', method.bank_address);
    set('profile_account_holder_name', method.account_holder_name);
    set('profile_account_number', method.account_number);
    set('profile_ifsc_code', method.ifsc_code);
};

const handlePaymentModeChange = (methods = []) => {
    toggleBankFields();

    const mode = document.getElementById('profile_payment_mode')?.value;
    const existing = getPaymentMethodByMode(methods, mode);

    if (existing && (existing.can_resubmit || profileCanEditWithoutApproval)) {
        fillPaymentMethodFormFields(existing);
    }
};

let cachedPaymentMethods = [];

const formatPaymentMethodDetails = (method) => {
    if (method.payment_mode !== 'bank_transfer') {
        return '<span class="text-muted">No bank account required</span>';
    }

    const parts = [
        method.bank_name,
        method.bank_branch,
        method.bank_address,
        method.account_holder_name,
        method.account_number ? `A/C ${method.account_number}` : null,
        method.ifsc_code,
    ].filter(Boolean);

    return parts.length ? parts.join(' · ') : '—';
};

const renderPaymentMethods = (employee) => {
    const select = document.getElementById('profile_payment_mode');
    const uploadForm = document.getElementById('profilePaymentMethodForm');
    const uploadHint = document.getElementById('profilePaymentMethodUploadHint');
    const tableBody = document.getElementById('profilePaymentMethodsTableBody');
    const requiredWrap = document.getElementById('profileRequiredPaymentMethods');
    const methods = employee.payment_methods || [];
    cachedPaymentMethods = methods;
    const submittableModes = getSubmittablePaymentModes(methods);

    if (select) {
        if (submittableModes.length === 0) {
            select.innerHTML = '<option value="">No payment options available to submit</option>';
            select.disabled = true;
        } else {
            select.disabled = false;
            select.innerHTML = '<option value="">Select payment option</option>' + submittableModes
                .map((option) => {
                    const existing = getPaymentMethodByMode(methods, option.value);
                    return `<option value="${option.value}">${option.label}${paymentMethodOptionSuffix(existing)}</option>`;
                })
                .join('');
        }
    }

    if (uploadForm) {
        uploadForm.classList.toggle('d-none', submittableModes.length === 0);
    }

    if (uploadHint) {
        uploadHint.textContent = profileCanEditWithoutApproval
            ? 'Select a payment option and save bank details directly for this employee.'
            : (submittableModes.length
                ? 'Select a payment option to submit, change approved details, or re-submit a rejected option.'
                : 'All payment options are currently pending HR review.');
    }

    toggleBankFields();

    if (tableBody) {
        if (methods.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No payment options submitted yet.</td></tr>';
        } else {
            tableBody.innerHTML = methods.map((method) => `
                <tr>
                    <td>${PAYMENT_MODE_LABELS[method.payment_mode] || method.payment_mode}</td>
                    <td>${formatPaymentMethodDetails(method)}</td>
                    <td>${statusBadge(method.status, 'document')}</td>
                    <td>${method.status === 'rejected' && method.notes
                        ? `<span class="text-danger small">${method.notes}</span>`
                        : '<span class="text-muted">—</span>'}</td>
                    <td>${formatDateTime(method.submitted_at)}</td>
                    <td>${formatReviewCell(method)}</td>
                    <td class="text-end">
                        ${method.can_resubmit
        ? `<button type="button" class="btn btn-sm btn-outline-primary" data-change-payment-method="${method.payment_mode}">${method.status === 'approved' ? 'Change' : 'Re-submit'}</button>`
        : '<span class="text-muted">—</span>'}
                    </td>
                </tr>
            `).join('');
        }
    }

    const paymentSubmitBtn = document.getElementById('profilePaymentMethodSubmit');
    if (paymentSubmitBtn) {
        paymentSubmitBtn.textContent = profileCanEditWithoutApproval ? 'Save' : 'Submit for Approval';
    }

    if (requiredWrap) {
        requiredWrap.innerHTML = PAYMENT_MODE_OPTIONS.map((option) => {
            const existing = getPaymentMethodByMode(methods, option.value);
            let className = '';
            let icon = '○';
            let suffix = '';

            if (existing) {
                if (existing.status === 'approved') {
                    className = 'profile-required-doc--uploaded';
                    icon = '✓';
                    suffix = ' (Approved — can be changed)';
                } else if (existing.status === 'pending') {
                    className = 'profile-required-doc--missing';
                    icon = '⏳';
                    suffix = ' (Pending)';
                } else if (existing.status === 'rejected') {
                    className = 'profile-required-doc--missing';
                    icon = '✕';
                    suffix = ' (Rejected — re-submit required)';
                }
            }

            return `<span class="profile-required-doc ${className}">${icon} ${option.label}${suffix}</span>`;
        }).join('');
    }
};

const renderPendingPaymentMethodApprovals = (methods = []) => {
    const section = document.getElementById('profileBankApprovalsSection');
    const tableBody = document.getElementById('profilePendingBankBody');
    const countBadge = document.getElementById('profilePendingBankCount');

    if (!section || !tableBody) {
        return;
    }

    section.classList.toggle('d-none', methods.length === 0);
    countBadge.textContent = `${methods.length} pending`;

    if (methods.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No pending payment options.</td></tr>';
        return;
    }

    tableBody.innerHTML = methods.map((method) => `
        <tr>
            <td>
                <strong>${method.employee?.full_name || '—'}</strong>
                <div class="small text-muted">${method.employee?.employee_code || ''}</div>
            </td>
            <td>${PAYMENT_MODE_LABELS[method.payment_mode] || method.payment_mode || '—'}</td>
            <td>${formatPaymentMethodDetails(method)}</td>
            <td>${method.submitted_by?.name || '—'}${method.submitted_by?.role ? `<div class="small text-muted">${method.submitted_by.role}</div>` : ''}</td>
            <td>${formatDateTime(method.submitted_at)}</td>
            <td class="text-end">${renderReviewIconActionGroup('data-approve-payment-method', 'data-reject-payment-method', method.id)}</td>
        </tr>
    `).join('');
};

const loadPendingPaymentMethodApprovals = async (canReview) => {
    if (!canReview) {
        renderPendingPaymentMethodApprovals([]);
        return;
    }

    try {
        const { data } = await api.get('/employee-payment-methods/pending');
        renderPendingPaymentMethodApprovals(data.data.payment_methods || []);
    } catch (error) {
        console.error(getErrorMessage(error));
    }
};

const COMPLIANCE_FIELD_OPTIONS = [
    { value: 'pan', label: 'PAN Number', inputMode: 'text', maxLength: 10, uppercase: true },
    { value: 'aadhaar', label: 'Aadhaar Number', inputMode: 'numeric', maxLength: 12 },
    { value: 'uan', label: 'UAN', inputMode: 'numeric', maxLength: 12 },
    { value: 'pf', label: 'PF Number', inputMode: 'text', maxLength: 30 },
    { value: 'esi', label: 'ESI Number', inputMode: 'text', maxLength: 30 },
];

const COMPLIANCE_FIELD_LABELS = Object.fromEntries(
    COMPLIANCE_FIELD_OPTIONS.map((option) => [option.value, option.label]),
);

const getComplianceFieldOptionsForSalary = (salary = {}) => COMPLIANCE_FIELD_OPTIONS.filter((option) => {
    if (option.value === 'pf') {
        return Boolean(salary.pf_applicable);
    }

    if (option.value === 'esi') {
        return Boolean(salary.esi_applicable);
    }

    return true;
});

const getComplianceFieldByType = (fields, fieldType) => fields.find((field) => field.field_type === fieldType);

const getSubmittableComplianceFields = (fields, salary = {}) => {
    const availableOptions = getComplianceFieldOptionsForSalary(salary);

    if (profileCanEditWithoutApproval) {
        return availableOptions;
    }

    return availableOptions.filter((option) => {
        const existing = getComplianceFieldByType(fields, option.value);
        return !existing || existing.can_resubmit;
    });
};

const complianceFieldOptionSuffix = (existing) => {
    if (!existing?.can_resubmit) {
        return '';
    }

    return existing.status === 'approved' ? ' (Change)' : ' (Re-submit)';
};

const fillComplianceFieldForm = (field) => {
    const valueInput = document.getElementById('profile_compliance_value');

    if (valueInput) {
        valueInput.value = field?.value ?? '';
    }
};

const handleComplianceFieldTypeChange = (fields = []) => {
    const fieldType = document.getElementById('profile_compliance_field_type')?.value;
    const valueInput = document.getElementById('profile_compliance_value');
    const option = COMPLIANCE_FIELD_OPTIONS.find((item) => item.value === fieldType);
    const existing = getComplianceFieldByType(fields, fieldType);

    if (valueInput) {
        valueInput.inputMode = option?.inputMode || 'text';
        valueInput.maxLength = option?.maxLength || 255;
        valueInput.classList.toggle('text-uppercase', Boolean(option?.uppercase));

        if (existing && (existing.can_resubmit || profileCanEditWithoutApproval)) {
            fillComplianceFieldForm(existing);
        } else if (!fieldType) {
            valueInput.value = '';
        }
    }
};

let cachedComplianceFields = [];

const renderComplianceFields = (employee, salary = {}) => {
    const select = document.getElementById('profile_compliance_field_type');
    const uploadForm = document.getElementById('profileComplianceFieldForm');
    const uploadHint = document.getElementById('profileComplianceFieldUploadHint');
    const tableBody = document.getElementById('profileComplianceFieldsTableBody');
    const requiredWrap = document.getElementById('profileRequiredComplianceFields');
    const fields = employee.compliance_fields || [];
    cachedComplianceFields = fields;
    const availableOptions = getComplianceFieldOptionsForSalary(salary);
    const submittableOptions = getSubmittableComplianceFields(fields, salary);

    renderDl(document.getElementById('profileComplianceFlags'), [
        ['PF Applicable', yesNo(salary.pf_applicable)],
        ['ESI Applicable', yesNo(salary.esi_applicable)],
        ['Professional Tax', yesNo(salary.professional_tax_applicable)],
    ]);

    if (select) {
        if (submittableOptions.length === 0) {
            select.innerHTML = '<option value="">No compliance fields available to submit</option>';
            select.disabled = true;
        } else {
            select.disabled = false;
            select.innerHTML = '<option value="">Select field</option>' + submittableOptions
                .map((option) => {
                    const existing = getComplianceFieldByType(fields, option.value);
                    return `<option value="${option.value}">${option.label}${complianceFieldOptionSuffix(existing)}</option>`;
                })
                .join('');
        }
    }

    if (uploadForm) {
        uploadForm.classList.toggle('d-none', submittableOptions.length === 0);
    }

    if (uploadHint) {
        uploadHint.textContent = profileCanEditWithoutApproval
            ? 'Select a compliance field and save the value directly for this employee.'
            : (submittableOptions.length
                ? 'Select a field to submit, change approved details, or re-submit a rejected field.'
                : 'All applicable compliance fields are currently pending HR review.');
    }

    handleComplianceFieldTypeChange(fields);

    if (tableBody) {
        if (fields.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No compliance fields submitted yet.</td></tr>';
        } else {
            tableBody.innerHTML = fields.map((field) => `
                <tr>
                    <td>${COMPLIANCE_FIELD_LABELS[field.field_type] || field.field_type}</td>
                    <td>${field.value || '—'}</td>
                    <td>${statusBadge(field.status, 'document')}</td>
                    <td>${field.status === 'rejected' && field.notes
                        ? `<span class="text-danger small">${field.notes}</span>`
                        : '<span class="text-muted">—</span>'}</td>
                    <td>${formatDateTime(field.submitted_at)}</td>
                    <td>${formatReviewCell(field)}</td>
                    <td class="text-end">
                        ${field.can_resubmit
        ? `<button type="button" class="btn btn-sm btn-outline-primary" data-change-compliance-field="${field.field_type}">${field.status === 'approved' ? 'Change' : 'Re-submit'}</button>`
        : '<span class="text-muted">—</span>'}
                    </td>
                </tr>
            `).join('');
        }
    }

    const complianceSubmitBtn = document.getElementById('profileComplianceFieldSubmit');
    if (complianceSubmitBtn) {
        complianceSubmitBtn.textContent = profileCanEditWithoutApproval ? 'Save' : 'Submit for Approval';
    }

    if (requiredWrap) {
        requiredWrap.innerHTML = availableOptions.map((option) => {
            const existing = getComplianceFieldByType(fields, option.value);
            let className = '';
            let icon = '○';
            let suffix = '';

            if (existing) {
                if (existing.status === 'approved') {
                    className = 'profile-required-doc--uploaded';
                    icon = '✓';
                    suffix = profileCanEditWithoutApproval ? ' (Approved)' : ' (Approved — can be changed)';
                } else if (existing.status === 'pending') {
                    className = 'profile-required-doc--missing';
                    icon = '⏳';
                    suffix = ' (Pending)';
                } else if (existing.status === 'rejected') {
                    className = 'profile-required-doc--missing';
                    icon = '✕';
                    suffix = ' (Rejected — re-submit required)';
                }
            }

            return `<span class="profile-required-doc ${className}">${icon} ${option.label}${suffix}</span>`;
        }).join('');
    }
};

const renderPendingComplianceApprovals = (fields = []) => {
    const section = document.getElementById('profileComplianceApprovalsSection');
    const tableBody = document.getElementById('profilePendingComplianceBody');
    const countBadge = document.getElementById('profilePendingComplianceCount');

    if (!section || !tableBody) {
        return;
    }

    section.classList.toggle('d-none', fields.length === 0);
    countBadge.textContent = `${fields.length} pending`;

    if (fields.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No pending compliance fields.</td></tr>';
        return;
    }

    tableBody.innerHTML = fields.map((field) => `
        <tr>
            <td>
                <strong>${field.employee?.full_name || '—'}</strong>
                <div class="small text-muted">${field.employee?.employee_code || ''}</div>
            </td>
            <td>${COMPLIANCE_FIELD_LABELS[field.field_type] || field.field_type || '—'}</td>
            <td>${field.value || '—'}</td>
            <td>${field.submitted_by?.name || '—'}${field.submitted_by?.role ? `<div class="small text-muted">${field.submitted_by.role}</div>` : ''}</td>
            <td>${formatDateTime(field.submitted_at)}</td>
            <td class="text-end">${renderReviewIconActionGroup('data-approve-compliance-field', 'data-reject-compliance-field', field.id)}</td>
        </tr>
    `).join('');
};

const loadPendingComplianceApprovals = async (canReview) => {
    if (!canReview) {
        renderPendingComplianceApprovals([]);
        return;
    }

    try {
        const { data } = await api.get('/employee-compliance-fields/pending');
        renderPendingComplianceApprovals(data.data.compliance_fields || []);
    } catch (error) {
        console.error(getErrorMessage(error));
    }
};

const getDocumentsByType = (documents, typeId) => documents.filter((document) => document.document_type_id === typeId);

const getUploadableDocumentTypes = (documentTypes, documents) => {
    if (profileCanEditWithoutApproval) {
        return documentTypes;
    }

    return documentTypes.filter((type) => {
        if (type.allow_multiple) {
            return true;
        }

        const existing = getDocumentsByType(documents, type.id)[0];

        return !existing || existing.can_reupload;
    });
};

const DOCUMENT_MAX_FILE_BYTES = 5 * 1024 * 1024;
const DOCUMENT_ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

const formatFileMb = (bytes) => (bytes / (1024 * 1024)).toFixed(1);

const validateDocumentFileSelection = () => {
    const fileInput = document.getElementById('profile_document_file');
    const form = document.getElementById('profileDocumentForm');
    const files = Array.from(fileInput?.files || []);

    let message = '';

    const invalidType = files.find((file) => {
        const extension = file.name.split('.').pop()?.toLowerCase() || '';

        return !DOCUMENT_ALLOWED_EXTENSIONS.includes(extension);
    });

    const tooLarge = files.filter((file) => file.size > DOCUMENT_MAX_FILE_BYTES);

    if (invalidType) {
        message = `"${invalidType.name}" is not allowed. Only PDF, JPG, and PNG files are accepted.`;
    } else if (tooLarge.length > 0) {
        message = tooLarge
            .map((file) => `"${file.name}" is ${formatFileMb(file.size)} MB`)
            .join(', ')
            + ' — maximum allowed size is 5 MB per file.';
    }

    if (form) {
        setFieldError(form, 'file', message);
    }

    fileInput?.classList.toggle('is-invalid', Boolean(message));

    if (message) {
        fileInput.value = '';
    }

    return !message;
};

const syncDocumentFileInput = (documentTypes, typeId) => {
    const fileInput = document.getElementById('profile_document_file');
    const fileLabel = document.getElementById('profile_document_file_label');
    const fileHint = document.getElementById('profile_document_file_hint');
    const selectedType = documentTypes.find((type) => String(type.id) === String(typeId));

    if (!fileInput) {
        return;
    }

    fileInput.value = '';

    const setHint = (text) => {
        if (!fileHint) {
            return;
        }

        fileHint.textContent = text;
        fileHint.classList.toggle('d-none', !text);
    };

    if (selectedType?.allow_multiple) {
        fileInput.setAttribute('multiple', 'multiple');
        fileInput.name = 'files[]';

        if (fileLabel) {
            fileLabel.textContent = 'Files (PDF, JPG, PNG — max 5MB each) *';
        }

        setHint('You can select multiple files for this document type.');

        return;
    }

    fileInput.removeAttribute('multiple');
    fileInput.name = 'file';

    if (fileLabel) {
        fileLabel.textContent = 'File (PDF, JPG, PNG — max 5MB) *';
    }

    setHint(selectedType ? 'Select one file for this document type.' : '');
};

const renderDocuments = (employee, documentTypes = []) => {
    const select = document.getElementById('profile_document_type_id');
    const uploadForm = document.getElementById('profileDocumentForm');
    const uploadHint = document.getElementById('profileDocumentUploadHint');
    const tableBody = document.getElementById('profileDocumentsTableBody');
    const requiredWrap = document.getElementById('profileRequiredDocuments');
    const documents = employee.documents || [];
    const uploadableTypes = getUploadableDocumentTypes(documentTypes, documents);

    if (select) {
        if (uploadableTypes.length === 0) {
            select.innerHTML = '<option value="">No document types available to upload</option>';
            select.disabled = true;
        } else {
            select.disabled = false;
            select.innerHTML = '<option value="">Select document type</option>' + uploadableTypes
                .map((type) => {
                    const existing = getDocumentsByType(documents, type.id)[0];
                    const suffix = existing?.can_reupload ? ' (Re-upload)' : '';
                    const modeLabel = type.allow_multiple ? ' · Multiple' : '';
                    return `<option value="${type.id}">${type.name}${type.is_required ? ' *' : ''}${modeLabel}${suffix}</option>`;
                })
                .join('');

            syncDocumentFileInput(documentTypes, select.value);
        }
    }

    if (uploadForm) {
        uploadForm.classList.toggle('d-none', uploadableTypes.length === 0);
    }

    const documentSubmitBtn = document.getElementById('profileDocumentSubmit');
    if (documentSubmitBtn) {
        documentSubmitBtn.textContent = profileCanEditWithoutApproval ? 'Save Document' : 'Upload';
    }

    if (uploadHint) {
        uploadHint.textContent = profileCanEditWithoutApproval
            ? 'Upload or replace documents directly for this employee.'
            : (uploadableTypes.length
                ? 'Select a document type that is not yet uploaded, or one that was rejected.'
                : 'All document types are either pending approval or already approved.');
    }

    if (tableBody) {
        if (documents.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No documents uploaded yet.</td></tr>';
        } else {
            tableBody.innerHTML = documents.map((document) => `
                <tr>
                    <td>
                        <div>${document.document_type?.name || '—'}</div>
                        <div class="small text-muted">${document.original_name || '—'}</div>
                    </td>
                    <td>${statusBadge(document.status, 'document')}</td>
                    <td>${document.status === 'rejected' && document.notes
                        ? `<span class="text-danger small">${document.notes}</span>`
                        : '<span class="text-muted">—</span>'}</td>
                    <td>${formatDateTime(document.created_at)}</td>
                    <td>${formatReviewCell(document)}</td>
                    <td class="text-end">
                        <div class="table-action-group justify-content-end">
                            ${renderViewDocumentIconButton(document.id, document.document_type?.name || 'Document')}
                            ${profileCanDeleteDocuments
                                ? renderDeleteDocumentIconButton(document.id, document.document_type?.name || 'Document')
                                : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        }
    }

    if (requiredWrap) {
        if (documentTypes.length === 0) {
            requiredWrap.innerHTML = '<p class="text-muted small mb-0">No document types configured by your company yet.</p>';
        } else {
            requiredWrap.innerHTML = documentTypes.map((type) => {
                const typeDocuments = getDocumentsByType(documents, type.id);
                const approvedCount = typeDocuments.filter((document) => document.status === 'approved').length;
                const pendingCount = typeDocuments.filter((document) => document.status === 'pending').length;
                const rejectedCount = typeDocuments.filter((document) => document.status === 'rejected').length;
                let className = '';
                let icon = '○';
                let suffix = '';

                if (typeDocuments.length > 0) {
                    if (approvedCount > 0) {
                        className = 'profile-required-doc--uploaded';
                        icon = '✓';
                        suffix = type.allow_multiple
                            ? ` (${approvedCount} approved)`
                            : ' (Approved)';
                    } else if (pendingCount > 0) {
                        className = 'profile-required-doc--missing';
                        icon = '⏳';
                        suffix = type.allow_multiple
                            ? ` (${pendingCount} pending)`
                            : ' (Pending)';
                    } else if (rejectedCount > 0) {
                        className = 'profile-required-doc--missing';
                        icon = '✕';
                        suffix = type.allow_multiple
                            ? ` (${rejectedCount} rejected)`
                            : ' (Rejected — re-upload required)';
                    }
                } else if (type.is_required) {
                    className = 'profile-required-doc--missing';
                    icon = '!';
                    suffix = ' (Required)';
                }

                return `<span class="profile-required-doc ${className}">${icon} ${type.name}${suffix}</span>`;
            }).join('');
        }
    }
};

const renderPendingDocumentApprovals = (documents = []) => {
    const section = document.getElementById('profileDocumentApprovalsSection');
    const tableBody = document.getElementById('profilePendingDocumentsBody');
    const countBadge = document.getElementById('profilePendingDocumentsCount');

    if (!section || !tableBody) {
        return;
    }

    section.classList.toggle('d-none', documents.length === 0);
    countBadge.textContent = `${documents.length} pending`;

    if (documents.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No pending documents.</td></tr>';
        return;
    }

    tableBody.innerHTML = documents.map((document) => `
        <tr>
            <td>
                <strong>${document.employee?.full_name || '—'}</strong>
                <div class="small text-muted">${document.employee?.employee_code || ''}</div>
            </td>
            <td>${document.document_type?.name || '—'}</td>
            <td>${document.uploaded_by?.name || '—'}${document.uploaded_by?.role ? `<div class="small text-muted">${document.uploaded_by.role}</div>` : ''}</td>
            <td>${formatDateTime(document.created_at)}</td>
            <td class="text-end">
                <div class="table-action-group">
                    ${renderViewDocumentIconButton(document.id, document.document_type?.name || 'Document', 'data-view-review="1"')}
                    ${renderReviewIconActions('data-approve-document', 'data-reject-document', document.id)}
                </div>
            </td>
        </tr>
    `).join('');
};

const loadPendingDocumentApprovals = async (canReview) => {
    if (!canReview) {
        renderPendingDocumentApprovals([]);
        return;
    }

    try {
        const { data } = await api.get('/employee-documents/pending');
        renderPendingDocumentApprovals(data.data.documents || []);
    } catch (error) {
        console.error(getErrorMessage(error));
    }
};

const showEmployeeSections = (hasEmployee) => {
    const sections = [
        ['profileWorkContent', 'profileWorkEmpty'],
        ['profilePersonalContent', 'profilePersonalEmpty'],
        ['profileSalaryContent', 'profileSalaryEmpty'],
        ['profileBankContent', 'profileBankEmpty'],
        ['profileCompliancesContent', 'profileCompliancesEmpty'],
        ['profileDocumentsContent', 'profileDocumentsEmpty'],
        ['profileOtherContent', 'profileOtherEmpty'],
    ];

    sections.forEach(([contentId, emptyId]) => {
        setVisible(document.getElementById(contentId), document.getElementById(emptyId), hasEmployee);
    });
};

const populateEmployeeProfile = (employee, documentTypes = []) => {
    showEmployeeSections(true);
    renderWorkTab(employee);
    renderPersonalTab(employee);
    renderSalaryTab(employee);
    renderOtherTab(employee);
    renderPaymentMethods(employee);
    renderComplianceFields(employee, employee.salary);
    renderDocuments(employee, documentTypes);
};

const showFormStatus = (element, message) => {
    if (!element) {
        return;
    }

    element.textContent = message;
    element.classList.remove('d-none');
};

document.addEventListener('DOMContentLoaded', async () => {
    const targetEmployeeId = window.PROFILE_TARGET_EMPLOYEE_ID || null;
    const isAdminEmployeeProfile = Boolean(targetEmployeeId);
    const profileEmployeeEndpoint = isAdminEmployeeProfile
        ? `/employees/${targetEmployeeId}/profile`
        : '/profile/employee';
    const profileSubmitPrefix = isAdminEmployeeProfile
        ? `/employees/${targetEmployeeId}/profile`
        : '/profile';

    const profileForm = document.getElementById('profileForm');
    const familySectionForm = document.getElementById('profileFamilySectionForm');
    const addressSectionForm = document.getElementById('profileAddressSectionForm');
    const emergencySectionForm = document.getElementById('profileEmergencySectionForm');
    const paymentMethodForm = document.getElementById('profilePaymentMethodForm');
    const complianceFieldForm = document.getElementById('profileComplianceFieldForm');
    const documentForm = document.getElementById('profileDocumentForm');
    const salaryForm = document.getElementById('profileSalaryForm');
    const assetsForm = document.getElementById('profileAssetsForm');
    const tabButtons = document.querySelectorAll('#profileTabs [data-bs-toggle="tab"]');

    let employeeProfile = null;
    let documentTypes = [];
    let canReviewDocuments = false;
    let canReviewBank = false;
    let canReviewCompliances = false;
    let canReviewPersonalSections = false;
    let canReviewFamilyMembers = false;
    let canUpdateContactInfo = false;
    let rejectDocumentId = null;
    let rejectSubmission = null;
    let rejectModal = null;
    let rejectProfileSubmissionModal = null;
    let previewBlobUrl = null;
    let previewFallbackName = 'document';

    const lightboxEl = () => document.getElementById('viewDocumentLightbox');

    const closeDocumentLightbox = () => {
        const lightbox = lightboxEl();

        if (!lightbox) {
            return;
        }

        lightbox.classList.add('d-none');
        document.body.classList.remove('document-lightbox-open');
        clearDocumentPreview();
    };

    const openDocumentLightbox = (title = 'Document') => {
        const lightbox = lightboxEl();
        const titleEl = document.getElementById('viewDocumentLightboxTitle');

        if (!lightbox) {
            return;
        }

        if (titleEl) {
            titleEl.textContent = title;
        }

        lightbox.classList.remove('d-none');
        document.body.classList.add('document-lightbox-open');
    };

    const applyEmployeeProfilePayload = async (payload) => {
        employeeProfile = payload.employee;
        documentTypes = payload.document_types || [];
        profileCanEditWithoutApproval = Boolean(
            payload.capabilities?.can_edit_without_approval
            ?? payload.capabilities?.can_edit_profile_without_approval,
        );
        profileCanManageSalary = Boolean(payload.capabilities?.can_manage_salary);
        profileCanManageAssets = Boolean(payload.capabilities?.can_manage_assets);
        canReviewDocuments = Boolean(payload.capabilities?.can_review_documents ?? payload.capabilities?.can_review_profile);
        profileCanDeleteDocuments = canReviewDocuments;
        canReviewBank = Boolean(payload.capabilities?.can_review_bank ?? payload.capabilities?.can_review_profile);
        canReviewCompliances = Boolean(payload.capabilities?.can_review_compliances ?? payload.capabilities?.can_review_profile);
        canReviewPersonalSections = Boolean(payload.capabilities?.can_review_personal_sections ?? payload.capabilities?.can_review_profile);
        canReviewFamilyMembers = Boolean(payload.capabilities?.can_review_family_members ?? payload.capabilities?.can_review_profile);
        canUpdateContactInfo = Boolean(payload.capabilities?.can_update_contact_info ?? isAdminEmployeeProfile);
        populateEmployeeProfile(employeeProfile, documentTypes);
        setContactInfoFormAccess(canUpdateContactInfo);

        if (! isAdminEmployeeProfile) {
            await Promise.all([
                loadPendingDocumentApprovals(canReviewDocuments),
                loadPendingPaymentMethodApprovals(canReviewBank),
                loadPendingComplianceApprovals(canReviewCompliances),
                loadPendingPersonalSectionApprovals(canReviewPersonalSections),
                loadPendingFamilyMemberApprovals(canReviewFamilyMembers),
            ]);
        }

        return payload;
    };

    const refreshEmployeeProfile = async () => {
        const { data } = await api.get(profileEmployeeEndpoint);

        return applyEmployeeProfilePayload(data.data);
    };

    const refreshEmployeeDocuments = refreshEmployeeProfile;

    tabButtons.forEach((button) => {
        button.addEventListener('shown.bs.tab', (event) => {
            const hashEntry = Object.entries(PROFILE_TAB_HASHES).find(([, tabId]) => tabId === event.target.id);

            if (hashEntry) {
                window.history.replaceState(null, '', `#${hashEntry[0]}`);
            }
        });
    });

    activateProfileTabFromHash();
    window.addEventListener('hashchange', activateProfileTabFromHash);

    document.getElementById('profile_payment_mode')?.addEventListener('change', () => {
        handlePaymentModeChange(cachedPaymentMethods);
    });

    document.getElementById('profilePaymentMethodsTableBody')?.addEventListener('click', (event) => {
        const changeBtn = event.target.closest('[data-change-payment-method]');

        if (!changeBtn) {
            return;
        }

        const select = document.getElementById('profile_payment_mode');
        const form = document.getElementById('profilePaymentMethodForm');

        if (select) {
            select.value = changeBtn.dataset.changePaymentMethod;
            handlePaymentModeChange(cachedPaymentMethods);
        }

        form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.getElementById('profile_compliance_field_type')?.addEventListener('change', () => {
        handleComplianceFieldTypeChange(cachedComplianceFields);
    });

    document.getElementById('profileComplianceFieldsTableBody')?.addEventListener('click', (event) => {
        const changeBtn = event.target.closest('[data-change-compliance-field]');

        if (!changeBtn) {
            return;
        }

        const select = document.getElementById('profile_compliance_field_type');
        const form = document.getElementById('profileComplianceFieldForm');

        if (select) {
            select.value = changeBtn.dataset.changeComplianceField;
            handleComplianceFieldTypeChange(cachedComplianceFields);
        }

        form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.getElementById('profile_compliance_value')?.addEventListener('input', (event) => {
        const fieldType = document.getElementById('profile_compliance_field_type')?.value;
        const option = COMPLIANCE_FIELD_OPTIONS.find((item) => item.value === fieldType);

        if (option?.inputMode === 'numeric') {
            event.target.value = event.target.value.replace(/\D/g, '');
        }

        if (option?.uppercase) {
            event.target.value = event.target.value.toUpperCase();
        }
    });

    ['profile_phone', 'profile_emergency_contact_phone', 'profile_postal_code'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', (event) => {
            if (id === 'profile_emergency_contact_phone') {
                normalizePhoneInput(event.target);
                return;
            }

            if (['profile_phone', 'profile_postal_code'].includes(id)) {
                event.target.value = event.target.value.replace(/\D/g, '');
            }
        });
    });

    try {
        if (isAdminEmployeeProfile) {
            const employeeResponse = await api.get(profileEmployeeEndpoint);
            await applyEmployeeProfilePayload(employeeResponse.data.data);

            if (!profileCanEditWithoutApproval) {
                throw new Error('You are not allowed to edit this employee profile.');
            }

            populateProfileHeader(
                {
                    name: employeeProfile.full_name,
                    email: employeeProfile.email,
                    role: employeeProfile.role,
                    company: employeeProfile.company,
                },
                employeeProfile,
            );
        } else {
        const [userResponse, employeeResponse] = await Promise.allSettled([
            api.get('/profile'),
            api.get(profileEmployeeEndpoint),
        ]);

        if (userResponse.status === 'fulfilled') {
            const user = userResponse.value.data.data.user;
            populateProfileHeader(user, employeeResponse.status === 'fulfilled'
                ? employeeResponse.value.data.data.employee
                : null);

            if (profileForm) {
                profileForm.querySelector('#name').value = user.name;
                profileForm.querySelector('#email').value = user.email;
            }
        }

        if (employeeResponse.status === 'fulfilled') {
            await applyEmployeeProfilePayload(employeeResponse.value.data.data);

            if (userResponse.status === 'fulfilled') {
                populateProfileHeader(userResponse.value.data.data.user, employeeProfile);
            }
        } else {
            showEmployeeSections(false);
        }
        }
    } catch (error) {
        console.error(getErrorMessage(error));
        showEmployeeSections(false);
    }

    document.getElementById('profile_same_as_permanent')?.addEventListener('change', toggleTemporaryAddressFields);

    document.getElementById('profileAddFamilyMember')?.addEventListener('click', () => {
        const list = document.getElementById('profileFamilyMembersList');
        const row = createFamilyMemberRow();

        if (list && row) {
            list.appendChild(row);
            updateFamilyMemberRemoveButtons();
        }
    });

    document.getElementById('profileFamilyMembersList')?.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('[data-remove-family-member]');

        if (!removeBtn) {
            return;
        }

        const rows = document.querySelectorAll('[data-family-member-row]');

        if (rows.length <= 1) {
            return;
        }

        removeBtn.closest('[data-family-member-row]')?.remove();
        updateFamilyMemberRemoveButtons();
    });

    document.getElementById('profileFamilyMembersList')?.addEventListener('input', (event) => {
        if (event.target.matches('[data-family-phone]')) {
            normalizePhoneInput(event.target);
        }

        if (event.target.matches('[data-family-dob]')) {
            applyFamilyDobConstraints(event.target);
        }
    });

    ['profile_permanent_postal_code', 'profile_temp_postal_code'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', (event) => {
            event.target.value = event.target.value.replace(/\D/g, '');
        });
    });

    document.querySelectorAll('.profile-salary-input').forEach((input) => {
        input.addEventListener('input', updateProfileSalaryPreview);
    });

    salaryForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!profileCanManageSalary) {
            return;
        }

        const submitBtn = document.getElementById('profileSalarySubmit');
        const statusEl = document.getElementById('profileSalaryStatusMsg');
        const payload = collectProfileSalaryPayload();

        if (!payload.annual_ctc || !payload.basic_salary || !payload.salary_effective_from) {
            alert('Annual CTC, basic salary, and effective date are required.');
            return;
        }

        setSubmitLoading(submitBtn, true, { submittingText: 'Saving...' });

        try {
            const { data } = await api.put(`${profileSubmitPrefix}/salary`, payload);

            await applyEmployeeProfilePayload(data.data);
            document.getElementById('profile_salary_revision_notes').value = '';
            showFormStatus(statusEl, data.message || 'Salary saved.');
        } catch (error) {
            applyBackendErrors(salaryForm, error.response?.data?.errors, setFieldError);
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    assetsForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!profileCanManageAssets || !isAdminEmployeeProfile) {
            return;
        }

        const submitBtn = document.getElementById('profileAssetsSubmit');
        const statusEl = document.getElementById('profileAssetsStatusMsg');
        const payload = { assets: collectProfileAssetsPayload() };

        setSubmitLoading(submitBtn, true, { submittingText: 'Saving...' });

        try {
            const { data } = await api.put(`${profileSubmitPrefix}/assets`, payload);

            await applyEmployeeProfilePayload(data.data);
            showFormStatus(statusEl, data.message || 'Assets saved.');
        } catch (error) {
            applyBackendErrors(assetsForm, error.response?.data?.errors, setFieldError);
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    familySectionForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = document.getElementById('profileFamilySectionSubmit');
        const statusEl = document.getElementById('profileFamilySectionStatusMsg');
        const members = collectFamilyMembersFromForm();

        if (members.length === 0) {
            alert('Add at least one family member.');
            return;
        }

        if (members.some((member) => !isValidTenDigitPhone(member.phone))) {
            alert('Mobile number must be exactly 10 digits.');
            return;
        }

        if (members.some((member) => !isValidFamilyDob(member.date_of_birth))) {
            alert('Date of birth must be a valid date with a 4-digit year (YYYY-MM-DD), not in the future, and 1900 or later.');
            return;
        }

        setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });

        try {
            const { data } = await api.post(`${profileSubmitPrefix}/family-members`, { members });

            await refreshEmployeeProfile();
            resetFamilyForm();
            showFormStatus(statusEl, data.message || 'Submitted.');
        } catch (error) {
            applyBackendErrors(familySectionForm, error.response?.data?.errors, setFieldError);
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    addressSectionForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = document.getElementById('profileAddressSectionSubmit');
        const statusEl = document.getElementById('profileAddressSectionStatusMsg');
        const addressPayload = collectAddressPayload();

        setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });

        try {
            const { data } = await api.post(`${profileSubmitPrefix}/personal-sections`, {
                section_type: 'address',
                ...addressPayload,
            });

            await refreshEmployeeProfile();
            showFormStatus(statusEl, data.message || 'Submitted.');
        } catch (error) {
            applyBackendErrors(addressSectionForm, error.response?.data?.errors, setFieldError);
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    emergencySectionForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = document.getElementById('profileEmergencySectionSubmit');
        const statusEl = document.getElementById('profileEmergencySectionStatusMsg');
        const name = document.getElementById('profile_emergency_contact_name')?.value.trim();
        const relation = document.getElementById('profile_emergency_contact_relation')?.value.trim();
        const phone = document.getElementById('profile_emergency_contact_phone')?.value.trim();

        if (!name || !relation) {
            return;
        }

        if (!isValidTenDigitPhone(phone)) {
            alert('Mobile number must be exactly 10 digits.');
            return;
        }

        setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });

        try {
            const { data } = await api.post(`${profileSubmitPrefix}/personal-sections`, {
                section_type: 'emergency_contact',
                name,
                relation,
                phone: phone || null,
            });

            await refreshEmployeeProfile();
            showFormStatus(statusEl, data.message || 'Submitted.');
        } catch (error) {
            applyBackendErrors(emergencySectionForm, error.response?.data?.errors, setFieldError);
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    paymentMethodForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = document.getElementById('profilePaymentMethodSubmit');
        const statusEl = document.getElementById('profilePaymentMethodStatus');
        const paymentMode = document.getElementById('profile_payment_mode')?.value;

        if (!paymentMode) {
            return;
        }

        setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });

        try {
            const payload = { payment_mode: paymentMode };

            if (paymentMode === 'bank_transfer') {
                payload.bank_name = document.getElementById('profile_bank_name')?.value.trim() || null;
                payload.bank_branch = document.getElementById('profile_bank_branch')?.value.trim() || null;
                payload.bank_address = document.getElementById('profile_bank_address')?.value.trim() || null;
                payload.account_holder_name = document.getElementById('profile_account_holder_name')?.value.trim() || null;
                payload.account_number = document.getElementById('profile_account_number')?.value.trim() || null;
                payload.ifsc_code = document.getElementById('profile_ifsc_code')?.value.trim().toUpperCase() || null;
            }

            const { data } = await api.post(`${profileSubmitPrefix}/payment-methods`, payload);

            await refreshEmployeeProfile();
            paymentMethodForm.reset();
            toggleBankFields();
            showFormStatus(statusEl, data.message || 'Submitted.');
        } catch (error) {
            applyBackendErrors(paymentMethodForm, error.response?.data?.errors, setFieldError);
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    complianceFieldForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = document.getElementById('profileComplianceFieldSubmit');
        const statusEl = document.getElementById('profileComplianceFieldStatus');
        const fieldType = document.getElementById('profile_compliance_field_type')?.value;
        const valueInput = document.getElementById('profile_compliance_value');

        if (!fieldType || !valueInput?.value.trim()) {
            return;
        }

        setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });

        try {
            const payload = {
                field_type: fieldType,
                value: valueInput.value.trim(),
            };

            if (fieldType === 'pan') {
                payload.value = payload.value.toUpperCase();
            }

            const { data } = await api.post(`${profileSubmitPrefix}/compliance-fields`, payload);

            await refreshEmployeeProfile();
            complianceFieldForm.reset();
            handleComplianceFieldTypeChange([]);
            showFormStatus(statusEl, data.message || 'Submitted.');
        } catch (error) {
            applyBackendErrors(complianceFieldForm, error.response?.data?.errors, setFieldError);
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    document.getElementById('profile_document_type_id')?.addEventListener('change', (event) => {
        syncDocumentFileInput(documentTypes, event.target.value);

        if (documentForm) {
            setFieldError(documentForm, 'file', '');
        }
    });

    document.getElementById('profile_document_file')?.addEventListener('change', () => {
        validateDocumentFileSelection();
    });

    documentForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitBtn = document.getElementById('profileDocumentSubmit');
        const statusEl = document.getElementById('profileDocumentStatus');
        const fileInput = document.getElementById('profile_document_file');
        const typeSelect = document.getElementById('profile_document_type_id');
        const selectedType = documentTypes.find((type) => String(type.id) === String(typeSelect?.value));
        const selectedFiles = Array.from(fileInput?.files || []);

        if (!selectedType || selectedFiles.length === 0) {
            return;
        }

        if (!validateDocumentFileSelection()) {
            return;
        }

        if (!selectedType.allow_multiple && selectedFiles.length > 1) {
            alert('Only one file can be uploaded for this document type.');
            return;
        }

        setSubmitLoading(submitBtn, true, { submittingText: 'Uploading...' });

        const uploadProgress = bindUploadProgress({
            wrap: document.getElementById('profileDocumentUploadProgress'),
            bar: document.getElementById('profileDocumentUploadProgressBar'),
            percentEl: document.getElementById('profileDocumentUploadProgressPercent'),
            labelEl: document.getElementById('profileDocumentUploadProgressLabel'),
        });

        const fileLabel = selectedFiles.length === 1
            ? `Uploading ${selectedFiles[0].name}...`
            : `Uploading ${selectedFiles.length} files...`;

        uploadProgress.update(0, fileLabel);
        fileInput.disabled = true;
        typeSelect.disabled = true;

        try {
            const formData = new FormData();
            formData.append('document_type_id', typeSelect.value);

            if (selectedType.allow_multiple) {
                selectedFiles.forEach((file) => formData.append('files[]', file));
            } else {
                formData.append('file', selectedFiles[0]);
            }

            const { data } = await api.post(`${profileSubmitPrefix}/documents`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: uploadProgress.onUploadProgress,
            });

            uploadProgress.update(100, 'Upload complete. Saving...');

            await refreshEmployeeProfile();
            documentForm.reset();
            syncDocumentFileInput(documentTypes, '');
            showFormStatus(statusEl, data.message || 'Uploaded.');
        } catch (error) {
            applyBackendErrors(documentForm, error.response?.data?.errors, setFieldError);
            alert(getErrorMessage(error));
        } finally {
            uploadProgress.hide();
            fileInput.disabled = false;
            typeSelect.disabled = false;
            setSubmitLoading(submitBtn, false);
        }
    });

    const clearDocumentPreview = () => {
        const iframe = document.getElementById('viewDocumentFrame');
        const img = document.getElementById('viewDocumentImage');

        if (iframe) {
            iframe.removeAttribute('src');
        }

        if (img) {
            img.removeAttribute('src');
        }

        if (previewBlobUrl) {
            window.URL.revokeObjectURL(previewBlobUrl);
            previewBlobUrl = null;
        }
    };

    const viewDocumentFile = async (documentId, useReviewEndpoint = false, title = 'Document') => {
        const url = useReviewEndpoint
            ? `/employee-documents/${documentId}/download`
            : `${profileSubmitPrefix}/documents/${documentId}/download`;

        const response = await api.get(url, { responseType: 'blob' });
        const mime = response.headers['content-type'] || response.data.type || 'application/octet-stream';
        const disposition = response.headers['content-disposition'];
        const match = disposition?.match(/filename="?([^"]+)"?/);

        previewFallbackName = match?.[1] || 'document';
        clearDocumentPreview();
        previewBlobUrl = window.URL.createObjectURL(response.data);

        const iframe = document.getElementById('viewDocumentFrame');
        const img = document.getElementById('viewDocumentImage');
        const unsupported = document.getElementById('viewDocumentUnsupported');

        iframe?.classList.add('d-none');
        img?.classList.add('d-none');
        unsupported?.classList.add('d-none');

        if (mime.startsWith('image/')) {
            img.src = previewBlobUrl;
            img.classList.remove('d-none');
        } else if (mime === 'application/pdf') {
            iframe.src = previewBlobUrl;
            iframe.classList.remove('d-none');
        } else {
            unsupported?.classList.remove('d-none');
        }

        openDocumentLightbox(title);
    };

    document.getElementById('viewDocumentLightboxClose')?.addEventListener('click', closeDocumentLightbox);

    lightboxEl()?.addEventListener('click', (event) => {
        if (event.target === lightboxEl() || event.target.classList.contains('document-lightbox-stage')) {
            closeDocumentLightbox();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !lightboxEl()?.classList.contains('d-none')) {
            closeDocumentLightbox();
        }
    });

    document.getElementById('viewDocumentOpenTab')?.addEventListener('click', () => {
        if (previewBlobUrl) {
            window.open(previewBlobUrl, '_blank', 'noopener,noreferrer');
        }
    });

    document.getElementById('viewDocumentFallbackDownload')?.addEventListener('click', () => {
        if (!previewBlobUrl) {
            return;
        }

        const link = document.createElement('a');
        link.href = previewBlobUrl;
        link.download = previewFallbackName;
        link.click();
    });

    const handleViewDocumentClick = async (button) => {
        try {
            await viewDocumentFile(
                button.dataset.viewDocument,
                button.dataset.viewReview === '1',
                button.dataset.viewTitle || 'Document',
            );
        } catch (error) {
            alert(getErrorMessage(error));
        }
    };

    const handleDeleteDocumentClick = async (button) => {
        const title = button.dataset.deleteTitle || 'this document';

        if (!window.confirm(`Delete ${title}? This will permanently remove the file.`)) {
            return;
        }

        try {
            await api.delete(`/employee-documents/${button.dataset.deleteDocument}`);
            await refreshEmployeeProfile();
        } catch (error) {
            alert(getErrorMessage(error));
        }
    };

    document.getElementById('profileDocumentsTableBody')?.addEventListener('click', async (event) => {
        const viewBtn = event.target.closest('[data-view-document]');
        const deleteBtn = event.target.closest('[data-delete-document]');

        if (deleteBtn) {
            await handleDeleteDocumentClick(deleteBtn);
            return;
        }

        if (!viewBtn || viewBtn.closest('#profilePendingDocumentsBody')) {
            return;
        }

        await handleViewDocumentClick(viewBtn);
    });

    document.getElementById('profilePendingDocumentsBody')?.addEventListener('click', async (event) => {
        const viewBtn = event.target.closest('[data-view-document]');
        const approveBtn = event.target.closest('[data-approve-document]');
        const rejectBtn = event.target.closest('[data-reject-document]');

        if (viewBtn) {
            await handleViewDocumentClick(viewBtn);
            return;
        }

        if (approveBtn) {
            try {
                await api.patch(`/employee-documents/${approveBtn.dataset.approveDocument}/approve`);
                await refreshEmployeeProfile();
            } catch (error) {
                alert(getErrorMessage(error));
            }
            return;
        }

        if (rejectBtn) {
            rejectDocumentId = rejectBtn.dataset.rejectDocument;
            document.getElementById('rejectDocumentNotes').value = '';
            rejectModal = rejectModal || Modal.getOrCreateInstance(document.getElementById('rejectDocumentModal'));
            rejectModal.show();
        }
    });

    document.getElementById('rejectDocumentForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!rejectDocumentId) {
            return;
        }

        const submitBtn = document.getElementById('rejectDocumentSubmit');
        const notes = document.getElementById('rejectDocumentNotes')?.value.trim();

        setSubmitLoading(submitBtn, true, { submittingText: 'Rejecting...' });

        try {
            await api.patch(`/employee-documents/${rejectDocumentId}/reject`, { notes });
            rejectModal?.hide();
            rejectDocumentId = null;
            await refreshEmployeeProfile();
        } catch (error) {
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    const openRejectProfileSubmissionModal = (type, id) => {
        rejectSubmission = { type, id };
        document.getElementById('rejectProfileSubmissionNotes').value = '';
        rejectProfileSubmissionModal = rejectProfileSubmissionModal
            || Modal.getOrCreateInstance(document.getElementById('rejectProfileSubmissionModal'));
        rejectProfileSubmissionModal.show();
    };

    document.getElementById('profileFamilyResubmitCancel')?.addEventListener('click', () => {
        resetFamilyForm();
    });

    document.getElementById('profileFamilyMembersTableBody')?.addEventListener('click', (event) => {
        const resubmitBtn = event.target.closest('[data-resubmit-family-member]');

        if (!resubmitBtn || !employeeProfile) {
            return;
        }

        const memberId = Number(resubmitBtn.dataset.resubmitFamilyMember);
        const member = (employeeProfile.family_members || []).find((item) => item.id === memberId);

        if (!member) {
            return;
        }

        resetFamilyForm(member);
        document.getElementById('profileFamilySectionForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.getElementById('profilePendingFamilyBody')?.addEventListener('click', async (event) => {
        const approveBtn = event.target.closest('[data-approve-family-member]');
        const rejectBtn = event.target.closest('[data-reject-family-member]');

        if (approveBtn) {
            try {
                await api.patch(`/employee-family-members/${approveBtn.dataset.approveFamilyMember}/approve`);
                await refreshEmployeeProfile();
            } catch (error) {
                alert(getErrorMessage(error));
            }
            return;
        }

        if (rejectBtn) {
            openRejectProfileSubmissionModal('family_member', rejectBtn.dataset.rejectFamilyMember);
        }
    });

    document.getElementById('profilePendingBankBody')?.addEventListener('click', async (event) => {
        const approveBtn = event.target.closest('[data-approve-payment-method]');
        const rejectBtn = event.target.closest('[data-reject-payment-method]');

        if (approveBtn) {
            try {
                await api.patch(`/employee-payment-methods/${approveBtn.dataset.approvePaymentMethod}/approve`);
                await refreshEmployeeProfile();
            } catch (error) {
                alert(getErrorMessage(error));
            }
            return;
        }

        if (rejectBtn) {
            openRejectProfileSubmissionModal('payment_method', rejectBtn.dataset.rejectPaymentMethod);
        }
    });

    document.getElementById('profilePendingPersonalBody')?.addEventListener('click', async (event) => {
        const approveBtn = event.target.closest('[data-approve-personal-section]');
        const rejectBtn = event.target.closest('[data-reject-personal-section]');

        if (approveBtn) {
            try {
                await api.patch(`/employee-personal-sections/${approveBtn.dataset.approvePersonalSection}/approve`);
                await refreshEmployeeProfile();
            } catch (error) {
                alert(getErrorMessage(error));
            }
            return;
        }

        if (rejectBtn) {
            openRejectProfileSubmissionModal('personal_section', rejectBtn.dataset.rejectPersonalSection);
        }
    });

    document.getElementById('profilePendingComplianceBody')?.addEventListener('click', async (event) => {
        const approveBtn = event.target.closest('[data-approve-compliance-field]');
        const rejectBtn = event.target.closest('[data-reject-compliance-field]');

        if (approveBtn) {
            try {
                await api.patch(`/employee-compliance-fields/${approveBtn.dataset.approveComplianceField}/approve`);
                await refreshEmployeeProfile();
            } catch (error) {
                alert(getErrorMessage(error));
            }
            return;
        }

        if (rejectBtn) {
            openRejectProfileSubmissionModal('compliance_field', rejectBtn.dataset.rejectComplianceField);
        }
    });

    document.getElementById('rejectProfileSubmissionForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!rejectSubmission) {
            return;
        }

        const submitBtn = document.getElementById('rejectProfileSubmissionSubmit');
        const notes = document.getElementById('rejectProfileSubmissionNotes')?.value.trim();
        const url = rejectSubmission.type === 'payment_method'
            ? `/employee-payment-methods/${rejectSubmission.id}/reject`
            : rejectSubmission.type === 'personal_section'
                ? `/employee-personal-sections/${rejectSubmission.id}/reject`
                : rejectSubmission.type === 'family_member'
                    ? `/employee-family-members/${rejectSubmission.id}/reject`
                    : `/employee-compliance-fields/${rejectSubmission.id}/reject`;

        setSubmitLoading(submitBtn, true, { submittingText: 'Rejecting...' });

        try {
            await api.patch(url, { notes });
            rejectProfileSubmissionModal?.hide();
            rejectSubmission = null;
            await refreshEmployeeProfile();
        } catch (error) {
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    if (profileForm) {
        const statusEl = document.getElementById('profileSaveStatus');
        const submitBtn = profileForm.querySelector('button[type="submit"]');
        let isSubmitting = false;

        profileForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (isSubmitting) {
                return;
            }

            isSubmitting = true;
            setSubmitLoading(submitBtn, true, { isUpdate: true, updatingText: 'Saving...' });

            try {
                const formData = new FormData(profileForm);
                const { data } = await api.put('/profile', {
                    name: formData.get('name'),
                    email: formData.get('email'),
                });

                populateProfileHeader(data.data.user, employeeProfile);

                if (statusEl) {
                    statusEl.textContent = data.message || 'Saved.';
                    statusEl.classList.remove('d-none');
                }
            } catch (error) {
                alert(getErrorMessage(error));
            } finally {
                isSubmitting = false;
                setSubmitLoading(submitBtn, false, { isUpdate: true });
            }
        });
    }
});
