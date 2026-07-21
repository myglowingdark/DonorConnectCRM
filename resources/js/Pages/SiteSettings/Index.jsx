import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MetaWhatsAppConnect from '@/Components/MetaWhatsAppConnect';
import { formatINR } from '@/lib/format';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const TAB_LABELS = {
    modules: 'Modules',
    messaging: 'Messaging',
    plans: 'Plans & fees',
    billing: 'Platform billing',
    coupons: 'Coupons',
    defaults: 'Default commissions',
};

function FlashMessages() {
    const { flash } = usePage().props;
    return (
        <>
            {flash?.success && (
                <p className="mb-4 rounded-xl bg-secondary/10 px-4 py-2 text-sm text-secondary">{flash.success}</p>
            )}
            {flash?.error && (
                <p className="mb-4 rounded-xl bg-error/10 px-4 py-2 text-sm text-error">{flash.error}</p>
            )}
        </>
    );
}

function ModulesTab({ messaging }) {
    const form = useForm({
        whatsapp_module_enabled: !!messaging.whatsapp_module_enabled,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(route('site-settings.modules.update'));
            }}
            className="mx-auto max-w-2xl space-y-6"
        >
            <section className="space-y-4 rounded-2xl border border-primary/20 bg-primary/5 p-6 shadow-card">
                <h3 className="font-semibold">Site-wide modules</h3>
                <p className="text-xs text-on-surface-variant">
                    Enabling a module unlocks it for every organization. You can still turn it off for a single org on
                    Billing with a feature override.
                </p>
                <label className="flex items-start gap-3 rounded-xl border border-slate-100 bg-white p-4 text-sm">
                    <input
                        type="checkbox"
                        className="mt-1"
                        checked={form.data.whatsapp_module_enabled}
                        onChange={(e) => form.setData('whatsapp_module_enabled', e.target.checked)}
                    />
                    <span>
                        <span className="block font-semibold">WhatsApp module</span>
                        <span className="text-xs text-on-surface-variant">
                            Unlock Meta WhatsApp templates &amp; donor messaging for all orgs site-wide.
                        </span>
                    </span>
                </label>
            </section>
            <button disabled={form.processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                Save modules
            </button>
        </form>
    );
}

