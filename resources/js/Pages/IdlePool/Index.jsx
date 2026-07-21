import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function IdlePoolIndex({ telecallers, organizations }) {
    const [selected, setSelected] = useState(null);
    const form = useForm({
        organization_id: '',
        detach_organization_ids: [],
    });

    const openReassign = (telecaller) => {
        setSelected(telecaller);
        form.setData({
            organization_id: organizations[0]?.id || '',
            detach_organization_ids: (telecaller.organizations || []).map((o) => o.id),
        });
    };

    const toggleDetach = (orgId) => {
        const current = form.data.detach_organization_ids || [];
        form.setData(
            'detach_organization_ids',
            current.includes(orgId) ? current.filter((id) => id !== orgId) : [...current, orgId],
        );
    };

    const submit = (e) => {
        e.preventDefault();
        if (!selected) return;
        form.post(route('idle-pool.reassign', selected.id), {
            onSuccess: () => setSelected(null),
        });
    };

    return (
        <AuthenticatedLayout header="Idle telecaller pool">
            <Head title="Idle pool" />

            <div className="mb-6">
                <h2 className="text-headline-md">Internal telecallers</h2>
                <p className="text-sm text-on-surface-variant">
                    Reassign internal telecallers between organizations.
                </p>
            </div>

            <section className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                {!telecallers.length ? (
                    <EmptyState icon="groups" title="No internal telecallers" />
                ) : (
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Email</th>
                                <th className="px-4 py-3">Organizations</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {telecallers.map((t) => (
                                <tr key={t.id} className="border-t border-slate-100">
                                    <td className="px-4 py-3 font-medium">{t.name}</td>
                                    <td className="px-4 py-3">{t.email}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {(t.organizations || []).map((o) => (
                                                <span
                                                    key={o.id}
                                                    className="rounded-full bg-surface-container px-2 py-0.5 text-[10px] font-semibold"
                                                >
                                                    {o.name}
                                                </span>
                                            ))}
                                            {!(t.organizations || []).length && (
                                                <span className="text-on-surface-variant">Unassigned</span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            onClick={() => openReassign(t)}
                                            className="text-xs font-semibold text-secondary"
                                        >
                                            Reassign
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>

            {selected && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <form
                        onSubmit={submit}
                        className="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-elevated"
                    >
                        <h3 className="text-lg font-semibold">Reassign {selected.name}</h3>
                        <div>
                            <label className="text-xs font-semibold">Assign to organization</label>
                            <select
                                className="mt-1 w-full rounded-xl border-slate-200"
                                value={form.data.organization_id}
                                onChange={(e) => form.setData('organization_id', e.target.value)}
                                required
                            >
                                {organizations.map((o) => (
                                    <option key={o.id} value={o.id}>
                                        {o.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {(selected.organizations || []).length > 0 && (
                            <div>
                                <p className="mb-2 text-xs font-semibold text-on-surface-variant">
                                    Detach from (optional)
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {selected.organizations.map((o) => (
                                        <button
                                            key={o.id}
                                            type="button"
                                            onClick={() => toggleDetach(o.id)}
                                            className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                (form.data.detach_organization_ids || []).includes(o.id)
                                                    ? 'bg-error text-white'
                                                    : 'bg-surface-container'
                                            }`}
                                        >
                                            {o.name}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                        {Object.values(form.errors).map((err) => (
                            <p key={err} className="text-xs text-error">
                                {err}
                            </p>
                        ))}
                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setSelected(null)}
                                className="rounded-xl px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                            >
                                Reassign
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
