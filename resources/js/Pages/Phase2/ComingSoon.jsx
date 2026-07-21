import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function ComingSoon({ title, description, designReference }) {
    return (
        <AuthenticatedLayout header={title}>
            <Head title={title} />
            <div className="mx-auto max-w-2xl rounded-2xl border border-slate-100 bg-white p-10 text-center shadow-card">
                <span className="material-symbols-outlined mb-4 text-5xl text-primary">construction</span>
                <h2 className="text-headline-md text-on-surface">{title}</h2>
                <p className="mt-3 text-sm text-on-surface-variant">{description}</p>
                {designReference && (
                    <p className="mt-2 text-xs text-on-surface-variant">UI reference: {designReference}</p>
                )}
                <p className="mt-4 rounded-xl bg-surface-container-low px-4 py-3 text-xs text-on-surface-variant">
                    Database tables for this feature are already migrated as Phase 2 stubs.
                </p>
                <Link href={route('dashboard')} className="mt-6 inline-flex rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                    Back to dashboard
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
