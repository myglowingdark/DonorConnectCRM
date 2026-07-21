import { Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import OrgSwitcher from './OrgSwitcher';

const volunteerNav = [
    { href: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    { href: 'donors.index', label: 'Donors', icon: 'groups' },
    { href: 'transfers.index', label: 'Transfers', icon: 'swap_horiz' },
    { href: 'commissions.mine', label: 'Earnings', icon: 'payments' },
    { href: 'notifications.index', label: 'Alerts', icon: 'notifications' },
];

const adminNav = [
    { href: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    {
        label: 'Calling',
        icon: 'phone_in_talk',
        children: [
            { href: 'donors.index', label: 'Donors', icon: 'groups' },
            { href: 'assignments.index', label: 'Assignments', icon: 'assignment_ind' },
            { href: 'imports.index', label: 'Import', icon: 'upload_file' },
            { href: 'transfers.index', label: 'Transfers', icon: 'swap_horiz' },
            { href: 'handovers.index', label: 'Handover', icon: 'move_down' },
        ],
    },
    {
        label: 'Team',
        icon: 'group',
        children: [
            { href: 'users.index', label: 'Volunteers', icon: 'volunteer_activism' },
            { href: 'organization.profile', label: 'Org profile', icon: 'apartment' },
        ],
    },
    {
        label: 'Payments',
        icon: 'payments',
        children: [
            { href: 'commissions.settings', label: 'Commissions', icon: 'percent' },
            { href: 'commissions.cycles', label: 'Cycles', icon: 'calendar_month' },
            { href: 'attributions.index', label: 'Attributions', icon: 'verified' },
        ],
    },
    {
        label: 'Messaging',
        icon: 'sms',
        children: [
            { href: 'messaging.settings', label: 'Channels', icon: 'sms' },
            { href: 'email-reports.index', label: 'Email reports', icon: 'forward_to_inbox' },
        ],
    },
    {
        label: 'Insights',
        icon: 'analytics',
        children: [
            { href: 'reports.index', label: 'Reports', icon: 'analytics' },
            { href: 'sync.edit', label: 'API Sync', icon: 'sync' },
        ],
    },
    { href: 'notifications.index', label: 'Alerts', icon: 'notifications' },
];

const superAdminNav = [
    { href: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    {
        label: 'Organizations',
        icon: 'apartment',
        children: [
            { href: 'organizations.index', label: 'All organizations', icon: 'apartment' },
            { href: 'users.index', label: 'Users', icon: 'manage_accounts' },
            { href: 'platform.messaging.edit', label: 'Platform SMTP', icon: 'mail' },
        ],
    },
    {
        label: 'Calling',
        icon: 'phone_in_talk',
        children: [
            { href: 'donors.index', label: 'Donors', icon: 'groups' },
            { href: 'assignments.index', label: 'Assignments', icon: 'assignment_ind' },
            { href: 'imports.index', label: 'Import', icon: 'upload_file' },
            { href: 'transfers.index', label: 'Transfers', icon: 'swap_horiz' },
            { href: 'handovers.index', label: 'Handover', icon: 'move_down' },
        ],
    },
    {
        label: 'Payments',
        icon: 'payments',
        children: [
            { href: 'commissions.settings', label: 'Commissions', icon: 'percent' },
            { href: 'commissions.cycles', label: 'Cycles', icon: 'calendar_month' },
            { href: 'attributions.index', label: 'Attributions', icon: 'verified' },
        ],
    },
    {
        label: 'Messaging',
        icon: 'sms',
        children: [
            { href: 'messaging.settings', label: 'Channels', icon: 'sms' },
            { href: 'email-reports.index', label: 'Email reports', icon: 'forward_to_inbox' },
        ],
    },
    {
        label: 'Insights',
        icon: 'analytics',
        children: [
            { href: 'reports.index', label: 'Reports', icon: 'analytics' },
            { href: 'sync.edit', label: 'API Sync', icon: 'sync' },
        ],
    },
    { href: 'notifications.index', label: 'Alerts', icon: 'notifications' },
];

function safeRoute(name) {
    try {
        return route(name);
    } catch {
        return '#';
    }
}

function isActiveHref(href) {
    try {
        return route().current(href) || route().current(`${href.split('.')[0]}.*`);
    } catch {
        return false;
    }
}

function NavItem({ item, onClose }) {
    if (item.children) {
        const childActive = item.children.some((c) => isActiveHref(c.href));
        const [open, setOpen] = useState(childActive);

        return (
            <div>
                <button
                    type="button"
                    onClick={() => setOpen((v) => !v)}
                    className={`flex w-full items-center gap-3 rounded-r-lg px-4 py-3 text-left transition ${
                        childActive
                            ? 'bg-surface-container-low font-bold text-primary'
                            : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary'
                    }`}
                >
                    <span className="material-symbols-outlined">{item.icon}</span>
                    <span className="flex-1 text-label-md">{item.label}</span>
                    <span className="material-symbols-outlined text-[18px]">{open ? 'expand_less' : 'expand_more'}</span>
                </button>
                {open && (
                    <div className="ml-4 space-y-1 border-l border-outline-variant pl-2">
                        {item.children.map((child) => (
                            <NavItem key={child.href} item={child} onClose={onClose} />
                        ))}
                    </div>
                )}
            </div>
        );
    }

    const href = safeRoute(item.href);
    const active = isActiveHref(item.href);

    return (
        <Link
            href={href}
            onClick={onClose}
            className={`flex items-center gap-3 rounded-r-lg px-4 py-2.5 transition ${
                active
                    ? 'border-r-4 border-primary bg-surface-container-low font-bold text-primary'
                    : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary'
            }`}
        >
            <span className="material-symbols-outlined text-[20px]">{item.icon}</span>
            <span className="text-label-md">{item.label}</span>
        </Link>
    );
}

export default function AppSidebar({ mobileOpen = false, onClose }) {
    const { auth, currentOrganization } = usePage().props;
    const role = auth.user?.role;

    const links = useMemo(() => {
        if (role === 'super_admin') return superAdminNav;
        if (role === 'organization_admin') return adminNav;
        return volunteerNav;
    }, [role]);

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
                <div className="mb-4 shrink-0 px-6">
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

                <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto px-4 pb-2">
                    {links.map((link) => (
                        <NavItem key={link.href || link.label} item={link} onClose={onClose} />
                    ))}
                </nav>

                <div className="mt-auto shrink-0 space-y-1 border-t border-outline-variant px-4 pt-4">
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
