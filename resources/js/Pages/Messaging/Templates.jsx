import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

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

const templateVariables = [
    { token: '{{name}}', label: 'name' },
    { token: '{{org}}', label: 'org' },
    { token: '{{volunteer}}', label: 'volunteer' },
    { token: '{{phone}}', label: 'phone' },
    { token: '{{email}}', label: 'email' },
    { token: '{{donation_link}}', label: 'donation_link' },
    { token: '{{receipt}}', label: 'receipt' },
];

const watiStatusStyles = {
    draft: 'bg-slate-100 text-slate-600 ring-slate-200',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rejected: 'bg-rose-50 text-rose-700 ring-rose-200',
    paused: 'bg-orange-50 text-orange-700 ring-orange-200',
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

function highlightVariables(text) {
    if (!text) {
        return null;
    }
    const parts = String(text).split(/(\{\{[^}]+\}\})/g);
    return parts.map((part, index) =>
        /^\{\{[^}]+\}\}$/.test(part) ? (
            <span key={index} className="rounded bg-[#dcf8c6] px-0.5 font-medium text-[#075e54]">
                {part}
            </span>
        ) : (
            <span key={index}>{part}</span>
        ),
    );
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
    const { flash } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [actionId, setActionId] = useState(null);
    const [actionKind, setActionKind] = useState(null);
    const [actionError, setActionError] = useState(null);
    const bodyRef = useRef(null);
    const form = useForm({ ...emptyForm });

    const insertVariable = (token) => {
        const el = bodyRef.current;
        const current = form.data.body || '';
        if (!el) {
            form.setData('body', `${current}${token}`);
            return;
        }

        const start = el.selectionStart ?? current.length;
        const end = el.selectionEnd ?? current.length;
        const next = `${current.slice(0, start)}${token}${current.slice(end)}`;
        form.setData('body', next);

        requestAnimationFrame(() => {
            el.focus();
            const cursor = start + token.length;
            el.setSelectionRange(cursor, cursor);
        });
    };

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
        const hasFile = form.data.attachment instanceof File;
        const options = {
            forceFormData: hasFile,
            preserveScroll: true,
            onSuccess: () => {
                if (editing) {
                    setEditing(null);
                } else {
                    form.reset();
                    form.setData({ ...emptyForm });
                }
            },
        };

        // Laravel 12 boolean rules only accept 0/1 — FormData "false"/"" fails validation.
        // Omit attachment unless a File is present (JSON null can 500 some file validators).
        form
            .transform((data) => {
                const payload = {
                    ...data,
                    is_active: data.is_active ? 1 : 0,
                    remove_attachment: data.remove_attachment ? 1 : 0,
                    ...(editing && hasFile ? { _method: 'put' } : {}),
                };

                if (hasFile) {
                    payload.attachment = data.attachment;
                } else {
                    delete payload.attachment;
                }

                return payload;
            });

        if (editing) {
            if (hasFile) {
                form.post(route('messaging.templates.update', editing.id), options);
            } else {
                form.put(route('messaging.templates.update', editing.id), options);
            }
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

            {flash?.success && (
                <p className="mb-4 rounded-xl bg-secondary/10 px-4 py-2 text-sm text-secondary">{flash.success}</p>
            )}
            {flash?.error && (
                <p className="mb-4 rounded-xl bg-error/10 px-4 py-2 text-sm text-error">{flash.error}</p>
            )}

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
                        ref={bodyRef}
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
                    {isWhatsApp && (
                        <p className="text-[11px] text-on-surface-variant">
                            Meta rule: do not start or end the message with a variable (e.g. avoid ending with{' '}
                            {'{{org}}'}). Add a short closing like “Thank you.” after the last variable.
                        </p>
                    )}
                    <div className="space-y-1.5">
                        <p className="text-xs font-medium text-on-surface-variant">
                            Variables — click to insert at cursor
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {templateVariables.map((variable) => (
                                <button
                                    key={variable.token}
                                    type="button"
                                    onClick={() => insertVariable(variable.token)}
                                    className="rounded-full border border-[#25D366]/30 bg-[#f0fdf6] px-2.5 py-1 font-mono text-[11px] font-semibold text-[#128C7E] hover:border-[#25D366] hover:bg-[#dcf8c6]"
                                    title={`Insert ${variable.token}`}
                                >
                                    {variable.token}
                                </button>
                            ))}
                        </div>
                    </div>
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

                <div className="rounded-2xl border border-slate-100 bg-[#f7f8fa] p-4 shadow-card lg:col-span-3 sm:p-5">
                    {!templates.length ? (
                        <EmptyState icon="mail" title="No templates yet" description="Create one to speed up donor outreach." />
                    ) : (
                        <ul className="space-y-4">
                            {templates.map((t) => {
                                const busy = actionId === t.id;
                                const cardError =
                                    actionError?.templateId === t.id ? actionError.message : null;
                                const helper =
                                    t.channel === 'whatsapp' ? metaHelperText(t.meta_status) : null;
                                const canManage = t.channel !== 'whatsapp' || canManageWhatsAppTemplates;
                                const isWa = t.channel === 'whatsapp';
                                const showSubmit =
                                    isWa &&
                                    canManageWhatsAppTemplates &&
                                    (t.meta_status === 'draft' || t.meta_status === 'rejected');
                                const showRefresh =
                                    isWa &&
                                    canManageWhatsAppTemplates &&
                                    (t.meta_status === 'pending' || t.meta_status === 'paused');
                                const showResubmitPending =
                                    isWa && canManageWhatsAppTemplates && t.meta_status === 'pending';
                                const statusLabel = statusLabels[t.meta_status] || t.meta_status;
                                const statusClass =
                                    watiStatusStyles[t.meta_status] || watiStatusStyles.draft;

                                return (
                                    <li
                                        key={t.id}
                                        className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:border-[#25D366]/40 hover:shadow-md"
                                    >
                                        <div className="flex flex-col lg:flex-row">
                                            <div className="min-w-0 flex-1 p-4 sm:p-5">
                                                <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            {isWa && (
                                                                <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#25D366]/15 text-[#128C7E]">
                                                                    <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 fill-current" aria-hidden>
                                                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                                                    </svg>
                                                                </span>
                                                            )}
                                                            <h4 className="truncate text-base font-semibold text-slate-900">
                                                                {t.name}
                                                            </h4>
                                                            {!isWa && (
                                                                <StatusBadge status={t.channel} label={t.channel} />
                                                            )}
                                                            {isWa && t.meta_status && (
                                                                <span
                                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset ${statusClass}`}
                                                                >
                                                                    {statusLabel}
                                                                </span>
                                                            )}
                                                            {!t.is_active && (
                                                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                                    Inactive
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                                            {isWa ? (
                                                                <>
                                                                    <span className="font-medium text-[#128C7E]">WhatsApp</span>
                                                                    {t.meta_category && (
                                                                        <>
                                                                            <span className="text-slate-300">·</span>
                                                                            <span>{t.meta_category}</span>
                                                                        </>
                                                                    )}
                                                                    {(t.meta_name || t.meta_language) && (
                                                                        <>
                                                                            <span className="text-slate-300">·</span>
                                                                            <span className="font-mono text-[11px]">
                                                                                {t.meta_name || '—'} ·{' '}
                                                                                {(t.meta_language || 'en').toUpperCase()}
                                                                            </span>
                                                                        </>
                                                                    )}
                                                                </>
                                                            ) : (
                                                                t.subject && <span>{t.subject}</span>
                                                            )}
                                                            {t.has_attachment && (
                                                                <>
                                                                    <span className="text-slate-300">·</span>
                                                                    <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-700">
                                                                        <svg className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                                                                            <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
                                                                        </svg>
                                                                        {t.attachment_filename || 'Document'}
                                                                    </span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                {helper && (
                                                    <p className="mb-3 text-xs text-slate-500">{helper}</p>
                                                )}

                                                {isWa ? (
                                                    <div className="relative max-w-md overflow-hidden rounded-xl border border-[#e5ebe7] bg-[#e5ddd5]">
                                                        <div
                                                            className="pointer-events-none absolute inset-0 opacity-[0.08]"
                                                            style={{
                                                                backgroundImage:
                                                                    'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23000000\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
                                                            }}
                                                        />
                                                        <div className="relative p-3 sm:p-4">
                                                            <div className="ml-auto max-w-[92%] rounded-lg rounded-tr-sm bg-[#dcf8c6] px-3 py-2 shadow-sm">
                                                                {t.has_attachment && (
                                                                    <div className="mb-2 flex items-center gap-2 rounded-md bg-white/70 px-2 py-1.5 text-xs text-slate-700">
                                                                        <span className="flex h-8 w-8 items-center justify-center rounded bg-[#25D366]/20 text-[#128C7E]">
                                                                            <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                                                                                <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
                                                                            </svg>
                                                                        </span>
                                                                        <span className="truncate font-medium">
                                                                            {t.attachment_filename || 'Document.pdf'}
                                                                        </span>
                                                                    </div>
                                                                )}
                                                                <p className="whitespace-pre-wrap text-[13px] leading-relaxed text-slate-800">
                                                                    {highlightVariables(t.body)}
                                                                </p>
                                                                <p className="mt-1 text-right text-[10px] text-slate-500">
                                                                    Preview
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <p className="line-clamp-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                        {t.body}
                                                    </p>
                                                )}

                                                {t.meta_rejection_reason && (
                                                    <p className="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs text-rose-800">
                                                        {t.meta_rejection_reason}
                                                    </p>
                                                )}
                                                {cardError && (
                                                    <p className="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs text-rose-800">
                                                        {cardError}
                                                    </p>
                                                )}
                                            </div>

                                            {canManage && (
                                                <div className="flex shrink-0 flex-row flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 lg:w-44 lg:flex-col lg:items-stretch lg:border-l lg:border-t-0 lg:bg-white lg:px-3 lg:py-4">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(t)}
                                                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-[#128C7E] hover:border-[#25D366] hover:bg-[#f0fdf6]"
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
                                                            className="rounded-lg bg-[#25D366] px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#1ebe57] disabled:opacity-50"
                                                        >
                                                            {busy && actionKind === 'submit'
                                                                ? 'Submitting…'
                                                                : t.meta_status === 'rejected'
                                                                  ? 'Resubmit'
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
                                                            className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-100 disabled:opacity-50"
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
                                                            className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
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
                                                        className="rounded-lg px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50"
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
