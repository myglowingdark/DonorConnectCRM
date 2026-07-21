import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';

const checklistItems = [
    { key: 'has_volunteers', label: 'Invite telecalling volunteers', href: 'users.index' },
    { key: 'has_import', label: 'Import your first donor list', href: 'imports.index' },
    { key: 'has_call_logged', label: 'Log a call outcome', href: 'donors.index' },
    { key: 'messaging_configured', label: 'Configure messaging (email / WhatsApp / SMS)', href: 'messaging.settings' },
];

const inviteRoles = [
    { value: 'volunteer', label: 'Telecalling volunteer' },
    { value: 'organization_admin', label: 'Organization admin' },
    { value: 'team_lead', label: 'Team lead' },
    { value: 'finance', label: 'Finance' },
    { value: 'viewer', label: 'Viewer' },
];

export default function OnboardingIndex({ checklist, invites, organization }) {
    const inviteForm = useForm({ email: '', role: 'volunteer' });

    const doneCount = checklistItems.filter((item) => checklist[item.key]).length;

    return (
        <AuthenticatedLayout header="Onboarding">
            <Head title="Onboarding" />

            <div className="mb-6">
                <h2 className="text-headline-md">Get {organization.name} ready</h2>
                <p className="text-sm text-on-surface-variant">
                    {doneCount} of {checklistItems.length} setup steps complete
                    {organization.onboarded_at ? ` · Onboarded ${formatDateTime(organization.onboarded_at)}` : ''}.
                </p>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Setup checklist</h3>
                <ul className="space-y-3">
                    {checklistItems.map((item) => {
                        const done = checklist[item.key];
                        return (
                            <li
                                key={item.key}
                                className="flex items-center justify-between gap-4 rounded-xl border border-slate-100 px-4 py-3"
                            >
                                <div className="flex items-center gap-3">
                                    <span
                                        className={`material-symbols-outlined text-[22px] ${
                                            done ? 'text-green-600' : 'text-on-surface-variant'
                                        }`}
                                    >
                                        {done ? 'check_circle' : 'radio_button_unchecked'}
                                    </span>
                                    <span className={done ? 'text-on-surface-variant line-through' : 'font-medium'}>
                                        {item.label}
                                    </span>
                                </div>
                                {!done && (
                                    <Link
                                        href={route(item.href)}
                                        className="text-xs font-semibold text-secondary"
                                    >
                                        Go
                                    </Link>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </section>

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Invite teammates</h3>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            inviteForm.post(route('onboarding.invites.store'), {
                                onSuccess: () => inviteForm.reset('email'),
                            });
                        }}
                        className="space-y-3"
                    >
                        <input
                            type="email"
                            className="w-full rounded-xl border-slate-200"
                            placeholder="Email address"
                            value={inviteForm.data.email}
                            onChange={(e) => inviteForm.setData('email', e.target.value)}
                            required
                        />
                        <select
                            className="w-full rounded-xl border-slate-200"
                            value={inviteForm.data.role}
                            onChange={(e) => inviteForm.setData('role', e.target.value)}
                        >
                            {inviteRoles.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </select>
                        {Object.values(inviteForm.errors).map((err) => (
                            <p key={err} className="text-xs text-error">
                                {err}
                            </p>
                        ))}
                        <button
                            type="submit"
                            disabled={inviteForm.processing}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                        >
                            Send invite
                        </button>
                    </form>
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Pending invites</h3>
                    {!invites.length ? (
                        <p className="text-sm text-on-surface-variant">No pending invites.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {invites.map((inv) => (
                                <li
                                    key={inv.id}
                                    className="flex items-center justify-between border-t border-slate-100 py-2 first:border-t-0"
                                >
                                    <div>
                                        <p className="font-medium">{inv.email}</p>
                                        <p className="text-xs capitalize text-on-surface-variant">
                                            {String(inv.role).replaceAll('_', ' ')} · expires{' '}
                                            {formatDateTime(inv.expires_at)}
                                        </p>
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
