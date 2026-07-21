import AppSidebar from '@/Components/AppSidebar';
import FlashToasts from '@/Components/FlashToasts';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const mobileNav = [
    { href: 'dashboard', label: 'Home', icon: 'home' },
    { href: 'donors.index', label: 'Donors', icon: 'groups' },
    { href: 'donors.index', label: 'Follow-ups', icon: 'event', query: '?needs_call=1' },
    { href: 'commissions.mine', label: 'Earnings', icon: 'payments' },
    { href: 'profile.edit', label: 'Profile', icon: 'person' },
];

export default function AuthenticatedLayout({ header, children }) {
    const { auth, currentOrganization, unreadNotificationsCount = 0 } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <div className="min-h-screen bg-background text-on-surface">
            <FlashToasts />
            <AppSidebar mobileOpen={mobileOpen} onClose={() => setMobileOpen(false)} />

            <div className="lg:pl-[280px]">
                <header className="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-outline-variant bg-white/90 px-4 py-3 backdrop-blur md:px-6">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            className="rounded-lg p-2 hover:bg-surface-container-low lg:hidden"
                            onClick={() => setMobileOpen(true)}
                        >
                            <span className="material-symbols-outlined">menu</span>
                        </button>
                        <div>
                            {header && <div className="text-lg font-semibold text-primary">{header}</div>}
                            {currentOrganization && (
                                <div className="flex items-center gap-2 text-xs text-on-surface-variant">
                                    <span
                                        className="inline-block h-2 w-2 rounded-full"
                                        style={{ backgroundColor: currentOrganization.brand_color }}
                                    />
                                    {currentOrganization.name}
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('notifications.index')}
                            className="relative rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low"
                        >
                            <span className="material-symbols-outlined">notifications</span>
                            {unreadNotificationsCount > 0 && (
                                <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-white">
                                    {unreadNotificationsCount > 99 ? '99+' : unreadNotificationsCount}
                                </span>
                            )}
                        </Link>
                        <div className="hidden text-right sm:block">
                            <p className="text-sm font-semibold">{auth.user?.name}</p>
                            <p className="text-xs text-on-surface-variant">{auth.user?.role_label}</p>
                        </div>
                    </div>
                </header>

                <main className="px-4 py-6 pb-24 md:px-6 lg:pb-8">{children}</main>
            </div>

            <nav className="fixed bottom-0 left-0 right-0 z-40 flex border-t border-outline-variant bg-white lg:hidden">
                {mobileNav.map((item) => (
                    <Link
                        key={item.label}
                        href={`${route(item.href)}${item.query || ''}`}
                        className="flex flex-1 flex-col items-center gap-1 py-2 text-[10px] text-on-surface-variant"
                    >
                        <span className="material-symbols-outlined text-[22px]">{item.icon}</span>
                        {item.label}
                    </Link>
                ))}
            </nav>
        </div>
    );
}
