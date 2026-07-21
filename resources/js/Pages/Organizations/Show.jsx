import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatINR, formatDateTime } from '@/lib/format';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

function UsageBar({ label, used, limit }) {
    const pct = limit ? Math.min(100, Math.round((used / limit) * 100)) : 0;
    const unlimited = limit === null || limit === undefined;

    return (
        <div>
            <div className="mb-1 flex items-center justify-between text-sm">
                <span className="font-medium">{label}</span>
                <span className="tabular-nums text-on-surface-variant">
                    {used ?? 0}
                    {unlimited ? '' : ` / ${limit}`}
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-surface-container">
                <div
                    className={`h-full rounded-full ${pct >= 90 ? 'bg-error' : pct >= 70 ? 'bg-amber-500' : 'bg-secondary'}`}
                    style={{ width: unlimited ? '0%' : `${pct}%` }}
                />
            </div>
        </div>
    );
}

export default function OrganizationShow({
    organization,
    apiConnection,
    canManageSync,
    volunteers,
    recentDonations,
    recentPayments,
    latestCycle,
    monthCollection,
    canEdit,
    plan,
    subscription,
    usageMeters,
    limits,
    health,
}) {
    const { appBrand = 'DonorConnect' } = usePage().props;
    const razorpayForm = useForm({
        razorpay_enabled: !!organization.razorpay_enabled,
        razorpay_key_id: organization.razorpay_key_id || '',
        razorpay_key_secret: '',
        razorpay_webhook_secret: '',
    });

    return (
        <AuthenticatedLayout header="Organization">
            <Head title={organization.name} />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="flex items-center gap-3">
                    <div
                        className="flex h-14 w-14 items-center justify-center rounded-xl text-lg font-bold text-white"
                        style={{ backgroundColor: organization.brand_color }}
                    >
                        {(organization.name || 'OR').slice(0, 2).toUpperCase()}
                    </div>
                    <div>
                        <h2 className="text-headline-md">{organization.name}</h2>
                        <p className="text-sm text-on-surface-variant">
                            {organization.slug} · {organization.timezone} · {organization.currency}
                        </p>
                    </div>
                </div>
                <div className="flex gap-2">
                    {canEdit && (
                        <Link
                            href={route('organizations.edit', organization.id)}
                            className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                        >
                            Edit settings
                        </Link>
                    )}
                    <Link
                        href={route('organization.switch')}
                        method="post"
                        data={{ organization_id: organization.id }}
                        as="button"
                        className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                    >
                        Open workspace
                    </Link>
                </div>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    ['Donors', organization.donors_count, organization.donors_limit ? `/ ${organization.donors_limit}` : ''],
                    ['Team', organization.users_count, ''],
                    ['Donations', organization.donations_count, ''],
                    ['This month', formatINR(monthCollection), ''],
                ].map(([label, value, suffix]) => (
                    <div key={label} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                        <p className="text-xs text-on-surface-variant">{label}</p>
                        <p className="text-xl font-bold tabular-nums">
                            {value}
                            {suffix && <span className="text-sm font-normal text-on-surface-variant">{suffix}</span>}
                        </p>
                    </div>
                ))}
            </div>

            {(plan || subscription || usageMeters) && (
                <div className="mb-6 grid gap-6 xl:grid-cols-2">
                    <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-3 font-semibold">Plan & subscription</h3>
                        <p className="text-sm">
                            <span className="text-on-surface-variant">Plan: </span>
                            <span className="font-medium">{plan?.name || 'None'}</span>
                        </p>
                        <p className="mt-2 text-sm capitalize">
                            <span className="text-on-surface-variant">Status: </span>
                            {String(subscription?.status || 'unknown').replaceAll('_', ' ')}
                        </p>
                        {subscription?.trial_ends_at && (
                            <p className="mt-2 text-sm">
                                <span className="text-on-surface-variant">Trial ends: </span>
                                {formatDateTime(subscription.trial_ends_at)}
                            </p>
                        )}
                    </section>

                    {usageMeters && limits && (
                        <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                            <h3 className="mb-4 font-semibold">Usage meters</h3>
                            <div className="grid gap-3">
                                <UsageBar label="Donors" used={usageMeters.donors} limit={limits.donors} />
                                <UsageBar label="Seats" used={usageMeters.seats_used} limit={limits.seats} />
                                <UsageBar
                                    label="Imports (month)"
                                    used={usageMeters.imports_this_month}
                                    limit={limits.imports_monthly}
                                />
                            </div>
                        </section>
                    )}

                    {health && (
                        <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card xl:col-span-2">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="font-semibold">Health score</h3>
                                <span
                                    className={`text-2xl font-bold tabular-nums ${
                                        health.score >= 80
                                            ? 'text-green-700'
                                            : health.score >= 50
                                              ? 'text-amber-600'
                                              : 'text-error'
                                    }`}
                                >
                                    {health.score}/100
                                </span>
                            </div>
                            {!health.issues?.length ? (
                                <p className="text-sm text-on-surface-variant">No issues detected.</p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {health.issues.map((issue) => (
                                        <li
                                            key={issue.code}
                                            className="flex items-start gap-2 rounded-xl border border-slate-100 px-3 py-2"
                                        >
                                            <span
                                                className={`material-symbols-outlined text-[18px] ${
                                                    issue.severity === 'high'
                                                        ? 'text-error'
                                                        : issue.severity === 'medium'
                                                          ? 'text-amber-600'
                                                          : 'text-on-surface-variant'
                                                }`}
                                            >
                                                {issue.severity === 'high' ? 'error' : 'info'}
                                            </span>
                                            <span>{issue.message}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card xl:col-span-2">
                    <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="font-semibold">WordPress / DonorConnect site</h3>
                            <p className="mt-1 text-xs text-on-surface-variant">
                                This organization connects its own WordPress site. Super Admin or Org Admin can
                                configure the Bridge credentials.
                            </p>
                        </div>
                        {canManageSync && (
                            <Link
                                href={route('organizations.sync.edit', organization.id)}
                                className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                            >
                                {apiConnection ? 'Manage connection' : 'Connect WordPress site'}
                            </Link>
                        )}
                    </div>
                    {apiConnection ? (
                        <div className="grid gap-3 sm:grid-cols-3 text-sm">
                            <div>
                                <p className="text-xs text-on-surface-variant">Status</p>
                                <p className="font-medium capitalize">
                                    {String(apiConnection.sync_status || 'idle').replaceAll('_', ' ')}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-on-surface-variant">Last synced</p>
                                <p className="font-medium">{formatDateTime(apiConnection.last_synced_at) || '—'}</p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-on-surface-variant">Base URL</p>
                                <p className="truncate font-medium" title={apiConnection.base_url}>
                                    {apiConnection.base_url || '—'}
                                </p>
                            </div>
                            {apiConnection.last_error && (
                                <p className="sm:col-span-3 rounded-lg bg-rose-50 p-2 text-xs text-rose-700">
                                    {apiConnection.last_error}
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="text-sm text-on-surface-variant">
                            No site connected yet. Install DonorConnect Bridge on the org WordPress site, then connect
                            here.
                        </p>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-semibold">Volunteers</h3>
                        <Link href={route('users.index')} className="text-xs font-semibold text-secondary">
                            Manage
                        </Link>
                    </div>
                    <ul className="space-y-2 text-sm">
                        {volunteers.length === 0 && (
                            <li className="text-on-surface-variant">No volunteers yet.</li>
                        )}
                        {volunteers.map((v) => (
                            <li key={v.id} className="flex items-center justify-between border-t border-slate-100 py-2">
                                <div>
                                    <p className="font-medium">{v.name}</p>
                                    <p className="text-xs text-on-surface-variant">{v.email}</p>
                                </div>
                                {v.is_internal_telecaller && (
                                    <span className="text-[10px] font-semibold uppercase text-secondary">
                                        {appBrand}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-semibold">Recent donations</h3>
                        <Link href={route('donors.index')} className="text-xs font-semibold text-secondary">
                            Donor list
                        </Link>
                    </div>
                    <ul className="space-y-2 text-sm">
                        {recentDonations.length === 0 && (
                            <li className="text-on-surface-variant">No donations yet.</li>
                        )}
                        {recentDonations.map((d) => (
                            <li key={d.id} className="flex items-center justify-between border-t border-slate-100 py-2">
                                <div>
                                    <p className="font-medium">{d.donor?.full_name || 'Donor'}</p>
                                    <p className="text-xs text-on-surface-variant">{formatDateTime(d.donated_at)}</p>
                                </div>
                                <p className="font-semibold tabular-nums">{formatINR(d.amount)}</p>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-3 font-semibold">Payment / commission</h3>
                    <p className="text-sm text-on-surface-variant">
                        Latest cycle: {latestCycle ? `${latestCycle.period} (${latestCycle.status})` : 'None yet'}
                    </p>
                    {latestCycle && (
                        <p className="mt-2 text-lg font-bold tabular-nums">{formatINR(latestCycle.payable_total)}</p>
                    )}
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Link href={route('commissions.settings')} className="text-xs font-semibold text-secondary">
                            Commission settings
                        </Link>
                        <Link href={route('commissions.cycles')} className="text-xs font-semibold text-secondary">
                            Cycles
                        </Link>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-1 font-semibold">Razorpay</h3>
                    <p className="mb-4 text-xs text-on-surface-variant">
                        Collect donor payments via Razorpay. Webhook URL:{' '}
                        <code className="break-all text-[10px]">
                            {typeof window !== 'undefined'
                                ? `${window.location.origin}/razorpay/webhook/${organization.id}`
                                : `/razorpay/webhook/${organization.id}`}
                        </code>
                    </p>
                    {canEdit ? (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                razorpayForm.put(route('organizations.razorpay.update', organization.id));
                            }}
                            className="space-y-3"
                        >
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={razorpayForm.data.razorpay_enabled}
                                    onChange={(e) => razorpayForm.setData('razorpay_enabled', e.target.checked)}
                                />
                                Enable Razorpay
                            </label>
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="Key ID"
                                value={razorpayForm.data.razorpay_key_id}
                                onChange={(e) => razorpayForm.setData('razorpay_key_id', e.target.value)}
                            />
                            <input
                                className="w-full rounded-xl border-slate-200"
                                type="password"
                                placeholder={
                                    organization.has_razorpay_secret ? 'Secret (leave blank to keep)' : 'Key secret'
                                }
                                value={razorpayForm.data.razorpay_key_secret}
                                onChange={(e) => razorpayForm.setData('razorpay_key_secret', e.target.value)}
                            />
                            <input
                                className="w-full rounded-xl border-slate-200"
                                type="password"
                                placeholder="Webhook secret"
                                value={razorpayForm.data.razorpay_webhook_secret}
                                onChange={(e) => razorpayForm.setData('razorpay_webhook_secret', e.target.value)}
                            />
                            <button className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                                Save Razorpay
                            </button>
                        </form>
                    ) : (
                        <p className="text-sm">{organization.razorpay_enabled ? 'Enabled' : 'Disabled'}</p>
                    )}
                    <ul className="mt-4 space-y-2 text-sm">
                        {recentPayments.map((p) => (
                            <li key={p.id} className="flex justify-between border-t border-slate-100 py-2">
                                <span>
                                    {p.donor?.full_name || 'Donor'} · {p.status}
                                </span>
                                <span className="tabular-nums">{formatINR(p.amount)}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
