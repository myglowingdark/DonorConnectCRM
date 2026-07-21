import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, router } from '@inertiajs/react';

function resolveVisitUrl(url) {
    if (!url) return null;

    try {
        const parsed = new URL(url, window.location.origin);
        if (parsed.origin === window.location.origin) {
            return parsed.pathname + parsed.search + parsed.hash;
        }
        return url;
    } catch {
        return url;
    }
}

function openNotification(notification) {
    const rawUrl = notification.data?.url;
    const url = resolveVisitUrl(rawUrl);

    const go = () => {
        if (!url) return;
        if (url.startsWith('http://') || url.startsWith('https://')) {
            window.location.assign(url);
            return;
        }
        router.visit(url);
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
                                    <p className="mt-1 text-sm text-on-surface-variant">{n.data?.body}</p>
                                </div>
                                {!n.read_at && <span className="mt-1 h-2 w-2 rounded-full bg-primary" />}
                            </div>
                            <p className="mt-2 text-xs text-on-surface-variant">{formatDateTime(n.created_at)}</p>
                        </button>
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
