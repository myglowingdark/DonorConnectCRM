import { Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import OrgSwitcher from './OrgSwitcher';

const volunteerNav = [
    { href: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    { href: 'dialer.queue', label: 'Call queue', icon: 'queue' },
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
            { href: 'organizations.sync.edit', label: 'WordPress site', icon: 'language', orgParam: true },
            { href: 'onboarding.show', label: 'Onboarding', icon: 'checklist' },
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
            { href: 'campaigns.index', label: 'Campaigns', icon: 'campaign' },
            { href: 'insights.index', label: 'ROI & forecast', icon: 'insights' },
            { href: 'audit.index', label: 'Audit log', icon: 'history' },
        ],
    },
    {
        label: 'Workspace',
        icon: 'settings',
        children: [
            { href: 'billing.index', label: 'Billing', icon: 'receipt_long' },
            { href: 'api-tokens.index', label: 'API keys', icon: 'key', feature: 'api' },
            { href: 'webhooks.index', label: 'Webhooks', icon: 'webhook', feature: 'webhooks' },
            { href: 'capacity.index', label: 'Capacity', icon: 'event_seat', feature: 'capacity_booking' },
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
            { href: 'organizations.sync.edit', label: 'WordPress site', icon: 'language', orgParam: true },
            { href: 'users.index', label: 'Users', icon: 'manage_accounts' },
            { href: 'margin.index', label: 'Margin dashboard', icon: 'account_balance' },
            { href: 'idle-pool.index', label: 'Idle telecallers', icon: 'groups_3' },
        ],
    },
    {
        label: 'Calling',
        icon: 'phone_in_talk',
        requiresOrg: true,
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
        requiresOrg: true,
        children: [
            { href: 'commissions.settings', label: 'Commissions', icon: 'percent' },
            { href: 'commissions.cycles', label: 'Cycles', icon: 'calendar_month' },
            { href: 'attributions.index', label: 'Attributions', icon: 'verified' },
        ],
    },
    {
        label: 'Messaging',
        icon: 'sms',
        requiresOrg: true,
        children: [
            { href: 'messaging.settings', label: 'Channels', icon: 'sms' },
            { href: 'email-reports.index', label: 'Email reports', icon: 'forward_to_inbox' },
        ],
    },
    {
        label: 'Insights',
        icon: 'analytics',
        requiresOrg: true,
        children: [
            { href: 'reports.index', label: 'Reports', icon: 'analytics' },
            { href: 'campaigns.index', label: 'Campaigns', icon: 'campaign' },
            { href: 'insights.index', label: 'ROI & forecast', icon: 'insights' },
            { href: 'audit.index', label: 'Audit log', icon: 'history' },
        ],
    },
    {
        label: 'Workspace',
        icon: 'settings',
        requiresOrg: true,
        children: [
            { href: 'billing.index', label: 'Billing', icon: 'receipt_long' },
            { href: 'api-tokens.index', label: 'API keys', icon: 'key', feature: 'api' },
            { href: 'webhooks.index', label: 'Webhooks', icon: 'webhook', feature: 'webhooks' },
            { href: 'capacity.index', label: 'Capacity', icon: 'event_seat', feature: 'capacity_booking' },
        ],
    },
    { href: 'notifications.index', label: 'Alerts', icon: 'notifications' },
];

function filterNavByFeatures(items, features = [], currentOrganization = null) {
    return items
        .map((item) => {
            if (item.requiresOrg && !currentOrganization?.id) {
                return null;
            }
            if (item.children) {
                const children = filterNavByFeatures(item.children, features, currentOrganization);
                if (children.length === 0) return null;
                return { ...item, children };
            }
            if (item.orgParam && !currentOrganization?.id) return null;
            if (item.feature && !features.includes(item.feature)) return null;
            return item;
        })
        .filter(Boolean);
}

function safeRoute(name, orgId = null) {
    try {
        if (orgId) {
            return route(name, orgId);
        }
        return route(name);
    } catch {
        return '#';
    }
}

function isActiveHref(href) {
    try {
        if (href === 'organizations.sync.edit') {
            return route().current('organizations.sync.*') || route().current('sync.*');
        }
        if (href === 'site-settings.index') {
            return route().current('site-settings.*') || route().current('platform.messaging.*');
        }
        return route().current(href) || route().current(`${href.split('.')[0]}.*`);
    } catch {
        return false;
    }
}

function NavItem({ item, onClose, currentOrganization }) {
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
                            <NavItem
                                key={child.href + (child.label || '')}
                                item={child}
                                onClose={onClose}
                                currentOrganization={currentOrganization}
                            />
                        ))}
                    </div>
                )}
            </div>
        );
    }

    const href = item.orgParam
        ? safeRoute(item.href, currentOrganization?.id)
        : safeRoute(item.href);
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
    const { auth, currentOrganization, features = [], impersonating } = usePage().props;
    const role = auth.user?.role;

    const links = useMemo(() => {
        let nav = volunteerNav;
        if (role === 'super_admin') nav = superAdminNav;
        else if (role === 'organization_admin') nav = adminNav;
        return filterNavByFeatures(nav, features, currentOrganization);
    }, [role, features, currentOrganization]);

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
                    {impersonating && (
                        <div className="mt-2 rounded-lg bg-amber-100 px-3 py-2 text-xs text-amber-900">
                            Impersonation active
                        </div>
                    )}
                </div>

                <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto px-4 pb-2">
                    {links.map((link) => (
                        <NavItem
                            key={link.href || link.label}
                            item={link}
                            onClose={onClose}
                            currentOrganization={currentOrganization}
                        />
                    ))}
                </nav>

                <div className="mt-auto shrink-0 space-y-1 border-t border-outline-variant px-4 pt-4">
                    {role === 'super_admin' && (
                        <Link
                            href={safeRoute('site-settings.index')}
                            onClick={onClose}
                            className={`flex items-center gap-3 px-4 py-3 hover:bg-surface-container-low ${
                                isActiveHref('site-settings.index')
                                    ? 'font-bold text-primary'
                                    : 'text-on-surface-variant'
                            }`}
                        >
                            <span className="material-symbols-outlined">tune</span>
                            <span className="text-label-md">Site Settings</span>
                        </Link>
                    )}
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
