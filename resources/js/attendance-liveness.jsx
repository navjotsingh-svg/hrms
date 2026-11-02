import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@aws-amplify/ui-react';
import { FaceLivenessDetectorCore } from '@aws-amplify/ui-react-liveness';
import { setWasmPaths, version_wasm } from '@tensorflow/tfjs-backend-wasm';
import '@aws-amplify/ui-react/styles.css';
import '@aws-amplify/ui-react-liveness/styles.css';

const CDN_BLAZEFACE_URL = 'https://cdn.liveness.rekognition.amazonaws.com/face-detection/tensorflow-models/blazeface/1.0.2/model/model.json';
const CDN_TFJS_WASM_URL = `https://cdn.liveness.rekognition.amazonaws.com/face-detection/tensorflow/tfjs-backend-wasm/${version_wasm || '4.11.0'}/`;

const WASM_BINARY_NAMES = [
    'tfjs-backend-wasm.wasm',
    'tfjs-backend-wasm-simd.wasm',
    'tfjs-backend-wasm-threaded-simd.wasm',
];

const localAssetPaths = () => {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    return {
        binaryPath: `${origin}/liveness-assets/tfjs-backend-wasm/`,
        faceModelUrl: `${origin}/liveness-assets/blazeface/model.json`,
        probeWasm: `${origin}/liveness-assets/tfjs-backend-wasm/tfjs-backend-wasm-simd.wasm`,
        probeModel: `${origin}/liveness-assets/blazeface/model.json`,
    };
};

const cdnAssetPaths = () => ({
    binaryPath: CDN_TFJS_WASM_URL,
    faceModelUrl: CDN_BLAZEFACE_URL,
    probeWasm: `${CDN_TFJS_WASM_URL}tfjs-backend-wasm-simd.wasm`,
    probeModel: CDN_BLAZEFACE_URL,
});

const wasmFileMapForPrefix = (binaryPath) => Object.fromEntries(
    WASM_BINARY_NAMES.map((name) => [name, `${binaryPath}${name}`]),
);

/**
 * Force TFJS to resolve wasm binaries from our prefix (or absolute file map).
 * Must run before Amplify/Human call setBackend('wasm'). Absolute URLs avoid
 * Emscripten falling back to site-root relative paths after Vite bundling.
 */
const applyWasmPaths = (binaryPath) => {
    if (!binaryPath) {
        return;
    }

    try {
        setWasmPaths(wasmFileMapForPrefix(binaryPath));
    } catch (error) {
        // Backend may already be initializing; Amplify also calls setWasmPaths.
        console.warn('Face liveness setWasmPaths skipped', error);
    }
};

const canFetchAsset = async (url) => {
    try {
        const response = await fetch(url, {
            method: 'GET',
            cache: 'no-store',
            mode: 'cors',
        });

        return response.ok;
    } catch {
        return false;
    }
};

const resolveWorkingAssetPaths = async () => {
    const local = localAssetPaths();
    const localOk = await canFetchAsset(local.probeWasm) && await canFetchAsset(local.probeModel);

    if (localOk) {
        applyWasmPaths(local.binaryPath);

        return { ...local, source: 'local' };
    }

    const cdn = cdnAssetPaths();
    const cdnOk = await canFetchAsset(cdn.probeWasm) && await canFetchAsset(cdn.probeModel);

    if (cdnOk) {
        applyWasmPaths(cdn.binaryPath);

        return { ...cdn, source: 'cdn' };
    }

    throw new Error(
        `Face detection assets are unreachable. Local probe failed (${local.probeWasm}). CDN probe also failed (${cdn.probeWasm}). Deploy public/vendor assets and ensure /liveness-assets/* routes work.`,
    );
};

const normalizeCredentials = (credentials = {}) => {
    const accessKeyId = credentials.accessKeyId || credentials.access_key_id;
    const secretAccessKey = credentials.secretAccessKey || credentials.secret_access_key;
    const sessionToken = credentials.sessionToken || credentials.session_token;
    const rawExpiration = credentials.expiration;

    let expiration;

    if (rawExpiration instanceof Date) {
        expiration = rawExpiration;
    } else if (typeof rawExpiration === 'string' && rawExpiration.trim() !== '') {
        const parsed = new Date(rawExpiration);
        expiration = Number.isNaN(parsed.getTime()) ? undefined : parsed;
    }

    return {
        accessKeyId,
        secretAccessKey,
        sessionToken,
        expiration,
    };
};

