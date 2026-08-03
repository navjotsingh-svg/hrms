import Human from '@vladmandic/human';

const MODEL_BASE = 'https://cdn.jsdelivr.net/npm/@vladmandic/human@3.3.6/models/';
export const FACE_EMBEDDING_MODEL = 'faceres';
export const DEFAULT_FACE_MATCH_THRESHOLD = 90;

const MATCH_OPTIONS = {
    order: 2,
    multiplier: 25,
    min: 0.2,
    max: 0.8,
};

/** Live webcam vs approved profile photo typically scores ~0.38–0.50 raw with faceres. */
const ATTENDANCE_MATCH_FLOOR = 0.34;
const ATTENDANCE_MATCH_CEILING = 0.48;
const LIVE_MATCH_FRAME_WINDOW = 8;

const BACKENDS = ['webgl', 'wasm', 'cpu'];

const buildHumanConfig = (backend) => ({
    backend,
    modelBasePath: MODEL_BASE,
    warmup: 'face',
    cacheSensitivity: 0.01,
    filter: {
        enabled: true,
        equalization: true,
        width: 0,
    },
    face: {
        enabled: true,
        detector: {
            enabled: true,
            maxDetected: 1,
            minConfidence: 0.4,
            rotation: true,
        },
        description: {
            enabled: true,
            modelPath: 'faceres.json',
        },
        insightface: { enabled: false },
        mesh: { enabled: false },
        iris: { enabled: false },
        emotion: { enabled: false },
        antispoof: { enabled: false },
    },
    body: { enabled: false },
    hand: { enabled: false },
    gesture: { enabled: false },
    object: { enabled: false },
    segmentation: { enabled: false },
});

let humanInstance = null;
let modelsPromise = null;
let profileDescriptorCache = null;
let profileDescriptorSource = null;
let storedProfileDescriptor = null;
let storedProfilePhotoUrl = null;
const recentLiveRawScores = [];

const resolveAssetUrl = (url) => {
    if (!url) {
        return url;
    }

    if (/^(?:https?:|blob:)/i.test(url)) {
        return url;
    }

    return new URL(url.startsWith('/') ? url : `/${url}`, window.location.origin).href;
};

