import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const FB_SDK_SRC = 'https://connect.facebook.net/en_US/sdk.js';

function loadFacebookSdk(appId, apiVersion) {
    return new Promise((resolve, reject) => {
        if (window.FB) {
            resolve(window.FB);
            return;
        }

        const previous = window.fbAsyncInit;
        window.fbAsyncInit = function fbAsyncInit() {
            window.FB.init({
                appId,
                cookie: true,
                xfbml: false,
                version: apiVersion.startsWith('v') ? apiVersion : `v${apiVersion}`,
            });
            if (typeof previous === 'function') {
                previous();
            }
            resolve(window.FB);
        };

        if (document.getElementById('facebook-jssdk')) {
            return;
        }

        const script = document.createElement('script');
        script.id = 'facebook-jssdk';
        script.async = true;
        script.defer = true;
        script.src = FB_SDK_SRC;
        script.onerror = () => reject(new Error('Could not load the Meta / Facebook SDK.'));
        document.body.appendChild(script);
    });
}

/**
 * Wati-style "Connect with Meta" using WhatsApp Embedded Signup.
 */
export default function MetaWhatsAppConnect({
    embeddedSignup,
    connectRoute,
    disabled = false,
    onConnected,
}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const sessionRef = useRef({ phone_number_id: null, waba_id: null });

    const ready = !!embeddedSignup?.ready && !!embeddedSignup?.app_id && !!embeddedSignup?.config_id;

    useEffect(() => {
        const listener = (event) => {
            if (!String(event.origin || '').includes('facebook.com')) {
                return;
            }

            try {
                const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                if (data?.type !== 'WA_EMBEDDED_SIGNUP') {
                    return;
                }

                if (data.event === 'FINISH' || data.event === 'FINISH_ONLY_WABA') {
                    sessionRef.current = {
                        phone_number_id: data.data?.phone_number_id || null,
                        waba_id: data.data?.waba_id || null,
                    };
                }
            } catch {
                // Ignore non-JSON postMessages from the Facebook frame.
            }
        };

        window.addEventListener('message', listener);
        return () => window.removeEventListener('message', listener);
    }, []);

    const connect = useCallback(async () => {
        setError('');
        if (!ready) {
            setError('Connect with Meta is not configured yet. Ask a Super Admin to set Meta App ID, App Secret, and Embedded Signup Config ID.');
            return;
        }

        setBusy(true);
        sessionRef.current = { phone_number_id: null, waba_id: null };

        try {
            const FB = await loadFacebookSdk(embeddedSignup.app_id, embeddedSignup.api_version || 'v21.0');

            await new Promise((resolve, reject) => {
                FB.login(
                    (response) => {
                        if (!response?.authResponse?.code) {
                            reject(new Error(response?.status === 'unknown'
                                ? 'Meta signup was cancelled or blocked by the browser.'
                                : 'Meta did not return an authorization code.'));
                            return;
                        }

                        // Session postMessage can arrive slightly after login callback.
                        window.setTimeout(() => {
                            const { phone_number_id, waba_id } = sessionRef.current;
                            if (!phone_number_id || !waba_id) {
                                reject(new Error(
                                    'Meta signup finished, but phone number / WABA IDs were not received. Enable sessionInfoVersion in Embedded Signup and try again.',
                                ));
                                return;
                            }

                            router.post(
                                connectRoute,
                                {
                                    code: response.authResponse.code,
                                    phone_number_id,
                                    waba_id,
                                },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        onConnected?.();
                                        resolve();
                                    },
                                    onError: (errors) => {
                                        reject(new Error(errors.whatsapp || Object.values(errors)[0] || 'Could not save Meta connection.'));
                                    },
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }, 400);
                    },
                    {
                        config_id: embeddedSignup.config_id,
                        response_type: 'code',
                        override_default_response_type: true,
                        extras: {
                            setup: {},
                            featureType: '',
                            sessionInfoVersion: '3',
                        },
                    },
                );
            });
        } catch (e) {
            setBusy(false);
            setError(e?.message || 'Meta connection failed.');
        }
    }, [connectRoute, embeddedSignup, onConnected, ready]);

    return (
        <div className="space-y-2">
            <button
                type="button"
                disabled={disabled || busy || !ready}
                onClick={connect}
                className="inline-flex items-center gap-2 rounded-xl bg-[#1877f2] px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
                {busy ? 'Connecting to Meta…' : 'Connect with Meta'}
            </button>
            {!ready && (
                <p className="text-xs text-on-surface-variant">
                    Super Admin must configure Meta App ID, App Secret, and Embedded Signup Config ID first
                    (Platform messaging or <code>.env</code>).
                </p>
            )}
            {error && <p className="text-xs text-error">{error}</p>}
        </div>
    );
}
