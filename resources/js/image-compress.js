const replaceExtension = (filename, extension) => {
    const base = filename.replace(/\.[^.]+$/, '');
    return `${base}.${extension}`;
};

export const compressImageFile = (file, maxWidth = 1200, quality = 0.75) => new Promise((resolve) => {
    if (!file.type?.startsWith('image/')) {
        resolve(file);
        return;
    }

    const drawToCanvas = async () => {
        if (typeof createImageBitmap === 'function') {
            try {
                const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                let { width, height } = bitmap;

                if (width > maxWidth) {
                    height = Math.round((height / width) * maxWidth);
                    width = maxWidth;
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const context = canvas.getContext('2d');

                if (!context) {
                    bitmap.close?.();
                    return null;
                }

                context.drawImage(bitmap, 0, 0, width, height);
                bitmap.close?.();

                return canvas;
            } catch {
                return null;
            }
        }

        return new Promise((resolveCanvas) => {
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
                    resolveCanvas(null);
                    return;
                }

                context.drawImage(image, 0, 0, width, height);
                resolveCanvas(canvas);
            };

            image.onerror = () => {
                URL.revokeObjectURL(objectUrl);
                resolveCanvas(null);
            };

            image.src = objectUrl;
        });
    };

    drawToCanvas().then((canvas) => {
        if (!canvas) {
            resolve(file);
            return;
        }

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
    });
});

export const compressImageFiles = (files, maxWidth = 1200, quality = 0.75) => Promise.all(
    Array.from(files).map((file) => compressImageFile(file, maxWidth, quality)),
);
