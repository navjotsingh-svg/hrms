import Human from '@vladmandic/human';

const MODEL_BASE = 'https://cdn.jsdelivr.net/npm/@vladmandic/human/models';

const humanConfig = {
    backend: 'webgl',
    modelBasePath: MODEL_BASE,
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
            minConfidence: 0.45,
            rotation: true,
        },
        description: { enabled: true },
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
};

let humanInstance = null;
let modelsPromise = null;
let profileDescriptorCache = null;
let profileDescriptorSource = null;

const getHuman = async () => {
    if (!humanInstance) {
        humanInstance = new Human(humanConfig);
        modelsPromise = humanInstance.load().catch((error) => {
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
    image.src = url;
});

export const ensureFaceModelsLoaded = () => getHuman();

const detectDescriptor = async (input) => {
    const human = await getHuman();
    const result = await human.detect(input);
    const face = result.face?.[0];

    return face?.embedding?.length ? face.embedding : null;
};

export const similarityFromRatio = (similarityRatio) => Math.max(0, Math.min(100, Math.round(similarityRatio * 100)));

export const compareDescriptors = (human, profileDescriptor, selfieDescriptor, threshold = 80) => {
    const similarityRatio = human.match.similarity(profileDescriptor, selfieDescriptor);
    const similarity = similarityFromRatio(similarityRatio);

    return {
        distance: 1 - similarityRatio,
        similarity,
        matched: similarity >= threshold,
    };
};

export const getProfileDescriptor = async (profilePhotoUrl, { forceRefresh = false } = {}) => {
    if (!profilePhotoUrl) {
        throw new Error('Profile photo is required for face verification.');
    }

    if (!forceRefresh && profileDescriptorCache && profileDescriptorSource === profilePhotoUrl) {
        return profileDescriptorCache;
    }

    const image = await loadImage(profilePhotoUrl);
    const descriptor = await detectDescriptor(image);

    if (!descriptor) {
        throw new Error('No face detected in your profile photo. Update your profile photo and try again.');
    }

    profileDescriptorCache = descriptor;
    profileDescriptorSource = profilePhotoUrl;

    return descriptor;
};

export const verifySelfieAgainstProfile = async ({
    profilePhotoUrl,
    videoElement,
    threshold = 80,
}) => {
    const human = await getHuman();
    const profileDescriptor = await getProfileDescriptor(profilePhotoUrl);
    const selfieDescriptor = await detectDescriptor(videoElement);

    if (!selfieDescriptor) {
        throw new Error('No face detected. Center your face in the frame with good lighting.');
    }

    const result = compareDescriptors(human, profileDescriptor, selfieDescriptor, threshold);

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
    threshold = 80,
}) => {
    if (!profilePhotoUrl || !videoElement?.videoWidth) {
        return { detected: false, similarity: null, matched: false };
    }

    const human = await getHuman();
    const profileDescriptor = await getProfileDescriptor(profilePhotoUrl);
    const selfieDescriptor = await detectDescriptor(videoElement);

    if (!selfieDescriptor) {
        return { detected: false, similarity: null, matched: false };
    }

    const result = compareDescriptors(human, profileDescriptor, selfieDescriptor, threshold);

    return {
        ...result,
        detected: true,
    };
};

export const descriptorToArray = (descriptor) => Array.from(descriptor);

export const resetProfileDescriptorCache = () => {
    profileDescriptorCache = null;
    profileDescriptorSource = null;
};
