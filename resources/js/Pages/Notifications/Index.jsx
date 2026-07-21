import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, router } from '@inertiajs/react';

function openNotification(notification) {
    const url = notification.data?.url;

    const go = () => {
        if (url) {
            window.location.assign(url);
        }
    };

    if (!notification.read_at) {
        router.post(
            route('notifications.read', notification.id),
            {},
            {
                preserveScroll: true,
                onFinish: go,
            },
        );
        return;
    }

    go();
}

export default function NotificationsIndex({ notifications }) {
    return (
        <AuthenticatedLayout header="Notifications">
            <Head title="Notifications" />
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-headline-md">Alert center</h2>
                <button
                    type="button"
                    onClick={() => router.post(route('notifications.read-all'))}
                    className="text-sm font-semibold text-secondary"
                >
                    Mark all read
                </button>
            </div>

            {!notifications.data.length ? (
                <EmptyState
                    icon="notifications"
                    title="You're all caught up"
                    description="Sync failures, transfers, and follow-up alerts will appear here."
                />
            ) : (
                <div className="space-y-3">
                    {notifications.data.map((n) => (
                        <button
                            key={n.id}
                            type="button"
                            onClick={() => openNotification(n)}
                            className={`w-full rounded-2xl border border-slate-100 bg-white p-4 text-left shadow-card transition hover:bg-surface-container-low ${
                                !n.read_at ? 'ring-1 ring-primary/20' : ''
                            }`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-semibold">{n.data?.title || 'Notification'}</p>
                                    <p className="mt-1 text-sm text-on-surface-variant">
                                        {n.data?.body || n.data?.message}
                                    </p>
                                    {n.data?.url && (
                                        <p className="mt-2 text-xs font-semibold text-secondary">Open related action →</p>
                                    )}
                                    <p className="mt-2 text-xs text-on-surface-variant">{formatDateTime(n.created_at)}</p>
                                </div>
                                {!n.read_at && (
                                    <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase text-primary">
                                        New
                                    </span>
                                )}
                            </div>
                        </button>
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