function MessagingTab({ messaging, webhookUrl, verifyTokenHint, embeddedSignup, instructions }) {
    const form = useForm({
        email_enabled: !!messaging.email_enabled,
        whatsapp_enabled: !!messaging.whatsapp_enabled,
        whatsapp_module_enabled: !!messaging.whatsapp_module_enabled,
        smtp_host: messaging.smtp_host || '',
        smtp_port: messaging.smtp_port || 587,
        smtp_encryption: messaging.smtp_encryption || 'tls',
        smtp_username: messaging.smtp_username || '',
        smtp_password: '',
        from_email: messaging.from_email || '',
        from_name: messaging.from_name || '',
        meta_access_token: '',
        meta_phone_number_id: messaging.meta_phone_number_id || '',
        meta_waba_id: messaging.meta_waba_id || '',
        meta_app_id: messaging.meta_app_id || '',
        meta_app_secret: '',
        meta_api_version: messaging.meta_api_version || 'v21.0',
        meta_embedded_signup_config_id: messaging.meta_embedded_signup_config_id || '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(route('site-settings.messaging.update'));
            }}
            className="mx-auto max-w-2xl space-y-6"
        >
            <section className="space-y-3 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <h3 className="font-semibold">Setup checklist</h3>
                <ol className="list-decimal space-y-2 pl-5 text-sm text-on-surface-variant">
                    {instructions.map((step) => (
                        <li key={step}>{step}</li>
                    ))}
                </ol>
            </section>

            <section className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <h3 className="font-semibold">Email / SMTP</h3>
                <p className="text-xs text-on-surface-variant">
                    EmailsBit: host <code>smtp.emailsbit.com</code>, try <code>588</code> + TLS first, or{' '}
                    <code>505</code> + SSL. Auth type LOGIN. “Connection refused” means this web host is blocking
                    outbound SMTP to that port — try the other port, or ask hosting to allow it.
                </p>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.email_enabled}
                        onChange={(e) => form.setData('email_enabled', e.target.checked)}
                    />
                    Platform email enabled
                </label>
                <div className="grid gap-3 md:grid-cols-2">
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="From email"
                        value={form.data.from_email}
                        onChange={(e) => form.setData('from_email', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="From name"
                        value={form.data.from_name}
                        onChange={(e) => form.setData('from_name', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="SMTP host (e.g. smtp.emailsbit.com)"
                        value={form.data.smtp_host}
                        onChange={(e) => form.setData('smtp_host', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="Port (588 or 505 for EmailsBit)"
                        type="number"
                        value={form.data.smtp_port}
                        onChange={(e) => form.setData('smtp_port', e.target.value)}
                    />
                    <select
                        className="rounded-xl border-slate-200"
                        value={form.data.smtp_encryption || 'tls'}
                        onChange={(e) => form.setData('smtp_encryption', e.target.value)}
                    >
                        <option value="tls">TLS — EmailsBit port 588</option>
                        <option value="ssl">SSL — EmailsBit port 505</option>
                    </select>
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="Username (often API key)"
                        value={form.data.smtp_username}
                        onChange={(e) => form.setData('smtp_username', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200 md:col-span-2"
                        placeholder={messaging.has_smtp_password ? 'Password (leave blank to keep)' : 'Password / API key'}
                        type="password"
                        value={form.data.smtp_password}
                        onChange={(e) => form.setData('smtp_password', e.target.value)}
                    />
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        onClick={() => router.post(route('site-settings.messaging.smtp.test'))}
                        className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                    >
                        Test SMTP connection
                    </button>
                    <p className="text-xs text-on-surface-variant">
                        Sends a test email to your account using saved platform SMTP. Save first if you just changed
                        settings.
                    </p>
                </div>
            </section>

            <section className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <h3 className="font-semibold">Shared Meta WhatsApp credentials</h3>
                <p className="text-xs text-on-surface-variant">
                    Orgs that choose “Use platform Meta WhatsApp credentials” will send through this account.
                    Webhook URL: <code className="break-all">{webhookUrl}</code>
                    {verifyTokenHint ? (
                        <>
                            <br />
                            Verify token: <code>{verifyTokenHint}</code>
                        </>
                    ) : null}
                </p>

                <div className="rounded-xl border border-[#1877f2]/30 bg-[#1877f2]/5 p-4">
                    <p className="mb-3 text-sm font-semibold text-[#1877f2]">Connect with Meta</p>
                    <MetaWhatsAppConnect
                        embeddedSignup={embeddedSignup}
                        connectRoute={route('site-settings.messaging.whatsapp.connect')}
                    />
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.whatsapp_enabled}
                        onChange={(e) => form.setData('whatsapp_enabled', e.target.checked)}
                    />
                    Allow orgs to use these platform Meta credentials
                </label>

                <p className="text-xs font-semibold text-on-surface-variant">
                    Manual IDs (digits only — not the +1 555 display number)
                </p>
                <div className="grid gap-3 md:grid-cols-2">
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="Meta App ID"
                        value={form.data.meta_app_id}
                        onChange={(e) => form.setData('meta_app_id', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="Embedded Signup Config ID"
                        value={form.data.meta_embedded_signup_config_id}
                        onChange={(e) => form.setData('meta_embedded_signup_config_id', e.target.value)}
                    />
                    <div>
                        <input
                            className="w-full rounded-xl border-slate-200"
                            placeholder="Phone Number ID (digits only)"
                            value={form.data.meta_phone_number_id}
                            onChange={(e) => form.setData('meta_phone_number_id', e.target.value)}
                        />
                        <p className="mt-1 text-[11px] text-on-surface-variant">
                            From Meta → WhatsApp → API Setup → Phone number ID (long number). Not +1 555-…
                        </p>
                    </div>
                    <div>
                        <input
                            className="w-full rounded-xl border-slate-200"
                            placeholder="WABA ID (digits only)"
                            value={form.data.meta_waba_id}
                            onChange={(e) => form.setData('meta_waba_id', e.target.value)}
                        />
                        <p className="mt-1 text-[11px] text-on-surface-variant">
                            WhatsApp Business Account ID from the same Meta API Setup page.
                        </p>
                    </div>
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="API version"
                        value={form.data.meta_api_version}
                        onChange={(e) => form.setData('meta_api_version', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200 md:col-span-2"
                        placeholder={
                            messaging.has_meta_access_token
                                ? 'Access token (leave blank to keep)'
                                : 'Permanent / temporary access token (required)'
                        }
                        type="password"
                        value={form.data.meta_access_token}
                        onChange={(e) => form.setData('meta_access_token', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200 md:col-span-2"
                        placeholder={
                            messaging.has_meta_app_secret
                                ? 'App secret (leave blank to keep / prefer .env)'
                                : 'App secret (prefer META_APP_SECRET in .env)'
                        }
                        type="password"
                        value={form.data.meta_app_secret}
                        onChange={(e) => form.setData('meta_app_secret', e.target.value)}
                    />
                </div>
                <button
                    type="button"
                    onClick={() => router.post(route('site-settings.messaging.whatsapp.test'))}
                    className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                >
                    Test Meta connection
                </button>
            </section>

            {Object.values(form.errors).map((err) => (
                <p key={err} className="text-xs text-error">
                    {err}
                </p>
            ))}

            <button disabled={form.processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                Save messaging
            </button>
        </form>
    );
}

function PlanCard({ plan, featureKeys }) {
    const form = useForm({
        name: plan.name || '',
        price_monthly: plan.price_monthly ?? 0,
        seats_limit: plan.seats_limit ?? '',
        donors_limit: plan.donors_limit ?? '',
        campaigns_limit: plan.campaigns_limit ?? '',
        whatsapp_monthly_limit: plan.whatsapp_monthly_limit ?? '',
        telecaller_hours_monthly: plan.telecaller_hours_monthly ?? '',
        imports_monthly_limit: plan.imports_monthly_limit ?? '',
        features: plan.features || [],
        is_active: !!plan.is_active,
        sort_order: plan.sort_order ?? 0,
    });

    const toggleFeature = (key) => {
        const next = form.data.features.includes(key)
            ? form.data.features.filter((f) => f !== key)
            : [...form.data.features, key];
        form.setData('features', next);
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(route('site-settings.plans.update', plan.id));
            }}
            className="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-card"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs uppercase tracking-wide text-on-surface-variant">{plan.code}</p>
                    <input
                        className="mt-1 w-full rounded-xl border-slate-200 font-semibold"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                    />
                    Active
                </label>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div>
                    <label className="text-xs font-semibold">Monthly fee (INR)</label>
                    <input
                        type="number"
                        min="0"
                        className="mt-1 w-full rounded-xl border-slate-200"
                        value={form.data.price_monthly}
                        onChange={(e) => form.setData('price_monthly', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-on-surface-variant">{formatINR(form.data.price_monthly)}/mo</p>
                </div>
                <div>
                    <label className="text-xs font-semibold">Sort order</label>
                    <input
                        type="number"
                        min="0"
                        className="mt-1 w-full rounded-xl border-slate-200"
                        value={form.data.sort_order}
                        onChange={(e) => form.setData('sort_order', e.target.value)}
                    />
                </div>
                {[
                    ['seats_limit', 'Seats'],
                    ['donors_limit', 'Donors'],
                    ['campaigns_limit', 'Campaigns'],
                    ['whatsapp_monthly_limit', 'WhatsApp / month'],
                    ['imports_monthly_limit', 'Imports / month'],
                    ['telecaller_hours_monthly', 'Telecaller hours'],
                ].map(([key, label]) => (
                    <div key={key}>
                        <label className="text-xs font-semibold">{label}</label>
                        <input
                            type="number"
                            min="0"
                            className="mt-1 w-full rounded-xl border-slate-200"
                            placeholder="Unlimited"
                            value={form.data[key]}
                            onChange={(e) => form.setData(key, e.target.value)}
                        />
                    </div>
                ))}
            </div>

            <div>
                <p className="mb-2 text-xs font-semibold">Features</p>
                <div className="flex flex-wrap gap-2">
                    {featureKeys.map((key) => (
                        <label
                            key={key}
                            className={`cursor-pointer rounded-full px-3 py-1 text-xs font-semibold ${
                                form.data.features.includes(key)
                                    ? 'bg-primary text-white'
                                    : 'bg-surface-container text-on-surface-variant'
                            }`}
                        >
                            <input
                                type="checkbox"
                                className="sr-only"
                                checked={form.data.features.includes(key)}
                                onChange={() => toggleFeature(key)}
                            />
                            {key.replaceAll('_', ' ')}
                        </label>
                    ))}
                </div>
            </div>

            <button disabled={form.processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                Save plan
            </button>
        </form>
    );
}

function PlansTab({ plans, featureKeys }) {
    return (
        <div className="space-y-4">
            <p className="text-sm text-on-surface-variant">
                Edit subscription fees, limits, and feature packs. Assign a plan to an organization from Workspace →
                Billing.
            </p>
            <div className="grid gap-4 xl:grid-cols-2">
                {plans.map((plan) => (
                    <PlanCard key={plan.id} plan={plan} featureKeys={featureKeys} />
                ))}
            </div>
        </div>
    );
}

function BillingTab({ platformBilling }) {
    const form = useForm({
        enabled: !!platformBilling.enabled,
        razorpay_key_id: platformBilling.razorpay_key_id || '',
        razorpay_key_secret: '',
        razorpay_webhook_secret: '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(route('site-settings.billing.update'));
            }}
            className="mx-auto max-w-2xl space-y-6"
        >
            <section className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <h3 className="font-semibold">Platform Razorpay (SaaS invoices)</h3>
                <p className="text-xs text-on-surface-variant">
                    Used to collect organization subscription fees. Separate from each org’s donor Razorpay keys.
                </p>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.enabled}
                        onChange={(e) => form.setData('enabled', e.target.checked)}
                    />
                    Platform billing enabled
                </label>
                <div className="grid gap-3">
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="Razorpay Key ID"
                        value={form.data.razorpay_key_id}
                        onChange={(e) => form.setData('razorpay_key_id', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200"
                        type="password"
                        placeholder={
                            platformBilling.has_razorpay_key_secret
                                ? 'Key secret (leave blank to keep)'
                                : 'Key secret'
                        }
                        value={form.data.razorpay_key_secret}
                        onChange={(e) => form.setData('razorpay_key_secret', e.target.value)}
                    />
                    <input
                        className="rounded-xl border-slate-200"
                        type="password"
                        placeholder={
                            platformBilling.has_razorpay_webhook_secret
                                ? 'Webhook secret (leave blank to keep)'
                                : 'Webhook secret'
                        }
                        value={form.data.razorpay_webhook_secret}
                        onChange={(e) => form.setData('razorpay_webhook_secret', e.target.value)}
                    />
                </div>
            </section>
            <button disabled={form.processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                Save platform billing
            </button>
        </form>
    );
}

function CouponsTab({ coupons, plans }) {
    const createForm = useForm({
        code: '',
        name: '',
        type: 'percent',
        value: 10,
        plan_ids: [],
        max_redemptions: '',
        starts_at: '',
        ends_at: '',
        is_active: true,
    });

    const togglePlan = (id) => {
        const next = createForm.data.plan_ids.includes(id)
            ? createForm.data.plan_ids.filter((x) => x !== id)
            : [...createForm.data.plan_ids, id];
        createForm.setData('plan_ids', next);
    };

    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    createForm.post(route('site-settings.coupons.store'), {
                        onSuccess: () => createForm.reset('code', 'name', 'value', 'plan_ids', 'max_redemptions', 'starts_at', 'ends_at'),
                    });
                }}
                className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card"
            >
                <h3 className="font-semibold">Create coupon</h3>
                <div className="grid gap-3 md:grid-cols-2">
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="Code (e.g. LAUNCH20)"
                        value={createForm.data.code}
                        onChange={(e) => createForm.setData('code', e.target.value.toUpperCase())}
                    />
                    <input
                        className="rounded-xl border-slate-200"
                        placeholder="Display name"
                        value={createForm.data.name}
                        onChange={(e) => createForm.setData('name', e.target.value)}
                    />
                    <select
                        className="rounded-xl border-slate-200"
                        value={createForm.data.type}
                        onChange={(e) => createForm.setData('type', e.target.value)}
                    >
                        <option value="percent">Percent off</option>
                        <option value="fixed">Fixed INR off</option>
                    </select>
                    <input
                        type="number"
                        min="1"
                        className="rounded-xl border-slate-200"
                        placeholder="Value"
                        value={createForm.data.value}
                        onChange={(e) => createForm.setData('value', e.target.value)}
                    />
                    <input
                        type="number"
                        min="1"
                        className="rounded-xl border-slate-200"
                        placeholder="Max redemptions (optional)"
                        value={createForm.data.max_redemptions}
                        onChange={(e) => createForm.setData('max_redemptions', e.target.value)}
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={createForm.data.is_active}
                            onChange={(e) => createForm.setData('is_active', e.target.checked)}
                        />
                        Active
                    </label>
                    <input
                        type="date"
                        className="rounded-xl border-slate-200"
                        value={createForm.data.starts_at}
                        onChange={(e) => createForm.setData('starts_at', e.target.value)}
                    />
                    <input
                        type="date"
                        className="rounded-xl border-slate-200"
                        value={createForm.data.ends_at}
                        onChange={(e) => createForm.setData('ends_at', e.target.value)}
                    />
                </div>
                <div>
                    <p className="mb-2 text-xs font-semibold">Applies to plans (empty = all)</p>
                    <div className="flex flex-wrap gap-2">
                        {plans.map((plan) => (
                            <label
                                key={plan.id}
                                className={`cursor-pointer rounded-full px-3 py-1 text-xs font-semibold ${
                                    createForm.data.plan_ids.includes(plan.id)
                                        ? 'bg-primary text-white'
                                        : 'bg-surface-container text-on-surface-variant'
                                }`}
                            >
                                <input
                                    type="checkbox"
                                    className="sr-only"
                                    checked={createForm.data.plan_ids.includes(plan.id)}
                                    onChange={() => togglePlan(plan.id)}
                                />
                                {plan.name}
                            </label>
                        ))}
                    </div>
                </div>
                {Object.values(createForm.errors).map((err) => (
                    <p key={err} className="text-xs text-error">
                        {err}
                    </p>
                ))}
                <button
                    disabled={createForm.processing}
                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                >
                    Create coupon
                </button>
            </form>

            <section className="rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <h3 className="mb-4 font-semibold">Existing coupons</h3>
                {!coupons.length ? (
                    <p className="text-sm text-on-surface-variant">No coupons yet.</p>
                ) : (
                    <div className="space-y-3">
                        {coupons.map((coupon) => (
                            <div
                                key={coupon.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 px-4 py-3"
                            >
                                <div>
                                    <p className="font-mono text-sm font-semibold">{coupon.code}</p>
                                    <p className="text-xs text-on-surface-variant">
                                        {coupon.name} ·{' '}
                                        {coupon.type === 'percent' ? `${coupon.value}%` : formatINR(coupon.value)} off ·{' '}
                                        {coupon.redeemed_count}
                                        {coupon.max_redemptions != null ? ` / ${coupon.max_redemptions}` : ''} used ·{' '}
                                        {coupon.is_active ? 'Active' : 'Inactive'}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    className="text-xs font-semibold text-error"
                                    onClick={() => {
                                        if (confirm(`Delete coupon ${coupon.code}?`)) {
                                            router.delete(route('site-settings.coupons.destroy', coupon.id));
                                        }
                                    }}
                                >
                                    Delete
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

function DefaultsTab({ commissionDefaults }) {
    const form = useForm({
        individual_enabled: !!commissionDefaults.individual_enabled,
        individual_default_percent: commissionDefaults.individual_default_percent ?? 5,
        shared_enabled: !!commissionDefaults.shared_enabled,
        shared_percent: commissionDefaults.shared_percent ?? 0,
        shared_eligibility: commissionDefaults.shared_eligibility || 'active_contributors',
        internal_individual_enabled: !!commissionDefaults.internal_individual_enabled,
        internal_individual_default_percent: commissionDefaults.internal_individual_default_percent ?? 5,
        internal_shared_enabled: !!commissionDefaults.internal_shared_enabled,
        internal_shared_percent: commissionDefaults.internal_shared_percent ?? 0,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(route('site-settings.commission-defaults.update'));
            }}
            className="mx-auto max-w-2xl space-y-6"
        >
            <p className="text-sm text-on-surface-variant">
                These rates seed new organizations. Per-org overrides remain under Payments → Commissions.
            </p>

            <section className="space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <h3 className="font-semibold">Org volunteer defaults</h3>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.individual_enabled}
                        onChange={(e) => form.setData('individual_enabled', e.target.checked)}
                    />
                    Individual commissions enabled
                </label>
                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="text-xs font-semibold">Default %</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.individual_default_percent}
                            onChange={(e) => form.setData('individual_default_percent', e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Shared eligibility</label>
                        <input
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.shared_eligibility}
                            onChange={(e) => form.setData('shared_eligibility', e.target.value)}
                        />
                    </div>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.shared_enabled}
                        onChange={(e) => form.setData('shared_enabled', e.target.checked)}
                    />
                    Shared pool enabled
                </label>
                <div>
                    <label className="text-xs font-semibold">Shared %</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        className="mt-1 w-full rounded-xl border-slate-200"
                        value={form.data.shared_percent}
                        onChange={(e) => form.setData('shared_percent', e.target.value)}
                    />
                </div>
            </section>

            <section className="space-y-4 rounded-2xl border border-primary/20 bg-primary/5 p-6 shadow-card">
                <h3 className="font-semibold">DonorConnect telecaller defaults</h3>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.internal_individual_enabled}
                        onChange={(e) => form.setData('internal_individual_enabled', e.target.checked)}
                    />
                    Internal individual commissions enabled
                </label>
                <div>
                    <label className="text-xs font-semibold">Internal default %</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        className="mt-1 w-full rounded-xl border-slate-200 bg-white"
                        value={form.data.internal_individual_default_percent}
                        onChange={(e) => form.setData('internal_individual_default_percent', e.target.value)}
                    />
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.internal_shared_enabled}
                        onChange={(e) => form.setData('internal_shared_enabled', e.target.checked)}
                    />
                    Internal shared pool enabled
                </label>
                <div>
                    <label className="text-xs font-semibold">Internal shared %</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        className="mt-1 w-full rounded-xl border-slate-200 bg-white"
                        value={form.data.internal_shared_percent}
                        onChange={(e) => form.setData('internal_shared_percent', e.target.value)}
                    />
                </div>
            </section>

            <button disabled={form.processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                Save defaults
            </button>
        </form>
    );
}

export default function SiteSettingsIndex({
    tab,
    tabs,
    featureKeys,
    messaging,
    webhookUrl,
    verifyTokenHint,
    embeddedSignup,
    instructions,
    plans,
    platformBilling,
    coupons,
    commissionDefaults,
}) {
    const [activeTab, setActiveTab] = useState(tab || 'modules');

    useEffect(() => {
        setActiveTab(tab || 'modules');
    }, [tab]);

    const switchTab = (next) => {
        setActiveTab(next);
        router.get(route('site-settings.index', { tab: next }), {}, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout header="Site Settings">
            <Head title="Site Settings" />

            <div className="mb-6">
                <h2 className="text-headline-md">Site Settings</h2>
                <p className="text-sm text-on-surface-variant">
                    Super Admin controls for modules, messaging, plans, billing, coupons, and default commissions.
                    Organization-specific tools stay under Workspace.
                </p>
            </div>

            <FlashMessages />

            <div className="mb-6 flex flex-wrap gap-2 border-b border-outline-variant pb-3">
                {tabs.map((key) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => switchTab(key)}
                        className={`rounded-full px-3 py-1.5 text-sm font-semibold transition ${
                            activeTab === key
                                ? 'bg-primary text-white'
                                : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'
                        }`}
                    >
                        {TAB_LABELS[key] || key}
                    </button>
                ))}
            </div>

            {activeTab === 'modules' && <ModulesTab messaging={messaging} />}
            {activeTab === 'messaging' && (
                <MessagingTab
                    messaging={messaging}
                    webhookUrl={webhookUrl}
                    verifyTokenHint={verifyTokenHint}
                    embeddedSignup={embeddedSignup}
                    instructions={instructions}
                />
            )}
            {activeTab === 'plans' && <PlansTab plans={plans} featureKeys={featureKeys} />}
            {activeTab === 'billing' && <BillingTab platformBilling={platformBilling} />}
            {activeTab === 'coupons' && <CouponsTab coupons={coupons} plans={plans} />}
            {activeTab === 'defaults' && <DefaultsTab commissionDefaults={commissionDefaults} />}
        </AuthenticatedLayout>
    );
}
