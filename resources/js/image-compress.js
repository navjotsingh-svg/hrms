const replaceExtension = (filename, extension) => {
    const base = filename.replace(/\.[^.]+$/, '');
    return `${base}.${extension}`;
};

export const compressImageFile = (file, maxWidth = 1200, quality = 0.75) => new Promise((resolve) => {
    if (!file.type?.startsWith('image/')) {
        resolve(file);
        return;
    }

    const image = new Image();
    const objectUrl = URL.createObjectURL(file);

    image.onload = () => {
        URL.revokeObjectURL(objectUrl);

        let { width, height } = image;

        if (width > maxWidth) {
            height = Math.round((height / width) * maxWidth);
            width = maxWidth;
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');

        if (!context) {
            resolve(file);
            return;
        }

        context.drawImage(image, 0, 0, width, height);
        canvas.toBlob((blob) => {
            if (!blob) {
                resolve(file);
                return;
            }

            resolve(new File(
                [blob],
                replaceExtension(file.name, 'jpg'),
                { type: 'image/jpeg', lastModified: Date.now() },
            ));
        }, 'image/jpeg', quality);
    };

    image.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        resolve(file);
    };

    image.src = objectUrl;
});

export const compressImageFiles = (files, maxWidth = 1200, quality = 0.75) => Promise.all(
    Array.from(files).map((file) => compressImageFile(file, maxWidth, quality)),
);
