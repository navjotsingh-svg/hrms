import { Modal } from 'bootstrap';

const CROP_SIZE = 480;
const FRAME_RATIO = 0.62;

const replaceExtension = (filename, extension) => {
    const base = filename.replace(/\.[^.]+$/, '') || 'profile-photo';
    return `${base}.${extension}`;
};

const normalizeImageOrientation = async (file) => {
    if (!file.type?.startsWith('image/') || typeof createImageBitmap !== 'function') {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const canvas = document.createElement('canvas');
        canvas.width = bitmap.width;
        canvas.height = bitmap.height;

        const context = canvas.getContext('2d');

        if (!context) {
            bitmap.close?.();
            return file;
        }

        context.drawImage(bitmap, 0, 0);
        bitmap.close?.();

        const blob = await new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', 0.92);
        });

        if (!blob) {
            return file;
        }

        return new File([blob], replaceExtension(file.name, 'jpg'), {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    } catch {
        return file;
    }
};

let modal = null;
let modalEl = null;
let stageEl = null;
let canvasEl = null;
let zoomInBtn = null;
let zoomOutBtn = null;
let zoomResetBtn = null;
let pendingFile = null;
let pendingResolve = null;
let pendingReject = null;
let initialized = false;
let cropSessionId = 0;
let resizeObserver = null;
let activePointerId = null;

let sourceImage = null;
let displayScale = 1;
let offsetX = 0;
let offsetY = 0;
let frameRect = { left: 0, top: 0, size: 0 };
let isDragging = false;
let dragStartX = 0;
let dragStartY = 0;
let dragOriginX = 0;
let dragOriginY = 0;

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

const getFrameRect = (width, height) => {
    const size = Math.min(width, height) * FRAME_RATIO;

    return {
        left: (width - size) / 2,
        top: (height - size) / 2,
        size,
    };
};

const getDisplayedSize = () => {
    if (!sourceImage) {
        return { width: 0, height: 0 };
    }

    return {
        width: sourceImage.naturalWidth * displayScale,
        height: sourceImage.naturalHeight * displayScale,
    };
};

const clampOffsets = () => {
    if (!canvasEl || !sourceImage) {
        return;
    }

    const { width, height } = canvasEl;
    const displayed = getDisplayedSize();
    frameRect = getFrameRect(width, height);

    const minOffsetX = frameRect.left + frameRect.size - displayed.width;
    const maxOffsetX = frameRect.left;
    const minOffsetY = frameRect.top + frameRect.size - displayed.height;
    const maxOffsetY = frameRect.top;

    offsetX = clamp(offsetX, minOffsetX, maxOffsetX);
    offsetY = clamp(offsetY, minOffsetY, maxOffsetY);
};

const fitImageToFrame = () => {
    if (!canvasEl || !sourceImage?.naturalWidth) {
        return;
    }

    frameRect = getFrameRect(canvasEl.width, canvasEl.height);
    displayScale = Math.max(
        frameRect.size / sourceImage.naturalWidth,
        frameRect.size / sourceImage.naturalHeight,
    ) * 1.05;

    const displayed = getDisplayedSize();
    offsetX = frameRect.left + (frameRect.size - displayed.width) / 2;
    offsetY = frameRect.top + (frameRect.size - displayed.height) / 2;
    clampOffsets();
};

const drawCropStage = () => {
    if (!canvasEl || !sourceImage) {
        return;
    }

    const context = canvasEl.getContext('2d');

    if (!context) {
        return;
    }

    const { width, height } = canvasEl;
    frameRect = getFrameRect(width, height);

    context.clearRect(0, 0, width, height);
    context.fillStyle = '#0f172a';
    context.fillRect(0, 0, width, height);

    const displayed = getDisplayedSize();
    context.drawImage(
        sourceImage,
        0,
        0,
        sourceImage.naturalWidth,
        sourceImage.naturalHeight,
        offsetX,
        offsetY,
        displayed.width,
        displayed.height,
    );

    context.fillStyle = 'rgba(15, 23, 42, 0.55)';
    context.fillRect(0, 0, width, frameRect.top);
    context.fillRect(0, frameRect.top, frameRect.left, frameRect.size);
    context.fillRect(frameRect.left + frameRect.size, frameRect.top, width - frameRect.left - frameRect.size, frameRect.size);
    context.fillRect(0, frameRect.top + frameRect.size, width, height - frameRect.top - frameRect.size);

    context.strokeStyle = '#2563eb';
    context.lineWidth = 3;
    context.strokeRect(frameRect.left, frameRect.top, frameRect.size, frameRect.size);
};

