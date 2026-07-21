import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatINR } from '@/lib/format';
import { Head } from '@inertiajs/react';

export default function MyCommission({ lineItems, totals }) {
    return (
        <AuthenticatedLayout header="My Commission">
            <Head title="My Commission" />

            <div className="mb-6">
                <h2 className="text-headline-md">Earnings</h2>
                <p className="text-sm text-on-surface-variant">
                    Commission from approved attributions in calculated cycles.
                </p>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2">
                <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                    <p className="text-xs text-on-surface-variant">Total payable (all cycles)</p>
                    <p className="text-xl font-bold tabular-nums">{formatINR(totals.payable)}</p>
                </div>
                <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                    <p className="text-xs text-on-surface-variant">Marked paid</p>
                    <p className="text-xl font-bold tabular-nums">{formatINR(totals.paid)}</p>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                <table className="min-w-full text-sm">
                    <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                        <tr>
                            <th className="px-4 py-3">Period</th>
                            <th className="px-4 py-3">Attributed</th>
                            <th className="px-4 py-3">Rate</th>
                            <th className="px-4 py-3">Payable</th>
                            <th className="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {lineItems.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-on-surface-variant">
                                    No commission line items yet.
                                </td>
                            </tr>
                        )}
                        {lineItems.data.map((item) => (
                            <tr key={item.id} className="border-t border-slate-100">
                                <td className="px-4 py-3 font-medium">{item.cycle?.period}</td>
                                <td className="px-4 py-3 tabular-nums">{formatINR(item.attributed_donation_total)}</td>
                                <td className="px-4 py-3 tabular-nums">{item.individual_rate}%</td>
                                <td className="px-4 py-3 font-semibold tabular-nums">{formatINR(item.final_payable)}</td>
                                <td className="px-4 py-3 capitalize">{item.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AuthenticatedLayout>
    );
}
