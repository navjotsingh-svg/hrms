const EMPLOYEE_STRENGTH_OPTIONS = ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'];
const PHONE_REGEX = /^[6-9]\d{9}$/;
const GSTIN_REGEX = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
const PAN_REGEX = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const LOGO_MAX_BYTES = 2048 * 1024;
const LOGO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'image/svg+xml'];

export const COMPANY_FORM_FIELDS = [
    'name',
    'legal_name',
    'email',
    'phone',
    'website',
    'industry',
    'founded_year',
    'employee_strength',
    'registration_number',
    'gstin',
    'pan_number',
    'contact_person_name',
    'contact_person_email',
    'contact_person_phone',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'country',
    'postal_code',
    'timezone',
    'status',
    'description',
];

const DESCRIPTION_MAX_LENGTH = 5000;

const stripHtml = (html) => {
    const element = document.createElement('div');
    element.innerHTML = html;

    return (element.textContent || element.innerText || '').trim();
};

export const COMPANY_ASYNC_FIELDS = [
    'email',
    'phone',
    'registration_number',
    'gstin',
    'pan_number',
    'contact_person_phone',
];

const currentYear = () => new Date().getFullYear();

const isEmpty = (value) => value === null || value === undefined || String(value).trim() === '';

const maxLengthError = (value, max, label) => {
    if (value.length > max) {
        return `${label} must not exceed ${max} characters.`;
    }

    return null;
};

const isValidUrl = (value) => {
    try {
        const url = new URL(value);

        return ['http:', 'https:'].includes(url.protocol);
    } catch {
        return false;
    }
};

export const validateCompanyField = (field, value) => {
    if (field === 'logo') {
        if (!value) {
            return null;
        }

        if (!LOGO_MIME_TYPES.includes(value.type)) {
            return 'Logo must be a JPEG, PNG, JPG, WebP, or SVG image.';
        }

        if (value.size > LOGO_MAX_BYTES) {
            return 'Logo must not exceed 2 MB.';
        }

        return null;
    }

    const trimmed = typeof value === 'string' ? value.trim() : value;

    switch (field) {
        case 'name':
            if (isEmpty(trimmed)) {
                return 'Company name is required.';
            }

            return maxLengthError(trimmed, 255, 'Company name');

        case 'legal_name':
            if (isEmpty(trimmed)) {
                return null;
            }

            return maxLengthError(trimmed, 255, 'Legal name');

        case 'email':
            if (isEmpty(trimmed)) {
                return 'Company email is required.';
            }

            if (!EMAIL_REGEX.test(trimmed)) {
                return 'Please enter a valid email address.';
            }

            return maxLengthError(trimmed, 255, 'Email');

        case 'phone':
            if (isEmpty(trimmed)) {
                return null;
            }

            if (!/^\d{10}$/.test(trimmed)) {
                return 'Phone number must be exactly 10 digits.';
            }

            if (!PHONE_REGEX.test(trimmed)) {
                return 'Phone number must be a valid 10-digit Indian mobile number.';
            }

            return null;

        case 'website':
            if (isEmpty(trimmed)) {
                return null;
            }

            if (!isValidUrl(trimmed)) {
                return 'Please enter a valid website URL.';
            }

            return maxLengthError(trimmed, 255, 'Website');

        case 'industry':
            if (isEmpty(trimmed)) {
                return null;
            }

            return maxLengthError(trimmed, 255, 'Industry');

        case 'founded_year':
            if (isEmpty(trimmed)) {
                return null;
            }

            if (!/^\d{4}$/.test(trimmed)) {
                return 'Founded year must be exactly 4 digits.';
            }

            const year = Number(trimmed);

            if (year < 1800 || year > currentYear()) {
                return `Founded year must be between 1800 and ${currentYear()}.`;
            }

            return null;

        case 'employee_strength':
            if (isEmpty(trimmed)) {
                return null;
            }

            if (!EMPLOYEE_STRENGTH_OPTIONS.includes(trimmed)) {
                return 'Please select a valid employee strength range.';
            }

            return null;

        case 'registration_number':
            if (isEmpty(trimmed)) {
                return null;
            }

            return maxLengthError(trimmed, 100, 'Registration number');

        case 'gstin':
            if (isEmpty(trimmed)) {
                return null;
            }

            const gstin = trimmed.toUpperCase();

            if (gstin.length !== 15) {
                return 'GSTIN must be exactly 15 characters.';
            }

            if (!GSTIN_REGEX.test(gstin)) {
                return 'GSTIN format is invalid.';
            }

            return null;

        case 'pan_number':
            if (isEmpty(trimmed)) {
                return null;
            }

            const pan = trimmed.toUpperCase();

            if (pan.length !== 10) {
                return 'PAN number must be exactly 10 characters.';
            }

            if (!PAN_REGEX.test(pan)) {
                return 'PAN number format is invalid.';
            }

            return null;

        case 'contact_person_name':
            if (isEmpty(trimmed)) {
                return null;
            }

            return maxLengthError(trimmed, 255, 'Contact person name');

        case 'contact_person_email':
            if (isEmpty(trimmed)) {
                return null;
            }

            if (!EMAIL_REGEX.test(trimmed)) {
                return 'Please enter a valid contact person email.';
            }

            return maxLengthError(trimmed, 255, 'Contact person email');

        case 'contact_person_phone':
            if (isEmpty(trimmed)) {
                return null;
            }

            if (!/^\d{10}$/.test(trimmed)) {
                return 'Contact phone must be exactly 10 digits.';
            }

            if (!PHONE_REGEX.test(trimmed)) {
                return 'Contact phone must be a valid 10-digit Indian mobile number.';
            }

            return null;

        case 'address_line_1':
        case 'address_line_2':
            if (isEmpty(trimmed)) {
                return null;
            }

            return maxLengthError(trimmed, 255, 'Address');

        case 'city':
        case 'state':
            if (isEmpty(trimmed)) {
                return null;
            }

            return maxLengthError(trimmed, 100, field === 'city' ? 'City' : 'State');

        case 'country':
            if (isEmpty(trimmed)) {
                return null;
            }

            return maxLengthError(trimmed, 100, 'Country');

        case 'postal_code':
            if (isEmpty(trimmed)) {
                return null;
            }

            if (!/^\d{6}$/.test(trimmed)) {
                return 'Postal code must be exactly 6 digits.';
            }

            return null;

        case 'timezone':
            return null;

        case 'status':
            if (isEmpty(trimmed)) {
                return 'Status is required.';
            }

            if (!['active', 'inactive'].includes(trimmed)) {
                return 'Please select a valid status.';
            }

            return null;

        case 'description':
            if (isEmpty(trimmed)) {
                return null;
            }

            const plainText = stripHtml(trimmed);

            if (plainText === '') {
                return null;
            }

            return maxLengthError(plainText, DESCRIPTION_MAX_LENGTH, 'Company description');

        default:
            return null;
    }
};

export const getCompanyFieldValue = (form, field) => {
    const input = form.querySelector(`[name="${field}"]`);

    if (!input) {
        return '';
    }

    return input.value;
};

export const validateCompanyForm = (form, logoFile = null) => {
    const errors = {};

    COMPANY_FORM_FIELDS.forEach((field) => {
        const message = validateCompanyField(field, getCompanyFieldValue(form, field));

        if (message) {
            errors[field] = message;
        }
    });

    const logoMessage = validateCompanyField('logo', logoFile);

    if (logoMessage) {
        errors.logo = logoMessage;
    }

    return {
        valid: Object.keys(errors).length === 0,
        errors,
    };
};