const resizeCanvas = () => {
    if (!canvasEl || !stageEl) {
        return false;
    }

    const rect = stageEl.getBoundingClientRect();
    const width = Math.max(Math.floor(rect.width), 280);
    const height = Math.max(Math.floor(rect.height), 280);

    if (width <= 0 || height <= 0) {
        return false;
    }

    canvasEl.width = width;
    canvasEl.height = height;

    if (sourceImage) {
        fitImageToFrame();
        drawCropStage();
    }

    return true;
};

const waitForStageLayout = async (sessionId) => {
    for (let attempt = 0; attempt < 20; attempt += 1) {
        if (sessionId !== cropSessionId) {
            return false;
        }

        if (resizeCanvas()) {
            return true;
        }

        await new Promise((resolve) => {
            window.requestAnimationFrame(() => resolve());
        });
    }

    return resizeCanvas();
};

const loadSourceImage = (file) => new Promise((resolve, reject) => {
    const image = new Image();
    const objectUrl = URL.createObjectURL(file);

    image.onload = () => {
        URL.revokeObjectURL(objectUrl);
        resolve(image);
    };

    image.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        reject(new Error('Unable to load image.'));
    };

    image.src = objectUrl;
});

const getStagePoint = (clientX, clientY) => {
    const rect = canvasEl?.getBoundingClientRect();

    if (!rect) {
        return { x: clientX, y: clientY };
    }

    const scaleX = canvasEl.width / rect.width;
    const scaleY = canvasEl.height / rect.height;

    return {
        x: (clientX - rect.left) * scaleX,
        y: (clientY - rect.top) * scaleY,
    };
};

const beginDrag = (clientX, clientY) => {
    if (!sourceImage) {
        return;
    }

    isDragging = true;
    dragStartX = clientX;
    dragStartY = clientY;
    dragOriginX = offsetX;
    dragOriginY = offsetY;
};

const moveDrag = (clientX, clientY) => {
    if (!isDragging || !sourceImage) {
        return;
    }

    const rect = canvasEl?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    const scaleX = canvasEl.width / rect.width;
    const scaleY = canvasEl.height / rect.height;

    offsetX = dragOriginX + ((clientX - dragStartX) * scaleX);
    offsetY = dragOriginY + ((clientY - dragStartY) * scaleY);
    clampOffsets();
    drawCropStage();
};

const endDrag = () => {
    isDragging = false;
    activePointerId = null;
};

const applyZoom = (delta, clientX, clientY) => {
    if (!sourceImage || !canvasEl) {
        return;
    }

    const point = getStagePoint(clientX, clientY);
    const imageX = (point.x - offsetX) / displayScale;
    const imageY = (point.y - offsetY) / displayScale;
    const nextScale = clamp(displayScale * (1 + delta), 0.05, 8);

    offsetX = point.x - imageX * nextScale;
    offsetY = point.y - imageY * nextScale;
    displayScale = nextScale;
    clampOffsets();
    drawCropStage();
};

const onPointerDown = (event) => {
    if (!sourceImage || event.button > 0) {
        return;
    }

    activePointerId = event.pointerId;
    stageEl?.setPointerCapture?.(event.pointerId);
    beginDrag(event.clientX, event.clientY);
    event.preventDefault();
    event.stopPropagation();
};

