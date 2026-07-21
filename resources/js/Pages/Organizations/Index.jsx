import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function OrganizationsIndex({ organizations }) {
    return (
        <AuthenticatedLayout header="Organizations">
            <Head title="Organizations" />
            <div className="mb-6 flex items-center justify-between">
                <h2 className="text-headline-md">Manage organizations</h2>
                <Link href={route('organizations.create')} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                    Create
                </Link>
            </div>
            <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                <table className="min-w-full text-sm">
                    <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                        <tr>
                            <th className="px-4 py-3">Organization</th>
                            <th className="px-4 py-3">Donors</th>
                            <th className="px-4 py-3">Limit</th>
                            <th className="px-4 py-3">Users</th>
                            <th className="px-4 py-3">Sync</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {organizations.data.map((org) => (
                            <tr key={org.id} className="border-t border-slate-100">
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <div
                                            className="flex h-9 w-9 items-center justify-center rounded-lg text-xs font-bold text-white"
                                            style={{ backgroundColor: org.brand_color }}
                                        >
                                            {org.name.slice(0, 2).toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="font-medium">{org.name}</p>
                                            <p className="text-xs text-on-surface-variant">{org.slug}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-4 py-3">{org.donors_count}</td>
                                <td className="px-4 py-3">{org.donors_limit ?? '∞'}</td>
                                <td className="px-4 py-3">{org.users_count}</td>
                                <td className="px-4 py-3">{org.api_connection?.sync_status || 'idle'}</td>
                                <td className="px-4 py-3 text-right">
                                    <Link href={route('organizations.edit', org.id)} className="text-xs font-semibold text-secondary">
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AuthenticatedLayout>
    );
}
