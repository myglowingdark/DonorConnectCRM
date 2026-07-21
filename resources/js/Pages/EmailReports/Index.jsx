import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function EmailReportsIndex({ recipients, schedules }) {
    const recipientForm = useForm({
        name: '',
        email: '',
        role_label: '',
        is_active: true,
    });

    const scheduleForm = useForm({
        type: 'weekly_stats',
        frequency: 'weekly',
        day_of_month: 1,
        send_at: '09:00',
        timezone: 'Asia/Kolkata',
        is_active: true,
        requires_approval: false,
    });

    return (
        <AuthenticatedLayout header="Email Reports">
            <Head title="Email Reports" />

            <div className="mb-6">
                <h2 className="text-headline-md">Recipients & schedules</h2>
                <p className="text-sm text-on-surface-variant">
                    Weekly stats (Mondays) and monthly commission summaries. Sent via org/platform SMTP.
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Recipients</h3>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            recipientForm.post(route('email-reports.recipients.store'), {
                                onSuccess: () => recipientForm.reset('name', 'email', 'role_label'),
                            });
                        }}
                        className="mb-4 grid gap-2"
                    >
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Name"
                            value={recipientForm.data.name}
                            onChange={(e) => recipientForm.setData('name', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Email"
                            type="email"
                            value={recipientForm.data.email}
                            onChange={(e) => recipientForm.setData('email', e.target.value)}
                        />
                        <input
                            className="rounded-xl border-slate-200"
                            placeholder="Role label"
                            value={recipientForm.data.role_label}
                            onChange={(e) => recipientForm.setData('role_label', e.target.value)}
                        />
                        <button className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                            Add recipient
                        </button>
                    </form>
                    <ul className="space-y-2 text-sm">
                        {recipients.map((r) => (
                            <li key={r.id} className="flex items-center justify-between border-t border-slate-100 py-2">
                                <div>
                                    <p className="font-medium">{r.name}</p>
                                    <p className="text-xs text-on-surface-variant">
                                        {r.email}
                                        {r.role_label ? ` · ${r.role_label}` : ''}
                                        {!r.is_active ? ' · inactive' : ''}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    className="text-xs font-semibold text-error"
                                    onClick={() => router.delete(route('email-reports.recipients.destroy', r.id))}
                                >
                                    Remove
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                    <h3 className="mb-4 font-semibold">Schedules</h3>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            scheduleForm.post(route('email-reports.schedules.store'), {
                                onSuccess: () => scheduleForm.reset(),
                            });
                        }}
                        className="mb-4 grid gap-2"
                    >
                        <select
                            className="rounded-xl border-slate-200"
                            value={scheduleForm.data.type}
                            onChange={(e) => {
                                const type = e.target.value;
                                scheduleForm.setData({
                                    ...scheduleForm.data,
                                    type,
                                    frequency: type === 'monthly_commission_summary' ? 'monthly' : 'weekly',
                                });
                            }}
                        >
                            <option value="weekly_stats">Weekly stats</option>
                            <option value="monthly_commission_summary">Monthly commission summary</option>
                        </select>
                        <input
                            type="time"
                            className="rounded-xl border-slate-200"
                            value={scheduleForm.data.send_at}
                            onChange={(e) => scheduleForm.setData('send_at', e.target.value)}
                        />
                        {scheduleForm.data.frequency === 'monthly' && (
                            <input
                                type="number"
                                min="1"
                                max="28"
                                className="rounded-xl border-slate-200"
                                placeholder="Day of month"
                                value={scheduleForm.data.day_of_month}
                                onChange={(e) => scheduleForm.setData('day_of_month', e.target.value)}
                            />
                        )}
                        <input
                            className="rounded-xl border-slate-200"
                            value={scheduleForm.data.timezone}
                            onChange={(e) => scheduleForm.setData('timezone', e.target.value)}
                        />
                        <button className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                            Add schedule
                        </button>
                    </form>
                    <ul className="space-y-2 text-sm">
                        {schedules.map((s) => (
                            <li key={s.id} className="flex items-center justify-between border-t border-slate-100 py-2">
                                <div>
                                    <p className="font-medium">{s.type.replaceAll('_', ' ')}</p>
                                    <p className="text-xs text-on-surface-variant">
                                        {s.frequency} · {String(s.send_at).slice(0, 5)} {s.timezone}
                                        {s.frequency === 'monthly' ? ` · day ${s.day_of_month}` : ''}
                                        {!s.is_active ? ' · inactive' : ''}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    className="text-xs font-semibold text-error"
                                    onClick={() => router.delete(route('email-reports.schedules.destroy', s.id))}
                                >
                                    Remove
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
