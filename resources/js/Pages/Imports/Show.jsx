import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

export default function ImportsShow({ batch, donors }) {
    return (
        <AuthenticatedLayout header="Imported list">
            <Head title={batch.original_filename || 'Import'} />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">
                        Import batch #{batch.id}
                    </p>
                    <h2 className="text-headline-md">{batch.original_filename || 'Imported donors'}</h2>
                    <p className="text-sm text-on-surface-variant">
                        by {batch.uploader?.name} · {formatDateTime(batch.created_at)}
                        {batch.campaign ? (
                            <>
                                {' '}
                                · campaign{' '}
                                <Link
                                    href={route('campaigns.show', batch.campaign.id)}
                                    className="font-semibold text-secondary"
                                >
                                    {batch.campaign.name}
                                </Link>
                            </>
                        ) : null}
                    </p>
                </div>
                <Link
                    href={route('imports.index')}
                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                >
                    Back to import
                </Link>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    ['Created', batch.rows_created],
                    ['Updated', batch.rows_updated],
                    ['Assigned', batch.rows_assigned],
                    ['Skipped', batch.rows_skipped],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                        <p className="text-xs uppercase text-on-surface-variant">{label}</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
                    </div>
                ))}
            </div>

            {(batch.tags || []).length > 0 && (
                <div className="mb-4 flex flex-wrap gap-2">
                    {batch.tags.map((tag) => (
                        <span
                            key={tag}
                            className="rounded-full bg-surface-container px-3 py-1 text-xs font-semibold"
                        >
                            {tag}
                        </span>
                    ))}
                </div>
            )}

            {(batch.errors || []).length > 0 && (
                <div className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p className="mb-2 font-semibold">Import notes</p>
                    <ul className="list-disc space-y-1 pl-5">
                        {batch.errors.slice(0, 10).map((err) => (
                            <li key={err}>{err}</li>
                        ))}
                    </ul>
                </div>
            )}

            <section className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                {!donors.data?.length ? (
                    <EmptyState icon="group" title="No donors in this import" />
                ) : (
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="px-4 py-3">Donor</th>
                                <th className="px-4 py-3">Phone</th>
                                <th className="px-4 py-3">Tags</th>
                                <th className="px-4 py-3">Assigned to</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {donors.data.map((d) => (
                                <tr key={d.id} className="border-t border-slate-100">
                                    <td className="px-4 py-3 font-medium">{d.full_name}</td>
                                    <td className="px-4 py-3">{d.phone || '—'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {(d.tags || []).map((t) => (
                                                <span
                                                    key={t}
                                                    className="rounded-full bg-surface-container px-2 py-0.5 text-[10px] font-semibold"
                                                >
                                                    {t}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {d.active_assignment?.volunteer?.name || '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('donors.show', d.id)}
                                            className="text-xs font-semibold text-secondary"
                                        >
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>

            {(donors.prev_page_url || donors.next_page_url) && (
                <div className="mt-4 flex justify-between text-sm">
                    {donors.prev_page_url ? (
                        <Link href={donors.prev_page_url} className="font-semibold text-secondary">
                            Previous
                        </Link>
                    ) : (
                        <span />
                    )}
                    {donors.next_page_url ? (
                        <Link href={donors.next_page_url} className="font-semibold text-secondary">
                            Next
                        </Link>
                    ) : null}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
