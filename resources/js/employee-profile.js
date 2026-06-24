import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';
import { renderReviewIconActionGroup, renderViewDocumentIconButton, renderDeleteDocumentIconButton, renderReviewIconActions } from './review-actions';

const EMPLOYMENT_LABELS = {
    full_time: 'Full Time',
    part_time: 'Part Time',
    contract: 'Contract',
    intern: 'Intern',
};

const PROBATION_STATUS_LABELS = {
    on_probation: 'On Probation',
    confirmed: 'Completed',
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

const COMPLIANCE_FIELD_LABELS = {
    pan: 'PAN Number',
    aadhaar: 'Aadhaar Number',
    uan: 'UAN',
    pf: 'PF Number',
    esi: 'ESI Number',
};

const PERSONAL_SECTION_LABELS = {
    address: 'Address',
    emergency_contact: 'Emergency Contact',
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

const statusBadge = (status) => {
    const label = DOCUMENT_STATUS_LABELS[status] || status;

    return `<span class="profile-status-badge profile-status-badge--${status}">${label}</span>`;
};

const yesNo = (value) => (value ? 'Yes' : 'No');

const setVisible = (showEl, hideEl, visible) => {
    showEl?.classList.toggle('d-none', !visible);
    hideEl?.classList.toggle('d-none', visible);
};

const emptyRow = (cols, message) => `<tr><td colspan="${cols}" class="text-center text-muted py-4">${message}</td></tr>`;

const getPersonalSectionByType = (sections, type) => (sections || []).find((section) => section.section_type === type);

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

const formatAddressBlock = (address = {}) => [
    address.address_line_1,
    address.address_line_2,
    address.city,
    address.state,
    address.postal_code,
    address.country,
].filter(Boolean).join(', ') || '—';

const formatPaymentMethodDetails = (method) => {
    if (method.payment_mode !== 'bank_transfer') {
        return '<span class="text-muted">No bank account required</span>';
    }

    const parts = [
        method.bank_name,
        method.bank_branch,
        method.account_holder_name,
        method.account_number ? `A/C ${method.account_number}` : null,
        method.ifsc_code,
    ].filter(Boolean);

    return parts.length ? parts.join(' · ') : '—';
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

const renderReviewActions = (canReviewItem, status, type, id) => {
    if (status !== 'pending') {
        return '<span class="text-muted">—</span>';
    }

    if (!canReviewItem) {
        return statusBadge('pending');
    }

    const attrs = REVIEW_TYPE_ATTRS[type];

    if (!attrs) {
        return '<span class="text-muted">—</span>';
    }

    const [approveAttr, rejectAttr] = attrs;

    return renderReviewIconActionGroup(approveAttr, rejectAttr, id);
};

const REVIEW_TYPE_ATTRS = {
    document: ['data-approve-document', 'data-reject-document'],
    payment_method: ['data-approve-payment-method', 'data-reject-payment-method'],
    compliance_field: ['data-approve-compliance-field', 'data-reject-compliance-field'],
    personal_section: ['data-approve-personal-section', 'data-reject-personal-section'],
    family_member: ['data-approve-family-member', 'data-reject-family-member'],
};

document.addEventListener('DOMContentLoaded', async () => {
    const employeeId = window.EMP_PROFILE_EMPLOYEE_ID;
    const alertBox = document.getElementById('empProfileAlert');

    let canReviewProfile = false;
    let reviewableMap = {};
    let rejectSubmission = null;
    let rejectDocumentId = null;
    let rejectProfileSubmissionModal = null;
    let rejectDocumentModal = null;
    let previewBlobUrl = null;
    let previewFallbackName = 'document';

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const canReviewItem = (type, id) => Boolean(reviewableMap[`${type}:${id}`]);

    const buildReviewableMap = (pendingReviews = []) => {
        reviewableMap = Object.fromEntries(
            pendingReviews.map((item) => [`${item.type}:${item.id}`, Boolean(item.can_review)]),
        );
    };

    const renderPendingReviews = (pendingReviews = []) => {
        const section = document.getElementById('empProfilePendingSection');
        const tableBody = document.getElementById('empProfilePendingBody');
        const countBadge = document.getElementById('empProfilePendingCount');
        const reviewable = pendingReviews.filter((item) => item.can_review);

        if (!section || !tableBody) {
            return;
        }

        section.classList.toggle('d-none', reviewable.length === 0);
        countBadge.textContent = `${reviewable.length} pending`;

        if (reviewable.length === 0) {
            tableBody.innerHTML = emptyRow(4, 'No pending items awaiting your review.');
            return;
        }

        tableBody.innerHTML = reviewable.map((item) => {
            const [approveAttr, rejectAttr] = REVIEW_TYPE_ATTRS[item.type] || [];
            const viewButton = item.type === 'document'
                ? renderViewDocumentIconButton(item.id, item.label || 'Document')
                : '';
            const actions = approveAttr && rejectAttr
                ? `<div class="table-action-group justify-content-end">${viewButton}${renderReviewIconActions(approveAttr, rejectAttr, item.id)}</div>`
                : '<span class="text-muted">—</span>';

            return `
                <tr>
                    <td>${item.label || '—'}</td>
                    <td>${item.summary || '—'}</td>
                    <td>${formatDateTime(item.submitted_at)}</td>
                    <td class="text-end">${actions}</td>
                </tr>
            `;
        }).join('');
    };

    const renderHeader = (employee) => {
        const departments = employee.departments?.length
            ? employee.departments.map((department) => department.name).join(', ')
            : (employee.department?.name || '—');

        document.getElementById('empProfileAvatarInitials').textContent = getInitials(employee.full_name);
        document.getElementById('empProfileDisplayName').textContent = employee.full_name || '—';
        document.getElementById('empProfileDisplayEmail').textContent = employee.email || '—';
        document.getElementById('empProfileDisplayCode').textContent = employee.employee_code || '—';
        document.getElementById('empProfileDisplayRole').textContent = employee.role?.name || '—';
        document.getElementById('empProfileDisplayDepartment').textContent = departments !== '—'
            ? `Department · ${departments}`
            : '';
    };

    const renderWorkTab = (employee) => {
        const salary = employee.salary || {};
        const departments = employee.departments?.length
            ? employee.departments.map((department) => department.name).join(', ')
            : (employee.department?.name || '—');

        renderDl(document.getElementById('empProfileWorkJob'), [
            ['Employee Code', employee.employee_code],
            ['Designation', employee.designation],
            ['Employment Type', EMPLOYMENT_LABELS[employee.employment_type] || employee.employment_type],
            ['Joining Date', formatDate(employee.joining_date)],
            ['Status', employee.status === 'active' ? 'Active' : 'Inactive'],
        ]);

        renderDl(document.getElementById('empProfileWorkOrg'), [
            ['Company', employee.company?.name],
            ['Departments', departments],
            ['System Role', employee.role?.name],
            ['Reporting Manager', employee.manager?.full_name || '—'],
            ['Shift', employee.shift ? `${employee.shift.name} (${employee.shift.time_range || '—'})` : '—'],
        ]);

        renderDl(document.getElementById('empProfileWorkProbation'), [
            ['Probation Applicable', yesNo(employee.probation_applicable)],
            ['Period (Months)', employee.probation_applicable ? employee.probation_period_months : '—'],
            ['End Date', employee.probation_applicable ? formatDate(employee.probation_end_date) : '—'],
            ['Status', employee.probation_applicable
                ? (PROBATION_STATUS_LABELS[employee.probation_status] || employee.probation_status)
                : 'Not Applicable'],
        ]);

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

    const renderSalaryTab = (employee) => {
        const salary = employee.salary || {};
        const hasSalary = Boolean(salary.annual_ctc);

        document.getElementById('profileSalaryManageSection')?.classList.add('d-none');
        document.getElementById('profileSalaryTabDesc').textContent = 'View current compensation structure for this employee.';

        setVisible(
            document.getElementById('profileSalaryContent'),
            document.getElementById('profileSalaryEmpty'),
            hasSalary,
        );

        if (!hasSalary) {
            return;
        }

        renderDl(document.getElementById('profileSalaryDisplay'), buildSalaryDisplayRows(salary));
        document.getElementById('profileSummaryAnnualCtc').textContent = formatCurrency(salary.annual_ctc || 0);
        document.getElementById('profileSummaryMonthlyGross').textContent = formatCurrency(salary.monthly_gross || 0);
    };

    const renderAssetStatus = (isAssigned) => {
        const label = isAssigned ? 'Available' : 'Not Available';
        const className = isAssigned ? 'profile-asset-status--available' : 'profile-asset-status--unavailable';

        return `<span class="profile-asset-status ${className}">${label}</span>`;
    };

    const buildAssetDescriptionView = (asset) => {
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

    const renderOtherTab = (employee) => {
        const assets = employee.assets || [];
        const hasAssets = assets.length > 0;
        const assetList = document.getElementById('profileAssetList');

        document.getElementById('profileOtherManageSection')?.classList.add('d-none');
        document.getElementById('profileOtherTabDesc').textContent = 'View assets assigned to this employee.';

        setVisible(
            document.getElementById('profileOtherContent'),
            document.getElementById('profileOtherEmpty'),
            hasAssets,
        );

        if (!hasAssets || !assetList) {
            return;
        }

        assetList.innerHTML = assets.map((asset) => `
            <li class="profile-asset-item">
                <div class="profile-asset-item-header">
                    <div class="profile-asset-item-title">
                        <span class="profile-asset-icon" aria-hidden="true">i</span>
                        <span class="profile-asset-name">${asset.name}</span>
                    </div>
                    ${renderAssetStatus(asset.is_assigned)}
                </div>
                ${buildAssetDescriptionView(asset)}
            </li>
        `).join('');
    };

    const renderPersonalTab = (employee) => {
        renderDl(document.getElementById('empProfilePersonalDisplay'), [
            ['Full Name', employee.full_name],
            ['Work Email', employee.email],
            ['Personal Email', employee.personal_email || '—'],
            ['Mobile Number', employee.phone || '—'],
            ['Gender', GENDER_LABELS[employee.gender] || employee.gender || '—'],
            ['Date of Birth', formatDate(employee.date_of_birth)],
        ]);

        const familyBody = document.getElementById('empProfileFamilyBody');
        const members = employee.family_members || [];

        if (familyBody) {
            familyBody.innerHTML = members.length === 0
                ? emptyRow(9, 'No family members submitted yet.')
                : members.map((member) => `
                    <tr>
                        <td>${member.name}</td>
                        <td>${member.relation}</td>
                        <td>${member.phone || '—'}</td>
                        <td>${formatDate(member.date_of_birth)}</td>
                        <td>${statusBadge(member.status)}</td>
                        <td>${member.status === 'rejected' && member.notes
        ? `<span class="text-danger small">${member.notes}</span>`
        : '<span class="text-muted">—</span>'}</td>
                        <td>${formatDateTime(member.submitted_at)}</td>
                        <td>${formatReviewCell(member)}</td>
                        <td class="text-end">${renderReviewActions(
        canReviewItem('family_member', member.id),
        member.status,
        'family_member',
        member.id,
    )}</td>
                    </tr>
                `).join('');
        }

        const sections = employee.personal_sections || [];
        const addressSection = getPersonalSectionByType(sections, 'address');
        const emergencySection = getPersonalSectionByType(sections, 'emergency_contact');
        const addressContainer = document.getElementById('empProfileAddressSection');
        const emergencyContainer = document.getElementById('empProfileEmergencySection');

        if (addressContainer) {
            if (!addressSection) {
                addressContainer.innerHTML = '<p class="text-muted small mb-0">No address submitted yet.</p>';
            } else {
                const permanent = addressSection.payload?.permanent || {};
                const temporary = addressSection.payload?.temporary || {};
                const sameAsPermanent = Boolean(addressSection.payload?.same_as_permanent);

                addressContainer.innerHTML = `
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span>${statusBadge(addressSection.status)}</span>
                        ${addressSection.status === 'rejected' && addressSection.notes
        ? `<span class="text-danger small">Review notes: ${addressSection.notes}</span>`
        : ''}
                    </div>
                    <dl class="profile-dl mb-3">
                        <div class="profile-dl-row"><dt>Permanent Address</dt><dd>${formatAddressBlock(permanent)}</dd></div>
                        <div class="profile-dl-row"><dt>Current Address</dt><dd>${sameAsPermanent ? 'Same as permanent' : formatAddressBlock(temporary)}</dd></div>
                        <div class="profile-dl-row"><dt>Submitted</dt><dd>${formatDateTime(addressSection.submitted_at)}</dd></div>
                        <div class="profile-dl-row"><dt>Reviewed By</dt><dd>${formatReviewCell(addressSection)}</dd></div>
                    </dl>
                    <div class="text-end">${renderReviewActions(
        canReviewItem('personal_section', addressSection.id),
        addressSection.status,
        'personal_section',
        addressSection.id,
    )}</div>
                `;
            }
        }

        if (emergencyContainer) {
            if (!emergencySection && !employee.emergency_contact_name) {
                emergencyContainer.innerHTML = '<p class="text-muted small mb-0">No emergency contact submitted yet.</p>';
            } else if (emergencySection) {
                const emergencyContact = getEmergencyContactDetails(emergencySection, employee);

                emergencyContainer.innerHTML = `
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span>${statusBadge(emergencySection.status)}</span>
                        ${emergencySection.status === 'rejected' && emergencySection.notes
        ? `<span class="text-danger small">Review notes: ${emergencySection.notes}</span>`
        : ''}
                    </div>
                    <dl class="profile-dl mb-3">
                        <div class="profile-dl-row"><dt>Name</dt><dd>${emergencyContact.name || '—'}</dd></div>
                        <div class="profile-dl-row"><dt>Relation</dt><dd>${emergencyContact.relation || '—'}</dd></div>
                        <div class="profile-dl-row"><dt>Mobile</dt><dd>${emergencyContact.phone || '—'}</dd></div>
                        <div class="profile-dl-row"><dt>Submitted</dt><dd>${formatDateTime(emergencySection.submitted_at)}</dd></div>
                        <div class="profile-dl-row"><dt>Reviewed By</dt><dd>${formatReviewCell(emergencySection)}</dd></div>
                    </dl>
                    <div class="text-end">${renderReviewActions(
        canReviewItem('personal_section', emergencySection.id),
        emergencySection.status,
        'personal_section',
        emergencySection.id,
    )}</div>
                `;
            } else {
                emergencyContainer.innerHTML = `
                    <dl class="profile-dl">
                        <div class="profile-dl-row"><dt>Name</dt><dd>${employee.emergency_contact_name || '—'}</dd></div>
                        <div class="profile-dl-row"><dt>Relation</dt><dd>${employee.emergency_contact_relation || '—'}</dd></div>
                        <div class="profile-dl-row"><dt>Mobile</dt><dd>${employee.emergency_contact_phone || '—'}</dd></div>
                    </dl>
                `;
            }
        }
    };

    const renderBankTab = (employee) => {
        const tableBody = document.getElementById('empProfileBankBody');
        const methods = employee.payment_methods || [];

        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = methods.length === 0
            ? emptyRow(7, 'No payment options submitted yet.')
            : methods.map((method) => `
                <tr>
                    <td>${PAYMENT_MODE_LABELS[method.payment_mode] || method.payment_mode}</td>
                    <td>${formatPaymentMethodDetails(method)}</td>
                    <td>${statusBadge(method.status)}</td>
                    <td>${method.status === 'rejected' && method.notes
        ? `<span class="text-danger small">${method.notes}</span>`
        : '<span class="text-muted">—</span>'}</td>
                    <td>${formatDateTime(method.submitted_at)}</td>
                    <td>${formatReviewCell(method)}</td>
                    <td class="text-end">${renderReviewActions(
        canReviewItem('payment_method', method.id),
        method.status,
        'payment_method',
        method.id,
    )}</td>
                </tr>
            `).join('');
    };

    const renderCompliancesTab = (employee) => {
        const salary = employee.salary || {};
        const fields = employee.compliance_fields || [];
        const tableBody = document.getElementById('empProfileCompliancesBody');

        renderDl(document.getElementById('empProfileComplianceFlags'), [
            ['PF Applicable', yesNo(salary.pf_applicable)],
            ['ESI Applicable', yesNo(salary.esi_applicable)],
            ['Professional Tax', yesNo(salary.professional_tax_applicable)],
        ]);

        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = fields.length === 0
            ? emptyRow(7, 'No compliance fields submitted yet.')
            : fields.map((field) => `
                <tr>
                    <td>${COMPLIANCE_FIELD_LABELS[field.field_type] || field.field_type}</td>
                    <td>${field.value || '—'}</td>
                    <td>${statusBadge(field.status)}</td>
                    <td>${field.status === 'rejected' && field.notes
        ? `<span class="text-danger small">${field.notes}</span>`
        : '<span class="text-muted">—</span>'}</td>
                    <td>${formatDateTime(field.submitted_at)}</td>
                    <td>${formatReviewCell(field)}</td>
                    <td class="text-end">${renderReviewActions(
        canReviewItem('compliance_field', field.id),
        field.status,
        'compliance_field',
        field.id,
    )}</td>
                </tr>
            `).join('');
    };

    const renderDocumentsTab = (employee, documentTypes = []) => {
        const tableBody = document.getElementById('empProfileDocumentsBody');
        const requiredWrap = document.getElementById('empProfileRequiredDocuments');
        const documents = employee.documents || [];

        if (tableBody) {
            tableBody.innerHTML = documents.length === 0
                ? emptyRow(6, 'No documents uploaded yet.')
                : documents.map((document) => `
                    <tr>
                        <td>${document.document_type?.name || '—'}</td>
                        <td>${statusBadge(document.status)}</td>
                        <td>${document.status === 'rejected' && document.notes
        ? `<span class="text-danger small">${document.notes}</span>`
        : '<span class="text-muted">—</span>'}</td>
                        <td>${formatDateTime(document.created_at)}</td>
                        <td>${formatReviewCell(document)}</td>
                        <td class="text-end">
                            <div class="table-action-group">
                                ${renderViewDocumentIconButton(document.id, document.document_type?.name || 'Document')}
                                ${document.status === 'pending' && canReviewItem('document', document.id)
        ? renderReviewIconActions('data-approve-document', 'data-reject-document', document.id)
        : ''}
                                ${canReviewProfile
        ? renderDeleteDocumentIconButton(document.id, document.document_type?.name || 'Document')
        : ''}
                            </div>
                        </td>
                    </tr>
                `).join('');
        }

        if (requiredWrap) {
            if (documentTypes.length === 0) {
                requiredWrap.innerHTML = '<p class="text-muted small mb-0">No document types configured.</p>';
            } else {
                requiredWrap.innerHTML = documentTypes.map((type) => {
                    const existing = documents.find((document) => document.document_type_id === type.id);
                    let className = '';
                    let icon = '○';
                    let suffix = '';

                    if (existing) {
                        if (existing.status === 'approved') {
                            className = 'profile-required-doc--uploaded';
                            icon = '✓';
                            suffix = ' (Approved)';
                        } else if (existing.status === 'pending') {
                            className = 'profile-required-doc--missing';
                            icon = '⏳';
                            suffix = ' (Pending)';
                        } else if (existing.status === 'rejected') {
                            className = 'profile-required-doc--missing';
                            icon = '✕';
                            suffix = ' (Rejected)';
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

    const renderProfile = (employee, documentTypes = [], pendingReviews = []) => {
        buildReviewableMap(pendingReviews);
        renderPendingReviews(pendingReviews);
        renderHeader(employee);
        renderWorkTab(employee);
        renderSalaryTab(employee);
        renderOtherTab(employee);
        renderPersonalTab(employee);
        renderBankTab(employee);
        renderCompliancesTab(employee);
        renderDocumentsTab(employee, documentTypes);
    };

    const loadProfile = async () => {
        const { data } = await api.get(`/employees/${employeeId}/profile`);
        canReviewProfile = Boolean(data.data.capabilities?.can_review_profile);
        renderProfile(
            data.data.employee,
            data.data.document_types || [],
            data.data.pending_reviews || [],
        );
        return data.data;
    };

    const clearDocumentPreview = () => {
        if (previewBlobUrl) {
            URL.revokeObjectURL(previewBlobUrl);
            previewBlobUrl = null;
        }

        document.getElementById('viewDocumentFrame')?.classList.add('d-none');
        document.getElementById('viewDocumentImage')?.classList.add('d-none');
        document.getElementById('viewDocumentUnsupported')?.classList.add('d-none');
    };

    const closeDocumentLightbox = () => {
        const lightbox = document.getElementById('viewDocumentLightbox');

        if (!lightbox) {
            return;
        }

        lightbox.classList.add('d-none');
        document.body.classList.remove('document-lightbox-open');
        clearDocumentPreview();
    };

    const openDocumentLightbox = (title = 'Document') => {
        const lightbox = document.getElementById('viewDocumentLightbox');
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

    const showDocumentPreview = async (documentId, title) => {
        clearDocumentPreview();
        openDocumentLightbox(title);

        try {
            const response = await api.get(`/employee-documents/${documentId}/download`, { responseType: 'blob' });
            const blob = response.data;
            previewBlobUrl = URL.createObjectURL(blob);
            previewFallbackName = title.replace(/\s+/g, '-').toLowerCase();

            const contentType = blob.type || '';
            const frame = document.getElementById('viewDocumentFrame');
            const image = document.getElementById('viewDocumentImage');
            const unsupported = document.getElementById('viewDocumentUnsupported');

            if (contentType.startsWith('image/')) {
                image.src = previewBlobUrl;
                image.classList.remove('d-none');
            } else if (contentType === 'application/pdf') {
                frame.src = previewBlobUrl;
                frame.classList.remove('d-none');
            } else {
                unsupported?.classList.remove('d-none');
            }
        } catch (error) {
            closeDocumentLightbox();
            alert(getErrorMessage(error));
        }
    };

    const openRejectProfileSubmissionModal = (type, id) => {
        rejectSubmission = { type, id };
        document.getElementById('rejectProfileSubmissionNotes').value = '';
        rejectProfileSubmissionModal = rejectProfileSubmissionModal
            || Modal.getOrCreateInstance(document.getElementById('rejectProfileSubmissionModal'));
        rejectProfileSubmissionModal.show();
    };

    const handleApproveClick = async (event) => {
        const approveMap = [
            ['data-approve-document', (id) => `/employee-documents/${id}/approve`],
            ['data-approve-payment-method', (id) => `/employee-payment-methods/${id}/approve`],
            ['data-approve-compliance-field', (id) => `/employee-compliance-fields/${id}/approve`],
            ['data-approve-personal-section', (id) => `/employee-personal-sections/${id}/approve`],
            ['data-approve-family-member', (id) => `/employee-family-members/${id}/approve`],
        ];

        for (const [attr, buildUrl] of approveMap) {
            const button = event.target.closest(`[${attr}]`);

            if (!button) {
                continue;
            }

            try {
                await api.patch(buildUrl(button.getAttribute(attr)));
                await loadProfile();
            } catch (error) {
                alert(getErrorMessage(error));
            }

            return;
        }
    };

    const handleRejectClick = (event) => {
        const rejectMap = [
            ['data-reject-document', 'document'],
            ['data-reject-payment-method', 'payment_method'],
            ['data-reject-compliance-field', 'compliance_field'],
            ['data-reject-personal-section', 'personal_section'],
            ['data-reject-family-member', 'family_member'],
        ];

        for (const [attr, type] of rejectMap) {
            const button = event.target.closest(`[${attr}]`);

            if (!button) {
                continue;
            }

            if (type === 'document') {
                rejectDocumentId = button.getAttribute(attr);
                document.getElementById('rejectDocumentNotes').value = '';
                rejectDocumentModal = rejectDocumentModal
                    || Modal.getOrCreateInstance(document.getElementById('rejectDocumentModal'));
                rejectDocumentModal.show();
            } else {
                openRejectProfileSubmissionModal(type, button.getAttribute(attr));
            }

            return;
        }
    };

    const handleDeleteDocumentClick = async (button) => {
        const title = button.dataset.deleteTitle || 'this document';

        if (!window.confirm(`Delete ${title}? This will permanently remove the file.`)) {
            return;
        }

        try {
            await api.delete(`/employee-documents/${button.dataset.deleteDocument}`);
            await loadProfile();
        } catch (error) {
            alert(getErrorMessage(error));
        }
    };

    document.getElementById('empProfileTabContent')?.addEventListener('click', async (event) => {
        const viewBtn = event.target.closest('[data-view-document]');
        const deleteBtn = event.target.closest('[data-delete-document]');

        if (deleteBtn) {
            await handleDeleteDocumentClick(deleteBtn);
            return;
        }

        if (viewBtn) {
            await showDocumentPreview(viewBtn.dataset.viewDocument, viewBtn.dataset.viewTitle || 'Document');
            return;
        }

        await handleApproveClick(event);
        handleRejectClick(event);
    });

    document.getElementById('empProfilePendingSection')?.addEventListener('click', async (event) => {
        const viewBtn = event.target.closest('[data-view-document]');

        if (viewBtn) {
            await showDocumentPreview(viewBtn.dataset.viewDocument, viewBtn.dataset.viewTitle || 'Document');
            return;
        }

        await handleApproveClick(event);
        handleRejectClick(event);
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
            await loadProfile();
        } catch (error) {
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
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
            rejectDocumentModal?.hide();
            rejectDocumentId = null;
            await loadProfile();
        } catch (error) {
            alert(getErrorMessage(error));
        } finally {
            setSubmitLoading(submitBtn, false);
        }
    });

    document.getElementById('viewDocumentLightboxClose')?.addEventListener('click', closeDocumentLightbox);
    document.getElementById('viewDocumentOpenTab')?.addEventListener('click', () => {
        if (previewBlobUrl) {
            window.open(previewBlobUrl, '_blank');
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

    if (!employeeId) {
        showAlert('Employee not found.');
        return;
    }

    const renderTimeline = (entries = []) => {
        const container = document.getElementById('empProfileTimelineList');

        if (!container) {
            return;
        }

        if (!entries.length) {
            container.innerHTML = '<div class="text-muted py-4 text-center">No timeline entries found for this employee yet.</div>';
            return;
        }

        container.innerHTML = entries.map((entry) => {
            const changes = [];

            if (entry.old_values && entry.new_values) {
                Object.keys(entry.new_values).forEach((key) => {
                    changes.push(`<div class="small"><strong>${key}:</strong> ${entry.old_values[key] ?? '—'} → ${entry.new_values[key] ?? '—'}</div>`);
                });
            }

            return `
                <div class="activity-timeline-item">
                    <div class="activity-timeline-marker ${entry.status === 'failure' ? 'is-failure' : ''}"></div>
                    <div class="activity-timeline-body">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong>${entry.message || entry.action || 'Activity'}</strong>
                            <span class="text-muted small">${formatDateTime(entry.logged_at)}</span>
                        </div>
                        <div class="text-muted small">${entry.user_name || 'System'} · ${entry.module || 'system'} · ${entry.action || 'activity'}</div>
                        ${entry.action_note ? `<div class="small mt-1">Note: ${entry.action_note}</div>` : ''}
                        ${entry.failure_reason ? `<div class="small text-danger mt-1">${entry.failure_reason}</div>` : ''}
                        ${changes.length ? `<div class="mt-2">${changes.join('')}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    };

    let timelineLoaded = false;

    const loadTimeline = async () => {
        const container = document.getElementById('empProfileTimelineList');

        if (!container) {
            return;
        }

        container.innerHTML = '<div class="text-muted py-4 text-center">Loading timeline…</div>';

        try {
            const { data } = await api.get(`/activity-logs/employees/${employeeId}/timeline`);
            renderTimeline(data.data?.entries || data.entries || []);
            timelineLoaded = true;
        } catch (error) {
            container.innerHTML = `<div class="text-danger py-4 text-center">${getErrorMessage(error, 'Unable to load timeline.')}</div>`;
        }
    };

    document.getElementById('emp-profile-timeline-tab')?.addEventListener('shown.bs.tab', () => {
        if (!timelineLoaded) {
            loadTimeline();
        }
    });

    document.getElementById('empProfileTimelineRefresh')?.addEventListener('click', loadTimeline);

    try {
        await loadProfile();
    } catch (error) {
        showAlert(getErrorMessage(error));
    }
});
