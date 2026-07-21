import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link, router } from '@inertiajs/react';

export default function DialerQueue({ donor, queue_empty }) {
    const refresh = () => router.get(route('dialer.queue'));

    const skip = () => {
        if (!donor?.id) return;
        router.post(route('dialer.skip'), { donor_id: donor.id });
    };

    return (
        <AuthenticatedLayout header="Call queue">
            <Head title="Dialer queue" />

            <div className="mb-6">
                <h2 className="text-headline-md">Next donor</h2>
                <p className="text-sm text-on-surface-variant">
                    Work through your assigned callable donors in priority order.
                </p>
            </div>

            {queue_empty || !donor ? (
                <EmptyState
                    icon="call_end"
                    title="Queue empty"
                    description="No callable donors right now. Check back later or refresh the queue."
                    action={
                        <button
                            type="button"
                            onClick={refresh}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                        >
                            Refresh
                        </button>
                    }
                />
            ) : (
                <div className="rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div className="mb-2 flex flex-wrap items-center gap-2">
                                <h3 className="text-xl font-bold">{donor.full_name}</h3>
                                {donor.donor_status && (
                                    <StatusBadge
                                        status={donor.donor_status}
                                        label={String(donor.donor_status).replaceAll('_', ' ')}
                                    />
                                )}
                            </div>
                            <p className="text-sm text-on-surface-variant">
                                {donor.phone || 'No phone'}
                                {donor.email ? ` · ${donor.email}` : ''}
                            </p>
                            {donor.campaign && (
                                <p className="mt-2 text-sm">
                                    <span className="text-on-surface-variant">Campaign: </span>
                                    <span className="font-medium">{donor.campaign.name}</span>
                                </p>
                            )}
                            {(donor.tags || []).length > 0 && (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {donor.tags.map((tag) => (
                                        <span
                                            key={tag}
                                            className="rounded-full bg-surface-container px-2.5 py-1 text-xs font-semibold"
                                        >
                                            {tag}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {donor.phone && (
                                <a
                                    href={`tel:${donor.phone}`}
                                    className="inline-flex items-center gap-2 rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white"
                                >
                                    <span className="material-symbols-outlined text-[18px]">call</span>
                                    Call
                                </a>
                            )}
                            <Link
                                href={route('donors.show', donor.id)}
                                className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                            >
                                Open donor
                            </Link>
                            <button
                                type="button"
                                onClick={skip}
                                className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                            >
                                Skip
                            </button>
                            <button
                                type="button"
                                onClick={refresh}
                                className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                            >
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
