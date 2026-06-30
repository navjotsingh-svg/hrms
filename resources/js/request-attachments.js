import api from './api';

let previewBlobUrl = null;
let previewFallbackName = 'attachment';

const lightbox = () => document.getElementById('viewDocumentLightbox');

const clearPreview = () => {
    if (previewBlobUrl) {
        URL.revokeObjectURL(previewBlobUrl);
        previewBlobUrl = null;
    }

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

    const response = await api.get(normalizeApiPath(url), { responseType: 'blob' });
    const blob = response.data;
    const contentType = blob.type || response.headers['content-type'] || '';
    const disposition = response.headers['content-disposition'];
    const match = disposition?.match(/filename="?([^"]+)"?/);

    previewFallbackName = match?.[1] || title.replace(/\s+/g, '-').toLowerCase() || 'attachment';
    previewBlobUrl = URL.createObjectURL(blob);

    const frame = document.getElementById('viewDocumentFrame');
    const image = document.getElementById('viewDocumentImage');
    const unsupported = document.getElementById('viewDocumentUnsupported');

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
        if (!previewBlobUrl) {
            return;
        }

        window.open(previewBlobUrl, '_blank', 'noopener,noreferrer');
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
    if (!root) {
        return;
    }

    const images = root.querySelectorAll('[data-profile-photo-preview]');

    await Promise.all(Array.from(images).map(async (image) => {
        const url = image.dataset.profilePhotoPreview;

        if (!url || image.dataset.loaded === 'true') {
            return;
        }

        try {
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