const onPointerMove = (event) => {
    if (!isDragging || (activePointerId !== null && event.pointerId !== activePointerId)) {
        return;
    }

    moveDrag(event.clientX, event.clientY);
    event.preventDefault();
};

const onPointerUp = (event) => {
    if (activePointerId !== null && event.pointerId !== activePointerId) {
        return;
    }

    stageEl?.releasePointerCapture?.(event.pointerId);
    endDrag();
    event.preventDefault();
};

const onMouseDown = (event) => {
    if (event.button !== 0 || activePointerId !== null) {
        return;
    }

    beginDrag(event.clientX, event.clientY);
    event.preventDefault();
};

const onMouseMove = (event) => {
    if (!isDragging || activePointerId !== null) {
        return;
    }

    moveDrag(event.clientX, event.clientY);
    event.preventDefault();
};

const onMouseUp = () => {
    if (activePointerId !== null) {
        return;
    }

    endDrag();
};

const onWheel = (event) => {
    if (!sourceImage || !canvasEl) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const delta = event.deltaY > 0 ? -0.1 : 0.1;
    applyZoom(delta, event.clientX, event.clientY);
};

const bindStageEvents = () => {
    if (!stageEl || stageEl.dataset.cropBound === 'true') {
        return;
    }

    stageEl.dataset.cropBound = 'true';
    stageEl.addEventListener('pointerdown', onPointerDown);
    stageEl.addEventListener('pointermove', onPointerMove);
    stageEl.addEventListener('pointerup', onPointerUp);
    stageEl.addEventListener('pointercancel', onPointerUp);
    stageEl.addEventListener('mousedown', onMouseDown);
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
    stageEl.addEventListener('wheel', onWheel, { passive: false });

    zoomInBtn?.addEventListener('click', () => {
        const rect = canvasEl?.getBoundingClientRect();

        if (!rect) {
            return;
        }

        applyZoom(0.12, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    zoomOutBtn?.addEventListener('click', () => {
        const rect = canvasEl?.getBoundingClientRect();

        if (!rect) {
            return;
        }

        applyZoom(-0.12, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    zoomResetBtn?.addEventListener('click', () => {
        fitImageToFrame();
        drawCropStage();
    });
};

const mountModalToBody = () => {
    if (!modalEl || modalEl.parentElement === document.body) {
        return;
    }

    document.body.appendChild(modalEl);
};

const observeStageResize = () => {
    if (!stageEl || typeof ResizeObserver === 'undefined') {
        return;
    }

    resizeObserver?.disconnect();
    resizeObserver = new ResizeObserver(() => {
        if (sourceImage && modalEl?.classList.contains('show')) {
            resizeCanvas();
        }
    });
    resizeObserver.observe(stageEl);
};

const cleanup = () => {
    sourceImage = null;
    displayScale = 1;
    offsetX = 0;
    offsetY = 0;
    isDragging = false;
    activePointerId = null;
    pendingFile = null;

    if (canvasEl) {
        const context = canvasEl.getContext('2d');
        context?.clearRect(0, 0, canvasEl.width, canvasEl.height);
    }
};

const rejectPending = (error) => {
    const reject = pendingReject;
    pendingResolve = null;
    pendingReject = null;
    reject?.(error);
};

const resolvePending = (file) => {
    const resolve = pendingResolve;
    pendingResolve = null;
    pendingReject = null;
    resolve?.(file);
};

const exportCroppedFile = async () => {
    if (!sourceImage || !canvasEl) {
        throw new Error('Unable to crop image.');
    }

    frameRect = getFrameRect(canvasEl.width, canvasEl.height);

    const sourceX = (frameRect.left - offsetX) / displayScale;
    const sourceY = (frameRect.top - offsetY) / displayScale;
    const sourceSize = frameRect.size / displayScale;

    const safeX = clamp(sourceX, 0, Math.max(sourceImage.naturalWidth - 1, 0));
    const safeY = clamp(sourceY, 0, Math.max(sourceImage.naturalHeight - 1, 0));
    const safeSize = clamp(
        sourceSize,
        1,
        Math.min(sourceImage.naturalWidth - safeX, sourceImage.naturalHeight - safeY),
    );

    const output = document.createElement('canvas');
    output.width = CROP_SIZE;
    output.height = CROP_SIZE;

    const context = output.getContext('2d');

    if (!context) {
        throw new Error('Unable to crop image.');
    }

    context.drawImage(
        sourceImage,
        safeX,
        safeY,
        safeSize,
        safeSize,
        0,
        0,
        CROP_SIZE,
        CROP_SIZE,
    );

    const blob = await new Promise((resolve) => {
        output.toBlob(resolve, 'image/jpeg', 0.92);
    });

    if (!blob) {
        throw new Error('Unable to crop image.');
    }

    const baseName = pendingFile?.name.replace(/\.[^.]+$/, '') || 'profile-photo';

    return new File([blob], `${baseName}.jpg`, {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
};

const startCropSession = async (file) => {
    const sessionId = cropSessionId + 1;
    cropSessionId = sessionId;

    cleanup();

    try {
        pendingFile = await normalizeImageOrientation(file);
        sourceImage = await loadSourceImage(pendingFile);
    } catch (error) {
        rejectPending(error instanceof Error ? error : new Error('Unable to prepare image.'));
        return;
    }

    const onShown = async () => {
        if (sessionId !== cropSessionId || !pendingFile || !sourceImage) {
            return;
        }

        if (!stageEl || !canvasEl) {
            rejectPending(new Error('Crop UI is not available.'));
            modal?.hide();
            return;
        }

        try {
            bindStageEvents();
            observeStageResize();

            const ready = await waitForStageLayout(sessionId);

            if (!ready) {
                throw new Error('Crop area is not visible.');
            }

            drawCropStage();
        } catch (error) {
            rejectPending(error instanceof Error ? error : new Error('Unable to load image.'));
            modal?.hide();
        }
    };

    if (modalEl?.classList.contains('show')) {
        await onShown();
        return;
    }

    modalEl?.addEventListener('shown.bs.modal', onShown, { once: true });
    modal?.show();
};

const confirmCrop = async () => {
    if (!sourceImage || !pendingFile) {
        rejectPending(new Error('Unable to crop image.'));
        modal?.hide();
        return;
    }

    try {
        const croppedFile = await exportCroppedFile();
        resolvePending(croppedFile);
        modal?.hide();
    } catch (error) {
        rejectPending(error instanceof Error ? error : new Error('Unable to crop image.'));
        modal?.hide();
    }
};

const resolveElements = () => {
    modalEl = document.getElementById('profilePhotoCropModal');
    stageEl = document.getElementById('profilePhotoCropStage');
    canvasEl = document.getElementById('profilePhotoCropCanvas');
    zoomInBtn = document.getElementById('profilePhotoCropZoomInBtn');
    zoomOutBtn = document.getElementById('profilePhotoCropZoomOutBtn');
    zoomResetBtn = document.getElementById('profilePhotoCropZoomResetBtn');

    return Boolean(
        modalEl
        && stageEl
        && canvasEl
        && document.getElementById('profilePhotoCropConfirmBtn'),
    );
};

export const initProfilePhotoCrop = () => {
    if (!resolveElements()) {
        return false;
    }

    mountModalToBody();

    if (!modal) {
        modal = Modal.getOrCreateInstance(modalEl, { focus: false });
    }

    if (!initialized) {
        initialized = true;

        document.getElementById('profilePhotoCropConfirmBtn')?.addEventListener('click', () => {
            void confirmCrop();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            cropSessionId += 1;

            if (pendingReject) {
                rejectPending(new Error('cancelled'));
            }

            cleanup();
        });
    }

    return true;
};

export const cropProfilePhotoFile = (file) => new Promise((resolve, reject) => {
    if (!initProfilePhotoCrop() || !modal) {
        reject(new Error('Crop UI is not available.'));
        return;
    }

    pendingResolve = resolve;
    pendingReject = reject;
    void startCropSession(file);
});
