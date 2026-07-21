import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function UsersIndex({
    users,
    filters,
    roles,
    allOrganizations,
    canManageAllOrganizations,
    languages = [],
}) {
    const { auth, currentOrganization } = usePage().props;
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const isSuperAdmin = auth.user?.role === 'super_admin' || canManageAllOrganizations;

    const form = useForm({
        name: '',
        email: '',
        phone: '',
        languages: [],
        password: '',
        password_confirmation: '',
        role: 'volunteer',
        organization_ids: [],
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            name: '',
            email: '',
            phone: '',
            languages: [],
            password: '',
            password_confirmation: '',
            role: 'volunteer',
            // Org admins default to current organization only.
            organization_ids: isSuperAdmin
                ? []
                : currentOrganization?.id
                  ? [currentOrganization.id]
                  : allOrganizations[0]
                    ? [allOrganizations[0].id]
                    : [],
            is_active: true,
        });
        setOpen(true);
    };

    const openEdit = (user) => {
        setEditing(user);
        form.setData({
            name: user.name,
            email: user.email,
            phone: user.phone || '',
            languages: user.languages || [],
            password: '',
            password_confirmation: '',
            role: user.role,
            // Only orgs visible to this admin are editable here.
            organization_ids: (user.organizations || []).map((o) => o.id),
            is_active: user.is_active,
        });
        setOpen(true);
    };

    const toggleLanguage = (code) => {
        const current = form.data.languages || [];
        form.setData(
            'languages',
            current.includes(code) ? current.filter((x) => x !== code) : [...current, code],
        );
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            form.put(route('users.update', editing.id), {
                onSuccess: () => setOpen(false),
            });
        } else {
            form.post(route('users.store'), {
                onSuccess: () => setOpen(false),
            });
        }
    };

    const toggleOrg = (id) => {
        const current = form.data.organization_ids || [];
        form.setData(
            'organization_ids',
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    return (
        <AuthenticatedLayout header="Team Members">
            <Head title="Users" />

            <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-headline-md">Volunteers & admins</h2>
                    <p className="text-sm text-on-surface-variant">Assign people to organizations and manage access.</p>
                </div>
                <button
                    type="button"
                    onClick={openCreate}
                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                >
                    Add team member
                </button>
            </div>

            <div className="mb-4">
                <input
                    defaultValue={filters.search || ''}
                    placeholder="Search name, email, phone"
                    className="w-full max-w-md rounded-xl border-slate-200"
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            router.get(route('users.index'), { search: e.target.value }, { preserveState: true });
                        }
                    }}
                />
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                {!users.data.length ? (
                    <EmptyState icon="group" title="No users found" />
                ) : (
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Contact</th>
                                <th className="px-4 py-3">Role</th>
                                <th className="px-4 py-3">Organizations</th>
                                <th className="px-4 py-3">Donors</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-t border-slate-100">
                                    <td className="px-4 py-3 font-medium">{user.name}</td>
                                    <td className="px-4 py-3">
                                        <div>{user.email}</div>
                                        <div className="text-xs text-on-surface-variant">{user.phone}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="capitalize">{String(user.role).replaceAll('_', ' ')}</div>
                                        {(user.languages || []).length > 0 && (
                                            <div className="mt-1 text-xs text-on-surface-variant">
                                                {(user.languages || [])
                                                    .map((code) => languages.find((l) => l.value === code)?.label || code)
                                                    .join(', ')}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {(user.organizations || []).map((org) => (
                                                <span
                                                    key={org.id}
                                                    className="rounded-full px-2 py-0.5 text-[10px] font-semibold text-white"
                                                    style={{ backgroundColor: org.brand_color || '#1e3a8a' }}
                                                >
                                                    {org.name}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">{user.donor_count ?? 0}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            status={user.is_active ? 'success' : 'failed'}
                                            label={user.is_active ? 'Active' : 'Inactive'}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            onClick={() => openEdit(user)}
                                            className="text-xs font-semibold text-secondary"
                                        >
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-elevated">
                        <h3 className="text-lg font-semibold">{editing ? 'Edit team member' : 'Add team member'}</h3>
                        <form onSubmit={submit} className="mt-4 space-y-3">
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="Full name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="Email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="Phone"
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                            />
                            <select
                                className="w-full rounded-xl border-slate-200"
                                value={form.data.role}
                                onChange={(e) => form.setData('role', e.target.value)}
                            >
                                {roles
                                    .filter((r) => auth.user?.role === 'super_admin' || r.value !== 'super_admin')
                                    .map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                            </select>
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder={editing ? 'New password (optional)' : 'Password'}
                                type="password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="Confirm password"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            />
                            {(form.data.role === 'volunteer' || editing?.role === 'volunteer') && (
                                <div>
                                    <p className="mb-2 text-xs font-semibold text-on-surface-variant">
                                        Comfortable languages
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {languages.map((lang) => (
                                            <button
                                                key={lang.value}
                                                type="button"
                                                onClick={() => toggleLanguage(lang.value)}
                                                className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                    (form.data.languages || []).includes(lang.value)
                                                        ? 'bg-secondary text-white'
                                                        : 'bg-surface-container'
                                                }`}
                                            >
                                                {lang.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <div>
                                <p className="mb-2 text-xs font-semibold text-on-surface-variant">
                                    {isSuperAdmin ? 'Organizations' : 'Organization access'}
                                </p>
                                {!isSuperAdmin && (
                                    <p className="mb-2 text-xs text-on-surface-variant">
                                        You only manage membership for your organization. Other org assignments stay
                                        private.
                                    </p>
                                )}
                                <div className="flex flex-wrap gap-2">
                                    {allOrganizations.map((org) => (
                                        <button
                                            key={org.id}
                                            type="button"
                                            onClick={() => toggleOrg(org.id)}
                                            className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                form.data.organization_ids.includes(org.id)
                                                    ? 'bg-primary text-white'
                                                    : 'bg-surface-container'
                                            }`}
                                        >
                                            {org.name}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                />
                                Active
                            </label>
                            {Object.values(form.errors).map((err) => (
                                <p key={err} className="text-xs text-error">
                                    {err}
                                </p>
                            ))}
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setOpen(false)} className="rounded-xl px-4 py-2 text-sm">
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
