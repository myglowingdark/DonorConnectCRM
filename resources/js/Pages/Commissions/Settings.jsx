import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function CommissionSettings({ settings, volunteers, canEdit }) {
    const form = useForm({
        individual_enabled: !!settings.individual_enabled,
        individual_default_percent: settings.individual_default_percent ?? 5,
        shared_enabled: !!settings.shared_enabled,
        shared_percent: settings.shared_percent ?? 0,
        shared_eligibility: settings.shared_eligibility || 'active_contributors',
        effective_from: settings.effective_from || '',
        effective_to: settings.effective_to || '',
        volunteer_overrides: (volunteers || []).map((v) => ({
            volunteer_id: v.id,
            percent: v.override_percent ?? '',
        })),
    });

    const submit = (e) => {
        e.preventDefault();
        if (!canEdit) return;
        form.put(route('commissions.settings.update'));
    };

    return (
        <AuthenticatedLayout header="Commissions">
            <Head title="Payment / Commission Settings" />

            <div className="mb-6">
                <h2 className="text-headline-md">Payment per organization / volunteer</h2>
                <p className="text-sm text-on-surface-variant">
                    Set the default commission percent for this org, and optional per-volunteer overrides.
                    Cycle payouts remain Phase 2.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Individual commission</h3>
                    <label className="mb-4 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.individual_enabled}
                            disabled={!canEdit}
                            onChange={(e) => form.setData('individual_enabled', e.target.checked)}
                        />
                        Enable individual payment %
                    </label>
                    <div className="max-w-xs">
                        <label className="text-xs font-semibold">Default percent</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            disabled={!canEdit}
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.individual_default_percent}
                            onChange={(e) => form.setData('individual_default_percent', e.target.value)}
                        />
                        {form.errors.individual_default_percent && (
                            <p className="text-xs text-error">{form.errors.individual_default_percent}</p>
                        )}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Shared pool (optional)</h3>
                    <label className="mb-4 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.shared_enabled}
                            disabled={!canEdit}
                            onChange={(e) => form.setData('shared_enabled', e.target.checked)}
                        />
                        Enable shared team pool
                    </label>
                    <div className="grid gap-3 md:grid-cols-2">
                        <div>
                            <label className="text-xs font-semibold">Shared percent</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                disabled={!canEdit}
                                className="mt-1 w-full rounded-xl border-slate-200"
                                value={form.data.shared_percent}
                                onChange={(e) => form.setData('shared_percent', e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold">Eligibility</label>
                            <input
                                disabled={!canEdit}
                                className="mt-1 w-full rounded-xl border-slate-200"
                                value={form.data.shared_eligibility}
                                onChange={(e) => form.setData('shared_eligibility', e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold">Effective from</label>
                            <input
                                type="date"
                                disabled={!canEdit}
                                className="mt-1 w-full rounded-xl border-slate-200"
                                value={form.data.effective_from}
                                onChange={(e) => form.setData('effective_from', e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold">Effective to</label>
                            <input
                                type="date"
                                disabled={!canEdit}
                                className="mt-1 w-full rounded-xl border-slate-200"
                                value={form.data.effective_to}
                                onChange={(e) => form.setData('effective_to', e.target.value)}
                            />
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-1 font-semibold">Per-volunteer overrides</h3>
                    <p className="mb-4 text-xs text-on-surface-variant">
                        Leave blank to use the org default. Enter a percent to override for that volunteer.
                    </p>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                                <tr>
                                    <th className="px-3 py-2">Volunteer</th>
                                    <th className="px-3 py-2">Override %</th>
                                    <th className="px-3 py-2">Effective %</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(volunteers || []).length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-3 py-4 text-on-surface-variant">
                                            No volunteers in this organization yet.
                                        </td>
                                    </tr>
                                )}
                                {(volunteers || []).map((v, index) => (
                                    <tr key={v.id} className="border-t border-slate-100">
                                        <td className="px-3 py-2">
                                            <p className="font-medium">{v.name}</p>
                                            <p className="text-xs text-on-surface-variant">{v.email}</p>
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                disabled={!canEdit}
                                                placeholder="Default"
                                                className="w-28 rounded-xl border-slate-200"
                                                value={form.data.volunteer_overrides[index]?.percent ?? ''}
                                                onChange={(e) => {
                                                    const next = [...form.data.volunteer_overrides];
                                                    next[index] = {
                                                        volunteer_id: v.id,
                                                        percent: e.target.value,
                                                    };
                                                    form.setData('volunteer_overrides', next);
                                                }}
                                            />
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {form.data.volunteer_overrides[index]?.percent !== '' &&
                                            form.data.volunteer_overrides[index]?.percent != null
                                                ? form.data.volunteer_overrides[index].percent
                                                : form.data.individual_default_percent}
                                            %
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {canEdit && (
                    <button
                        disabled={form.processing}
                        className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                    >
                        Save payment settings
                    </button>
                )}
            </form>
        </AuthenticatedLayout>
    );
}
