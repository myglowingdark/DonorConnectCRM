import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusBadge from '@/Components/StatusBadge';
import { formatDate, formatDateTime, formatINR } from '@/lib/format';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function DonorShow({ donor, campaigns, outcomes, nextDonorId }) {
    const { auth } = usePage().props;
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        outcome: 'interested',
        notes: '',
        follow_up_at: '',
        pledged_amount: '',
        campaign_id: '',
        attribute_donation: false,
        go_next: false,
    });

    const submit = (goNext = false) => {
        transform((formData) => ({
            ...formData,
            go_next: goNext,
        }));
        post(route('donors.log-call', donor.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (!goNext) reset('notes', 'follow_up_at', 'pledged_amount');
            },
        });
    };

    return (
        <AuthenticatedLayout header="Donor Profile">
            <Head title={donor.full_name} />

            {donor.do_not_call && (
                <div className="mb-4 flex items-center justify-between gap-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">
                    <div className="flex items-center gap-2">
                        <span className="material-symbols-outlined">do_not_disturb_on</span>
                        <span className="font-semibold">Do Not Call — new calls are blocked.</span>
                    </div>
                    {['super_admin', 'organization_admin'].includes(auth.user?.role) && (
                        <Link
                            href={route('donors.clear-dnc', donor.id)}
                            method="post"
                            as="button"
                            className="rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white"
                        >
                            Remove restriction
                        </Link>
                    )}
                </div>
            )}

            <div className="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <h2 className="text-headline-md">{donor.full_name}</h2>
                            <StatusBadge status={donor.donor_status} label={String(donor.donor_status).replaceAll('_', ' ')} />
                            <span
                                className="rounded-full px-2.5 py-1 text-xs font-semibold text-white"
                                style={{ backgroundColor: donor.organization?.brand_color || '#1e3a8a' }}
                            >
                                {donor.organization?.name}
                            </span>
                        </div>
                        <p className="text-sm text-on-surface-variant">
                            {donor.phone || 'No phone'} · {donor.email || 'No email'}
                        </p>
                        <p className="mt-1 text-sm text-on-surface-variant">
                            {[donor.city, donor.state, donor.country].filter(Boolean).join(', ') || 'Address not set'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {donor.phone && !donor.do_not_call && (
                            <a
                                href={`tel:${donor.phone}`}
                                className="inline-flex items-center gap-2 rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white"
                            >
                                <span className="material-symbols-outlined text-[18px]">call</span>
                                Call
                            </a>
                        )}
                        <Link
                            href={route('donors.index')}
                            className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                        >
                            Back to queue
                        </Link>
                    </div>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl bg-surface-container-low p-4">
                        <p className="text-xs text-on-surface-variant">Last donation</p>
                        <p className="text-lg font-bold tabular-nums">{formatINR(donor.last_donation_amount)}</p>
                        <p className="text-xs text-on-surface-variant">{formatDate(donor.last_donation_at)}</p>
                    </div>
                    <div className="rounded-xl bg-surface-container-low p-4">
                        <p className="text-xs text-on-surface-variant">Total donated</p>
                        <p className="text-lg font-bold tabular-nums">{formatINR(donor.total_donated)}</p>
                    </div>
                    <div className="rounded-xl bg-surface-container-low p-4">
                        <p className="text-xs text-on-surface-variant">Donations</p>
                        <p className="text-lg font-bold">{donor.donations?.length || 0}</p>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-4 font-semibold">Donation history</h3>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="text-left text-xs uppercase text-on-surface-variant">
                                    <tr>
                                        <th className="py-2">Date</th>
                                        <th className="py-2">Amount</th>
                                        <th className="py-2">Status</th>
                                        <th className="py-2">Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(donor.donations || []).map((d) => (
                                        <tr key={d.id} className="border-t border-slate-100">
                                            <td className="py-2">{formatDate(d.donated_at)}</td>
                                            <td className="py-2 tabular-nums">{formatINR(d.amount)}</td>
                                            <td className="py-2">{d.payment_status}</td>
                                            <td className="py-2">{d.payment_method || '—'}</td>
                                        </tr>
                                    ))}
                                    {!donor.donations?.length && (
                                        <tr>
                                            <td colSpan={4} className="py-6 text-on-surface-variant">
                                                No donations synced yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-4 font-semibold">Interaction timeline</h3>
                        <ul className="space-y-4 border-l border-outline-variant pl-4">
                            {(donor.interactions || []).map((item) => (
                                <li key={item.id} className="relative">
                                    <span className="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-primary" />
                                    <p className="text-sm font-semibold">
                                        {String(item.outcome).replaceAll('_', ' ')} · {item.volunteer?.name}
                                    </p>
                                    <p className="text-xs text-on-surface-variant">{formatDateTime(item.contacted_at)}</p>
                                    {item.notes && <p className="mt-1 text-sm">{item.notes}</p>}
                                    {item.follow_up_at && (
                                        <p className="mt-1 text-xs text-amber-700">
                                            Follow-up: {formatDateTime(item.follow_up_at)}
                                        </p>
                                    )}
                                </li>
                            ))}
                            {!donor.interactions?.length && (
                                <li className="text-sm text-on-surface-variant">No interactions yet.</li>
                            )}
                        </ul>
                    </section>
                </div>

                <aside className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card xl:sticky xl:top-24 xl:self-start">
                    <h3 className="mb-1 font-semibold">Log call</h3>
                    <p className="mb-4 text-xs text-on-surface-variant">Fast entry after your external phone call.</p>

                    <fieldset disabled={donor.do_not_call || processing} className="space-y-4">
                        <div className="grid grid-cols-2 gap-2">
                            {outcomes.map((outcome) => (
                                <button
                                    key={outcome.value}
                                    type="button"
                                    onClick={() => setData('outcome', outcome.value)}
                                    className={`rounded-xl border px-2 py-3 text-left text-xs ${
                                        data.outcome === outcome.value
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : 'border-slate-200'
                                    }`}
                                >
                                    <span className="material-symbols-outlined mb-1 block text-[18px]">
                                        {outcome.icon}
                                    </span>
                                    {outcome.label}
                                </button>
                            ))}
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Notes</label>
                            <textarea
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                rows={4}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            />
                            {errors.notes && <p className="mt-1 text-xs text-error">{errors.notes}</p>}
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Follow-up</label>
                            <input
                                type="datetime-local"
                                value={data.follow_up_at}
                                onChange={(e) => setData('follow_up_at', e.target.value)}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            />
                            {errors.follow_up_at && <p className="mt-1 text-xs text-error">{errors.follow_up_at}</p>}
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Pledged amount (₹)</label>
                            <input
                                type="number"
                                min="0"
                                value={data.pledged_amount}
                                onChange={(e) => setData('pledged_amount', e.target.value)}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Campaign</label>
                            <select
                                value={data.campaign_id}
                                onChange={(e) => setData('campaign_id', e.target.value)}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            >
                                <option value="">None</option>
                                {campaigns.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.attribute_donation}
                                onChange={(e) => setData('attribute_donation', e.target.checked)}
                            />
                            Attribute future donation to me (Phase 2)
                        </label>

                        {errors.outcome && <p className="text-xs text-error">{errors.outcome}</p>}

                        <div className="flex flex-col gap-2">
                            <button
                                type="button"
                                onClick={() => submit(false)}
                                className="rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                Save outcome
                            </button>
                            <button
                                type="button"
                                onClick={() => submit(true)}
                                disabled={!nextDonorId}
                                className="rounded-xl bg-secondary px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                Save + Next Donor
                            </button>
                        </div>
                    </fieldset>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
