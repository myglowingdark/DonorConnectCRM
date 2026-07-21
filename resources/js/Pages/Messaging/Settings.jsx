import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function MessagingSettings({ settings }) {
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
        whatsapp_provider: settings.whatsapp_provider || '',
        whatsapp_api_key: '',
        whatsapp_from_number: settings.whatsapp_from_number || '',
        sms_provider: settings.sms_provider || '',
        sms_api_key: '',
        sms_from_number: settings.sms_from_number || '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('messaging.settings.update'));
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
                                />
                                {label}
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

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">WhatsApp provider</h3>
                    <div className="grid gap-3 md:grid-cols-2">
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Provider (e.g. meta, twilio)"
                            value={form.data.whatsapp_provider}
                            onChange={(e) => form.setData('whatsapp_provider', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="From number"
                            value={form.data.whatsapp_from_number}
                            onChange={(e) => form.setData('whatsapp_from_number', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200 md:col-span-2"
                            placeholder={
                                settings.has_whatsapp_api_key
                                    ? 'API key (leave blank to keep)'
                                    : 'API key'
                            }
                            type="password"
                            value={form.data.whatsapp_api_key}
                            onChange={(e) => form.setData('whatsapp_api_key', e.target.value)}
                        />
                    </div>
                </section>

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
