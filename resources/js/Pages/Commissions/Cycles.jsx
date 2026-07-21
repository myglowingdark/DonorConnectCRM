import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatINR } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CommissionCycles({ cycles, defaultPeriod, settings }) {
    const form = useForm({ period: defaultPeriod });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('commissions.cycles.calculate'));
    };

    return (
        <AuthenticatedLayout header="Commission Cycles">
            <Head title="Commission Cycles" />

            <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 className="text-headline-md">Monthly commission cycles</h2>
                    <p className="text-sm text-on-surface-variant">
                        Calculate from approved attributions. Default rate{' '}
                        {settings.individual_default_percent}%
                        {settings.shared_enabled ? ` · shared ${settings.shared_percent}%` : ''}.
                    </p>
                </div>
                <form onSubmit={submit} className="flex items-end gap-2">
                    <div>
                        <label className="text-xs font-semibold">Period</label>
                        <input
                            type="month"
                            className="mt-1 rounded-xl border-slate-200"
                            value={form.data.period}
                            onChange={(e) => form.setData('period', e.target.value)}
                        />
                    </div>
                    <button
                        disabled={form.processing}
                        className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                    >
                        Calculate
                    </button>
                </form>
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                <table className="min-w-full text-sm">
                    <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                        <tr>
                            <th className="px-4 py-3">Period</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Verified</th>
                            <th className="px-4 py-3">Payable</th>
                            <th className="px-4 py-3">Volunteers</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {cycles.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-on-surface-variant">
                                    No cycles yet. Calculate a month to start.
                                </td>
                            </tr>
                        )}
                        {cycles.data.map((cycle) => (
                            <tr key={cycle.id} className="border-t border-slate-100">
                                <td className="px-4 py-3 font-medium">{cycle.period}</td>
                                <td className="px-4 py-3 capitalize">{cycle.status}</td>
                                <td className="px-4 py-3 tabular-nums">{formatINR(cycle.verified_donation_total)}</td>
                                <td className="px-4 py-3 tabular-nums font-semibold">{formatINR(cycle.payable_total)}</td>
                                <td className="px-4 py-3">{cycle.line_items_count}</td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={route('commissions.cycles.show', cycle.id)}
                                        className="text-xs font-semibold text-secondary"
                                    >
                                        Open
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AuthenticatedLayout>
    );
}
