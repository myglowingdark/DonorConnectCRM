import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime } from '@/lib/format';
import { Head, router, usePage } from '@inertiajs/react';

export default function TransfersIndex({ transfers, filters, statuses, isAdmin }) {
    const { auth } = usePage().props;
    const userId = auth.user?.id;

    return (
        <AuthenticatedLayout header="Donor Transfers">
            <Head title="Transfers" />

            <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 className="text-headline-md">Interaction transfers</h2>
                    <p className="text-sm text-on-surface-variant">
                        Volunteers can hand donors to teammates. Receiver must accept before the queue moves.
                    </p>
                </div>
                <select
                    className="rounded-xl border-slate-200 text-sm"
                    value={filters.status || ''}
                    onChange={(e) =>
                        router.get(route('transfers.index'), { status: e.target.value || undefined }, { preserveState: true })
                    }
                >
                    <option value="">All statuses</option>
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </select>
            </div>

            {!transfers.data.length ? (
                <EmptyState icon="swap_horiz" title="No transfers yet" description="Request a transfer from a donor profile." />
            ) : (
                <div className="space-y-3">
                    {transfers.data.map((transfer) => {
                        const canRespond =
                            isAdmin || transfer.to_volunteer_id === userId || transfer.to_volunteer?.id === userId;
                        const canCancel =
                            isAdmin ||
                            transfer.requested_by === userId ||
                            transfer.from_volunteer_id === userId ||
                            transfer.from_volunteer?.id === userId;

                        return (
                            <div key={transfer.id} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <div className="mb-2 flex flex-wrap items-center gap-2">
                                            <p className="font-semibold">{transfer.donor?.full_name}</p>
                                            <StatusBadge
                                                status={transfer.status}
                                                label={String(transfer.status).replaceAll('_', ' ')}
                                            />
                                        </div>
                                        <p className="text-sm text-on-surface-variant">
                                            {transfer.from_volunteer?.name} → {transfer.to_volunteer?.name}
                                        </p>
                                        {transfer.reason && <p className="mt-2 text-sm">{transfer.reason}</p>}
                                        <p className="mt-2 text-xs text-on-surface-variant">
                                            Requested {formatDateTime(transfer.created_at)}
                                            {transfer.responded_at
                                                ? ` · Responded ${formatDateTime(transfer.responded_at)}`
                                                : ''}
                                        </p>
                                    </div>

                                    {transfer.status === 'pending' && (
                                        <div className="flex flex-wrap gap-2">
                                            {canRespond && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post(route('transfers.accept', transfer.id))}
                                                        className="rounded-xl bg-secondary px-3 py-2 text-xs font-semibold text-white"
                                                    >
                                                        Accept
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post(route('transfers.reject', transfer.id))}
                                                        className="rounded-xl border border-outline-variant px-3 py-2 text-xs font-semibold"
                                                    >
                                                        Decline
                                                    </button>
                                                </>
                                            )}
                                            {canCancel && (
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(route('transfers.cancel', transfer.id))}
                                                    className="rounded-xl px-3 py-2 text-xs font-semibold text-error"
                                                >
                                                    Cancel
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
