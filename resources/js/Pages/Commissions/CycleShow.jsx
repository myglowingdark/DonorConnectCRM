import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatINR, formatDateTime } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';

export default function CommissionCycleShow({ cycle }) {
    return (
        <AuthenticatedLayout header="Commission Cycle">
            <Head title={`Cycle ${cycle.period}`} />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <Link href={route('commissions.cycles')} className="text-xs font-semibold text-secondary">
                        ← All cycles
                    </Link>
                    <h2 className="text-headline-md">Cycle {cycle.period}</h2>
                    <p className="text-sm capitalize text-on-surface-variant">Status: {cycle.status}</p>
                </div>
                <div className="flex gap-2">
                    {cycle.status === 'draft' && (
                        <button
                            type="button"
                            onClick={() => router.post(route('commissions.cycles.approve', cycle.id))}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                        >
                            Approve
                        </button>
                    )}
                    {cycle.status === 'approved' && (
                        <button
                            type="button"
                            onClick={() => router.post(route('commissions.cycles.pay', cycle.id))}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                        >
                            Mark paid
                        </button>
                    )}
                </div>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    ['Verified donations', cycle.verified_donation_total],
                    ['Individual total', cycle.individual_total],
                    ['Shared pool', cycle.shared_pool],
                    ['Payable total', cycle.payable_total],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                        <p className="text-xs text-on-surface-variant">{label}</p>
                        <p className="text-lg font-bold tabular-nums">{formatINR(value)}</p>
                    </div>
                ))}
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                <table className="min-w-full text-sm">
                    <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                        <tr>
                            <th className="px-4 py-3">Volunteer</th>
                            <th className="px-4 py-3">Attributed</th>
                            <th className="px-4 py-3">Rate</th>
                            <th className="px-4 py-3">Individual</th>
                            <th className="px-4 py-3">Shared</th>
                            <th className="px-4 py-3">Payable</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(cycle.line_items || []).map((item) => (
                            <tr key={item.id} className="border-t border-slate-100">
                                <td className="px-4 py-3">
                                    <p className="font-medium">{item.volunteer?.name}</p>
                                    <p className="text-xs text-on-surface-variant">{item.volunteer?.email}</p>
                                </td>
                                <td className="px-4 py-3 tabular-nums">{formatINR(item.attributed_donation_total)}</td>
                                <td className="px-4 py-3 tabular-nums">{item.individual_rate}%</td>
                                <td className="px-4 py-3 tabular-nums">{formatINR(item.individual_commission)}</td>
                                <td className="px-4 py-3 tabular-nums">{formatINR(item.shared_allocation)}</td>
                                <td className="px-4 py-3 font-semibold tabular-nums">{formatINR(item.final_payable)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {(cycle.approved_at || cycle.paid_at) && (
                <p className="mt-4 text-xs text-on-surface-variant">
                    {cycle.approved_at && <>Approved {formatDateTime(cycle.approved_at)}. </>}
                    {cycle.paid_at && <>Paid {formatDateTime(cycle.paid_at)}.</>}
                </p>
            )}
        </AuthenticatedLayout>
    );
}
