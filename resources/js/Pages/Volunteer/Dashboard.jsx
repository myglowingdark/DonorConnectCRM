import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KpiCard from '@/Components/KpiCard';
import EmptyState from '@/Components/EmptyState';
import { formatDate, formatINR } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

export default function VolunteerDashboard({
    stats,
    nextDonor,
    followUps,
    recentActivity,
    weeklyCalls,
    phase2Notice,
}) {
    return (
        <AuthenticatedLayout header="Volunteer Dashboard">
            <Head title="Dashboard" />

            <div className="mb-6">
                <h2 className="text-headline-md text-on-surface">Ready for today’s calls</h2>
                <p className="mt-1 text-sm text-on-surface-variant">
                    Select a donor, call externally, log the outcome, then move to the next.
                </p>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <KpiCard label="My assigned donors" value={stats.assigned_donors} icon="groups" />
                <KpiCard label="Calls completed today" value={stats.calls_today} icon="call" accent="secondary" />
                <KpiCard label="Follow-ups due today" value={stats.follow_ups_due} icon="event" accent="warning" />
                <KpiCard
                    label="Verified donations this month"
                    value={formatINR(stats.verified_donations_month)}
                    icon="volunteer_activism"
                    hint={phase2Notice}
                />
                <KpiCard label="Est. individual commission" value="—" icon="payments" hint="Phase 2" />
                <KpiCard label="Est. shared commission" value="—" icon="groups" hint="Phase 2" />
            </div>

            <div className="mb-6 grid gap-6 lg:grid-cols-3">
                <div className="rounded-2xl border border-primary/20 bg-gradient-to-br from-primary to-primary-container p-6 text-white shadow-card lg:col-span-2">
                    <p className="text-sm font-medium text-primary-fixed">Next donor to call</p>
                    {nextDonor ? (
                        <>
                            <h3 className="mt-2 text-2xl font-bold">{nextDonor.full_name}</h3>
                            <p className="mt-1 text-primary-fixed">{nextDonor.phone || 'No phone'}</p>
                            <p className="mt-3 text-sm">
                                {nextDonor.next_follow_up_at
                                    ? `Follow-up ${formatDate(nextDonor.next_follow_up_at)}`
                                    : nextDonor.last_contacted_at
                                      ? `Last called ${formatDate(nextDonor.last_contacted_at)}`
                                      : 'Never contacted'}
                                {' · '}
                                Last donation {formatINR(nextDonor.last_donation_amount)} on{' '}
                                {formatDate(nextDonor.last_donation_at)}
                            </p>
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Link
                                    href={route('donors.show', nextDonor.id)}
                                    className="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary"
                                >
                                    View
                                </Link>
                                {nextDonor.phone && (
                                    <a
                                        href={`tel:${nextDonor.phone}`}
                                        className="rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white"
                                    >
                                        Call
                                    </a>
                                )}
                                <Link
                                    href={route('donors.show', nextDonor.id)}
                                    className="rounded-xl border border-white/40 px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Log Outcome
                                </Link>
                            </div>
                        </>
                    ) : (
                        <p className="mt-4 text-primary-fixed">No callable donors in your queue right now.</p>
                    )}
                </div>

                <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Weekly calls</h3>
                    <div className="space-y-2">
                        {Object.keys(weeklyCalls || {}).length === 0 && (
                            <p className="text-sm text-on-surface-variant">No calls logged this week yet.</p>
                        )}
                        {Object.entries(weeklyCalls || {}).map(([day, total]) => (
                            <div key={day} className="flex items-center justify-between text-sm">
                                <span className="text-on-surface-variant">{day}</span>
                                <span className="font-semibold">{total}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="font-semibold">Follow-ups due</h3>
                        <Link href={`${route('donors.index')}?follow_up_due=1`} className="text-sm text-secondary">
                            View all
                        </Link>
                    </div>
                    {followUps?.length ? (
                        <ul className="space-y-3">
                            {followUps.map((donor) => (
                                <li key={donor.id}>
                                    <Link
                                        href={route('donors.show', donor.id)}
                                        className="flex items-center justify-between rounded-xl px-3 py-2 hover:bg-surface-container-low"
                                    >
                                        <div>
                                            <p className="font-medium">{donor.full_name}</p>
                                            <p className="text-xs text-on-surface-variant">{donor.phone}</p>
                                        </div>
                                        <span className="text-xs font-semibold text-amber-700">
                                            {formatDate(donor.next_follow_up_at)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState icon="event_available" title="No follow-ups due" description="You're caught up." />
                    )}
                </div>

                <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Recent activity</h3>
                    {recentActivity?.length ? (
                        <ul className="space-y-4 border-l border-outline-variant pl-4">
                            {recentActivity.map((item) => (
                                <li key={item.id} className="relative">
                                    <span className="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-secondary" />
                                    <p className="text-sm font-medium">
                                        {item.donor?.full_name} · {item.outcome}
                                    </p>
                                    <p className="text-xs text-on-surface-variant">{formatDate(item.contacted_at)}</p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState icon="history" title="No recent calls" description="Logged calls will appear here." />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
