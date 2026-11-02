import React, { useCallback, useMemo } from 'react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@aws-amplify/ui-react';
import { FaceLivenessDetectorCore } from '@aws-amplify/ui-react-liveness';
import '@aws-amplify/ui-react/styles.css';
import '@aws-amplify/ui-react-liveness/styles.css';

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

    if (state === 'CONNECTION_TIMEOUT' || /timeout/i.test(detail)) {
        return 'Face liveness timed out connecting to AWS. Check network/firewall access to Rekognition streaming and try again.';
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

    const credentialProvider = useCallback(async () => {
        if (!normalized.accessKeyId || !normalized.secretAccessKey || !normalized.sessionToken) {
            throw new Error('Face liveness credentials are incomplete.');
        }

        return normalized;
    }, [normalized]);

    const config = useMemo(() => ({
        credentialProvider,
    }), [credentialProvider]);

    return (
        <ThemeProvider>
            <div className="attendance-liveness-panel">
                <p className="small text-muted mb-2">
                    Follow the oval instructions. A photo or video of a face will fail this check.
                </p>
                <FaceLivenessDetectorCore
                    key={sessionId}
                    sessionId={sessionId}
                    region={region}
                    onAnalysisComplete={onComplete}
                    onError={(error) => {
                        // Helpful for support: full Amplify error in browser console.
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
