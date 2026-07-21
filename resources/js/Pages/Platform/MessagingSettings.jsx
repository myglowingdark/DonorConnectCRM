import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MetaWhatsAppConnect from '@/Components/MetaWhatsAppConnect';
import { Head, router, useForm, usePage } from '@inertiajs/react';

export default function PlatformMessagingSettings({
    settings,
    webhookUrl,
    verifyTokenHint,
    embeddedSignup,
    instructions = [],
}) {
    const { flash } = usePage().props;
    const form = useForm({
        email_enabled: !!settings.email_enabled,
        whatsapp_module_enabled: !!settings.whatsapp_module_enabled,
        whatsapp_enabled: !!settings.whatsapp_enabled,
        smtp_host: settings.smtp_host || '',
        smtp_port: settings.smtp_port || 587,
        smtp_encryption: settings.smtp_encryption || 'tls',
        smtp_username: settings.smtp_username || '',
        smtp_password: '',
        from_email: settings.from_email || '',
        from_name: settings.from_name || '',
        meta_access_token: '',
        meta_phone_number_id: settings.meta_phone_number_id || '',
        meta_waba_id: settings.meta_waba_id || '',
        meta_app_id: settings.meta_app_id || '',
        meta_app_secret: '',
        meta_api_version: settings.meta_api_version || 'v21.0',
        meta_embedded_signup_config_id: settings.meta_embedded_signup_config_id || '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('platform.messaging.update'));
    };

    return (
        <AuthenticatedLayout header="Platform messaging">
            <Head title="Platform messaging" />

            <div className="mb-6">
                <h2 className="text-headline-md">Platform messaging & modules</h2>
                <p className="text-sm text-on-surface-variant">
                    Site-wide Super Admin controls. Turn modules on for all organizations, connect Meta (Wati-style), then
                    configure shared SMTP / WhatsApp credentials.
                </p>
            </div>

            {flash?.success && (
                <p className="mb-4 rounded-xl bg-secondary/10 px-4 py-2 text-sm text-secondary">{flash.success}</p>
            )}
            {flash?.error && (
                <p className="mb-4 rounded-xl bg-error/10 px-4 py-2 text-sm text-error">{flash.error}</p>
            )}

            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-6">
                <section className="space-y-4 rounded-2xl border border-primary/20 bg-primary/5 p-6 shadow-card">
                    <h3 className="font-semibold">Site-wide modules</h3>
                    <p className="text-xs text-on-surface-variant">
                        Enabling a module unlocks it for every organization. You can still turn it off for a single org
                        on Billing with a feature override.
                    </p>
                    <label className="flex items-start gap-3 rounded-xl border border-slate-100 bg-white p-4 text-sm">
                        <input
                            type="checkbox"
                            className="mt-1"
                            checked={form.data.whatsapp_module_enabled}
                            onChange={(e) => form.setData('whatsapp_module_enabled', e.target.checked)}
                        />
                        <span>
                            <span className="block font-semibold">WhatsApp module</span>
                            <span className="text-xs text-on-surface-variant">
                                Unlock Meta WhatsApp templates &amp; donor messaging for all orgs site-wide.
                            </span>
                        </span>
                    </label>
                </section>

                <section className="space-y-3 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                    <h3 className="font-semibold">Super Admin setup checklist</h3>
                    <ol className="list-decimal space-y-2 pl-5 text-sm text-on-surface-variant">
                        {instructions.map((step) => (
                            <li key={step}>{step}</li>
                        ))}
                    </ol>
                </section>

                <section className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                    <h3 className="font-semibold">Email / SMTP</h3>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.email_enabled}
                            onChange={(e) => form.setData('email_enabled', e.target.checked)}
                        />
                        Platform email enabled
                    </label>
                    <div className="grid gap-3 md:grid-cols-2">
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="From email"
                            value={form.data.from_email}
                            onChange={(e) => form.setData('from_email', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="From name"
                            value={form.data.from_name}
                            onChange={(e) => form.setData('from_name', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="SMTP host"
                            value={form.data.smtp_host}
                            onChange={(e) => form.setData('smtp_host', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Port"
                            type="number"
                            value={form.data.smtp_port}
                            onChange={(e) => form.setData('smtp_port', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Encryption (tls/ssl)"
                            value={form.data.smtp_encryption}
                            onChange={(e) => form.setData('smtp_encryption', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Username"
                            value={form.data.smtp_username}
                            onChange={(e) => form.setData('smtp_username', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200 md:col-span-2"
                            placeholder={settings.has_smtp_password ? 'Password (leave blank to keep)' : 'Password'}
                            type="password"
                            value={form.data.smtp_password}
                            onChange={(e) => form.setData('smtp_password', e.target.value)}
                        />
                    </div>
                </section>

                <section className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                    <h3 className="font-semibold">Shared Meta WhatsApp credentials</h3>
                    <p className="text-xs text-on-surface-variant">
                        Orgs that choose “Use platform Meta WhatsApp credentials” will send through this account.
                        Webhook URL: <code className="break-all">{webhookUrl}</code>
                        {verifyTokenHint ? (
                            <>
                                <br />
                                Verify token: <code>{verifyTokenHint}</code>
                            </>
                        ) : null}
                    </p>

                    <div className="rounded-xl border border-[#1877f2]/30 bg-[#1877f2]/5 p-4">
                        <p className="mb-3 text-sm font-semibold text-[#1877f2]">Connect with Meta (recommended)</p>
                        <p className="mb-3 text-xs text-on-surface-variant">
                            Opens Meta Embedded Signup — pick/create Business Portfolio, WABA, and phone number. We save
                            the IDs and access token automatically (same idea as Wati).
                        </p>
                        <MetaWhatsAppConnect
                            embeddedSignup={embeddedSignup}
                            connectRoute={route('platform.messaging.whatsapp.connect')}
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.whatsapp_enabled}
                            onChange={(e) => form.setData('whatsapp_enabled', e.target.checked)}
                        />
                        Allow orgs to use these platform Meta credentials
                    </label>

                    <p className="text-xs font-semibold text-on-surface-variant">Manual / advanced (optional)</p>
                    <div className="grid gap-3 md:grid-cols-2">
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Meta App ID"
                            value={form.data.meta_app_id}
                            onChange={(e) => form.setData('meta_app_id', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Embedded Signup Config ID"
                            value={form.data.meta_embedded_signup_config_id}
                            onChange={(e) => form.setData('meta_embedded_signup_config_id', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Phone Number ID"
                            value={form.data.meta_phone_number_id}
                            onChange={(e) => form.setData('meta_phone_number_id', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="WABA ID"
                            value={form.data.meta_waba_id}
                            onChange={(e) => form.setData('meta_waba_id', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="API version"
                            value={form.data.meta_api_version}
                            onChange={(e) => form.setData('meta_api_version', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200 md:col-span-2"
                            placeholder={
                                settings.has_meta_access_token
                                    ? 'Access token (leave blank to keep)'
                                    : 'Permanent access token'
                            }
                            type="password"
                            value={form.data.meta_access_token}
                            onChange={(e) => form.setData('meta_access_token', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200 md:col-span-2"
                            placeholder={
                                settings.has_meta_app_secret
                                    ? 'App secret (leave blank to keep / prefer .env)'
                                    : 'App secret (prefer META_APP_SECRET in .env)'
                            }
                            type="password"
                            value={form.data.meta_app_secret}
                            onChange={(e) => form.setData('meta_app_secret', e.target.value)}
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => router.post(route('platform.messaging.whatsapp.test'))}
                        className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                    >
                        Test Meta connection
                    </button>
                </section>

                {Object.values(form.errors).map((err) => (
                    <p key={err} className="text-xs text-error">
                        {err}
                    </p>
                ))}

                <button disabled={form.processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                    Save platform messaging
                </button>
            </form>
        </AuthenticatedLayout>
    );
}
