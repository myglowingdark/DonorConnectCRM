import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MetaWhatsAppConnect from '@/Components/MetaWhatsAppConnect';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function MessagingSettings({
    settings,
    hasWhatsAppFeature = false,
    canManageWhatsAppConfig = false,
    thankYouTemplates = [],
    embeddedSignup = null,
    whatsappInstructions = [],
}) {
    const { flash } = usePage().props;
    const form = useForm({
        email_enabled: !!settings.email_enabled,
        whatsapp_enabled: !!settings.whatsapp_enabled,
        sms_enabled: !!settings.sms_enabled,
        smtp_host: settings.smtp_host || '',
        smtp_port: settings.smtp_port || 587,
        smtp_encryption: settings.smtp_encryption || 'tls',
        smtp_username: settings.smtp_username || '',
        smtp_password: '',
        from_email: settings.from_email || '',
        from_name: settings.from_name || '',
        whatsapp_provider: settings.whatsapp_provider || 'meta',
        whatsapp_api_key: '',
        whatsapp_from_number: settings.whatsapp_from_number || '',
        whatsapp_use_platform: settings.whatsapp_use_platform !== false,
        whatsapp_phone_number_id: settings.whatsapp_phone_number_id || '',
        whatsapp_waba_id: settings.whatsapp_waba_id || '',
        sms_provider: settings.sms_provider || '',
        sms_api_key: '',
        sms_from_number: settings.sms_from_number || '',
        bulk_whatsapp_enabled: !!settings.bulk_whatsapp_enabled,
        auto_donation_thankyou_enabled: !!settings.auto_donation_thankyou_enabled,
        auto_donation_thankyou_template_id: settings.auto_donation_thankyou_template_id || '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('messaging.settings.update'));
    };

    const testConnection = () => {
        router.post(route('messaging.whatsapp.test'));
    };

    return (
        <AuthenticatedLayout header="Messaging">
            <Head title="Messaging Settings" />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 className="text-headline-md">Email, WhatsApp & SMS</h2>
                    <p className="text-sm text-on-surface-variant">
                        Configure org channels and SMTP. Templates use placeholders like{' '}
                        <code>{'{{name}}'}</code>, <code>{'{{org}}'}</code>, <code>{'{{volunteer}}'}</code>.
                    </p>
                </div>
                <Link
                    href={route('messaging.templates')}
                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                >
                    Manage templates
                </Link>
            </div>

            {flash?.success && (
                <p className="mb-4 rounded-xl bg-secondary/10 px-4 py-2 text-sm text-secondary">{flash.success}</p>
            )}
            {flash?.error && (
                <p className="mb-4 rounded-xl bg-error/10 px-4 py-2 text-sm text-error">{flash.error}</p>
            )}

            <form onSubmit={submit} className="space-y-6">
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Channels</h3>
                    <div className="flex flex-wrap gap-4">
                        {[
                            ['email_enabled', 'Email'],
                            ['whatsapp_enabled', 'WhatsApp'],
                            ['sms_enabled', 'SMS / short message'],
                        ].map(([key, label]) => (
                            <label key={key} className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data[key]}
                                    onChange={(e) => form.setData(key, e.target.checked)}
                                    disabled={key === 'whatsapp_enabled' && !hasWhatsAppFeature}
                                />
                                {label}
                                {key === 'whatsapp_enabled' && !hasWhatsAppFeature && (
                                    <span className="text-xs text-on-surface-variant">(module off)</span>
                                )}
                            </label>
                        ))}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-1 font-semibold">Organization SMTP</h3>
                    <p className="mb-4 text-xs text-on-surface-variant">
                        Optional. Leave blank to fall back to platform SMTP (super admin), then the app .env mailer.
                    </p>
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
                        <select
                            className="rounded-xl border-slate-200"
                            value={form.data.smtp_encryption}
                            onChange={(e) => form.setData('smtp_encryption', e.target.value)}
                        >
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                        </select>
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="SMTP username"
                            value={form.data.smtp_username}
                            onChange={(e) => form.setData('smtp_username', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200 md:col-span-2"
                            placeholder={
                                settings.has_smtp_password ? 'SMTP password (leave blank to keep)' : 'SMTP password'
                            }
                            type="password"
                            value={form.data.smtp_password}
                            onChange={(e) => form.setData('smtp_password', e.target.value)}
                        />
                    </div>
                </section>

                {hasWhatsAppFeature ? (
                    <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-1 font-semibold">Meta WhatsApp Cloud API</h3>
                        <p className="mb-4 text-xs text-on-surface-variant">
                            Connect your WhatsApp Business Account with Meta (recommended), use the shared platform
                            number, or paste Cloud API credentials manually.
                        </p>

                        {canManageWhatsAppConfig && whatsappInstructions?.length > 0 && (
                            <div className="mb-4 rounded-xl bg-surface-container/60 p-4">
                                <p className="mb-2 text-sm font-semibold">Org Admin / Super Admin checklist</p>
                                <ol className="list-decimal space-y-1.5 pl-5 text-xs text-on-surface-variant">
                                    {whatsappInstructions.map((step) => (
                                        <li key={step}>{step}</li>
                                    ))}
                                </ol>
                            </div>
                        )}

                        {!canManageWhatsAppConfig ? (
                            <p className="text-sm text-on-surface-variant">
                                Only Super Admin or Organization Admin can change Meta credentials.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                <div className="rounded-xl border border-[#1877f2]/30 bg-[#1877f2]/5 p-4">
                                    <p className="mb-2 text-sm font-semibold text-[#1877f2]">
                                        Connect with Meta (recommended)
                                    </p>
                                    <p className="mb-3 text-xs text-on-surface-variant">
                                        Same flow as Wati: Meta opens a popup to create/link Business Portfolio, WhatsApp
                                        Business Account, and phone number. Approvals for the number happen in that flow;
                                        message templates are approved separately under Templates.
                                    </p>
                                    <MetaWhatsAppConnect
                                        embeddedSignup={embeddedSignup}
                                        connectRoute={route('messaging.whatsapp.connect')}
                                    />
                                </div>

                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.whatsapp_use_platform}
                                        onChange={(e) => form.setData('whatsapp_use_platform', e.target.checked)}
                                    />
                                    Use platform Meta WhatsApp credentials (shared number)
                                </label>
                                {!form.data.whatsapp_use_platform && (
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <input
                                            className="rounded-xl border-slate-200"
                                            placeholder="Display / from number (E.164)"
                                            value={form.data.whatsapp_from_number}
                                            onChange={(e) => form.setData('whatsapp_from_number', e.target.value)}
                                        />
                                        <input
                                            className="rounded-xl border-slate-200"
                                            placeholder="Phone Number ID"
                                            value={form.data.whatsapp_phone_number_id}
                                            onChange={(e) => form.setData('whatsapp_phone_number_id', e.target.value)}
                                        />
                                        <input
                                            className="rounded-xl border-slate-200"
                                            placeholder="WhatsApp Business Account ID (WABA)"
                                            value={form.data.whatsapp_waba_id}
                                            onChange={(e) => form.setData('whatsapp_waba_id', e.target.value)}
                                        />
                                        <input
                                            className="rounded-xl border-slate-200 md:col-span-2"
                                            placeholder={
                                                settings.has_whatsapp_api_key
                                                    ? 'Permanent access token (leave blank to keep)'
                                                    : 'Permanent access token'
                                            }
                                            type="password"
                                            value={form.data.whatsapp_api_key}
                                            onChange={(e) => form.setData('whatsapp_api_key', e.target.value)}
                                        />
                                    </div>
                                )}
                                <button
                                    type="button"
                                    onClick={testConnection}
                                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                                >
                                    Test Meta connection
                                </button>
                            </div>
                        )}
                    </section>
                ) : (
                    <section className="rounded-2xl border border-dashed border-outline-variant bg-surface-container/40 p-5">
                        <h3 className="mb-1 font-semibold">Meta WhatsApp Cloud API</h3>
                        <p className="text-sm text-on-surface-variant">
                            The WhatsApp module is not enabled for this organization. A Super Admin can turn it on
                            site-wide under{' '}
                            <Link href={route('platform.messaging.edit')} className="font-semibold text-secondary">
                                Platform messaging → WhatsApp module
                            </Link>
                            , or for this org only under{' '}
                            <Link href={route('billing.index')} className="font-semibold text-secondary">
                                Billing
                            </Link>
                            .
                        </p>
                    </section>
                )}

                {hasWhatsAppFeature && canManageWhatsAppConfig && (
                    <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-1 font-semibold">Upcoming WhatsApp automations</h3>
                        <p className="mb-4 text-xs text-on-surface-variant">
                            Bulk send and auto thank-you will use these settings in a later release. Configure them now.
                        </p>
                        <div className="space-y-3">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.bulk_whatsapp_enabled}
                                    onChange={(e) => form.setData('bulk_whatsapp_enabled', e.target.checked)}
                                />
                                Enable bulk WhatsApp to filtered donor lists (coming soon)
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.auto_donation_thankyou_enabled}
                                    onChange={(e) => form.setData('auto_donation_thankyou_enabled', e.target.checked)}
                                />
                                Auto thank-you / receipt after donation (coming soon)
                            </label>
                            <select
                                className="w-full rounded-xl border-slate-200 md:max-w-md"
                                value={form.data.auto_donation_thankyou_template_id}
                                onChange={(e) => form.setData('auto_donation_thankyou_template_id', e.target.value)}
                                disabled={!form.data.auto_donation_thankyou_enabled}
                            >
                                <option value="">Select approved thank-you template</option>
                                {thankYouTemplates.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </section>
                )}

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">SMS provider</h3>
                    <div className="grid gap-3 md:grid-cols-2">
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Provider"
                            value={form.data.sms_provider}
                            onChange={(e) => form.setData('sms_provider', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="From number / sender ID"
                            value={form.data.sms_from_number}
                            onChange={(e) => form.setData('sms_from_number', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200 md:col-span-2"
                            placeholder={settings.has_sms_api_key ? 'API key (leave blank to keep)' : 'API key'}
                            type="password"
                            value={form.data.sms_api_key}
                            onChange={(e) => form.setData('sms_api_key', e.target.value)}
                        />
                    </div>
                </section>

                {Object.values(form.errors).map((err) => (
                    <p key={err} className="text-xs text-error">
                        {err}
                    </p>
                ))}

                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                >
                    Save settings
                </button>
            </form>
        </AuthenticatedLayout>
    );
}
