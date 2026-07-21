import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function OrgSwitcher() {
    const { auth, currentOrganization, organizations = [] } = usePage().props;
    const [open, setOpen] = useState(false);
    const isSuperAdmin = auth?.user?.role === 'super_admin';

    if (!currentOrganization) {
        if (!isSuperAdmin) {
            return null;
        }

        return (
            <div className="rounded-xl border border-dashed border-outline-variant bg-surface-container-low p-3">
                <p className="text-xs font-semibold text-on-surface">No organization yet</p>
                <p className="mt-1 text-[11px] text-on-surface-variant">
                    Create an organization to start calling and syncing donors.
                </p>
                <Link
                    href={route('organizations.create')}
                    className="mt-2 inline-flex text-xs font-semibold text-secondary"
                >
                    Create organization →
                </Link>
            </div>
        );
    }

    const switchTo = (organizationId) => {
        router.post(
            route('organization.switch'),
            { organization_id: organizationId },
            {
                preserveScroll: true,
                onFinish: () => setOpen(false),
            },
        );
    };

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center gap-3 rounded-xl bg-surface-container-low p-3 text-left transition hover:bg-surface-container"
            >
                <div
                    className="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold text-white"
                    style={{ backgroundColor: currentOrganization.brand_color || '#1e3a8a' }}
                >
                    {currentOrganization.initials}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-label-md font-bold text-on-surface">
                        {currentOrganization.name}
                    </p>
                    <p className="text-xs text-on-surface-variant">Organization</p>
                </div>
                <span
                    className="h-2.5 w-2.5 rounded-full"
                    style={{ backgroundColor: currentOrganization.brand_color || '#14b8a6' }}
                />
                <span className="material-symbols-outlined text-outline">unfold_more</span>
            </button>

            {open && (
                <div className="absolute left-0 right-0 z-50 mt-2 overflow-hidden rounded-xl border border-outline-variant bg-white shadow-elevated">
                    {organizations.map((org) => (
                        <button
                            key={org.id}
                            type="button"
                            disabled={org.id === currentOrganization.id}
                            onClick={() => switchTo(org.id)}
                            className="flex w-full items-center gap-3 px-3 py-3 text-left hover:bg-surface-container-low disabled:opacity-50"
                        >
                            <div
                                className="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold text-white"
                                style={{ backgroundColor: org.brand_color }}
                            >
                                {org.initials}
                            </div>
                            <span className="truncate text-sm font-medium">{org.name}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
