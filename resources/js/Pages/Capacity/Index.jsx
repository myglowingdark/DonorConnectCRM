import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { formatDate } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CapacityIndex({ bookings, campaigns, canApprove }) {
    const form = useForm({
        seats: 1,
        starts_on: '',
        ends_on: '',
        campaign_id: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('capacity.store'), {
            onSuccess: () => form.reset('notes'),
        });
    };

    return (
        <AuthenticatedLayout header="Capacity booking">
            <Head title="Capacity booking" />

            <div className="mb-6">
                <h2 className="text-headline-md">Telecaller capacity</h2>
                <p className="text-sm text-on-surface-variant">
                    Book DonorConnect telecaller seats for campaign windows.
                </p>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Request seats</h3>
                <form onSubmit={submit} className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label className="text-xs font-semibold">Seats</label>
                        <input
                            type="number"
                            min="1"
                            max="100"
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.seats}
                            onChange={(e) => form.setData('seats', e.target.value)}
                            required
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Starts on</label>
                        <input
                            type="date"
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.starts_on}
                            onChange={(e) => form.setData('starts_on', e.target.value)}
                            required
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Ends on</label>
                        <input
                            type="date"
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.ends_on}
                            onChange={(e) => form.setData('ends_on', e.target.value)}
                            required
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Campaign (optional)</label>
                        <select
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.campaign_id}
                            onChange={(e) => form.setData('campaign_id', e.target.value)}
                        >
                            <option value="">Any / general</option>
                            {campaigns.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="md:col-span-2">
                        <label className="text-xs font-semibold">Notes</label>
                        <textarea
                            className="mt-1 w-full rounded-xl border-slate-200"
                            rows={2}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </div>
                    {Object.values(form.errors).map((err) => (
                        <p key={err} className="text-xs text-error md:col-span-3">
                            {err}
                        </p>
                    ))}
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white md:w-fit"
                    >
                        Submit request
                    </button>
                </form>
            </section>

            <section className="rounded-2xl border border-slate-100 bg-white shadow-card">
                {!bookings.data?.length ? (
                    <EmptyState icon="event_seat" title="No bookings yet" />
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                                    <tr>
                                        {canApprove && <th className="px-4 py-3">Organization</th>}
                                        <th className="px-4 py-3">Seats</th>
                                        <th className="px-4 py-3">Dates</th>
                                        <th className="px-4 py-3">Campaign</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Created by</th>
                                        {canApprove && <th className="px-4 py-3" />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {bookings.data.map((b) => (
                                        <tr key={b.id} className="border-t border-slate-100">
                                            {canApprove && (
                                                <td className="px-4 py-3">{b.organization?.name || '—'}</td>
                                            )}
                                            <td className="px-4 py-3 tabular-nums">{b.seats}</td>
                                            <td className="px-4 py-3 text-xs">
                                                {formatDate(b.starts_on)} – {formatDate(b.ends_on)}
                                            </td>
                                            <td className="px-4 py-3">{b.campaign?.name || '—'}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={b.status} label={b.status} />
                                            </td>
                                            <td className="px-4 py-3">{b.creator?.name || '—'}</td>
                                            {canApprove && b.status === 'pending' && (
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Link
                                                            href={route('capacity.approve', b.id)}
                                                            method="post"
                                                            as="button"
                                                            className="text-xs font-semibold text-green-700"
                                                        >
                                                            Approve
                                                        </Link>
                                                        <Link
                                                            href={route('capacity.reject', b.id)}
                                                            method="post"
                                                            as="button"
                                                            className="text-xs font-semibold text-error"
                                                        >
                                                            Reject
                                                        </Link>
                                                    </div>
                                                </td>
                                            )}
                                            {canApprove && b.status !== 'pending' && (
                                                <td className="px-4 py-3" />
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {bookings.links?.length > 3 && (
                            <div className="flex flex-wrap gap-2 border-t border-slate-100 p-4">
                                {bookings.links.map((link, idx) => (
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
            </section>
        </AuthenticatedLayout>
    );
}
