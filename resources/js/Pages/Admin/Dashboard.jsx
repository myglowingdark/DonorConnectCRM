import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KpiCard from '@/Components/KpiCard';
import { formatINR } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

export default function AdminDashboard({ organization, stats, team, outcomes, pendingActions, phase2Notice }) {
    return (
        <AuthenticatedLayout header="Organization Dashboard">
            <Head title="Admin Dashboard" />

            <div className="mb-6">
                <h2 className="text-headline-md">{organization?.name}</h2>
                <p className="text-sm text-on-surface-variant">Team performance, sync health, and pending actions.</p>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <KpiCard label="Telecalling-attributed donations" value={formatINR(stats.telecalling_donations)} icon="payments" />
                <KpiCard label="Active volunteers" value={stats.active_volunteers} icon="volunteer_activism" accent="secondary" />
                <KpiCard label="Calls this week" value={stats.calls_this_week} icon="call" />
                <KpiCard label="Follow-ups due" value={stats.follow_ups_due} icon="event" accent="warning" />
                <KpiCard label="Estimated total commission" value="—" icon="percent" hint={phase2Notice} />
                <KpiCard label="Sync status" value={stats.sync_status} icon="sync" />
            </div>

            <div className="mb-6 grid gap-6 lg:grid-cols-3">
                <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card lg:col-span-2">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="font-semibold">Team performance</h3>
                        <Link href={route('users.index')} className="text-sm text-secondary">
                            Manage volunteers
                        </Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-left text-xs uppercase text-on-surface-variant">
                                <tr>
                                    <th className="py-2">Volunteer</th>
                                    <th className="py-2">Donors</th>
                                    <th className="py-2">Calls (month)</th>
                                    <th className="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {team.map((v) => (
                                    <tr key={v.id} className="border-t border-slate-100">
                                        <td className="py-2 font-medium">{v.name}</td>
                                        <td className="py-2">{v.donor_count}</td>
                                        <td className="py-2">{v.calls_count}</td>
                                        <td className="py-2">{v.is_active ? 'Active' : 'Inactive'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-3 font-semibold">Call outcomes (14 days)</h3>
                        <ul className="space-y-2 text-sm">
                            {Object.entries(outcomes || {}).map(([outcome, total]) => (
                                <li key={outcome} className="flex justify-between">
                                    <span className="capitalize text-on-surface-variant">
                                        {String(outcome).replaceAll('_', ' ')}
                                    </span>
                                    <span className="font-semibold">{total}</span>
                                </li>
                            ))}
                            {!Object.keys(outcomes || {}).length && (
                                <li className="text-on-surface-variant">No recent outcomes.</li>
                            )}
                        </ul>
                    </div>

                    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-3 font-semibold">Pending actions</h3>
                        <ul className="space-y-3 text-sm">
                            <li className="flex justify-between">
                                <span>Overdue follow-ups</span>
                                <Link href={`${route('donors.index')}?follow_up_due=1`} className="font-semibold text-amber-700">
                                    {pendingActions.overdue_follow_ups}
                                </Link>
                            </li>
                            <li className="flex justify-between">
                                <span>Sync errors</span>
                                <Link href={route('sync.edit')} className="font-semibold text-error">
                                    {pendingActions.sync_errors}
                                </Link>
                            </li>
                            <li className="text-xs text-on-surface-variant">Attribution approvals: Phase 2</li>
                        </ul>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
