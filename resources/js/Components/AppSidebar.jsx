import { Link, router, usePage } from '@inertiajs/react';
import OrgSwitcher from './OrgSwitcher';

const volunteerLinks = [
    { href: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    { href: 'donors.index', label: 'Donors', icon: 'groups' },
    { href: 'commissions.mine', label: 'Earnings', icon: 'payments' },
    { href: 'notifications.index', label: 'Alerts', icon: 'notifications' },
];

const adminLinks = [
    { href: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    { href: 'donors.index', label: 'Donors', icon: 'groups' },
    { href: 'assignments.index', label: 'Assignments', icon: 'assignment_ind' },
    { href: 'users.index', label: 'Volunteers', icon: 'volunteer_activism' },
    { href: 'reports.index', label: 'Reports', icon: 'analytics' },
    { href: 'sync.edit', label: 'API Sync', icon: 'sync' },
    { href: 'commissions.settings', label: 'Commissions', icon: 'percent' },
    { href: 'notifications.index', label: 'Alerts', icon: 'notifications' },
];

const superAdminLinks = [
    { href: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    { href: 'organizations.index', label: 'Organizations', icon: 'apartment' },
    { href: 'users.index', label: 'Users', icon: 'manage_accounts' },
    { href: 'donors.index', label: 'Donors', icon: 'groups' },
    { href: 'assignments.index', label: 'Assignments', icon: 'assignment_ind' },
    { href: 'reports.index', label: 'Reports', icon: 'analytics' },
    { href: 'sync.edit', label: 'API Sync', icon: 'sync' },
    { href: 'notifications.index', label: 'Alerts', icon: 'notifications' },
];

function safeRoute(name) {
    try {
        return route(name);
    } catch {
        return '#';
    }
}

export default function AppSidebar({ mobileOpen = false, onClose }) {
    const { auth, currentOrganization } = usePage().props;
    const role = auth.user?.role;

    const links =
        role === 'super_admin'
            ? superAdminLinks
            : role === 'organization_admin'
              ? adminLinks
              : volunteerLinks;

    return (
        <>
            {mobileOpen && (
                <button
                    type="button"
                    aria-label="Close menu"
                    className="fixed inset-0 z-40 bg-black/30 lg:hidden"
                    onClick={onClose}
                />
            )}
            <aside
                className={`fixed left-0 top-0 z-50 flex h-full w-[280px] flex-col border-r border-outline-variant bg-surface-container-lowest py-6 transition-transform lg:translate-x-0 ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="mb-6 px-6">
                    <div className="mb-4 flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-on-primary">
                            <span className="material-symbols-outlined">volunteer_activism</span>
                        </div>
                        <div>
                            <h1 className="text-headline-sm font-bold text-primary">DonorConnect</h1>
                            <p className="text-label-md text-on-surface-variant">CRM</p>
                        </div>
                    </div>
                    <OrgSwitcher />
                    {currentOrganization && (
                        <p className="mt-3 truncate text-xs text-on-surface-variant">
                            Viewing: <span className="font-semibold text-primary">{currentOrganization.name}</span>
                        </p>
                    )}
                </div>

                <nav className="flex-1 space-y-1 px-4">
                    {links.map((link) => {
                        const href = safeRoute(link.href);
                        const active = route().current(link.href) || route().current(`${link.href.split('.')[0]}.*`);
                        return (
                            <Link
                                key={link.href}
                                href={href}
                                onClick={onClose}
                                className={`flex items-center gap-3 rounded-r-lg px-4 py-3 transition ${
                                    active
                                        ? 'border-r-4 border-primary bg-surface-container-low font-bold text-primary'
                                        : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary'
                                }`}
                            >
                                <span className="material-symbols-outlined">{link.icon}</span>
                                <span className="text-label-md">{link.label}</span>
                            </Link>
                        );
                    })}
                </nav>

                <div className="mt-auto space-y-1 border-t border-outline-variant px-4 pt-4">
                    <Link
                        href={route('profile.edit')}
                        onClick={onClose}
                        className="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:bg-surface-container-low"
                    >
                        <span className="material-symbols-outlined">person</span>
                        <span className="text-label-md">Profile</span>
                    </Link>
                    <button
                        type="button"
                        onClick={() => {
                            onClose?.();
                            router.post(route('logout'));
                        }}
                        className="flex w-full items-center gap-3 px-4 py-3 text-left text-error hover:bg-error-container/20"
                    >
                        <span className="material-symbols-outlined">logout</span>
                        <span className="text-label-md">Logout</span>
                    </button>
                </div>
            </aside>
        </>
    );
}
