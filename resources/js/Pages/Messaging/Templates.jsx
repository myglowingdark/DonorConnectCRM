import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const emptyForm = {
    name: '',
    channel: 'email',
    subject: '',
    body: '',
    is_active: true,
    meta_name: '',
    meta_language: 'en',
    meta_category: 'UTILITY',
    attachment: null,
    remove_attachment: false,
};

const statusTone = {
    draft: 'idle',
    pending: 'running',
    approved: 'success',
    rejected: 'failed',
    paused: 'follow_up',
};

function metaHelperText(status) {
    switch (status) {
        case 'draft':
            return 'Not submitted yet — submit to Meta for approval before sending.';
        case 'pending':
            return 'Waiting for Meta… status refreshes automatically.';
        case 'approved':
            return 'Ready to send to donors.';
        case 'rejected':
            return 'Meta rejected this template — fix the copy or document, then resubmit.';
        case 'paused':
            return 'Paused by Meta — refresh status or resubmit after review.';
        default:
            return null;
    }
}

function firstError(errors) {
    if (!errors) {
        return null;
    }
    const values = Object.values(errors);
    if (!values.length) {
        return null;
    }
    const first = values[0];
    return Array.isArray(first) ? first[0] : first;
}

export default function MessagingTemplates({
    templates,
    channels,
    hasWhatsAppFeature = false,
    canManageWhatsAppTemplates = false,
    metaCategories = [],
    metaStatuses = [],
    pendingMetaSyncCount = 0,
}) {
    const [editing, setEditing] = useState(null);
    const [actionId, setActionId] = useState(null);
    const [actionKind, setActionKind] = useState(null);
    const [actionError, setActionError] = useState(null);
    const form = useForm({ ...emptyForm });

    const statusLabels = useMemo(() => {
        return Object.fromEntries(metaStatuses.map((s) => [s.value, s.label]));
    }, [metaStatuses]);

    const hasPending =
        pendingMetaSyncCount > 0 ||
        templates.some((t) => t.channel === 'whatsapp' && (t.meta_status === 'pending' || t.meta_status === 'paused'));

    useEffect(() => {
        if (!canManageWhatsAppTemplates || !hasPending) {
            return undefined;
        }

        const timer = window.setInterval(() => {
            router.post(
                route('messaging.templates.sync-meta-pending'),
                {},
                {
                    preserveScroll: true,
                    preserveState: false,
                },
            );
        }, 20000);

        return () => window.clearInterval(timer);
    }, [canManageWhatsAppTemplates, hasPending]);

    const openCreate = () => {
        setEditing(null);
        form.setData({ ...emptyForm });
        form.clearErrors();
        setActionError(null);
    };

    const openEdit = (template) => {
        setEditing(template);
        form.setData({
            name: template.name,
            channel: template.channel,
            subject: template.subject || '',
            body: template.body,
            is_active: !!template.is_active,
            meta_name: template.meta_name || '',
            meta_language: template.meta_language || 'en',
            meta_category: template.meta_category || 'UTILITY',
            attachment: null,
            remove_attachment: false,
        });
        form.clearErrors();
        setActionError(null);
    };

    const submit = (e) => {
        e.preventDefault();
        const options = {
            forceFormData: true,
            onSuccess: () => {
                if (editing) {
                    setEditing(null);
                } else {
                    form.reset();
                    form.setData({ ...emptyForm });
                }
            },
        };

        if (editing) {
            form.put(route('messaging.templates.update', editing.id), options);
        } else {
            form.post(route('messaging.templates.store'), options);
        }
    };

    const runTemplateAction = (templateId, url, kind = 'submit') => {
        setActionId(templateId);
        setActionKind(kind);
        setActionError(null);
        router.post(url, {}, {
            preserveScroll: true,
            onError: (errors) => {
                setActionError({
                    templateId,
                    message: firstError(errors) || 'Request failed. Check Meta credentials and try again.',
                });
            },
            onFinish: () => {
                setActionId(null);
                setActionKind(null);
            },
        });
    };

    const availableChannels = channels.filter(
        (c) => c.value !== 'whatsapp' || canManageWhatsAppTemplates || (editing && editing.channel === 'whatsapp'),
    );

    const isWhatsApp = form.data.channel === 'whatsapp';
    const supportsAttachment = form.data.channel === 'email' || form.data.channel === 'whatsapp';

    return (
        <AuthenticatedLayout header="Message Templates">
            <Head title="Templates" />

            <div className="mb-6 flex items-center justify-between gap-3">
                <div>
                    <h2 className="text-headline-md">Templates</h2>
                    <p className="text-sm text-on-surface-variant">
                        Reusable email, WhatsApp, and SMS copy. WhatsApp templates require Meta approval before sending.
                        Use {'{{receipt}}'} with a PDF/DOC attachment for document headers.
                    </p>
                </div>
                <Link href={route('messaging.settings')} className="text-sm font-semibold text-secondary">
                    Messaging settings
                </Link>
            </div>

            {actionError && !actionError.templateId && (
                <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {actionError.message}
                </div>
            )}

            {hasPending && canManageWhatsAppTemplates && (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <span>
                        Waiting for Meta approval on {pendingMetaSyncCount || 'some'} template(s). Status refreshes
                        automatically every 20 seconds.
                    </span>
                    <button
                        type="button"
                        className="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold"
                        onClick={() =>
                            runTemplateAction(null, route('messaging.templates.sync-meta-pending'), 'sync')
                        }
                    >
                        Refresh now
                    </button>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-5">
                <form
                    onSubmit={submit}
                    encType="multipart/form-data"
                    className="space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-card lg:col-span-2"
                >
                    <h3 className="font-semibold">{editing ? 'Edit template' : 'New template'}</h3>
                    <input
                        className="w-full rounded-xl border-slate-200"
                        placeholder="Template name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                    <select
                        className="w-full rounded-xl border-slate-200"
                        value={form.data.channel}
                        onChange={(e) => form.setData('channel', e.target.value)}
                        disabled={!!editing}
                    >
                        {availableChannels.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                    {form.data.channel === 'email' && (
                        <input
                            className="w-full rounded-xl border-slate-200"
                            placeholder="Subject"
                            value={form.data.subject}
                            onChange={(e) => form.setData('subject', e.target.value)}
                        />
                    )}
                    {isWhatsApp && (
                        <>
                            <input
                                className="w-full rounded-xl border-slate-200"
                                placeholder="Meta template name (lowercase_underscore)"
                                value={form.data.meta_name}
                                onChange={(e) => form.setData('meta_name', e.target.value)}
                            />
                            <div className="grid grid-cols-2 gap-2">
                                <input
                                    className="rounded-xl border-slate-200"
                                    placeholder="Language (en)"
                                    value={form.data.meta_language}
                                    onChange={(e) => form.setData('meta_language', e.target.value)}
                                />
                                <select
                                    className="rounded-xl border-slate-200"
                                    value={form.data.meta_category}
                                    onChange={(e) => form.setData('meta_category', e.target.value)}
                                >
                                    {metaCategories.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </>
                    )}
                    <textarea
                        className="w-full rounded-xl border-slate-200"
                        rows={6}
                        placeholder={
                            isWhatsApp
                                ? 'Body — use {{name}}, {{org}}, {{volunteer}}; {{receipt}} = document header'
                                : 'Body — use {{name}}, {{org}}, {{volunteer}}, {{receipt}}'
                        }
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        required
                    />
                    {supportsAttachment && (
                        <div className="space-y-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 p-3">
                            <label className="block text-sm font-medium text-on-surface">
                                Document attachment (PDF / DOC)
                            </label>
                            <p className="text-xs text-on-surface-variant">
                                Optional. Use {'{{receipt}}'} in the body to mark a receipt document. WhatsApp sends it
                                as a document header; email attaches the file.
                            </p>
                            {editing?.has_attachment && !form.data.remove_attachment && !form.data.attachment && (
                                <div className="flex flex-wrap items-center gap-2 text-xs">
                                    <span className="rounded-full bg-slate-200 px-2.5 py-1 font-semibold text-slate-800">
                                        {editing.attachment_filename || 'Document attached'}
                                    </span>
                                    <button
                                        type="button"
                                        className="font-semibold text-error"
                                        onClick={() => form.setData('remove_attachment', true)}
                                    >
                                        Remove
                                    </button>
                                </div>
                            )}
                            {(form.data.remove_attachment || !editing?.has_attachment || form.data.attachment) && (
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    className="block w-full text-sm"
                                    onChange={(e) => {
                                        form.setData('attachment', e.target.files?.[0] || null);
                                        form.setData('remove_attachment', false);
                                    }}
                                />
                            )}
                            {form.errors.attachment && (
                                <p className="text-xs text-error">{form.errors.attachment}</p>
                            )}
                        </div>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                        />
                        Active
                    </label>
                    {Object.entries(form.errors)
                        .filter(([key]) => key !== 'attachment')
                        .map(([key, err]) => (
                            <p key={key} className="text-xs text-error">
                                {err}
                            </p>
                        ))}
                    <div className="flex gap-2">
                        {editing && (
                            <button type="button" onClick={openCreate} className="rounded-xl px-3 py-2 text-sm">
                                Cancel
                            </button>
                        )}
                        <button
                            type="submit"
                            disabled={form.processing || (isWhatsApp && !canManageWhatsAppTemplates)}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {editing ? 'Update' : 'Create'}
                        </button>
                    </div>
                    {isWhatsApp && !canManageWhatsAppTemplates && (
                        <p className="text-xs text-on-surface-variant">
                            Only Super Admin or Organization Admin can manage WhatsApp templates.
                        </p>
                    )}
                    {!hasWhatsAppFeature && (
                        <p className="text-xs text-on-surface-variant">WhatsApp module is not enabled for this organization.</p>
                    )}
                </form>

                <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card lg:col-span-3">
                    {!templates.length ? (
                        <EmptyState icon="mail" title="No templates yet" description="Create one to speed up donor outreach." />
                    ) : (
                        <ul className="space-y-3">
                            {templates.map((t) => {
                                const busy = actionId === t.id;
                                const cardError =
                                    actionError?.templateId === t.id ? actionError.message : null;
                                const helper =
                                    t.channel === 'whatsapp' ? metaHelperText(t.meta_status) : null;
                                const canManage = t.channel !== 'whatsapp' || canManageWhatsAppTemplates;
                                const showSubmit =
                                    t.channel === 'whatsapp' &&
                                    canManageWhatsAppTemplates &&
                                    (t.meta_status === 'draft' || t.meta_status === 'rejected');
                                const showRefresh =
                                    t.channel === 'whatsapp' &&
                                    canManageWhatsAppTemplates &&
                                    (t.meta_status === 'pending' || t.meta_status === 'paused');
                                const showResubmitPending =
                                    t.channel === 'whatsapp' &&
                                    canManageWhatsAppTemplates &&
                                    t.meta_status === 'pending';

                                return (
                                    <li
                                        key={t.id}
                                        className="rounded-xl border border-slate-100 bg-slate-50/40 p-4"
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="min-w-0 flex-1">
                                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold text-on-surface">{t.name}</p>
                                                    <StatusBadge status={t.channel} label={t.channel} />
                                                    {t.channel === 'whatsapp' && t.meta_status && (
                                                        <StatusBadge
                                                            status={statusTone[t.meta_status] || t.meta_status}
                                                            label={statusLabels[t.meta_status] || t.meta_status}
                                                        />
                                                    )}
                                                    {!t.is_active && (
                                                        <span className="text-[10px] font-semibold uppercase text-on-surface-variant">
                                                            Inactive
                                                        </span>
                                                    )}
                                                    {t.has_attachment && (
                                                        <span className="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-800">
                                                            {t.attachment_filename || 'Document attached'}
                                                        </span>
                                                    )}
                                                </div>
                                                {helper && (
                                                    <p className="mb-1 text-xs text-on-surface-variant">{helper}</p>
                                                )}
                                                {t.subject && (
                                                    <p className="text-xs text-on-surface-variant">{t.subject}</p>
                                                )}
                                                {t.meta_name && (
                                                    <p className="text-xs text-on-surface-variant">
                                                        Meta: {t.meta_name} · {t.meta_language || 'en'}
                                                    </p>
                                                )}
                                                <p className="mt-1 line-clamp-2 text-sm text-on-surface">{t.body}</p>
                                                {t.meta_rejection_reason && (
                                                    <p className="mt-1 text-xs text-error">{t.meta_rejection_reason}</p>
                                                )}
                                                {cardError && (
                                                    <p className="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs text-rose-800">
                                                        {cardError}
                                                    </p>
                                                )}
                                            </div>
                                            {canManage && (
                                                <div className="flex shrink-0 flex-row flex-wrap gap-2 sm:flex-col sm:items-stretch">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(t)}
                                                        className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-secondary hover:bg-slate-50"
                                                    >
                                                        Edit
                                                    </button>
                                                    {showSubmit && (
                                                        <button
                                                            type="button"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                runTemplateAction(
                                                                    t.id,
                                                                    route('messaging.templates.submit-meta', t.id),
                                                                    'submit',
                                                                )
                                                            }
                                                            className="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-50"
                                                        >
                                                            {busy && actionKind === 'submit'
                                                                ? 'Submitting…'
                                                                : t.meta_status === 'rejected'
                                                                  ? 'Resubmit to Meta'
                                                                  : 'Submit to Meta'}
                                                        </button>
                                                    )}
                                                    {showRefresh && (
                                                        <button
                                                            type="button"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                runTemplateAction(
                                                                    t.id,
                                                                    route('messaging.templates.sync-meta', t.id),
                                                                    'sync',
                                                                )
                                                            }
                                                            className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100 disabled:opacity-50"
                                                        >
                                                            {busy && actionKind === 'sync' ? 'Refreshing…' : 'Refresh status'}
                                                        </button>
                                                    )}
                                                    {showResubmitPending && (
                                                        <button
                                                            type="button"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                runTemplateAction(
                                                                    t.id,
                                                                    route('messaging.templates.submit-meta', t.id),
                                                                    'submit',
                                                                )
                                                            }
                                                            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-on-surface-variant hover:bg-slate-50 disabled:opacity-50"
                                                        >
                                                            {busy && actionKind === 'submit' ? 'Submitting…' : 'Resubmit'}
                                                        </button>
                                                    )}
                                                    <button
                                                        type="button"
                                                        disabled={busy}
                                                        onClick={() => {
                                                            if (confirm('Delete this template?')) {
                                                                setActionId(t.id);
                                                                setActionKind('delete');
                                                                router.delete(route('messaging.templates.destroy', t.id), {
                                                                    preserveScroll: true,
                                                                    onFinish: () => {
                                                                        setActionId(null);
                                                                        setActionKind(null);
                                                                    },
                                                                });
                                                            }
                                                        }}
                                                        className="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-error hover:bg-rose-50 disabled:opacity-50"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
