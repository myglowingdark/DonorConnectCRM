import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { formatDate, formatDateTime, formatINR } from '@/lib/format';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const priorityMeta = {
    overdue: { label: 'Overdue follow-up', className: 'bg-rose-100 text-rose-800' },
    due_today: { label: 'Follow-up due today', className: 'bg-amber-100 text-amber-800' },
    upcoming: { label: 'Upcoming follow-up', className: 'bg-sky-100 text-sky-800' },
    new: { label: 'Never contacted', className: 'bg-teal-100 text-teal-800' },
    cold: { label: 'Longest waiting', className: 'bg-indigo-100 text-indigo-800' },
    later: { label: 'Later', className: 'bg-slate-100 text-slate-700' },
    do_not_call: { label: 'Do not call', className: 'bg-rose-100 text-rose-800' },
};

export default function DonorsIndex({
    donors,
    filters = {},
    nextToCall = null,
    queueStats = {},
    isVolunteer = false,
    campaigns = [],
    volunteers = [],
    availableTags = [],
}) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');
    const [showAdvanced, setShowAdvanced] = useState(false);
    const [advanced, setAdvanced] = useState({
        min_amount: filters.min_amount || '',
        max_amount: filters.max_amount || '',
        donated_after: filters.donated_after || '',
        donated_before: filters.donated_before || '',
        last_called_after: filters.last_called_after || '',
        last_called_before: filters.last_called_before || '',
        last_called_by: filters.last_called_by || '',
        campaign_id: filters.campaign_id || '',
        tag: filters.tag || '',
        was_transferred: filters.was_transferred || '',
    });
    const volunteerView = isVolunteer || auth.user?.role === 'volunteer';

    const applyFilters = (next = {}) => {
        const payload = { ...filters, search, ...next };

        // Clear empty / undefined filter keys
        Object.keys(payload).forEach((key) => {
            if (payload[key] === undefined || payload[key] === null || payload[key] === '') {
                delete payload[key];
            }
        });

        router.get(route('donors.index'), payload, { preserveState: true, replace: true });
    };

    const applyAdvanced = () => {
        setShowAdvanced(false);
        applyFilters({
            needs_call: undefined,
            ...advanced,
            was_transferred: advanced.was_transferred ? 1 : undefined,
        });
    };

    const clearAdvanced = () => {
        setAdvanced({
            min_amount: '',
            max_amount: '',
            donated_after: '',
            donated_before: '',
            last_called_after: '',
            last_called_before: '',
            last_called_by: '',
            campaign_id: '',
            tag: '',
            was_transferred: '',
        });
        setShowAdvanced(false);
        applyFilters({
            min_amount: undefined,
            max_amount: undefined,
            donated_after: undefined,
            donated_before: undefined,
            last_called_after: undefined,
            last_called_before: undefined,
            last_called_by: undefined,
            campaign_id: undefined,
            tag: undefined,
            was_transferred: undefined,
        });
    };

    const hasAdvanced = !!(
        filters.min_amount ||
        filters.max_amount ||
        filters.donated_after ||
        filters.donated_before ||
        filters.last_called_after ||
        filters.last_called_before ||
        filters.last_called_by ||
        filters.campaign_id ||
        filters.tag ||
        filters.was_transferred
    );

    const toggle = (key) => {
        const turningOn = !filters[key];
        const next = {
            needs_call: undefined,
            assigned_to_me: undefined,
            uncontacted: undefined,
            follow_up_due: undefined,
            interested: undefined,
            do_not_call: undefined,
        };

        if (turningOn) {
            next[key] = 1;
        }

        // "All my donors" clears queue filters
        if (key === 'assigned_to_me' && turningOn) {
            next.assigned_to_me = volunteerView ? undefined : 1;
        }

        applyFilters(next);
    };

    const chips = [
        { key: 'needs_call', label: `Needs call${queueStats.needs_call != null ? ` (${queueStats.needs_call})` : ''}` },
        { key: 'follow_up_due', label: `Follow-up due${queueStats.overdue != null || queueStats.due_today != null ? ` (${(queueStats.overdue || 0) + (queueStats.due_today || 0)})` : ''}` },
        { key: 'uncontacted', label: `Uncontacted${queueStats.uncontacted != null ? ` (${queueStats.uncontacted})` : ''}` },
        { key: 'interested', label: 'Interested' },
        { key: 'do_not_call', label: 'Do not call' },
        ...(!volunteerView ? [{ key: 'assigned_to_me', label: 'My donors' }] : [{ key: 'assigned_to_me', label: 'All my donors' }]),
    ];

    const showAllMine = volunteerView && !filters.needs_call && !filters.follow_up_due && !filters.uncontacted && !filters.interested && !filters.do_not_call;

    return (
        <AuthenticatedLayout header="Calling Queue">
            <Head title="Donors" />

            <div className="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-headline-md">Who to call next</h2>
                    <p className="text-sm text-on-surface-variant">
                        Scheduled follow-ups first. If none, donors contacted longest ago.
                    </p>
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters({ needs_call: undefined });
                    }}
                    className="flex w-full max-w-md gap-2"
                >
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name, phone, email"
                        className="w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                    />
                    <button type="submit" className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                        Search
                    </button>
                </form>
            </div>

            {nextToCall && !nextToCall.do_not_call && (
                <div className="mb-6 rounded-2xl border border-primary/20 bg-gradient-to-r from-primary to-primary-container p-5 text-white shadow-card">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-sm font-medium text-primary-fixed">Next interaction</p>
                            <h3 className="mt-1 text-2xl font-bold">{nextToCall.full_name}</h3>
                            <p className="mt-1 text-primary-fixed">{nextToCall.phone || 'No phone on file'}</p>
                            <p className="mt-2 text-sm">
                                {nextToCall.next_follow_up_at
                                    ? `Follow-up: ${formatDateTime(nextToCall.next_follow_up_at)}`
                                    : nextToCall.last_contacted_at
                                      ? `No follow-up · last called ${formatDate(nextToCall.last_contacted_at)}`
                                      : 'Never contacted — good first call'}
                                {' · '}
                                Last gift {formatINR(nextToCall.last_donation_amount)}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {nextToCall.phone && (
                                <a
                                    href={`tel:${nextToCall.phone}`}
                                    className="rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Call now
                                </a>
                            )}
                            <Link
                                href={route('donors.show', nextToCall.id)}
                                className="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary"
                            >
                                Open & log outcome
                            </Link>
                        </div>
                    </div>
                </div>
            )}

            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-3">
                    <p className="text-xs font-semibold uppercase text-rose-700">Overdue</p>
                    <p className="text-2xl font-bold text-rose-800">{queueStats.overdue ?? 0}</p>
                </div>
                <div className="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                    <p className="text-xs font-semibold uppercase text-amber-700">Due today</p>
                    <p className="text-2xl font-bold text-amber-800">{queueStats.due_today ?? 0}</p>
                </div>
                <div className="rounded-xl border border-teal-100 bg-teal-50 px-4 py-3">
                    <p className="text-xs font-semibold uppercase text-teal-700">Uncontacted</p>
                    <p className="text-2xl font-bold text-teal-800">{queueStats.uncontacted ?? 0}</p>
                </div>
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                {chips.map((chip) => {
                    const active =
                        chip.key === 'assigned_to_me'
                            ? showAllMine || !!filters.assigned_to_me
                            : !!filters[chip.key];
                    return (
                        <button
                            key={chip.key}
                            type="button"
                            onClick={() => {
                                if (chip.key === 'assigned_to_me' && volunteerView) {
                                    applyFilters({
                                        needs_call: undefined,
                                        follow_up_due: undefined,
                                        uncontacted: undefined,
                                        interested: undefined,
                                        do_not_call: undefined,
                                        assigned_to_me: undefined,
                                    });
                                    return;
                                }
                                toggle(chip.key);
                            }}
                            className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
                                active ? 'bg-primary text-white' : 'bg-surface-container text-on-surface-variant'
                            }`}
                        >
                            {chip.label}
                        </button>
                    );
                })}
                <button
                    type="button"
                    onClick={() => setShowAdvanced(true)}
                    className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
                        hasAdvanced ? 'bg-secondary text-white' : 'border border-outline-variant text-on-surface-variant'
                    }`}
                >
                    Advanced filters
                </button>
            </div>

            {showAdvanced && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold">Advanced donor filters</h3>
                            <button type="button" onClick={() => setShowAdvanced(false)} className="text-sm">
                                Close
                            </button>
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <label className="text-xs font-semibold">Donation ≥</label>
                                <input
                                    type="number"
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.min_amount}
                                    onChange={(e) => setAdvanced({ ...advanced, min_amount: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Donation ≤</label>
                                <input
                                    type="number"
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.max_amount}
                                    onChange={(e) => setAdvanced({ ...advanced, max_amount: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Donated after</label>
                                <input
                                    type="date"
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.donated_after}
                                    onChange={(e) => setAdvanced({ ...advanced, donated_after: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Donated before</label>
                                <input
                                    type="date"
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.donated_before}
                                    onChange={(e) => setAdvanced({ ...advanced, donated_before: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Last called after</label>
                                <input
                                    type="date"
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.last_called_after}
                                    onChange={(e) => setAdvanced({ ...advanced, last_called_after: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Last called before</label>
                                <input
                                    type="date"
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.last_called_before}
                                    onChange={(e) => setAdvanced({ ...advanced, last_called_before: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Last called by</label>
                                <select
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.last_called_by}
                                    onChange={(e) => setAdvanced({ ...advanced, last_called_by: e.target.value })}
                                >
                                    <option value="">Any</option>
                                    {volunteers.map((v) => (
                                        <option key={v.id} value={v.id}>
                                            {v.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Campaign / project</label>
                                <select
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.campaign_id}
                                    onChange={(e) => setAdvanced({ ...advanced, campaign_id: e.target.value })}
                                >
                                    <option value="">Any</option>
                                    {campaigns.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs font-semibold">Tag</label>
                                <select
                                    className="mt-1 w-full rounded-xl border-slate-200"
                                    value={advanced.tag}
                                    onChange={(e) => setAdvanced({ ...advanced, tag: e.target.value })}
                                >
                                    <option value="">Any</option>
                                    {availableTags.map((tag) => (
                                        <option key={tag} value={tag}>
                                            {tag}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <label className="flex items-end gap-2 pb-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={!!advanced.was_transferred}
                                    onChange={(e) =>
                                        setAdvanced({ ...advanced, was_transferred: e.target.checked ? 1 : '' })
                                    }
                                />
                                Transferred only
                            </label>
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <button type="button" onClick={clearAdvanced} className="rounded-xl px-4 py-2 text-sm">
                                Clear
                            </button>
                            <button
                                type="button"
                                onClick={applyAdvanced}
                                className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                            >
                                Apply filters
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
                {donors.data.length === 0 ? (
                    <EmptyState
                        icon="phone_in_talk"
                        title="No one in this queue"
                        description="Try “All my donors”, or clear filters. Ask an admin if you need more assignments."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                                <tr>
                                    <th className="px-4 py-3">Priority</th>
                                    <th className="px-4 py-3">Donor</th>
                                    <th className="px-4 py-3">Phone</th>
                                    <th className="px-4 py-3">Last donation</th>
                                    <th className="px-4 py-3">Next interaction</th>
                                    <th className="px-4 py-3">Status</th>
                                    {!volunteerView && <th className="px-4 py-3">Volunteer</th>}
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {donors.data.map((donor) => {
                                    const priority = donor.call_priority || 'later';
                                    const meta = priorityMeta[priority] || priorityMeta.later;
                                    const overdue = priority === 'overdue';
                                    const dueToday = priority === 'due_today';

                                    return (
                                        <tr
                                            key={donor.id}
                                            className={`border-t border-slate-100 hover:bg-slate-50 ${
                                                overdue ? 'bg-rose-50/70' : dueToday ? 'bg-amber-50/60' : ''
                                            }`}
                                        >
                                            <td className="px-4 py-3">
                                                <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${meta.className}`}>
                                                    {meta.label}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 font-medium">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span>{donor.full_name}</span>
                                                    {donor.was_transferred && (
                                                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-800">
                                                            Transferred
                                                        </span>
                                                    )}
                                                    {(donor.tags || []).map((tag) => (
                                                        <span
                                                            key={tag}
                                                            className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700"
                                                        >
                                                            {tag}
                                                        </span>
                                                    ))}
                                                </div>
                                                {donor.preferred_language && (
                                                    <div className="mt-0.5 text-xs text-on-surface-variant">
                                                        Lang: {donor.preferred_language}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {donor.phone ? (
                                                    <a href={`tel:${donor.phone}`} className="font-medium text-secondary hover:underline">
                                                        {donor.phone}
                                                    </a>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatINR(donor.last_donation_amount)}
                                                <div className="text-xs text-on-surface-variant">
                                                    {formatDate(donor.last_donation_at)}
                                                </div>
                                            </td>
                                            <td className={`px-4 py-3 ${overdue ? 'font-semibold text-rose-700' : dueToday ? 'font-semibold text-amber-700' : ''}`}>
                                                {donor.next_follow_up_at
                                                    ? formatDateTime(donor.next_follow_up_at)
                                                    : donor.last_contacted_at
                                                      ? `Last called ${formatDate(donor.last_contacted_at)}`
                                                      : 'Never contacted'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={donor.donor_status}
                                                    label={
                                                        donor.donor_status?.replaceAll?.('_', ' ') ||
                                                        donor.donor_status
                                                    }
                                                />
                                            </td>
                                            {!volunteerView && (
                                                <td className="px-4 py-3">
                                                    {donor.active_assignment?.volunteer?.name || '—'}
                                                </td>
                                            )}
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    {donor.phone && !donor.do_not_call && (
                                                        <a
                                                            href={`tel:${donor.phone}`}
                                                            className="rounded-lg border border-secondary/30 px-3 py-1.5 text-xs font-semibold text-secondary"
                                                        >
                                                            Call
                                                        </a>
                                                    )}
                                                    <Link
                                                        href={route('donors.show', donor.id)}
                                                        className="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white"
                                                    >
                                                        Open
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {donors.links?.length > 3 && (
                    <div className="flex flex-wrap gap-2 border-t border-slate-100 p-4">
                        {donors.links.map((link, idx) => (
                            <Link
                                key={idx}
                                href={link.url || '#'}
                                className={`rounded-lg px-3 py-1 text-xs ${
                                    link.active ? 'bg-primary text-white' : 'bg-surface-container'
                                } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
