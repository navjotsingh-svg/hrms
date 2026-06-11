export const applyCompanyHeaderLogo = (img) => {
    const frame = img?.closest('.company-header-logo-frame');

    if (!frame || !img) {
        return;
    }

    const setFrame = () => {
        const { naturalWidth, naturalHeight } = img;

        if (!naturalWidth || !naturalHeight) {
            return;
        }

        const ratio = naturalWidth / naturalHeight;

        frame.classList.remove(
            'company-header-logo-frame--wide',
            'company-header-logo-frame--square',
            'company-header-logo-frame--tall',
        );

        if (ratio >= 1.35) {
            frame.classList.add('company-header-logo-frame--wide');
        } else if (ratio <= 0.8) {
            frame.classList.add('company-header-logo-frame--tall');
        } else {
            frame.classList.add('company-header-logo-frame--square');
        }
    };

    if (img.complete && img.naturalWidth) {
        setFrame();
    } else {
        img.addEventListener('load', setFrame, { once: true });
    }
};