export const describeLivenessError = (error) => {
    const state = error?.state || error?.error?.name || '';
    const detail = error?.error?.message
        || error?.message
        || '';

    if (state === 'CAMERA_ACCESS_ERROR' || /permission|NotAllowedError|NotReadableError/i.test(detail)) {
        return 'Camera access failed. Allow camera permission, close other apps using the camera, and try again.';
    }

    if (state === 'CONNECTION_TIMEOUT' || (/timeout/i.test(detail) && !/model loading timed out/i.test(detail))) {
        return 'Face liveness timed out connecting to AWS. Check network/firewall access to Rekognition streaming and try again.';
    }

    if (/wasm|WebAssembly|model loading timed out|blazeface|404|unreachable|probe failed/i.test(detail)) {
        return detail.includes('unreachable') || detail.includes('probe failed')
            ? detail
            : 'Face detection assets failed to load. Deploy vendor assets, open /liveness-assets/blazeface/model.json in the browser, then hard-refresh.';
    }

    if (state === 'MOBILE_LANDSCAPE_ERROR' || /landscape/i.test(detail)) {
        return 'Rotate your device to portrait mode and try again.';
    }

    if (/credential|AccessDenied|UnrecognizedClient|security token/i.test(detail)) {
        return 'Face liveness credentials were rejected. Ensure the IAM user can call rekognition:StartFaceLivenessSession and sts:GetSessionToken.';
    }

    if (/Session|session/i.test(detail) && /not found|expired|Invalid/i.test(detail)) {
        return 'Face liveness session expired or was already used. Tap Try again to start a new check.';
    }

    return detail || 'Face liveness failed due to a client issue. Try again in portrait mode with good lighting.';
};

function LivenessPanel({
    sessionId,
    region,
    credentials,
    onComplete,
    onError,
    onCancel,
}) {
    const normalized = useMemo(() => normalizeCredentials(credentials), [credentials]);
    const [assetPaths, setAssetPaths] = useState(null);
    const [bootError, setBootError] = useState(null);
    // Amplify stays on "Verifying..." after onAnalysisComplete (no onDone transition) — replace UI ourselves.
    const [outcome, setOutcome] = useState(null);

    useEffect(() => {
        let cancelled = false;

        setOutcome(null);

        resolveWorkingAssetPaths()
            .then((paths) => {
                if (!cancelled) {
                    console.info('Face liveness assets source:', paths.source, paths.binaryPath);
                    setAssetPaths(paths);
                }
            })
            .catch((error) => {
                if (!cancelled) {
                    console.error('Face liveness asset probe failed', error);
                    setBootError(error);
                    onError?.(error);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [onError, sessionId]);

    const credentialProvider = useCallback(async () => {
        if (!normalized.accessKeyId || !normalized.secretAccessKey) {
            throw new Error('Face liveness credentials are incomplete.');
        }

        const payload = {
            accessKeyId: normalized.accessKeyId,
            secretAccessKey: normalized.secretAccessKey,
            expiration: normalized.expiration,
        };

        if (normalized.sessionToken) {
            payload.sessionToken = normalized.sessionToken;
        }

        return payload;
    }, [normalized]);

    const config = useMemo(() => {
        if (!assetPaths) {
            return null;
        }

        applyWasmPaths(assetPaths.binaryPath);

        return {
            credentialProvider,
            binaryPath: assetPaths.binaryPath,
            faceModelUrl: assetPaths.faceModelUrl,
        };
    }, [credentialProvider, assetPaths]);

    const handleAnalysisComplete = useCallback(async (deviceInfo) => {
        setOutcome({ status: 'verifying' });

        try {
            const result = await onComplete?.(deviceInfo);

            if (result && result.passed === false) {
                setOutcome({
                    status: 'failed',
                    message: result.message || 'Face liveness did not succeed. Try again.',
                });

                return;
            }

            setOutcome({
                status: 'passed',
                message: result?.message || 'Live check passed. Confirm to mark attendance.',
            });
        } catch (error) {
            const message = describeLivenessError(error);
            setOutcome({ status: 'failed', message });
            throw error;
        }
    }, [onComplete]);

    if (bootError) {
        return (
            <div className="alert alert-danger mb-0">
                {describeLivenessError(bootError)}
            </div>
        );
    }

    if (!config) {
        return (
            <div className="small text-muted py-4 text-center">
                Loading face detection assets…
            </div>
        );
    }

    if (outcome?.status === 'passed') {
        return (
            <div className="attendance-liveness-panel">
                <div className="alert alert-success mb-0">
                    {outcome.message}
                </div>
            </div>
        );
    }

    if (outcome?.status === 'failed') {
        return (
            <div className="attendance-liveness-panel">
                <div className="alert alert-danger mb-0">
                    {outcome.message}
                </div>
            </div>
        );
    }

    return (
        <ThemeProvider>
            <div className="attendance-liveness-panel">
                <p className="small text-muted mb-2">
                    Follow the oval instructions. A photo or video of a face will fail this check.
                </p>
                <FaceLivenessDetectorCore
                    key={`${sessionId}-${assetPaths.source}`}
                    sessionId={sessionId}
                    region={region}
                    onAnalysisComplete={handleAnalysisComplete}
                    onError={(error) => {
                        console.error('Face liveness error', error);
                        onError?.(error);
                    }}
                    onUserCancel={onCancel}
                    config={config}
                />
            </div>
        </ThemeProvider>
    );
}

export function mountAttendanceLiveness(container, options) {
    if (!container) {
        throw new Error('Liveness mount container is missing.');
    }

    const root = createRoot(container);

    const render = (props) => {
        root.render(
            <LivenessPanel
                sessionId={props.sessionId}
                region={props.region}
                credentials={props.credentials}
                onComplete={props.onComplete}
                onError={props.onError}
                onCancel={props.onCancel}
            />,
        );
    };

    render(options);

    return {
        update: render,
        unmount: () => {
            root.unmount();
            container.innerHTML = '';
        },
    };
}
