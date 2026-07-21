import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatINR, formatDateTime } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';

export default function AttributionsIndex({ attributions, filters, counts }) {
    const setStatus = (status) => {
        router.get(route('attributions.index'), { status }, { preserveState: true });
    };

    const review = (id, action) => {
        const note = window.prompt('Optional admin note') || '';
        router.post(route(`attributions.${action}`, id), { admin_note: note });
    };

    return (
        <AuthenticatedLayout header="Attributions">
            <Head title="Donation Attributions" />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 className="text-headline-md">Donation attribution queue</h2>
                    <p className="text-sm text-on-surface-variant">
                        Approve volunteer credit for donations linked from call logs.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    {['pending', 'approved', 'rejected', 'all'].map((status) => (
                        <button
                            key={status}
                            type="button"
                            onClick={() => setStatus(status)}
                            className={`rounded-lg px-3 py-1.5 capitalize ${
                                filters.status === status
                                    ? 'bg-primary text-white'
                                    : 'border border-outline-variant'
                            }`}
                        >
                            {status}
                            {status !== 'all' && counts[status] != null ? ` (${counts[status]})` : ''}
                        </button>
                    ))}
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                <table className="min-w-full text-sm">
                    <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                        <tr>
                            <th className="px-4 py-3">Donor</th>
                            <th className="px-4 py-3">Volunteer</th>
                            <th className="px-4 py-3">Donation</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {attributions.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-on-surface-variant">
                                    No attributions in this filter.
                                </td>
                            </tr>
                        )}
                        {attributions.data.map((row) => (
                            <tr key={row.id} className="border-t border-slate-100">
                                <td className="px-4 py-3">
                                    <Link href={route('donors.show', row.donor_id)} className="font-medium text-secondary">
                                        {row.donor?.full_name}
                                    </Link>
                                </td>
                                <td className="px-4 py-3">{row.volunteer?.name}</td>
                                <td className="px-4 py-3">
                                    <p className="font-semibold tabular-nums">{formatINR(row.donation?.amount)}</p>
                                    <p className="text-xs text-on-surface-variant">
                                        {row.donation?.donated_at ? formatDateTime(row.donation.donated_at) : '—'}
                                    </p>
                                </td>
                                <td className="px-4 py-3 capitalize">{row.status}</td>
                                <td className="px-4 py-3 text-right">
                                    {row.status === 'pending' && (
                                        <div className="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() => review(row.id, 'approve')}
                                                className="rounded-lg bg-primary px-3 py-1 text-xs font-semibold text-white"
                                            >
                                                Approve
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => review(row.id, 'reject')}
                                                className="rounded-lg border border-outline-variant px-3 py-1 text-xs font-semibold"
                                            >
                                                Reject
                                            </button>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AuthenticatedLayout>
    );
}
