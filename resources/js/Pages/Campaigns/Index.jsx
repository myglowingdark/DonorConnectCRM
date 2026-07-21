import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatINR } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function CampaignsIndex({ campaigns }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        status: 'active',
        starts_at: '',
        ends_at: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('campaigns.store'), {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout header="Campaigns">
            <Head title="Campaigns" />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 className="text-headline-md">Campaign performance</h2>
                    <p className="text-sm text-on-surface-variant">
                        Revenue, donations, calls, and conversion by campaign.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                >
                    New campaign
                </button>
            </div>

            {!campaigns.length ? (
                <EmptyState icon="campaign" title="No campaigns yet" description="Create one or link a campaign while importing donors." />
            ) : (
                <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="px-4 py-3">Campaign</th>
                                <th className="px-4 py-3">Leads</th>
                                <th className="px-4 py-3">Calls</th>
                                <th className="px-4 py-3">Donations</th>
                                <th className="px-4 py-3">Revenue</th>
                                <th className="px-4 py-3">Conversion</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {campaigns.map((c) => (
                                <tr key={c.id} className="border-t border-slate-100">
                                    <td className="px-4 py-3">
                                        <p className="font-medium">{c.name}</p>
                                        <p className="text-xs capitalize text-on-surface-variant">{c.status}</p>
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">{c.leads}</td>
                                    <td className="px-4 py-3 tabular-nums">{c.calls}</td>
                                    <td className="px-4 py-3 tabular-nums">{c.donations_count}</td>
                                    <td className="px-4 py-3 tabular-nums">{formatINR(c.revenue)}</td>
                                    <td className="px-4 py-3 tabular-nums">{c.conversion_rate}%</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('campaigns.show', c.id)}
                                            className="text-xs font-semibold text-secondary"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {open && (
                <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
                    <form
                        onSubmit={submit}
                        className="w-full max-w-md space-y-3 rounded-2xl bg-white p-5 shadow-xl"
                    >
                        <h3 className="text-lg font-semibold">Create campaign</h3>
                        <div>
                            <label className="text-xs font-semibold">Name</label>
                            <input
                                className="mt-1 w-full rounded-xl border-slate-200"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold">Starts</label>
                                <input
                                    type="date"
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={form.data.starts_at}
                                    onChange={(e) => form.setData('starts_at', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Ends</label>
                                <input
                                    type="date"
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={form.data.ends_at}
                                    onChange={(e) => form.setData('ends_at', e.target.value)}
                                />
                            </div>
                        </div>
                        {Object.values(form.errors).map((err) => (
                            <p key={err} className="text-xs text-error">
                                {err}
                            </p>
                        ))}
                        <div className="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                            >
                                Create
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
