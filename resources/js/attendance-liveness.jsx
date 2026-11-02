import React, { useCallback, useMemo } from 'react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@aws-amplify/ui-react';
import { FaceLivenessDetectorCore } from '@aws-amplify/ui-react-liveness';
import '@aws-amplify/ui-react/styles.css';
import '@aws-amplify/ui-react-liveness/styles.css';

function LivenessPanel({
    sessionId,
    region,
    credentials,
    onComplete,
    onError,
    onCancel,
}) {
    const credentialProvider = useCallback(async () => ({
        accessKeyId: credentials.access_key_id,
        secretAccessKey: credentials.secret_access_key,
        sessionToken: credentials.session_token,
        expiration: credentials.expiration ? new Date(credentials.expiration) : undefined,
    }), [credentials]);

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
                    sessionId={sessionId}
                    region={region}
                    onAnalysisComplete={onComplete}
                    onError={onError}
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
