import { Modal } from 'bootstrap';

const modalEl = () => document.getElementById('logoLightboxModal');

const bindLightboxEvents = () => {
    const element = modalEl();

    if (!element || element.dataset.lightboxBound === 'true') {
        return;
    }

    element.dataset.lightboxBound = 'true';
    element.addEventListener('show.bs.modal', () => {
        document.body.classList.add('logo-lightbox-open');
    });
    element.addEventListener('hidden.bs.modal', () => {
        document.body.classList.remove('logo-lightbox-open');
    });
};

export const openLogoLightbox = (src) => {
    const element = modalEl();
    const imageEl = document.getElementById('logoLightboxImage');

    if (!element || !imageEl || !src || src === window.location.href) {
        return;
    }

    bindLightboxEvents();
    imageEl.src = src;
    Modal.getOrCreateInstance(element).show();
};
