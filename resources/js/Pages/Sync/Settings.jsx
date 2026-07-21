import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime } from '@/lib/format';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function SyncSettings({
    organization,
    connection,
    history,
    authTypes,
    defaultMappings,
    crmApiBaseUrl,
    routes: actionRoutes,
}) {
    const { flash } = usePage().props;
    const [copied, setCopied] = useState(false);
    const form = useForm({
        name: connection?.name || 'DonorConnect Bridge',
        base_url: connection?.base_url || '',
        auth_type: connection?.auth_type || 'hmac',
        token: '',
        username: '',
        password: '',
        api_key: '',
        api_key_header: 'X-DC-API-Key',
        hmac_secret: '',
        site_id: connection?.site_id || '',
        field_mappings: connection?.field_mappings || defaultMappings,
        is_active: connection?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        if (connection?.id && actionRoutes?.update) {
            form.put(actionRoutes.update);
        } else {
            form.post(actionRoutes.store);
        }
    };

    return (
        <AuthenticatedLayout header="WordPress site">
            <Head title={`${organization?.name || 'Org'} · WordPress`} />

            <div className="mb-6">
                <div className="mb-2 flex flex-wrap items-center gap-2">
                    <h2 className="text-headline-md">Connect WordPress site</h2>
                    {organization?.name && (
                        <span className="rounded-full bg-surface-container px-3 py-1 text-xs font-semibold">
                            {organization.name}
                        </span>
                    )}
                </div>
                <p className="text-sm text-on-surface-variant">
                    Each organization connects its own WordPress site (DonorConnect Bridge + NGOBuddy). Super Admins
                    and Org Admins can manage this connection. Credentials are encrypted and never shown again after
                    saving.
                </p>
                {actionRoutes?.profile && (
                    <Link href={actionRoutes.profile} className="mt-2 inline-block text-sm font-semibold text-secondary">
                        ← Back to org profile
                    </Link>
                )}
                <div className="mt-3 rounded-xl border border-slate-100 bg-surface-container-low p-4 text-xs text-on-surface-variant">
                    <p className="font-semibold text-on-surface">Recommended: Pair with DonorConnect</p>
                    <ol className="mt-2 list-decimal space-y-1 pl-4">
                        <li>Click Generate pairing code below (valid 15 minutes, one use).</li>
                        <li>
                            In WordPress Admin → DonorConnect, paste CRM URL{' '}
                            <code>{crmApiBaseUrl?.replace('/v1', '') || '/api'}</code> and the pairing code.
                        </li>
                        <li>Click Pair with DonorConnect in WordPress, then Test connection here.</li>
                    </ol>
                </div>
                <div className="mt-3 rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => router.post(actionRoutes?.pairing_code)}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                        >
                            Generate pairing code
                        </button>
                        {flash?.bridge_pairing_code && (
                            <button
                                type="button"
                                onClick={() => {
                                    navigator.clipboard?.writeText(flash.bridge_pairing_code);
                                    setCopied(true);
                                    setTimeout(() => setCopied(false), 2000);
                                }}
                                className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                            >
                                {copied ? 'Copied' : 'Copy code'}
                            </button>
                        )}
                    </div>
                    {flash?.bridge_pairing_code && (
                        <div className="mt-3 space-y-1 text-sm">
                            <p className="font-mono break-all rounded-lg bg-white px-3 py-2 text-on-surface">
                                {flash.bridge_pairing_code}
                            </p>
                            <p className="text-xs text-on-surface-variant">
                                Expires {formatDateTime(flash.bridge_pairing_expires_at)} · paste once in WordPress
                            </p>
                        </div>
                    )}
                </div>
                <div className="mt-3 rounded-xl border border-slate-100 bg-surface-container-low p-4 text-xs text-on-surface-variant">
                    <p className="font-semibold text-on-surface">Manual setup (fallback)</p>
                    <ol className="mt-2 list-decimal space-y-1 pl-4">
                        <li>
                            Install <code>wordpress-plugins/donorconnect-bridge</code> on this org’s WordPress site
                            (alongside NGOBuddy).
                        </li>
                        <li>Open WP Admin → DonorConnect → Reveal secrets (Site ID, API Key, HMAC Secret).</li>
                        <li>
                            Paste REST base URL like <code>https://this-org.org/wp-json/donorconnect/v1</code> below.
                        </li>
                        <li>
                            Auth type: <strong>HMAC (DonorConnect Bridge)</strong>. Save, then Test connection / Sync
                            now.
                        </li>
                    </ol>
                </div>
            </div>

            {!connection && (
                <div className="mb-6">
                    <EmptyState
                        icon="cloud_sync"
                        title="No WordPress site connected yet"
                        description="Add this organization’s WordPress Bridge endpoint to start syncing donors."
                    />
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-3">
                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card xl:col-span-2"
                >
                    <div>
                        <label className="text-xs font-semibold">Connection name</label>
                        <input
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold">API base URL</label>
                        <input
                            className="mt-1 w-full rounded-xl border-slate-200"
                            placeholder="https://example.org/wp-json/donorconnect/v1"
                            value={form.data.base_url}
                            onChange={(e) => form.setData('base_url', e.target.value)}
                        />
                        {form.errors.base_url && <p className="text-xs text-error">{form.errors.base_url}</p>}
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Authentication</label>
                        <select
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.auth_type}
                            onChange={(e) => form.setData('auth_type', e.target.value)}
                        >
                            {authTypes.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {form.data.auth_type === 'bearer' && (
                        <div>
                            <label className="text-xs font-semibold">
                                Bearer token {connection?.has_credentials && '(leave blank to keep existing)'}
                            </label>
                            <input
                                type="password"
                                className="mt-1 w-full rounded-xl border-slate-200"
                                value={form.data.token}
                                onChange={(e) => form.setData('token', e.target.value)}
                                autoComplete="new-password"
                            />
                        </div>
                    )}
                    {form.data.auth_type === 'basic' && (
                        <div className="grid grid-cols-2 gap-3">
                            <input
                                className="rounded-xl border-slate-200"
                                placeholder="Username"
                                value={form.data.username}
                                onChange={(e) => form.setData('username', e.target.value)}
                            />
                            <input
                                type="password"
                                className="rounded-xl border-slate-200"
                                placeholder="Password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </div>
                    )}
                    {form.data.auth_type === 'api_key' && (
                        <div className="grid grid-cols-2 gap-3">
                            <input
                                className="rounded-xl border-slate-200"
                                placeholder="Header name"
                                value={form.data.api_key_header}
                                onChange={(e) => form.setData('api_key_header', e.target.value)}
                            />
                            <input
                                type="password"
                                className="rounded-xl border-slate-200"
                                placeholder="API key"
                                value={form.data.api_key}
                                onChange={(e) => form.setData('api_key', e.target.value)}
                            />
                        </div>
                    )}
                    {form.data.auth_type === 'hmac' && (
                        <div className="space-y-3">
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="Site ID (from WP plugin)"
                                value={form.data.site_id}
                                onChange={(e) => form.setData('site_id', e.target.value)}
                            />
                            <input
                                type="password"
                                className="w-full rounded-xl border-slate-200"
                                placeholder={
                                    connection?.has_credentials ? 'API key (leave blank to keep)' : 'API key'
                                }
                                value={form.data.api_key}
                                onChange={(e) => form.setData('api_key', e.target.value)}
                                autoComplete="new-password"
                            />
                            <input
                                type="password"
                                className="w-full rounded-xl border-slate-200"
                                placeholder={
                                    connection?.has_credentials
                                        ? 'HMAC secret (leave blank to keep)'
                                        : 'HMAC secret'
                                }
                                value={form.data.hmac_secret}
                                onChange={(e) => form.setData('hmac_secret', e.target.value)}
                                autoComplete="new-password"
                            />
                        </div>
                    )}

                    <div className="rounded-xl bg-surface-container-low p-4 text-xs text-on-surface-variant">
                        <p className="mb-2 font-semibold text-on-surface">Default field mappings</p>
                        <pre className="overflow-x-auto whitespace-pre-wrap">
                            {JSON.stringify(form.data.field_mappings, null, 2)}
                        </pre>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button
                            disabled={form.processing}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                        >
                            Save connection
                        </button>
                        {connection?.id && actionRoutes?.test && (
                            <>
                                <button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => router.post(actionRoutes.test)}
                                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold disabled:opacity-50"
                                >
                                    Test connection
                                </button>
                                <button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => router.post(actionRoutes.run)}
                                    className="rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                                >
                                    Sync donors now
                                </button>
                                <button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => router.post(actionRoutes.razorpay_status)}
                                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold disabled:opacity-50"
                                >
                                    Check WP Razorpay
                                </button>
                                <button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => {
                                        if (
                                            confirm(
                                                'Pull Razorpay API keys from this organization’s WordPress site?',
                                            )
                                        ) {
                                            router.post(actionRoutes.razorpay);
                                        }
                                    }}
                                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                                >
                                    Sync Razorpay keys
                                </button>
                            </>
                        )}
                    </div>
                </form>

                <aside className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-3 font-semibold">Last sync</h3>
                    {connection ? (
                        <div className="space-y-2 text-sm">
                            <StatusBadge status={connection.sync_status} label={connection.sync_status} />
                            <p>Last synced: {formatDateTime(connection.last_synced_at)}</p>
                        {connection?.credentials_readable === false && (
                            <p className="rounded-lg bg-amber-50 p-2 text-xs text-amber-900">
                                Saved bridge secrets cannot be read (encryption key mismatch). Paste Site ID, API key,
                                and HMAC secret from WordPress, then Save connection.
                            </p>
                        )}
                        {connection?.last_error && (
                            <p className="rounded-lg bg-rose-50 p-2 text-xs text-rose-700">
                                {connection.last_error}
                            </p>
                        )}
                        </div>
                    ) : (
                        <p className="text-sm text-on-surface-variant">Save a connection to see sync status.</p>
                    )}
                </aside>
            </div>

            <div className="mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Sync history</h3>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="py-2">Started</th>
                                <th className="py-2">Status</th>
                                <th className="py-2">Donors +</th>
                                <th className="py-2">Donors ~</th>
                                <th className="py-2">Donations +</th>
                                <th className="py-2">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            {history.map((run) => (
                                <tr key={run.id} className="border-t border-slate-100">
                                    <td className="py-2">{formatDateTime(run.started_at)}</td>
                                    <td className="py-2">
                                        <StatusBadge status={run.status} label={run.status} />
                                    </td>
                                    <td className="py-2">{run.donors_imported}</td>
                                    <td className="py-2">{run.donors_updated}</td>
                                    <td className="py-2">{run.donations_imported}</td>
                                    <td className="py-2 text-xs text-error">{run.error_details || '—'}</td>
                                </tr>
                            ))}
                            {!history.length && (
                                <tr>
                                    <td colSpan={6} className="py-6 text-on-surface-variant">
                                        No sync runs yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
