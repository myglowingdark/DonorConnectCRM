import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatINR } from '@/lib/format';
import { Head } from '@inertiajs/react';

export default function InsightsIndex({
    campaignRoi,
    volunteerLeaderboard,
    pledgeForecast,
    superAdminBi,
}) {
    return (
        <AuthenticatedLayout header="Insights">
            <Head title="Insights" />

            <div className="mb-6">
                <h2 className="text-headline-md">Performance insights</h2>
                <p className="text-sm text-on-surface-variant">
                    Campaign ROI, volunteer leaderboard, and pledge collection forecast.
                </p>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    ['Pledged (month)', formatINR(pledgeForecast.pledged_sum)],
                    ['Collected (month)', formatINR(pledgeForecast.donated_sum)],
                    ['Collection gap', formatINR(pledgeForecast.collection_gap)],
                    ['Aging pledges (14d+)', pledgeForecast.aging_pledges],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                        <p className="text-xs text-on-surface-variant">{label}</p>
                        <p className="text-xl font-bold tabular-nums">{value}</p>
                    </div>
                ))}
            </div>

            {superAdminBi && (
                <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Platform overview</h3>
                    <div className="mb-4 grid gap-3 sm:grid-cols-3">
                        <div className="rounded-xl bg-surface-container-low p-4">
                            <p className="text-xs text-on-surface-variant">Trials ending (7d)</p>
                            <p className="text-2xl font-bold">{superAdminBi.trial_ending_soon}</p>
                        </div>
                        <div className="rounded-xl bg-surface-container-low p-4">
                            <p className="text-xs text-on-surface-variant">Telecaller utilization</p>
                            <p className="text-2xl font-bold">
                                {superAdminBi.telecaller_utilization_percent}%
                            </p>
                        </div>
                        <div className="rounded-xl bg-surface-container-low p-4">
                            <p className="text-xs text-on-surface-variant">Orgs by status</p>
                            <ul className="mt-1 text-sm">
                                {Object.entries(superAdminBi.org_counts_by_status || {}).map(([status, total]) => (
                                    <li key={status} className="capitalize">
                                        {status.replaceAll('_', ' ')}: {total}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                    {superAdminBi.top_orgs_by_revenue?.length > 0 && (
                        <div>
                            <p className="mb-2 text-xs font-semibold uppercase text-on-surface-variant">
                                Top orgs by revenue (month)
                            </p>
                            <ul className="space-y-1 text-sm">
                                {superAdminBi.top_orgs_by_revenue.map((org) => (
                                    <li key={org.id} className="flex justify-between border-t border-slate-100 py-2">
                                        <span>{org.name}</span>
                                        <span className="font-semibold tabular-nums">{formatINR(org.revenue)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </section>
            )}

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Campaign ROI</h3>
                    {!campaignRoi.length ? (
                        <EmptyState icon="campaign" title="No campaigns" />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="text-left text-xs uppercase text-on-surface-variant">
                                    <tr>
                                        <th className="py-2 pr-4">Campaign</th>
                                        <th className="py-2 pr-4">Revenue</th>
                                        <th className="py-2 pr-4">Est. cost</th>
                                        <th className="py-2 pr-4">ROI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {campaignRoi.map((row) => (
                                        <tr key={row.id} className="border-t border-slate-100">
                                            <td className="py-2 pr-4 font-medium">{row.name}</td>
                                            <td className="py-2 pr-4 tabular-nums">{formatINR(row.revenue)}</td>
                                            <td className="py-2 pr-4 tabular-nums">{formatINR(row.estimated_cost)}</td>
                                            <td
                                                className={`py-2 pr-4 tabular-nums font-semibold ${
                                                    row.roi >= 0 ? 'text-green-700' : 'text-error'
                                                }`}
                                            >
                                                {formatINR(row.roi)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Volunteer leaderboard</h3>
                    {!volunteerLeaderboard.length ? (
                        <EmptyState icon="groups" title="No volunteer activity" />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="text-left text-xs uppercase text-on-surface-variant">
                                    <tr>
                                        <th className="py-2 pr-4">Volunteer</th>
                                        <th className="py-2 pr-4">Calls</th>
                                        <th className="py-2 pr-4">Conv.</th>
                                        <th className="py-2 pr-4">Rate</th>
                                        <th className="py-2 pr-4">Quality</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {volunteerLeaderboard.map((v) => (
                                        <tr key={v.id} className="border-t border-slate-100">
                                            <td className="py-2 pr-4">
                                                <p className="font-medium">{v.name}</p>
                                                {(v.languages || []).length > 0 && (
                                                    <p className="text-xs text-on-surface-variant">
                                                        {v.languages.join(', ')}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="py-2 pr-4 tabular-nums">{v.calls}</td>
                                            <td className="py-2 pr-4 tabular-nums">{v.conversions}</td>
                                            <td className="py-2 pr-4 tabular-nums">{v.conversion_rate}%</td>
                                            <td className="py-2 pr-4 tabular-nums">
                                                {v.avg_quality ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
