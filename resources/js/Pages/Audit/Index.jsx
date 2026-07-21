import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';

export default function AuditIndex({ logs, filters, organizations }) {
    const applyFilters = (updates) => {
        router.get(
            route('audit.index'),
            { ...filters, ...updates },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout header="Audit log">
            <Head title="Audit log" />

            <div className="mb-6">
                <h2 className="text-headline-md">Audit trail</h2>
                <p className="text-sm text-on-surface-variant">
                    Track who changed what across your organization.
                </p>
            </div>

            <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center">
                {organizations.length > 0 && (
                    <select
                        className="rounded-xl border-slate-200"
                        value={filters.organization_id || ''}
                        onChange={(e) =>
                            applyFilters({ organization_id: e.target.value || undefined })
                        }
                    >
                        <option value="">Current organization</option>
                        {organizations.map((org) => (
                            <option key={org.id} value={org.id}>
                                {org.name}
                            </option>
                        ))}
                    </select>
                )}
                <input
                    defaultValue={filters.action || ''}
                    placeholder="Filter by action"
                    className="w-full max-w-md rounded-xl border-slate-200"
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            applyFilters({ action: e.target.value || undefined });
                        }
                    }}
                />
                {(filters.action || filters.organization_id) && (
                    <button
                        type="button"
                        onClick={() => applyFilters({ action: undefined, organization_id: undefined })}
                        className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                    >
                        Clear filters
                    </button>
                )}
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                {!logs.data?.length ? (
                    <EmptyState icon="history" title="No audit entries" />
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                                    <tr>
                                        <th className="px-4 py-3">When</th>
                                        <th className="px-4 py-3">Actor</th>
                                        <th className="px-4 py-3">Action</th>
                                        <th className="px-4 py-3">Subject</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((log) => (
                                        <tr key={log.id} className="border-t border-slate-100">
                                            <td className="px-4 py-3 text-xs">
                                                {formatDateTime(log.created_at)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <p className="font-medium">{log.actor?.name ?? 'System'}</p>
                                                {log.actor?.email && (
                                                    <p className="text-xs text-on-surface-variant">
                                                        {log.actor.email}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">{log.action}</td>
                                            <td className="px-4 py-3 text-xs">
                                                {log.subject_type
                                                    ? `${log.subject_type}#${log.subject_id}`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {logs.links?.length > 3 && (
                            <div className="flex flex-wrap gap-2 border-t border-slate-100 p-4">
                                {logs.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        className={`rounded-lg px-3 py-1 text-xs ${
                                            link.active ? 'bg-primary text-white' : 'bg-surface-container'
                                        } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
