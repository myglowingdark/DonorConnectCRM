import { classNames } from '@/lib/format';

const styles = {
    new: 'bg-blue-100 text-blue-800',
    follow_up: 'bg-amber-100 text-amber-800',
    interested: 'bg-teal-100 text-teal-800',
    pledged: 'bg-indigo-100 text-indigo-800',
    donated: 'bg-green-100 text-green-800',
    not_interested: 'bg-slate-100 text-slate-700',
    do_not_call: 'bg-rose-100 text-rose-800',
    success: 'bg-green-100 text-green-800',
    failed: 'bg-rose-100 text-rose-800',
    running: 'bg-amber-100 text-amber-800',
    idle: 'bg-slate-100 text-slate-700',
};

export default function StatusBadge({ status, label }) {
    const key = (status || '').toString().toLowerCase();
    return (
        <span
            className={classNames(
                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                styles[key] || 'bg-surface-container text-on-surface-variant',
            )}
        >
            {label || status || '—'}
        </span>
    );
}
