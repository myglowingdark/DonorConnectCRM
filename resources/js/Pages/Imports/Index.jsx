import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ImportsIndex({ volunteers, batches }) {
    const [selectedVolunteers, setSelectedVolunteers] = useState(volunteers.map((v) => Number(v.id)));
    const form = useForm({
        file: null,
        assign_after_import: true,
        volunteer_ids: volunteers.map((v) => Number(v.id)),
        cap_per_volunteer: '',
    });

    const toggleVolunteer = (id) => {
        const next = selectedVolunteers.includes(id)
            ? selectedVolunteers.filter((x) => x !== id)
            : [...selectedVolunteers, id];
        setSelectedVolunteers(next);
        form.setData('volunteer_ids', next);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('imports.store'), {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout header="Donor Import">
            <Head title="Import Donors" />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 className="text-headline-md">Excel / CSV upload</h2>
                    <p className="text-sm text-on-surface-variant">
                        Upload a donor list, optionally auto-assign with equal distribution and a per-volunteer cap.
                    </p>
                </div>
                <a
                    href={route('imports.template')}
                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                >
                    Download template
                </a>
            </div>

            <form onSubmit={submit} className="mb-8 space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <div>
                    <label className="text-xs font-semibold text-on-surface-variant">File (.csv or .xlsx)</label>
                    <input
                        type="file"
                        accept=".csv,.txt,.xlsx,.xlsm"
                        className="mt-1 block w-full text-sm"
                        onChange={(e) => form.setData('file', e.target.files?.[0] || null)}
                        required
                    />
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.assign_after_import}
                        onChange={(e) => form.setData('assign_after_import', e.target.checked)}
                    />
                    Assign imported donors equally to selected volunteers
                </label>

                {form.data.assign_after_import && (
                    <>
                        <div>
                            <p className="mb-2 text-xs font-semibold text-on-surface-variant">Team members</p>
                            <div className="flex flex-wrap gap-2">
                                {volunteers.map((v) => (
                                    <button
                                        key={v.id}
                                        type="button"
                                        onClick={() => toggleVolunteer(Number(v.id))}
                                        className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                            selectedVolunteers.includes(Number(v.id))
                                                ? 'bg-primary text-white'
                                                : 'bg-surface-container'
                                        }`}
                                    >
                                        {v.name}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">
                                Cap per volunteer (optional)
                            </label>
                            <input
                                type="number"
                                min="1"
                                className="mt-1 w-full max-w-xs rounded-xl border-slate-200"
                                placeholder="e.g. 50"
                                value={form.data.cap_per_volunteer}
                                onChange={(e) => form.setData('cap_per_volunteer', e.target.value)}
                            />
                        </div>
                    </>
                )}

                {Object.values(form.errors).map((err) => (
                    <p key={err} className="text-xs text-error">
                        {err}
                    </p>
                ))}

                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                >
                    Upload & process
                </button>
            </form>

            <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Recent imports</h3>
                {!batches.length ? (
                    <EmptyState icon="upload_file" title="No imports yet" />
                ) : (
                    <ul className="space-y-3 text-sm">
                        {batches.map((b) => (
                            <li key={b.id} className="rounded-xl border border-slate-100 p-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="font-semibold">{b.original_filename || 'Upload'}</p>
                                    <p className="text-xs text-on-surface-variant">{formatDateTime(b.created_at)}</p>
                                </div>
                                <p className="mt-1 text-on-surface-variant">
                                    by {b.uploader?.name} · {b.rows_created} created · {b.rows_updated} updated ·{' '}
                                    {b.rows_assigned} assigned · {b.rows_skipped} skipped
                                    {b.cap_per_volunteer ? ` · cap ${b.cap_per_volunteer}` : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AuthenticatedLayout>
    );
}
