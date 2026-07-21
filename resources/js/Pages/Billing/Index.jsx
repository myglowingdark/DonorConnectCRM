import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { formatDate, formatDateTime, formatINR } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';

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

export default function BillingIndex({
    organization,
    plans,
    meters,
    limits,
    features,
    invoices,
    platformBillingEnabled,
    canManagePlans,
    canEditWhiteLabel,
}) {
    const whiteLabelForm = useForm({
        custom_domain: organization.custom_domain || '',
        email_from_name: organization.email_from_name || '',
        brand_color: organization.brand_color || '#1e3a8a',
    });

    const planForm = useForm({
        plan_id: organization.plan?.id || '',
        subscription_status: organization.subscription_status || 'trial',
        trial_ends_at: organization.trial_ends_at
            ? organization.trial_ends_at.slice(0, 10)
            : '',
    });

    const statusColors = {
        active: 'success',
        trial: 'pending',
        past_due: 'failed',
        suspended: 'failed',
    };

    return (
        <AuthenticatedLayout header="Billing">
            <Head title="Billing" />

            <div className="mb-6">
                <h2 className="text-headline-md">Subscription & usage</h2>
                <p className="text-sm text-on-surface-variant">
                    Plan limits, usage meters, invoices, and white-label settings for {organization.name}.
                </p>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                    <p className="text-xs text-on-surface-variant">Current plan</p>
                    <p className="text-lg font-bold">{organization.plan?.name || 'None'}</p>
                    {organization.plan?.price_monthly > 0 && (
                        <p className="text-sm text-on-surface-variant">
                            {formatINR(organization.plan.price_monthly)}/mo
                        </p>
                    )}
                </div>
                <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                    <p className="text-xs text-on-surface-variant">Status</p>
                    <div className="mt-1">
                        <StatusBadge
                            status={statusColors[organization.subscription_status] || 'pending'}
                            label={String(organization.subscription_status || 'unknown').replaceAll('_', ' ')}
                        />
                    </div>
                </div>
                <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                    <p className="text-xs text-on-surface-variant">Trial ends</p>
                    <p className="text-lg font-bold">{formatDate(organization.trial_ends_at)}</p>
                </div>
                <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
                    <p className="text-xs text-on-surface-variant">Platform billing</p>
                    <p className="text-lg font-bold">{platformBillingEnabled ? 'Enabled' : 'Disabled'}</p>
                </div>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Usage meters</h3>
                <div className="grid gap-4 md:grid-cols-2">
                    <UsageBar label="Donors" used={meters.donors} limit={limits.donors} />
                    <UsageBar label="Seats" used={meters.seats_used} limit={limits.seats} />
                    <UsageBar label="Imports (month)" used={meters.imports_this_month} limit={limits.imports_monthly} />
                    <UsageBar
                        label="WhatsApp (month)"
                        used={meters.messages_this_month}
                        limit={limits.whatsapp_monthly}
                    />
                    {limits.campaigns != null && (
                        <UsageBar label="Campaigns" used={0} limit={limits.campaigns} />
                    )}
                </div>
                {features?.length > 0 && (
                    <div className="mt-4 flex flex-wrap gap-2">
                        {features.map((f) => (
                            <span
                                key={f}
                                className="rounded-full bg-secondary/10 px-2.5 py-1 text-xs font-semibold text-secondary"
                            >
                                {f.replaceAll('_', ' ')}
                            </span>
                        ))}
                    </div>
                )}
            </section>

            {canManagePlans && (
                <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Assign plan</h3>
                    <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <div
                                key={plan.id}
                                className={`rounded-xl border p-4 ${
                                    organization.plan?.id === plan.id
                                        ? 'border-primary bg-primary/5'
                                        : 'border-slate-100'
                                }`}
                            >
                                <p className="font-semibold">{plan.name}</p>
                                <p className="text-sm text-on-surface-variant">{plan.code}</p>
                                <p className="mt-2 font-bold tabular-nums">{formatINR(plan.price_monthly)}/mo</p>
                                <ul className="mt-2 space-y-1 text-xs text-on-surface-variant">
                                    {plan.donors_limit && <li>{plan.donors_limit} donors</li>}
                                    {plan.seats_limit && <li>{plan.seats_limit} seats</li>}
                                    {plan.campaigns_limit && <li>{plan.campaigns_limit} campaigns</li>}
                                </ul>
                            </div>
                        ))}
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            planForm.put(route('organizations.plan.update', organization.id));
                        }}
                        className="grid gap-3 md:grid-cols-3"
                    >
                        <select
                            className="rounded-xl border-slate-200"
                            value={planForm.data.plan_id}
                            onChange={(e) => planForm.setData('plan_id', e.target.value)}
                            required
                        >
                            <option value="">Select plan</option>
                            {plans.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-xl border-slate-200"
                            value={planForm.data.subscription_status}
                            onChange={(e) => planForm.setData('subscription_status', e.target.value)}
                        >
                            <option value="trial">Trial</option>
                            <option value="active">Active</option>
                            <option value="past_due">Past due</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <input
                            type="date"
                            className="rounded-xl border-slate-200"
                            value={planForm.data.trial_ends_at}
                            onChange={(e) => planForm.setData('trial_ends_at', e.target.value)}
                        />
                        <button
                            type="submit"
                            disabled={planForm.processing}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white md:col-span-3 md:w-fit"
                        >
                            Update plan
                        </button>
                    </form>
                </section>
            )}

            <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="font-semibold">Invoices</h3>
                    {canManagePlans && (
                        <Link
                            href={route('billing.invoices.store')}
                            method="post"
                            as="button"
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                        >
                            Create invoice
                        </Link>
                    )}
                </div>
                {!invoices.length ? (
                    <EmptyState icon="receipt_long" title="No invoices" description="Platform invoices will appear here." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-left text-xs uppercase text-on-surface-variant">
                                <tr>
                                    <th className="py-2 pr-4">Invoice</th>
                                    <th className="py-2 pr-4">Plan</th>
                                    <th className="py-2 pr-4">Amount</th>
                                    <th className="py-2 pr-4">Status</th>
                                    <th className="py-2 pr-4">Period</th>
                                    <th className="py-2 pr-4" />
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.map((inv) => (
                                    <tr key={inv.id} className="border-t border-slate-100">
                                        <td className="py-2 pr-4 font-mono text-xs">{inv.invoice_number}</td>
                                        <td className="py-2 pr-4">{inv.plan?.name || '—'}</td>
                                        <td className="py-2 pr-4 tabular-nums">{formatINR(inv.amount)}</td>
                                        <td className="py-2 pr-4 capitalize">{inv.status}</td>
                                        <td className="py-2 pr-4 text-xs text-on-surface-variant">
                                            {formatDate(inv.period_start)} – {formatDate(inv.period_end)}
                                        </td>
                                        <td className="py-2 pr-4 text-right">
                                            {inv.status !== 'paid' && (
                                                <Link
                                                    href={route('billing.invoices.pay', inv.id)}
                                                    method="post"
                                                    as="button"
                                                    className="text-xs font-semibold text-secondary"
                                                >
                                                    Pay
                                                </Link>
                                            )}
                                            {inv.paid_at && (
                                                <span className="text-xs text-on-surface-variant">
                                                    Paid {formatDateTime(inv.paid_at)}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {canEditWhiteLabel && (
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-1 font-semibold">White-label</h3>
                    <p className="mb-4 text-xs text-on-surface-variant">
                        Custom domain, email sender name, and brand color for your organization.
                    </p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            whiteLabelForm.put(route('billing.white-label.update'));
                        }}
                        className="grid gap-3 md:grid-cols-2"
                    >
                        <div>
                            <label className="text-xs font-semibold">Custom domain</label>
                            <input
                                className="mt-1 w-full rounded-xl border-slate-200"
                                placeholder="donate.example.org"
                                value={whiteLabelForm.data.custom_domain}
                                onChange={(e) => whiteLabelForm.setData('custom_domain', e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold">Email from name</label>
                            <input
                                className="mt-1 w-full rounded-xl border-slate-200"
                                placeholder="Your Organization"
                                value={whiteLabelForm.data.email_from_name}
                                onChange={(e) => whiteLabelForm.setData('email_from_name', e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold">Brand color</label>
                            <input
                                type="color"
                                className="mt-1 h-10 w-full rounded-xl border-slate-200"
                                value={whiteLabelForm.data.brand_color}
                                onChange={(e) => whiteLabelForm.setData('brand_color', e.target.value)}
                            />
                        </div>
                        <div className="flex items-end">
                            <button
                                type="submit"
                                disabled={whiteLabelForm.processing}
                                className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                            >
                                Save white-label
                            </button>
                        </div>
                    </form>
                </section>
            )}
        </AuthenticatedLayout>
    );
}
