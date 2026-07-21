import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusBadge from '@/Components/StatusBadge';
import { formatDate, formatDateTime, formatINR } from '@/lib/format';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function DonorShow({
    donor,
    timeline = [],
    campaigns,
    outcomes,
    nextDonorId,
    languages = [],
    transferVolunteers = [],
    canTransfer = false,
    messagingChannels = [],
    messageTemplates = [],
}) {
    const { auth } = usePage().props;
    const [showTransfer, setShowTransfer] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        outcome: 'interested',
        notes: '',
        follow_up_at: '',
        pledged_amount: '',
        campaign_id: '',
        preferred_language: donor.preferred_language || '',
        attribute_donation: false,
        go_next: false,
    });

    const transferForm = useForm({
        to_volunteer_id: '',
        reason: '',
    });

    const messageForm = useForm({
        channel: messagingChannels[0]?.value || 'email',
        message_template_id: '',
        subject: '',
        body: '',
    });

    const applyTemplate = (templateId) => {
        const template = messageTemplates.find((t) => String(t.id) === String(templateId));
        messageForm.setData((current) => ({
            ...current,
            message_template_id: templateId,
            channel: template?.channel || current.channel,
            subject: template?.subject || '',
            body: template?.body || '',
        }));
    };

    const submitMessage = (e) => {
        e.preventDefault();
        messageForm.post(route('donors.messages.send', donor.id), {
            preserveScroll: true,
            onSuccess: () => messageForm.setData('body', ''),
        });
    };

    const submit = (goNext = false) => {
        transform((formData) => ({
            ...formData,
            go_next: goNext,
        }));
        post(route('donors.log-call', donor.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (!goNext) reset('notes', 'follow_up_at', 'pledged_amount');
            },
        });
    };

    const submitTransfer = (e) => {
        e.preventDefault();
        transferForm.post(route('transfers.store', donor.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowTransfer(false);
                transferForm.reset();
            },
        });
    };

    const languageLabel =
        languages.find((l) => l.value === donor.preferred_language)?.label || donor.preferred_language;

    return (
        <AuthenticatedLayout header="Donor Profile">
            <Head title={donor.full_name} />

            {donor.do_not_call && (
                <div className="mb-4 flex items-center justify-between gap-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">
                    <div className="flex items-center gap-2">
                        <span className="material-symbols-outlined">do_not_disturb_on</span>
                        <span className="font-semibold">Do Not Call — new calls are blocked.</span>
                    </div>
                    {['super_admin', 'organization_admin'].includes(auth.user?.role) && (
                        <Link
                            href={route('donors.clear-dnc', donor.id)}
                            method="post"
                            as="button"
                            className="rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white"
                        >
                            Remove restriction
                        </Link>
                    )}
                </div>
            )}

            <div className="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <h2 className="text-headline-md">{donor.full_name}</h2>
                            <StatusBadge status={donor.donor_status} label={String(donor.donor_status).replaceAll('_', ' ')} />
                            {donor.was_transferred && (
                                <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                    Transferred
                                </span>
                            )}
                            {donor.pending_transfer && (
                                <span className="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800">
                                    Transfer pending → {donor.pending_transfer.to_volunteer?.name}
                                </span>
                            )}
                            <span
                                className="rounded-full px-2.5 py-1 text-xs font-semibold text-white"
                                style={{ backgroundColor: donor.organization?.brand_color || '#1e3a8a' }}
                            >
                                {donor.organization?.name}
                            </span>
                        </div>
                        <p className="text-sm text-on-surface-variant">
                            {donor.phone || 'No phone'} · {donor.email || 'No email'}
                        </p>
                        <p className="mt-1 text-sm text-on-surface-variant">
                            {[donor.city, donor.state, donor.country].filter(Boolean).join(', ') || 'Address not set'}
                            {languageLabel ? ` · Prefers ${languageLabel}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {donor.phone && !donor.do_not_call && (
                            <a
                                href={`tel:${donor.phone}`}
                                className="inline-flex items-center gap-2 rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white"
                            >
                                <span className="material-symbols-outlined text-[18px]">call</span>
                                Call
                            </a>
                        )}
                        {canTransfer && !donor.pending_transfer && (
                            <button
                                type="button"
                                onClick={() => setShowTransfer(true)}
                                className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                            >
                                Transfer
                            </button>
                        )}
                        <Link
                            href={route('donors.index')}
                            className="rounded-xl border border-outline-variant px-4 py-2 text-sm font-semibold"
                        >
                            Back to queue
                        </Link>
                    </div>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl bg-surface-container-low p-4">
                        <p className="text-xs text-on-surface-variant">Last donation</p>
                        <p className="text-lg font-bold tabular-nums">{formatINR(donor.last_donation_amount)}</p>
                        <p className="text-xs text-on-surface-variant">{formatDate(donor.last_donation_at)}</p>
                    </div>
                    <div className="rounded-xl bg-surface-container-low p-4">
                        <p className="text-xs text-on-surface-variant">Total donated</p>
                        <p className="text-lg font-bold tabular-nums">{formatINR(donor.total_donated)}</p>
                    </div>
                    <div className="rounded-xl bg-surface-container-low p-4">
                        <p className="text-xs text-on-surface-variant">Donations</p>
                        <p className="text-lg font-bold">{donor.donations?.length || 0}</p>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-1 font-semibold">Send message</h3>
                        <p className="mb-4 text-xs text-on-surface-variant">
                            Email, WhatsApp, or short SMS from this organization only.
                        </p>
                        {!messagingChannels.length ? (
                            <p className="text-sm text-on-surface-variant">
                                No messaging channels enabled. Ask an admin to configure Messaging.
                            </p>
                        ) : (
                            <form onSubmit={submitMessage} className="space-y-3">
                                <div className="flex flex-wrap gap-2">
                                    {messagingChannels.map((channel) => (
                                        <button
                                            key={channel.value}
                                            type="button"
                                            onClick={() => messageForm.setData('channel', channel.value)}
                                            className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                messageForm.data.channel === channel.value
                                                    ? 'bg-secondary text-white'
                                                    : 'bg-surface-container'
                                            }`}
                                        >
                                            {channel.label}
                                        </button>
                                    ))}
                                </div>
                                <select
                                    className="w-full rounded-xl border-slate-200"
                                    value={messageForm.data.message_template_id}
                                    onChange={(e) => applyTemplate(e.target.value)}
                                >
                                    <option value="">Blank / custom message</option>
                                    {messageTemplates
                                        .filter((t) => t.channel === messageForm.data.channel)
                                        .map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name}
                                            </option>
                                        ))}
                                </select>
                                {messageForm.data.channel === 'email' && (
                                    <input
                                        className="w-full rounded-xl border-slate-200"
                                        placeholder="Subject"
                                        value={messageForm.data.subject}
                                        onChange={(e) => messageForm.setData('subject', e.target.value)}
                                    />
                                )}
                                <textarea
                                    className="w-full rounded-xl border-slate-200"
                                    rows={4}
                                    placeholder="Message body"
                                    value={messageForm.data.body}
                                    onChange={(e) => messageForm.setData('body', e.target.value)}
                                    required
                                />
                                {Object.values(messageForm.errors).map((err) => (
                                    <p key={err} className="text-xs text-error">
                                        {err}
                                    </p>
                                ))}
                                <button
                                    type="submit"
                                    disabled={messageForm.processing}
                                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Send {messagingChannels.find((c) => c.value === messageForm.data.channel)?.label || 'message'}
                                </button>
                            </form>
                        )}
                    </section>

                    <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-4 font-semibold">Donation history</h3>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="text-left text-xs uppercase text-on-surface-variant">
                                    <tr>
                                        <th className="py-2">Date</th>
                                        <th className="py-2">Amount</th>
                                        <th className="py-2">Status</th>
                                        <th className="py-2">Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(donor.donations || []).map((d) => (
                                        <tr key={d.id} className="border-t border-slate-100">
                                            <td className="py-2">{formatDate(d.donated_at)}</td>
                                            <td className="py-2 tabular-nums">{formatINR(d.amount)}</td>
                                            <td className="py-2">{d.payment_status}</td>
                                            <td className="py-2">{d.payment_method || '—'}</td>
                                        </tr>
                                    ))}
                                    {!donor.donations?.length && (
                                        <tr>
                                            <td colSpan={4} className="py-6 text-on-surface-variant">
                                                No donations synced yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
                        <h3 className="mb-4 font-semibold">Interaction timeline</h3>
                        <ul className="space-y-4 border-l border-outline-variant pl-4">
                            {timeline.map((item) => (
                                <li key={item.id} className="relative">
                                    <span
                                        className={`absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full ${
                                            item.type === 'transfer' ? 'bg-amber-500' : 'bg-primary'
                                        }`}
                                    />
                                    {item.type === 'transfer' ? (
                                        <>
                                            <p className="text-sm font-semibold capitalize">
                                                {item.title}
                                                {item.actor ? ` · ${item.actor}` : ''}
                                            </p>
                                            <p className="text-xs text-on-surface-variant">
                                                {formatDateTime(item.at)}
                                            </p>
                                            <p className="mt-1 text-sm">
                                                {item.from} → {item.to}
                                                {item.status ? (
                                                    <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-800">
                                                        {String(item.status).replaceAll('_', ' ')}
                                                    </span>
                                                ) : null}
                                            </p>
                                            {item.reason && (
                                                <p className="mt-1 text-sm text-on-surface-variant">
                                                    Reason: {item.reason}
                                                </p>
                                            )}
                                            {item.response_note && (
                                                <p className="mt-1 text-sm text-on-surface-variant">
                                                    Note: {item.response_note}
                                                </p>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            <p className="text-sm font-semibold capitalize">
                                                {item.title}
                                                {item.actor ? ` · ${item.actor}` : ''}
                                            </p>
                                            <p className="text-xs text-on-surface-variant">
                                                {formatDateTime(item.at)}
                                            </p>
                                            {item.notes && <p className="mt-1 text-sm">{item.notes}</p>}
                                            {item.follow_up_at && (
                                                <p className="mt-1 text-xs text-amber-700">
                                                    Follow-up: {formatDateTime(item.follow_up_at)}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </li>
                            ))}
                            {!timeline.length && (
                                <li className="text-sm text-on-surface-variant">
                                    No interactions or transfers yet.
                                </li>
                            )}
                        </ul>
                    </section>
                </div>

                <aside className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card xl:sticky xl:top-24 xl:self-start">
                    <h3 className="mb-1 font-semibold">Log call</h3>
                    <p className="mb-4 text-xs text-on-surface-variant">Fast entry after your external phone call.</p>

                    <fieldset disabled={donor.do_not_call || processing} className="space-y-4">
                        <div className="grid grid-cols-2 gap-2">
                            {outcomes.map((outcome) => (
                                <button
                                    key={outcome.value}
                                    type="button"
                                    onClick={() => setData('outcome', outcome.value)}
                                    className={`rounded-xl border px-2 py-3 text-left text-xs ${
                                        data.outcome === outcome.value
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : 'border-slate-200'
                                    }`}
                                >
                                    <span className="material-symbols-outlined mb-1 block text-[18px]">
                                        {outcome.icon}
                                    </span>
                                    {outcome.label}
                                </button>
                            ))}
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Preferred language</label>
                            <select
                                value={data.preferred_language}
                                onChange={(e) => setData('preferred_language', e.target.value)}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            >
                                <option value="">Unknown / not set</option>
                                {languages.map((lang) => (
                                    <option key={lang.value} value={lang.value}>
                                        {lang.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Notes</label>
                            <textarea
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                rows={4}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            />
                            {errors.notes && <p className="mt-1 text-xs text-error">{errors.notes}</p>}
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Follow-up</label>
                            <input
                                type="datetime-local"
                                value={data.follow_up_at}
                                onChange={(e) => setData('follow_up_at', e.target.value)}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            />
                            {errors.follow_up_at && <p className="mt-1 text-xs text-error">{errors.follow_up_at}</p>}
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Pledged amount (₹)</label>
                            <input
                                type="number"
                                min="0"
                                value={data.pledged_amount}
                                onChange={(e) => setData('pledged_amount', e.target.value)}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-on-surface-variant">Campaign</label>
                            <select
                                value={data.campaign_id}
                                onChange={(e) => setData('campaign_id', e.target.value)}
                                className="mt-1 w-full rounded-xl border-slate-200 focus:border-secondary focus:ring-secondary"
                            >
                                <option value="">None</option>
                                {campaigns.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.attribute_donation}
                                onChange={(e) => setData('attribute_donation', e.target.checked)}
                            />
                            Attribute future donation to me (Phase 2)
                        </label>

                        {errors.outcome && <p className="text-xs text-error">{errors.outcome}</p>}

                        <div className="flex flex-col gap-2">
                            <button
                                type="button"
                                onClick={() => submit(false)}
                                className="rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                Save outcome
                            </button>
                            <button
                                type="button"
                                onClick={() => submit(true)}
                                disabled={!nextDonorId}
                                className="rounded-xl bg-secondary px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                Save + Next Donor
                            </button>
                        </div>
                    </fieldset>
                </aside>
            </div>

            {showTransfer && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated">
                        <h3 className="text-lg font-semibold">Transfer donor</h3>
                        <p className="mt-1 text-sm text-on-surface-variant">
                            The receiving volunteer must accept before this donor leaves your queue.
                        </p>
                        <form onSubmit={submitTransfer} className="mt-4 space-y-3">
                            <select
                                className="w-full rounded-xl border-slate-200"
                                value={transferForm.data.to_volunteer_id}
                                onChange={(e) => transferForm.setData('to_volunteer_id', e.target.value)}
                                required
                            >
                                <option value="">Select volunteer</option>
                                {transferVolunteers.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                        {(v.languages || []).length
                                            ? ` (${(v.languages || []).join(', ')})`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            <textarea
                                className="w-full rounded-xl border-slate-200"
                                rows={3}
                                placeholder="Why transfer? (optional)"
                                value={transferForm.data.reason}
                                onChange={(e) => transferForm.setData('reason', e.target.value)}
                            />
                            {Object.values(transferForm.errors).map((err) => (
                                <p key={err} className="text-xs text-error">
                                    {err}
                                </p>
                            ))}
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowTransfer(false)}
                                    className="rounded-xl px-4 py-2 text-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={transferForm.processing}
                                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Send request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
