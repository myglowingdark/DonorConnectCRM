import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KpiCard from '@/Components/KpiCard';
import { formatINR } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';

export default function ReportsIndex({ filters, cards, outcomeBreakdown, team, volunteers, organizations }) {
    const apply = (patch) => {
        router.get(route('reports.index'), { ...filters, ...patch }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout header="Reports">
            <Head title="Reports" />

            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 className="text-headline-md">Analytics</h2>
                    <p className="text-sm text-on-surface-variant">Organization-scoped calling and donation insights.</p>
                </div>
                <Link
                    href={route('reports.export', filters)}
                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                >
                    Export CSV
                </Link>
            </div>

            <div className="mb-6 grid gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-card md:grid-cols-4">
                <input type="date" className="rounded-xl border-slate-200" value={filters.from || ''} onChange={(e) => apply({ from: e.target.value })} />
                <input type="date" className="rounded-xl border-slate-200" value={filters.to || ''} onChange={(e) => apply({ to: e.target.value })} />
                <select className="rounded-xl border-slate-200" value={filters.volunteer_id || ''} onChange={(e) => apply({ volunteer_id: e.target.value || undefined })}>
                    <option value="">All volunteers</option>
                    {volunteers.map((v) => (
                        <option key={v.id} value={v.id}>{v.name}</option>
                    ))}
                </select>
                {organizations?.length > 0 && (
                    <select className="rounded-xl border-slate-200" value={filters.organization_id || ''} onChange={(e) => apply({ organization_id: e.target.value })}>
                        {organizations.map((o) => (
                            <option key={o.id} value={o.id}>{o.name}</option>
                        ))}
                    </select>
                )}
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Total collection" value={formatINR(cards.total_collection)} icon="payments" />
                <KpiCard label="Calls" value={cards.calls_total} icon="call" />
                <KpiCard label="Conversion rate" value={`${cards.conversion_rate}%`} icon="trending_up" accent="secondary" />
                <KpiCard label="Follow-ups due" value={cards.follow_ups_due} icon="event" accent="warning" />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Team performance</h3>
                    <table className="min-w-full text-sm">
                        <thead className="text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="py-2">Volunteer</th>
                                <th className="py-2">Calls</th>
                            </tr>
                        </thead>
                        <tbody>
                            {team.map((v) => (
                                <tr key={v.id} className="border-t border-slate-100">
                                    <td className="py-2">{v.name}</td>
                                    <td className="py-2">{v.calls_count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Call outcomes</h3>
                    <ul className="space-y-2 text-sm">
                        {Object.entries(outcomeBreakdown || {}).map(([k, v]) => (
                            <li key={k} className="flex justify-between">
                                <span className="capitalize text-on-surface-variant">{String(k).replaceAll('_', ' ')}</span>
                                <span className="font-semibold">{v}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
