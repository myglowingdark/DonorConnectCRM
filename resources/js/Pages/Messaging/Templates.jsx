import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const emptyForm = {
    name: '',
    channel: 'email',
    subject: '',
    body: '',
    is_active: true,
    meta_name: '',
    meta_language: 'en',
    meta_category: 'UTILITY',
};

export default function MessagingTemplates({
    templates,
    channels,
    hasWhatsAppFeature = false,
    canManageWhatsAppTemplates = false,
    metaCategories = [],
}) {
    const [editing, setEditing] = useState(null);
    const form = useForm({ ...emptyForm });

    const openCreate = () => {
        setEditing(null);
        form.setData({ ...emptyForm });
        form.clearErrors();
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
        });
        form.clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            form.put(route('messaging.templates.update', editing.id), {
                onSuccess: () => setEditing(null),
            });
        } else {
            form.post(route('messaging.templates.store'), {
                onSuccess: () => {
                    form.reset();
                    form.setData({ ...emptyForm });
                },
            });
        }
    };

    const availableChannels = channels.filter(
        (c) => c.value !== 'whatsapp' || canManageWhatsAppTemplates || (editing && editing.channel === 'whatsapp'),
    );

    const isWhatsApp = form.data.channel === 'whatsapp';

    return (
        <AuthenticatedLayout header="Message Templates">
            <Head title="Templates" />

            <div className="mb-6 flex items-center justify-between gap-3">
                <div>
                    <h2 className="text-headline-md">Templates</h2>
                    <p className="text-sm text-on-surface-variant">
                        Reusable email, WhatsApp, and SMS copy. WhatsApp templates require Meta approval before sending.
                    </p>
                </div>
                <Link href={route('messaging.settings')} className="text-sm font-semibold text-secondary">
                    Messaging settings
                </Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-5">
                <form onSubmit={submit} className="space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-card lg:col-span-2">
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
                                ? 'Body — use {{name}}, {{org}}, {{volunteer}} (mapped to Meta {{1}}, {{2}}…)'
                                : 'Body — use {{name}}, {{org}}, {{volunteer}}'
                        }
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        required
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                        />
                        Active
                    </label>
                    {Object.values(form.errors).map((err) => (
                        <p key={err} className="text-xs text-error">
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
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white"
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
                            {templates.map((t) => (
                                <li
                                    key={t.id}
                                    className="flex items-start justify-between gap-3 rounded-xl border border-slate-100 p-3"
                                >
                                    <div>
                                        <div className="mb-1 flex flex-wrap items-center gap-2">
                                            <p className="font-semibold">{t.name}</p>
                                            <StatusBadge status={t.channel} label={t.channel} />
                                            {t.channel === 'whatsapp' && t.meta_status && (
                                                <StatusBadge status={t.meta_status} label={t.meta_status} />
                                            )}
                                            {!t.is_active && (
                                                <span className="text-[10px] font-semibold uppercase text-on-surface-variant">
                                                    Inactive
                                                </span>
                                            )}
                                        </div>
                                        {t.subject && <p className="text-xs text-on-surface-variant">{t.subject}</p>}
                                        {t.meta_name && (
                                            <p className="text-xs text-on-surface-variant">
                                                Meta: {t.meta_name} · {t.meta_language || 'en'}
                                            </p>
                                        )}
                                        <p className="mt-1 line-clamp-2 text-sm">{t.body}</p>
                                        {t.meta_rejection_reason && (
                                            <p className="mt-1 text-xs text-error">{t.meta_rejection_reason}</p>
                                        )}
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        {(t.channel !== 'whatsapp' || canManageWhatsAppTemplates) && (
                                            <button
                                                type="button"
                                                onClick={() => openEdit(t)}
                                                className="text-xs font-semibold text-secondary"
                                            >
                                                Edit
                                            </button>
                                        )}
                                        {t.channel === 'whatsapp' && canManageWhatsAppTemplates && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(route('messaging.templates.submit-meta', t.id))}
                                                    className="text-xs font-semibold text-secondary"
                                                >
                                                    Submit to Meta
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(route('messaging.templates.sync-meta', t.id))}
                                                    className="text-xs font-semibold text-secondary"
                                                >
                                                    Sync status
                                                </button>
                                            </>
                                        )}
                                        {(t.channel !== 'whatsapp' || canManageWhatsAppTemplates) && (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (confirm('Delete this template?')) {
                                                        router.delete(route('messaging.templates.destroy', t.id));
                                                    }
                                                }}
                                                className="text-xs font-semibold text-error"
                                            >
                                                Delete
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
