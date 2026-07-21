import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ApiTokens({ tokens, plaintextToken }) {
    const [copied, setCopied] = useState(false);
    const form = useForm({
        name: '',
        expires_at: '',
    });

    const copyToken = async () => {
        if (!plaintextToken) return;
        await navigator.clipboard.writeText(plaintextToken);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('api-tokens.store'), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AuthenticatedLayout header="API tokens">
            <Head title="API tokens" />

            <div className="mb-6">
                <h2 className="text-headline-md">REST API keys</h2>
                <p className="text-sm text-on-surface-variant">
                    Create and revoke organization-scoped REST API keys.
                </p>
            </div>

            {plaintextToken && (
                <div className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p className="text-sm font-semibold text-amber-900">
                        Copy your new token now — it will not be shown again.
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <code className="break-all rounded-lg bg-white px-3 py-2 text-xs">{plaintextToken}</code>
                        <button
                            type="button"
                            onClick={copyToken}
                            className="rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white"
                        >
                            {copied ? 'Copied' : 'Copy'}
                        </button>
                    </div>
                </div>
            )}

            <section className="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                <h3 className="mb-4 font-semibold">Create token</h3>
                <form onSubmit={submit} className="flex flex-col gap-3 md:flex-row md:items-end">
                    <div className="flex-1">
                        <label className="text-xs font-semibold">Name</label>
                        <input
                            className="mt-1 w-full rounded-xl border-slate-200"
                            placeholder="Integration name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            required
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Expires (optional)</label>
                        <input
                            type="date"
                            className="mt-1 w-full rounded-xl border-slate-200"
                            value={form.data.expires_at}
                            onChange={(e) => form.setData('expires_at', e.target.value)}
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                    >
                        Create token
                    </button>
                </form>
                {Object.values(form.errors).map((err) => (
                    <p key={err} className="mt-2 text-xs text-error">
                        {err}
                    </p>
                ))}
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                {!tokens.length ? (
                    <EmptyState icon="key" title="No API tokens" description="Create one to access the REST API." />
                ) : (
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Prefix</th>
                                <th className="px-4 py-3">Last used</th>
                                <th className="px-4 py-3">Expires</th>
                                <th className="px-4 py-3">Created</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {tokens.map((token) => (
                                <tr key={token.id} className="border-t border-slate-100">
                                    <td className="px-4 py-3 font-medium">{token.name}</td>
                                    <td className="px-4 py-3 font-mono text-xs">{token.token_prefix}…</td>
                                    <td className="px-4 py-3 text-xs">
                                        {token.last_used_at ? formatDateTime(token.last_used_at) : 'Never'}
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        {token.expires_at ? formatDateTime(token.expires_at) : 'Never'}
                                    </td>
                                    <td className="px-4 py-3 text-xs">{formatDateTime(token.created_at)}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('api-tokens.destroy', token.id)}
                                            method="delete"
                                            as="button"
                                            className="text-xs font-semibold text-error"
                                        >
                                            Revoke
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
