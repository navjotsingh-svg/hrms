import api, { getErrorMessage } from './api';
import { applyCompanyHeaderLogo } from './logo-display';
import { openLogoLightbox } from './logo-lightbox';

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('companyShowRoot');

    if (!root) {
        return;
    }

    const companyId = root.dataset.companyId;
    const setText = (field, value) => {
        const element = document.querySelector(`[data-field="${field}"]`);

        if (element) {
            element.textContent = value || '—';
        }
    };

    try {
        const { data } = await api.get(`/companies/${companyId}`);
        const company = data.data.company;

        setText('title', company.name);
        setText('subtitle', company.legal_name || company.email);

        const logoWrap = document.querySelector('[data-field="logo-wrap"]');

        if (logoWrap) {
            if (company.logo_url) {
                logoWrap.innerHTML = `
                    <button type="button" class="company-header-logo-btn" data-field="logo-trigger" title="View logo">
                        <span class="company-header-logo-frame">
                            <img src="${company.logo_url}" alt="${company.name}" class="company-header-logo" loading="eager" decoding="async">
                        </span>
                    </button>
                `;

                const logoTrigger = logoWrap.querySelector('[data-field="logo-trigger"]');
                const logoImage = logoWrap.querySelector('.company-header-logo');

                applyCompanyHeaderLogo(logoImage);

                logoTrigger?.addEventListener('click', () => {
                    openLogoLightbox(company.logo_url);
                });
            } else {
                logoWrap.innerHTML = `<div class="company-detail-logo-default">${company.name.substring(0, 2).toUpperCase()}</div>`;
            }
        }

        setText('email', company.email);
        setText('phone', company.phone);
        setText('industry', company.industry);
        setText('founded_year', company.founded_year);
        setText('employee_strength', company.employee_strength);
        setText('timezone', company.timezone);
        setText('registration_number', company.registration_number);
        setText('gstin', company.gstin);
        setText('pan_number', company.pan_number);
        setText('address', company.full_address);
        setText('contact_person_name', company.contact_person_name);
        setText('contact_person_email', company.contact_person_email);
        setText('contact_person_phone', company.contact_person_phone);

        const websiteEl = root.querySelector('[data-field="website"]');

        if (websiteEl) {
            websiteEl.innerHTML = company.website
                ? `<a href="${company.website}" target="_blank">${company.website}</a>`
                : '—';
        }

        const statusBadge = root.querySelector('[data-field="status-badge"]');

        if (statusBadge) {
            statusBadge.className = `badge ${company.status === 'active' ? 'bg-success' : 'bg-secondary'} fs-6`;
            statusBadge.textContent = company.status.charAt(0).toUpperCase() + company.status.slice(1);
        }

        const adminName = root.querySelector('[data-field="admin_name"]');
        const adminEmail = root.querySelector('[data-field="admin_email"]');
        const adminEmpty = root.querySelector('[data-field="admin_empty"]');

        if (company.admin_user) {
            if (adminName) {
                adminName.textContent = company.admin_user.name;
            }

            if (adminEmail) {
                adminEmail.textContent = company.admin_user.email;
            }

            if (adminEmpty) {
                adminEmpty.classList.add('d-none');
            }
        } else if (adminEmpty) {
            adminEmpty.classList.remove('d-none');
        }

        const descriptionEl = root.querySelector('[data-field="description"]');
        const descriptionCard = root.querySelector('[data-field="description-card"]');

        if (descriptionEl) {
            descriptionEl.innerHTML = company.description || '';
        }

        if (descriptionCard) {
            descriptionCard.classList.toggle('d-none', !company.description);
        }
    } catch (error) {
        root.innerHTML = `<div class="alert alert-danger">${getErrorMessage(error)}</div>`;
    }
});
