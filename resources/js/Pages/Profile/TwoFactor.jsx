import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function TwoFactor({ enabled, secret, otpauthUrl, ssoDeferred }) {
    const form = useForm({ code: '' });
    const disableForm = useForm({ code: '' });

    return (
        <AuthenticatedLayout header="Two-factor authentication">
            <Head title="Two-factor authentication" />
            <div className="mx-auto max-w-lg space-y-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <p className="text-sm text-on-surface-variant">
                    Two-factor authentication is optional. You can enable it now or skip and turn it on later from
                    Profile.
                </p>

                {ssoDeferred && (
                    <p className="rounded-xl bg-surface-container-low px-3 py-2 text-sm text-on-surface-variant">
                        Google / Microsoft SSO is deferred for a future release. Authenticator-app 2FA is available
                        now.
                    </p>
                )}

                {enabled ? (
                    <div className="space-y-3">
                        <p className="text-sm font-medium text-on-surface">
                            Two-factor authentication is enabled on your account.
                        </p>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                disableForm.post(route('two-factor.disable'));
                            }}
                            className="space-y-3"
                        >
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="Enter code to disable"
                                value={disableForm.data.code}
                                onChange={(e) => disableForm.setData('code', e.target.value)}
                            />
                            <button
                                type="submit"
                                className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                            >
                                Disable 2FA
                            </button>
                        </form>
                        <Link href={route('dashboard')} className="inline-block text-sm font-semibold text-secondary">
                            Back to dashboard
                        </Link>
                    </div>
                ) : (
                    <>
                        <p className="text-sm text-on-surface-variant">
                            Scan this secret in your authenticator app, then enter the 6-digit code to confirm.
                        </p>
                        <p className="break-all rounded-xl bg-surface-container-low p-3 font-mono text-sm">{secret}</p>
                        <p className="break-all text-xs text-on-surface-variant">{otpauthUrl}</p>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(route('two-factor.confirm'));
                            }}
                            className="space-y-3"
                        >
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="123456"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                            />
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="submit"
                                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Confirm 2FA
                                </button>
                                <button
                                    type="button"
                                    onClick={() => router.post(route('two-factor.skip'))}
                                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                                >
                                    Skip for now
                                </button>
                            </div>
                        </form>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
