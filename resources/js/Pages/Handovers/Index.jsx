import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function HandoversIndex({ volunteers, donors, filters, history }) {
    const [selectedDonors, setSelectedDonors] = useState([]);
    const form = useForm({
        from_volunteer_id: filters.from_volunteer_id || '',
        to_volunteer_ids: [],
        mode: 'full',
        donor_ids: [],
        reassign_interactions: false,
        cap_per_volunteer: '',
        notes: '',
    });

    useEffect(() => {
        form.setData('donor_ids', selectedDonors);
    }, [selectedDonors]);

    const selectFrom = (id) => {
        form.setData('from_volunteer_id', id);
        setSelectedDonors([]);
        router.get(route('handovers.index'), { from_volunteer_id: id }, { preserveState: true, preserveScroll: true });
    };

    const toggleTo = (id) => {
        const current = form.data.to_volunteer_ids || [];
        const value = Number(id);
        form.setData(
            'to_volunteer_ids',
            current.includes(value) ? current.filter((x) => x !== value) : [...current, value],
        );
    };

    const toggleDonor = (id) => {
        const value = Number(id);
        setSelectedDonors((list) => (list.includes(value) ? list.filter((x) => x !== value) : [...list, value]));
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('handovers.store'), {
            onSuccess: () => {
                setSelectedDonors([]);
                form.setData('notes', '');
            },
        });
    };

    return (
        <AuthenticatedLayout header="Volunteer Handover">
            <Head title="Handover" />

            <div className="mb-6">
                <h2 className="text-headline-md">Hand over a leaving volunteer’s queue</h2>
                <p className="text-sm text-on-surface-variant">
                    Move assigned donors (partially or fully) to one or more teammates. Interaction history stays unless
                    you choose to reassign it.
                </p>
            </div>

            <form onSubmit={submit} className="mb-8 space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <div>
                    <p className="mb-2 text-xs font-semibold text-on-surface-variant">Leaving volunteer</p>
                    <div className="flex flex-wrap gap-2">
                        {volunteers.map((v) => (
                            <button
                                key={v.id}
                                type="button"
                                onClick={() => selectFrom(Number(v.id))}
                                className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
                                    Number(form.data.from_volunteer_id) === Number(v.id)
                                        ? 'bg-primary text-white'
                                        : 'bg-surface-container'
                                }`}
                            >
                                {v.name} ({v.donor_count || 0})
                            </button>
                        ))}
                    </div>
                </div>

                <div>
                    <p className="mb-2 text-xs font-semibold text-on-surface-variant">Receive into</p>
                    <div className="flex flex-wrap gap-2">
                        {volunteers
                            .filter((v) => Number(v.id) !== Number(form.data.from_volunteer_id))
                            .map((v) => (
                                <button
                                    key={v.id}
                                    type="button"
                                    onClick={() => toggleTo(v.id)}
                                    className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
                                        (form.data.to_volunteer_ids || []).includes(Number(v.id))
                                            ? 'bg-secondary text-white'
                                            : 'bg-surface-container'
                                    }`}
                                >
                                    {v.name}
                                </button>
                            ))}
                    </div>
                </div>

                <div className="flex flex-wrap gap-4">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name="mode"
                            checked={form.data.mode === 'full'}
                            onChange={() => form.setData('mode', 'full')}
                        />
                        Full handover
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name="mode"
                            checked={form.data.mode === 'partial'}
                            onChange={() => form.setData('mode', 'partial')}
                        />
                        Partial handover
                    </label>
                </div>

                {form.data.mode === 'partial' && (
                    <div className="rounded-xl bg-surface-container-low p-3">
                        <p className="mb-2 text-xs font-semibold text-on-surface-variant">
                            Select donors ({selectedDonors.length})
                        </p>
                        {!donors.length ? (
                            <p className="text-sm text-on-surface-variant">Choose a leaving volunteer first.</p>
                        ) : (
                            <ul className="max-h-56 space-y-1 overflow-y-auto">
                                {donors.map((d) => (
                                    <li key={d.id}>
                                        <label className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-white">
                                            <input
                                                type="checkbox"
                                                checked={selectedDonors.includes(Number(d.id))}
                                                onChange={() => toggleDonor(d.id)}
                                            />
                                            <span>
                                                {d.full_name}{' '}
                                                <span className="text-xs text-on-surface-variant">{d.phone}</span>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="text-xs font-semibold text-on-surface-variant">Cap per receiver (optional)</label>
                        <input
                            type="number"
                            min="1"
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.cap_per_volunteer}
                            onChange={(e) => form.setData('cap_per_volunteer', e.target.value)}
                        />
                    </div>
                    <label className="mt-6 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.reassign_interactions}
                            onChange={(e) => form.setData('reassign_interactions', e.target.checked)}
                        />
                        Also reassign past call interactions
                    </label>
                </div>

                <textarea
                    className="w-full rounded-xl border-slate-200"
                    rows={3}
                    placeholder="Notes (optional)"
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                />

                {Object.values(form.errors).map((err) => (
                    <p key={err} className="text-xs text-error">
                        {err}
                    </p>
                ))}

                <button
                    type="submit"
                    disabled={form.processing || !form.data.from_volunteer_id || !(form.data.to_volunteer_ids || []).length}
                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                >
                    Run handover
                </button>
            </form>

            <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Recent handovers</h3>
                {!history.length ? (
                    <EmptyState icon="move_down" title="No handovers yet" />
                ) : (
                    <ul className="space-y-3 text-sm">
                        {history.map((h) => (
                            <li key={h.id} className="rounded-xl border border-slate-100 p-3">
                                <p className="font-semibold">
                                    {h.from_volunteer?.name} → {(h.to_volunteer_ids || []).length} receiver(s)
                                </p>
                                <p className="text-on-surface-variant">
                                    {h.mode} · {h.donors_moved} donors
                                    {h.reassign_interactions ? ` · ${h.interactions_moved} interactions` : ''} · by{' '}
                                    {h.initiator?.name}
                                </p>
                                <p className="text-xs text-on-surface-variant">{formatDateTime(h.created_at)}</p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AuthenticatedLayout>
    );
}
