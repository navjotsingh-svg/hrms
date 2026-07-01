import api from './api';

let previewBlobUrl = null;
let previewSourceUrl = null;
let previewFallbackName = 'attachment';

const lightbox = () => document.getElementById('viewDocumentLightbox');

const isPublicAssetUrl = (url) => {
    const value = String(url || '').trim();

    return value.startsWith('/images/')
        || value.startsWith('/storage/')
        || value.startsWith('images/')
        || value.startsWith('storage/');
};

export const normalizePublicAssetUrl = (url) => {
    const value = String(url || '').trim();

    if (! value) {
        return '';
    }

    if (value.startsWith('images/') || value.startsWith('storage/')) {
        return `/${value}`;
    }

    return value;
};

export const isPublicAttachmentUrl = isPublicAssetUrl;

const imageExtension = (url) => {
    const match = String(url || '').toLowerCase().match(/\.([a-z0-9]+)(?:\?|$)/);

    return match?.[1] || '';
};

const clearPreview = () => {
    if (previewBlobUrl) {
        URL.revokeObjectURL(previewBlobUrl);
        previewBlobUrl = null;
    }

    previewSourceUrl = null;

    const frame = document.getElementById('viewDocumentFrame');
    const image = document.getElementById('viewDocumentImage');
    const unsupported = document.getElementById('viewDocumentUnsupported');

    if (frame) {
        frame.src = '';
        frame.classList.add('d-none');
    }

    if (image) {
        image.removeAttribute('src');
        image.classList.add('d-none');
    }

    unsupported?.classList.add('d-none');
};

const closeLightbox = () => {
    clearPreview();
    lightbox()?.classList.add('d-none');
    document.body.classList.remove('document-lightbox-open');
};

const openLightbox = (title) => {
    const titleEl = document.getElementById('viewDocumentLightboxTitle');

    if (titleEl) {
        titleEl.textContent = title || 'Attachment Preview';
    }

    lightbox()?.classList.remove('d-none');
    document.body.classList.add('document-lightbox-open');
};

const normalizeApiPath = (url) => {
    const value = String(url || '').trim();

    if (!value || value === '#') {
        throw new Error('Attachment is not available.');
    }

    return value.replace(/^\/api\/v1/, '');
};

export const previewRequestAttachment = async (url, title = 'Attachment') => {
    clearPreview();
    openLightbox(title);

    const value = String(url || '').trim();
    const frame = document.getElementById('viewDocumentFrame');
    const image = document.getElementById('viewDocumentImage');
    const unsupported = document.getElementById('viewDocumentUnsupported');

    previewFallbackName = title.replace(/\s+/g, '-').toLowerCase() || 'attachment';

    if (isPublicAssetUrl(value)) {
        previewSourceUrl = normalizePublicAssetUrl(value);
        const extension = imageExtension(previewSourceUrl);

        if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(extension)) {
            image.src = previewSourceUrl;
            image.classList.remove('d-none');
            return;
        }

        if (extension === 'pdf') {
            frame.src = previewSourceUrl;
            frame.classList.remove('d-none');
            return;
        }

        unsupported?.classList.remove('d-none');
        return;
    }

    const response = await api.get(normalizeApiPath(value), { responseType: 'blob' });
    const blob = response.data;
    const contentType = blob.type || response.headers['content-type'] || '';
    const disposition = response.headers['content-disposition'];
    const match = disposition?.match(/filename="?([^"]+)"?/);

    previewFallbackName = match?.[1] || previewFallbackName;
    previewBlobUrl = URL.createObjectURL(blob);

    if (contentType.startsWith('image/')) {
        image.src = previewBlobUrl;
        image.classList.remove('d-none');
        return;
    }

    if (contentType === 'application/pdf') {
        frame.src = previewBlobUrl;
        frame.classList.remove('d-none');
        return;
    }

    unsupported?.classList.remove('d-none');
};

export const bindRequestAttachmentLightbox = () => {
    document.getElementById('viewDocumentLightboxClose')?.addEventListener('click', closeLightbox);

    document.getElementById('viewDocumentFallbackDownload')?.addEventListener('click', () => {
        if (!previewBlobUrl) {
            return;
        }

        const link = document.createElement('a');
        link.href = previewBlobUrl;
        link.download = previewFallbackName;
        link.click();
    });

    document.getElementById('viewDocumentOpenTab')?.addEventListener('click', () => {
        const targetUrl = previewBlobUrl || previewSourceUrl;

        if (!targetUrl) {
            return;
        }

        window.open(targetUrl, '_blank', 'noopener,noreferrer');
    });

    lightbox()?.addEventListener('click', (event) => {
        if (event.target === lightbox() || event.target.classList.contains('document-lightbox-stage')) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !lightbox()?.classList.contains('d-none')) {
            closeLightbox();
        }
    });
};

export const bindRequestAttachmentHandlers = (root, { onError } = {}) => {
    if (!root) {
        return;
    }

    root.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-request-attachment]');

        if (!trigger) {
            return;
        }

        event.preventDefault();

        try {
            await previewRequestAttachment(
                trigger.dataset.requestAttachment,
                trigger.dataset.requestAttachmentLabel || 'Attachment',
            );
        } catch (error) {
            closeLightbox();
            onError?.(error);
        }
    });
};

export const loadProfilePhotoPreviews = async (root, { onError } = {}) => {
    await loadAttachmentImagePreviews(root, '[data-profile-photo-preview]', { onError });
};

export const loadAttachmentImagePreviews = async (root, selector = '[data-request-attachment-preview]', { onError } = {}) => {
    if (!root) {
        return;
    }

    const images = root.querySelectorAll(selector);

    await Promise.all(Array.from(images).map(async (image) => {
        const url = image.dataset.profilePhotoPreview || image.dataset.requestAttachmentPreview;

        if (!url || image.dataset.loaded === 'true') {
            return;
        }

        try {
            if (image.getAttribute('src')) {
                image.dataset.loaded = 'true';
                return;
            }

            const normalizedUrl = normalizePublicAssetUrl(url);

            if (isPublicAssetUrl(url)) {
                image.src = normalizedUrl;
                image.dataset.loaded = 'true';
                return;
            }

            const response = await api.get(normalizeApiPath(url), { responseType: 'blob' });
            const blob = response.data;

            if (!blob.type?.startsWith('image/')) {
                return;
            }

            image.src = URL.createObjectURL(blob);
            image.dataset.loaded = 'true';
        } catch (error) {
            onError?.(error);
        }
    }));
};
