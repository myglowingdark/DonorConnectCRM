import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ApiWebhooks({ webhooks, availableEvents }) {
    const form = useForm({
        url: '',
        events: [],
        secret: '',
    });

    const toggleEvent = (event) => {
        const current = form.data.events || [];
        form.setData(
            'events',
            current.includes(event) ? current.filter((e) => e !== event) : [...current, event],
        );
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('webhooks.store'), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AuthenticatedLayout header="Webhooks">
            <Head title="Webhooks" />

            <div className="mb-6">
                <h2 className="text-headline-md">Outbound webhooks</h2>
                <p className="text-sm text-on-surface-variant">
                    Receive donation, assignment, and pledge events at your endpoint.
                </p>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Create webhook</h3>
                <form onSubmit={submit} className="space-y-3">
                    <input
                        type="url"
                        className="w-full rounded-xl border-slate-200"
                        placeholder="https://example.com/webhooks/donorconnect"
                        value={form.data.url}
                        onChange={(e) => form.setData('url', e.target.value)}
                        required
                    />
                    <div>
                        <p className="mb-2 text-xs font-semibold text-on-surface-variant">Events</p>
                        <div className="flex flex-wrap gap-2">
                            {availableEvents.map((event) => (
                                <button
                                    key={event}
                                    type="button"
                                    onClick={() => toggleEvent(event)}
                                    className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                        (form.data.events || []).includes(event)
                                            ? 'bg-secondary text-white'
                                            : 'bg-surface-container'
                                    }`}
                                >
                                    {event}
                                </button>
                            ))}
                        </div>
                    </div>
                    <input
                        className="w-full rounded-xl border-slate-200"
                        placeholder="Signing secret (optional — auto-generated if blank)"
                        value={form.data.secret}
                        onChange={(e) => form.setData('secret', e.target.value)}
                    />
                    {Object.values(form.errors).map((err) => (
                        <p key={err} className="text-xs text-error">
                            {err}
                        </p>
                    ))}
                    <button
                        type="submit"
                        disabled={form.processing || !(form.data.events || []).length}
                        className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                    >
                        Create webhook
                    </button>
                </form>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                {!webhooks.length ? (
                    <EmptyState icon="webhook" title="No webhooks" description="Create one to receive outbound events." />
                ) : (
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="px-4 py-3">URL</th>
                                <th className="px-4 py-3">Events</th>
                                <th className="px-4 py-3">Deliveries</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Created</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {webhooks.map((hook) => (
                                <tr key={hook.id} className="border-t border-slate-100">
                                    <td className="max-w-xs truncate px-4 py-3 font-mono text-xs">{hook.url}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {(hook.events || []).map((ev) => (
                                                <span
                                                    key={ev}
                                                    className="rounded-full bg-surface-container px-2 py-0.5 text-[10px] font-semibold"
                                                >
                                                    {ev}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">{hook.deliveries_count ?? 0}</td>
                                    <td className="px-4 py-3">
                                        {hook.is_active ? (
                                            <span className="text-green-700">Active</span>
                                        ) : (
                                            <span className="text-on-surface-variant">Inactive</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs">{formatDateTime(hook.created_at)}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('webhooks.destroy', hook.id)}
                                            method="delete"
                                            as="button"
                                            className="text-xs font-semibold text-error"
                                        >
                                            Delete
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>
        </AuthenticatedLayout>
    );
}
