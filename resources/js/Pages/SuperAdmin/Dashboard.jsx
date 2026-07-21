import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KpiCard from '@/Components/KpiCard';
import StatusBadge from '@/Components/StatusBadge';
import { formatINR } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

export default function SuperAdminDashboard({ organizations, stats }) {
    return (
        <AuthenticatedLayout header="Super Admin">
            <Head title="Organizations Overview" />

            <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-headline-md">All organizations</h2>
                    <p className="text-sm text-on-surface-variant">System-wide health across DonorConnect CRM.</p>
                </div>
                <Link
                    href={route('organizations.create')}
                    className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                >
                    <span className="material-symbols-outlined text-[18px]">add</span>
                    New organization
                </Link>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Organizations" value={stats.organizations} icon="apartment" />
                <KpiCard label="Users" value={stats.users} icon="group" />
                <KpiCard label="Donors" value={stats.donors} icon="favorite" accent="secondary" />
                <KpiCard label="Donations this month" value={formatINR(stats.donations_month)} icon="payments" />
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {organizations.map((org) => (
                    <div key={org.id} className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <div className="mb-4 flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <div
                                    className="flex h-12 w-12 items-center justify-center rounded-xl text-sm font-bold text-white"
                                    style={{ backgroundColor: org.brand_color }}
                                >
                                    {org.initials}
                                </div>
                                <div>
                                    <h3 className="font-semibold">{org.name}</h3>
                                    <StatusBadge status={org.is_active ? 'success' : 'failed'} label={org.is_active ? 'Active' : 'Inactive'} />
                                </div>
                            </div>
                            <StatusBadge status={org.sync_status} label={org.sync_status} />
                        </div>
                        <div className="grid grid-cols-3 gap-2 text-center text-sm">
                            <div className="rounded-xl bg-surface-container-low p-2">
                                <p className="text-xs text-on-surface-variant">Volunteers</p>
                                <p className="font-bold">{org.users_count}</p>
                            </div>
                            <div className="rounded-xl bg-surface-container-low p-2">
                                <p className="text-xs text-on-surface-variant">Donors</p>
                                <p className="font-bold">
                                    {org.donors_count}
                                    {org.donors_limit != null && (
                                        <span className="text-xs font-normal text-on-surface-variant"> / {org.donors_limit}</span>
                                    )}
                                </p>
                            </div>
                            <div className="rounded-xl bg-surface-container-low p-2">
                                <p className="text-xs text-on-surface-variant">Month</p>
                                <p className="font-bold tabular-nums text-xs">{formatINR(org.monthly_collection)}</p>
                            </div>
                        </div>
                        <div className="mt-4 flex gap-2">
                            <Link
                                href={route('organizations.edit', org.id)}
                                className="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-semibold"
                            >
                                Edit
                            </Link>
                            <Link
                                href={route('organization.switch')}
                                method="post"
                                data={{ organization_id: org.id }}
                                as="button"
                                className="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white"
                            >
                                Open dashboard
                            </Link>
                        </div>
                    </div>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
