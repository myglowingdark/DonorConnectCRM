import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import KpiCard from '@/Components/KpiCard';
import { formatDateTime, formatINR } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';

export default function CampaignShow({
    campaign,
    filters,
    stats,
    outcomes,
    recentDonations,
    imports,
    donors,
}) {
    const apply = (patch) => {
        router.get(route('campaigns.show', campaign.id), { ...filters, ...patch }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout header="Campaign">
            <Head title={campaign.name} />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">
                        Campaign
                    </p>
                    <h2 className="text-headline-md">{campaign.name}</h2>
                    <p className="text-sm capitalize text-on-surface-variant">
                        {campaign.status}
                        {campaign.starts_at ? ` · ${campaign.starts_at}` : ''}
                        {campaign.ends_at ? ` → ${campaign.ends_at}` : ''}
                    </p>
                </div>
                <Link
                    href={route('campaigns.index')}
                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                >
                    All campaigns
                </Link>
            </div>

            <div className="mb-6 grid gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-card md:grid-cols-2">
                <input
                    type="date"
                    className="rounded-xl border-slate-200"
                    value={filters.from || ''}
                    onChange={(e) => apply({ from: e.target.value })}
                />
                <input
                    type="date"
                    className="rounded-xl border-slate-200"
                    value={filters.to || ''}
                    onChange={(e) => apply({ to: e.target.value })}
                />
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Revenue" value={formatINR(stats.revenue)} icon="payments" />
                <KpiCard label="Donations" value={stats.donations_count} icon="volunteer_activism" />
                <KpiCard label="Calls" value={stats.calls} icon="call" />
                <KpiCard
                    label="Call conversion"
                    value={`${stats.call_conversion_rate}%`}
                    icon="trending_up"
                    accent="secondary"
                />
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Leads linked" value={stats.leads} icon="groups" />
                <KpiCard label="Contacted leads" value={stats.contacted_leads} icon="phone_in_talk" />
                <KpiCard label="Donated leads" value={stats.donated_leads} icon="favorite" />
                <KpiCard
                    label="Lead conversion"
                    value={`${stats.lead_conversion_rate}%`}
                    icon="percent"
                    accent="secondary"
                />
            </div>

            <div className="mb-6 grid gap-6 lg:grid-cols-2">
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Call outcomes</h3>
                    {!Object.keys(outcomes || {}).length ? (
                        <p className="text-sm text-on-surface-variant">No calls in this range.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {Object.entries(outcomes).map(([k, v]) => (
                                <li key={k} className="flex justify-between">
                                    <span className="capitalize text-on-surface-variant">
                                        {String(k).replaceAll('_', ' ')}
                                    </span>
                                    <span className="font-semibold">{v}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Recent donations</h3>
                    {!recentDonations.length ? (
                        <p className="text-sm text-on-surface-variant">No donations yet.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {recentDonations.map((d) => (
                                <li key={d.id} className="flex items-center justify-between border-t border-slate-100 py-2">
                                    <div>
                                        <p className="font-medium">{d.donor?.full_name || 'Donor'}</p>
                                        <p className="text-xs text-on-surface-variant">
                                            {formatDateTime(d.donated_at)}
                                        </p>
                                    </div>
                                    <p className="font-semibold tabular-nums">{formatINR(d.amount)}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Imports linked</h3>
                {!imports.length ? (
                    <p className="text-sm text-on-surface-variant">No imports linked to this campaign.</p>
                ) : (
                    <ul className="space-y-2 text-sm">
                        {imports.map((b) => (
                            <li key={b.id} className="flex items-center justify-between border-t border-slate-100 py-2">
                                <div>
                                    <p className="font-medium">{b.original_filename || `Import #${b.id}`}</p>
                                    <p className="text-xs text-on-surface-variant">
                                        {b.rows_created} created · {formatDateTime(b.created_at)}
                                    </p>
                                </div>
                                <Link
                                    href={route('imports.show', b.id)}
                                    className="text-xs font-semibold text-secondary"
                                >
                                    View list
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                <div className="border-b border-slate-100 px-4 py-3">
                    <h3 className="font-semibold">Linked leads</h3>
                </div>
                {!donors.data?.length ? (
                    <EmptyState icon="group" title="No leads linked yet" />
                ) : (
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="px-4 py-3">Donor</th>
                                <th className="px-4 py-3">Phone</th>
                                <th className="px-4 py-3">Total donated</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {donors.data.map((d) => (
                                <tr key={d.id} className="border-t border-slate-100">
                                    <td className="px-4 py-3 font-medium">{d.full_name}</td>
                                    <td className="px-4 py-3">{d.phone || '—'}</td>
                                    <td className="px-4 py-3 tabular-nums">{formatINR(d.total_donated)}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('donors.show', d.id)}
                                            className="text-xs font-semibold text-secondary"
                                        >
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>
        </AuthenticatedLayout>
    );
}