const createHumanWithFallback = async () => {
    let lastError = null;

    for (const backend of BACKENDS) {
        try {
            const instance = new Human(buildHumanConfig(backend));
            await instance.load();
            return instance;
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError || new Error('Unable to initialize face recognition models.');
};

const getHuman = async () => {
    if (!humanInstance) {
        modelsPromise = createHumanWithFallback()
            .then((instance) => {
                humanInstance = instance;
                return instance;
            })
            .catch((error) => {
                modelsPromise = null;
                humanInstance = null;
                throw error;
            });
    }

    await modelsPromise;

    return humanInstance;
};

const loadImage = (url) => new Promise((resolve, reject) => {
    const image = new Image();
    image.crossOrigin = 'anonymous';
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('Unable to load profile photo for face verification.'));
    image.src = resolveAssetUrl(url);
});

export const ensureFaceModelsLoaded = () => getHuman();

export const setStoredProfileDescriptor = (descriptor, photoUrl = null) => {
    if (photoUrl) {
        storedProfilePhotoUrl = resolveAssetUrl(photoUrl);
        profileDescriptorSource = storedProfilePhotoUrl;
    }

    if (Array.isArray(descriptor) && descriptor.length >= 64) {
        storedProfileDescriptor = descriptor.map(Number);
        return;
    }

    storedProfileDescriptor = null;
};

export const bindProfileFaceContext = ({ photoUrl, descriptor = null } = {}) => {
    const resolvedUrl = photoUrl ? resolveAssetUrl(photoUrl) : null;

    if (resolvedUrl !== storedProfilePhotoUrl) {
        profileDescriptorCache = null;
        profileDescriptorSource = resolvedUrl;
        storedProfileDescriptor = null;
        storedProfilePhotoUrl = resolvedUrl;
        resetLiveMatchHistory();
    }

    if (Array.isArray(descriptor) && descriptor.length >= 64) {
        storedProfileDescriptor = descriptor.map(Number);
    }
};

export const resetLiveMatchHistory = () => {
    recentLiveRawScores.length = 0;
};

export const rawSimilarityBetweenDescriptors = async (profileDescriptor, selfieDescriptor) => {
    const human = await getHuman();
    const rawSimilarity = human.match.similarity(profileDescriptor, selfieDescriptor, MATCH_OPTIONS);

    if (Number.isFinite(rawSimilarity) && rawSimilarity > 0) {
        return rawSimilarity;
    }

    return cosineSimilarityRatio(profileDescriptor, selfieDescriptor);
};

export const toAttendanceMatchPercent = (rawSimilarity) => {
    if (!Number.isFinite(rawSimilarity) || rawSimilarity <= 0) {
        return 0;
    }

    const scaled = (rawSimilarity - ATTENDANCE_MATCH_FLOOR)
        / (ATTENDANCE_MATCH_CEILING - ATTENDANCE_MATCH_FLOOR);

    return Math.max(0, Math.min(100, Math.round(scaled * 100)));
};

const rememberLiveRawScore = (rawSimilarity) => {
    if (!Number.isFinite(rawSimilarity) || rawSimilarity <= 0) {
        return rawSimilarity;
    }

    recentLiveRawScores.push(rawSimilarity);

    if (recentLiveRawScores.length > LIVE_MATCH_FRAME_WINDOW) {
        recentLiveRawScores.shift();
    }

    return Math.max(...recentLiveRawScores);
};

const captureVideoFrame = (videoElement) => {
    const width = videoElement.videoWidth;
    const height = videoElement.videoHeight;

    if (!width || !height) {
        return null;
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        return null;
    }

    context.drawImage(videoElement, 0, 0, width, height);

    return canvas;
};

const normalizeDetectInput = (input) => {
    if (input instanceof HTMLVideoElement) {
        return captureVideoFrame(input) || input;
    }

    return input;
};

const detectDescriptor = async (input) => {
    const human = await getHuman();
    const detectInput = normalizeDetectInput(input);

    if (!detectInput) {
        return null;
    }

    const result = await human.detect(detectInput);
    const face = result.face?.[0];

    return face?.embedding?.length ? face.embedding : null;
};

export const cosineSimilarityRatio = (descriptorA, descriptorB) => {
    const length = Math.min(descriptorA.length, descriptorB.length);

    if (length < 64) {
        return 0;
    }

    let sum = 0;

    for (let index = 0; index < length; index += 1) {
        const diff = descriptorA[index] - descriptorB[index];
        sum += diff * diff;
    }

    const distance = Math.round(100 * MATCH_OPTIONS.multiplier * sum) / 100;

    if (distance === 0) {
        return 1;
    }

    const root = Math.sqrt(distance);
    const normalized = (1 - (root / 100) - MATCH_OPTIONS.min) / (MATCH_OPTIONS.max - MATCH_OPTIONS.min);

    return Math.max(0, Math.min(1, Math.round(normalized * 100) / 100));
};

export const similarityFromRatio = (similarityRatio) => Math.max(0, Math.min(100, Math.round(similarityRatio * 100)));

export const compareDescriptors = async (profileDescriptor, selfieDescriptor, threshold = DEFAULT_FACE_MATCH_THRESHOLD) => {
    const rawSimilarity = await rawSimilarityBetweenDescriptors(profileDescriptor, selfieDescriptor);
    const smoothedRawSimilarity = rememberLiveRawScore(rawSimilarity);
    const similarity = toAttendanceMatchPercent(smoothedRawSimilarity);

    return {
        distance: 1 - smoothedRawSimilarity,
        similarity,
        rawSimilarity: smoothedRawSimilarity,
        matched: similarity >= threshold,
    };
};

export const getProfileDescriptor = async (profilePhotoUrl, { forceRefresh = false } = {}) => {
    if (!profilePhotoUrl) {
        throw new Error('Profile photo is required for face verification.');
    }

    const resolvedUrl = resolveAssetUrl(profilePhotoUrl);

    if (storedProfilePhotoUrl && storedProfilePhotoUrl !== resolvedUrl) {
        profileDescriptorCache = null;
        storedProfileDescriptor = null;
        resetLiveMatchHistory();
    }

    storedProfilePhotoUrl = resolvedUrl;

    if (!forceRefresh && storedProfileDescriptor?.length >= 64 && profileDescriptorSource === resolvedUrl) {
        return storedProfileDescriptor;
    }

    if (!forceRefresh && profileDescriptorCache && profileDescriptorSource === resolvedUrl) {
        return profileDescriptorCache;
    }

    const image = await loadImage(profilePhotoUrl);
    const descriptor = await detectDescriptor(image);

    if (!descriptor) {
        throw new Error('No face detected in your profile photo. Update your profile photo and try again.');
    }

    profileDescriptorCache = descriptor;
    profileDescriptorSource = resolvedUrl;
    storedProfileDescriptor = Array.from(descriptor);

    return descriptor;
};

export const verifySelfieAgainstProfile = async ({
    profilePhotoUrl,
    videoElement,
    threshold = DEFAULT_FACE_MATCH_THRESHOLD,
}) => {
    await getHuman();
    const profileDescriptor = await getProfileDescriptor(profilePhotoUrl);
    const selfieDescriptor = await detectDescriptor(videoElement);

    if (!selfieDescriptor) {
        throw new Error('No face detected. Center your face in the frame with good lighting.');
    }

    const result = await compareDescriptors(profileDescriptor, selfieDescriptor, threshold);

    return {
        ...result,
        matched: result.matched,
        profileDescriptor,
        selfieDescriptor,
    };
};

export const previewMatchFromVideo = async ({
    profilePhotoUrl,
    videoElement,
    threshold = DEFAULT_FACE_MATCH_THRESHOLD,
}) => {
    if (!profilePhotoUrl || !videoElement?.videoWidth) {
        return { detected: false, similarity: null, matched: false };
    }

    try {
        await getHuman();
        const profileDescriptor = await getProfileDescriptor(profilePhotoUrl);
        const selfieDescriptor = await detectDescriptor(videoElement);

        if (!selfieDescriptor) {
            return { detected: false, similarity: null, matched: false };
        }

        const result = await compareDescriptors(profileDescriptor, selfieDescriptor, threshold);

        return {
            ...result,
            detected: true,
        };
    } catch {
        return { detected: false, similarity: null, matched: false };
    }
};

export const descriptorToArray = (descriptor) => Array.from(descriptor);

export const resetProfileDescriptorCache = () => {
    profileDescriptorCache = null;
    profileDescriptorSource = null;
    storedProfileDescriptor = null;
    storedProfilePhotoUrl = null;
    resetLiveMatchHistory();
};

export const detectFaceInFile = async (file) => {
    const objectUrl = URL.createObjectURL(file);

    try {
        const image = await loadImage(objectUrl);
        const descriptor = await detectDescriptor(image);

        return Boolean(descriptor?.length);
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
};

export const syncFaceReferenceFromProfilePhoto = async (profilePhotoUrl) => {
    const { default: api } = await import('./api');

    await ensureFaceModelsLoaded();
    bindProfileFaceContext({ photoUrl: profilePhotoUrl });

    const descriptor = await getProfileDescriptor(profilePhotoUrl, { forceRefresh: true });

    await api.post('/attendance/face-reference', {
        descriptor: descriptorToArray(descriptor),
    });
};
