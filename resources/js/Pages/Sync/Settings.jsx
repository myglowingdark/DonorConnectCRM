import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';

export default function SyncSettings({ connection, history, authTypes, defaultMappings }) {
    const form = useForm({
        name: connection?.name || 'WordPress Donors',
        base_url: connection?.base_url || '',
        auth_type: connection?.auth_type || 'bearer',
        token: '',
        username: '',
        password: '',
        api_key: '',
        api_key_header: 'X-API-Key',
        field_mappings: connection?.field_mappings || defaultMappings,
        is_active: connection?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        if (connection?.id) {
            form.put(route('sync.update', connection.id));
        } else {
            form.post(route('sync.store'));
        }
    };

    return (
        <AuthenticatedLayout header="WordPress API Sync">
            <Head title="API Sync" />

            <div className="mb-6">
                <h2 className="text-headline-md">Donor API connection</h2>
                <p className="text-sm text-on-surface-variant">
                    Credentials are encrypted and never sent back to the browser after saving.
                </p>
            </div>

            {!connection && (
                <div className="mb-6">
                    <EmptyState
                        icon="cloud_sync"
                        title="No API connection yet"
                        description="Add your WordPress donor endpoint to start syncing."
                    />
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-3">
                <form onSubmit={submit} className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card xl:col-span-2">
                    <div>
                        <label className="text-xs font-semibold">Connection name</label>
                        <input className="mt-1 w-full rounded-xl border-slate-200" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </div>
                    <div>
                        <label className="text-xs font-semibold">API base URL</label>
                        <input className="mt-1 w-full rounded-xl border-slate-200" placeholder="https://example.org/wp-json/donorconnect/v1" value={form.data.base_url} onChange={(e) => form.setData('base_url', e.target.value)} />
                        {form.errors.base_url && <p className="text-xs text-error">{form.errors.base_url}</p>}
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Authentication</label>
                        <select className="mt-1 w-full rounded-xl border-slate-200" value={form.data.auth_type} onChange={(e) => form.setData('auth_type', e.target.value)}>
                            {authTypes.map((t) => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                    </div>

                    {form.data.auth_type === 'bearer' && (
                        <div>
                            <label className="text-xs font-semibold">Bearer token {connection?.has_credentials && '(leave blank to keep existing)'}</label>
                            <input type="password" className="mt-1 w-full rounded-xl border-slate-200" value={form.data.token} onChange={(e) => form.setData('token', e.target.value)} autoComplete="new-password" />
                        </div>
                    )}
                    {form.data.auth_type === 'basic' && (
                        <div className="grid grid-cols-2 gap-3">
                            <input className="rounded-xl border-slate-200" placeholder="Username" value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
                            <input type="password" className="rounded-xl border-slate-200" placeholder="Password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                        </div>
                    )}
                    {form.data.auth_type === 'api_key' && (
                        <div className="grid grid-cols-2 gap-3">
                            <input className="rounded-xl border-slate-200" placeholder="Header name" value={form.data.api_key_header} onChange={(e) => form.setData('api_key_header', e.target.value)} />
                            <input type="password" className="rounded-xl border-slate-200" placeholder="API key" value={form.data.api_key} onChange={(e) => form.setData('api_key', e.target.value)} />
                        </div>
                    )}

                    <div className="rounded-xl bg-surface-container-low p-4 text-xs text-on-surface-variant">
                        <p className="mb-2 font-semibold text-on-surface">Default field mappings</p>
                        <pre className="overflow-x-auto whitespace-pre-wrap">{JSON.stringify(form.data.field_mappings, null, 2)}</pre>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button disabled={form.processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                            Save connection
                        </button>
                        {connection?.id && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => router.post(route('sync.test', connection.id))}
                                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                                >
                                    Test connection
                                </button>
                                <button
                                    type="button"
                                    onClick={() => router.post(route('sync.run', connection.id))}
                                    className="rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Sync now
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
                            {connection.last_error && (
                                <p className="rounded-lg bg-rose-50 p-2 text-xs text-rose-700">{connection.last_error}</p>
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
                                    <td className="py-2"><StatusBadge status={run.status} label={run.status} /></td>
                                    <td className="py-2">{run.donors_imported}</td>
                                    <td className="py-2">{run.donors_updated}</td>
                                    <td className="py-2">{run.donations_imported}</td>
                                    <td className="py-2 text-xs text-error">{run.error_details || '—'}</td>
                                </tr>
                            ))}
                            {!history.length && (
                                <tr>
                                    <td colSpan={6} className="py-6 text-on-surface-variant">No sync runs yet.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
