import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDate, formatINR } from '@/lib/format';
import { Head } from '@inertiajs/react';

export default function MarginIndex({ organizations, capacityBookings, idleTelecallers }) {
    return (
        <AuthenticatedLayout header="Margin dashboard">
            <Head title="Margin dashboard" />

            <div className="mb-6">
                <h2 className="text-headline-md">Platform margin</h2>
                <p className="text-sm text-on-surface-variant">
                    Per-org revenue vs commission payable and telecaller utilization.
                </p>
            </div>

            <section className="mb-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                <div className="border-b border-slate-100 px-5 py-3">
                    <h3 className="font-semibold">Organizations (this month)</h3>
                </div>
                {!organizations.length ? (
                    <EmptyState icon="business" title="No organizations" />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                                <tr>
                                    <th className="px-4 py-3">Organization</th>
                                    <th className="px-4 py-3">Donors</th>
                                    <th className="px-4 py-3">Revenue</th>
                                    <th className="px-4 py-3">Commission</th>
                                    <th className="px-4 py-3">Service fee</th>
                                    <th className="px-4 py-3">Est. margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                {organizations.map((org) => (
                                    <tr key={org.id} className="border-t border-slate-100">
                                        <td className="px-4 py-3 font-medium">{org.name}</td>
                                        <td className="px-4 py-3 tabular-nums">{org.donors_count}</td>
                                        <td className="px-4 py-3 tabular-nums">{formatINR(org.revenue)}</td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatINR(org.commission_payable)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatINR(org.estimated_service_fee)}
                                            <span className="text-xs text-on-surface-variant">
                                                {' '}
                                                ({org.service_fee_percent}%)
                                            </span>
                                        </td>
                                        <td
                                            className={`px-4 py-3 tabular-nums font-semibold ${
                                                org.estimated_margin >= 0 ? 'text-green-700' : 'text-error'
                                            }`}
                                        >
                                            {formatINR(org.estimated_margin)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Recent capacity bookings</h3>
                    {!capacityBookings.length ? (
                        <p className="text-sm text-on-surface-variant">No bookings.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {capacityBookings.map((b) => (
                                <li key={b.id} className="flex justify-between border-t border-slate-100 py-2">
                                    <div>
                                        <p className="font-medium">{b.organization?.name}</p>
                                        <p className="text-xs text-on-surface-variant">
                                            {b.seats} seats · {b.campaign?.name || 'General'} · {b.status}
                                        </p>
                                    </div>
                                    <span className="text-xs text-on-surface-variant">
                                        {formatDate(b.starts_on)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Idle internal telecallers</h3>
                    {!idleTelecallers.length ? (
                        <p className="text-sm text-on-surface-variant">All internal telecallers are active.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {idleTelecallers.map((t) => (
                                <li key={t.id} className="border-t border-slate-100 py-2">
                                    <p className="font-medium">{t.name}</p>
                                    <p className="text-xs text-on-surface-variant">{t.email}</p>
                                    <div className="mt-1 flex flex-wrap gap-1">
                                        {(t.organizations || []).map((o) => (
                                            <span
                                                key={o.id}
                                                className="rounded-full bg-surface-container px-2 py-0.5 text-[10px] font-semibold"
                                            >
                                                {o.name}
                                            </span>
                                        ))}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
