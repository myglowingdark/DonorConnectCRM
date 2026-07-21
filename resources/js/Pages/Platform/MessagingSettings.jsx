import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function PlatformMessagingSettings({ settings }) {
    const form = useForm({
        email_enabled: !!settings.email_enabled,
        smtp_host: settings.smtp_host || '',
        smtp_port: settings.smtp_port || 587,
        smtp_encryption: settings.smtp_encryption || 'tls',
        smtp_username: settings.smtp_username || '',
        smtp_password: '',
        from_email: settings.from_email || '',
        from_name: settings.from_name || '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('platform.messaging.update'));
    };

    return (
        <AuthenticatedLayout header="Platform SMTP">
            <Head title="Platform SMTP" />

            <div className="mb-6">
                <h2 className="text-headline-md">Default SMTP for all organizations</h2>
                <p className="text-sm text-on-surface-variant">
                    Used when an organization has not configured its own SMTP. Fallback order: org SMTP →
                    platform SMTP → app .env mailer.
                </p>
            </div>

            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
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
                <button disabled={form.processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                    Save platform SMTP
                </button>
            </form>
        </AuthenticatedLayout>
    );
}
