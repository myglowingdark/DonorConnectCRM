import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { formatDateTime } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';

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
                <EmptyState icon="notifications" title="You're all caught up" description="Sync failures and follow-up alerts will appear here." />
            ) : (
                <div className="space-y-3">
                    {notifications.data.map((n) => (
                        <div
                            key={n.id}
                            className={`rounded-2xl border border-slate-100 bg-white p-4 shadow-card ${!n.read_at ? 'ring-1 ring-primary/20' : ''}`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-semibold">{n.data?.title || 'Notification'}</p>
                                    <p className="mt-1 text-sm text-on-surface-variant">{n.data?.body || n.data?.message}</p>
                                    <p className="mt-2 text-xs text-on-surface-variant">{formatDateTime(n.created_at)}</p>
                                </div>
                                {!n.read_at && (
                                    <Link
                                        href={route('notifications.read', n.id)}
                                        method="post"
                                        as="button"
                                        className="text-xs font-semibold text-primary"
                                    >
                                        Mark read
                                    </Link>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
