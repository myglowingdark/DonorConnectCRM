import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function OrganizationForm({ organization }) {
    const editing = !!organization;
    const { data, setData, post, put, processing, errors } = useForm({
        name: organization?.name || '',
        slug: organization?.slug || '',
        brand_color: organization?.brand_color || '#1e3a8a',
        timezone: organization?.timezone || 'Asia/Kolkata',
        currency: organization?.currency || 'INR',
        is_active: organization?.is_active ?? true,
        logo: null,
    });

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            put(route('organizations.update', organization.id));
        } else {
            post(route('organizations.store'));
        }
    };

    return (
        <AuthenticatedLayout header={editing ? 'Edit Organization' : 'Create Organization'}>
            <Head title={editing ? 'Edit Organization' : 'Create Organization'} />
            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <div>
                    <label className="text-xs font-semibold">Name</label>
                    <input className="mt-1 w-full rounded-xl border-slate-200" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    {errors.name && <p className="text-xs text-error">{errors.name}</p>}
                </div>
                <div>
                    <label className="text-xs font-semibold">Slug</label>
                    <input className="mt-1 w-full rounded-xl border-slate-200" value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                </div>
                <div>
                    <label className="text-xs font-semibold">Brand color</label>
                    <input type="color" className="mt-1 h-10 w-20 rounded-lg" value={data.brand_color} onChange={(e) => setData('brand_color', e.target.value)} />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="text-xs font-semibold">Timezone</label>
                        <input className="mt-1 w-full rounded-xl border-slate-200" value={data.timezone} onChange={(e) => setData('timezone', e.target.value)} />
                    </div>
                    <div>
                        <label className="text-xs font-semibold">Currency</label>
                        <input className="mt-1 w-full rounded-xl border-slate-200" value={data.currency} onChange={(e) => setData('currency', e.target.value)} />
                    </div>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                    Active
                </label>
                <button disabled={processing} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                    {editing ? 'Update' : 'Create'}
                </button>
            </form>
        </AuthenticatedLayout>
    );
}
