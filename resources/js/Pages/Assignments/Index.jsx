import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function AssignmentsIndex({
    unassigned,
    assigned,
    volunteers,
    selectedVolunteerId,
    workload,
    filters,
}) {
    const [selectedUnassigned, setSelectedUnassigned] = useState([]);
    const [selectedAssigned, setSelectedAssigned] = useState([]);
    const [busy, setBusy] = useState(false);

    const activeVolunteerId = selectedVolunteerId ? Number(selectedVolunteerId) : null;

    const selectVolunteer = (id) => {
        setSelectedUnassigned([]);
        setSelectedAssigned([]);
        router.get(
            route('assignments.index'),
            { ...filters, volunteer_id: id },
            { preserveState: true, preserveScroll: true },
        );
    };

    const assignSelected = () => {
        if (!activeVolunteerId || selectedUnassigned.length === 0 || busy) {
            return;
        }

        setBusy(true);
        router.post(
            route('assignments.store'),
            {
                volunteer_id: activeVolunteerId,
                donor_ids: selectedUnassigned.map(Number),
            },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => setSelectedUnassigned([]),
            },
        );
    };

    const unassignSelected = () => {
        if (selectedAssigned.length === 0 || busy) {
            return;
        }

        setBusy(true);
        router.post(
            route('assignments.destroy'),
            {
                volunteer_id: activeVolunteerId,
                donor_ids: selectedAssigned.map(Number),
            },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => setSelectedAssigned([]),
            },
        );
    };

    const distribute = () => {
        if (!volunteers.length || busy) {
            return;
        }

        if (!window.confirm('Distribute all unassigned donors equally across active volunteers?')) {
            return;
        }

        setBusy(true);
        router.post(
            route('assignments.distribute'),
            {
                volunteer_ids: volunteers.map((v) => Number(v.id)),
            },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    };

    const toggle = (list, setList, id) => {
        const value = Number(id);
        setList(list.includes(value) ? list.filter((x) => x !== value) : [...list, value]);
    };

    return (
        <AuthenticatedLayout header="Donor Assignments">
            <Head title="Assignments" />

            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 className="text-headline-md">Assign donors fairly</h2>
                    <p className="text-sm text-on-surface-variant">
                        Select a volunteer, choose donors on the left, then assign.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={distribute}
                    disabled={busy || !volunteers.length}
                    className="rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                >
                    Distribute equally
                </button>
            </div>

            {!volunteers.length ? (
                <EmptyState
                    icon="volunteer_activism"
                    title="No volunteers in this organization"
                    description="Add volunteers first, then assign donors."
                />
            ) : (
                <>
                    <div className="mb-4 flex flex-wrap gap-2">
                        {volunteers.map((v) => {
                            const id = Number(v.id);
                            const active = activeVolunteerId === id;
                            return (
                                <button
                                    key={v.id}
                                    type="button"
                                    onClick={() => selectVolunteer(id)}
                                    className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
                                        active ? 'bg-primary text-white' : 'bg-surface-container'
                                    }`}
                                >
                                    {v.name} ({workload?.[String(id)] || workload?.[id] || 0})
                                </button>
                            );
                        })}
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <h3 className="font-semibold">Unassigned ({unassigned.length})</h3>
                                <button
                                    type="button"
                                    disabled={busy || !selectedUnassigned.length || !activeVolunteerId}
                                    onClick={assignSelected}
                                    className="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-40"
                                >
                                    Assign selected
                                </button>
                            </div>
                            {!unassigned.length ? (
                                <EmptyState icon="inbox" title="No unassigned donors" />
                            ) : (
                                <ul className="max-h-[28rem] space-y-2 overflow-y-auto">
                                    {unassigned.map((donor) => (
                                        <li key={donor.id}>
                                            <label className="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-surface-container-low">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedUnassigned.includes(Number(donor.id))}
                                                    onChange={() =>
                                                        toggle(selectedUnassigned, setSelectedUnassigned, donor.id)
                                                    }
                                                />
                                                <div>
                                                    <p className="text-sm font-medium">{donor.full_name}</p>
                                                    <p className="text-xs text-on-surface-variant">{donor.phone}</p>
                                                </div>
                                            </label>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <h3 className="font-semibold">Assigned ({assigned.length})</h3>
                                <button
                                    type="button"
                                    disabled={busy || !selectedAssigned.length}
                                    onClick={unassignSelected}
                                    className="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-semibold disabled:opacity-40"
                                >
                                    Unassign selected
                                </button>
                            </div>
                            {!assigned.length ? (
                                <EmptyState icon="person_off" title="No donors for this volunteer" />
                            ) : (
                                <ul className="max-h-[28rem] space-y-2 overflow-y-auto">
                                    {assigned.map((donor) => (
                                        <li key={donor.id}>
                                            <label className="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-surface-container-low">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedAssigned.includes(Number(donor.id))}
                                                    onChange={() =>
                                                        toggle(selectedAssigned, setSelectedAssigned, donor.id)
                                                    }
                                                />
                                                <div>
                                                    <p className="text-sm font-medium">{donor.full_name}</p>
                                                    <p className="text-xs text-on-surface-variant">{donor.phone}</p>
                                                </div>
                                            </label>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                </>
            )}
        </AuthenticatedLayout>
    );
}
