export default function KpiCard({ label, value, icon, hint, accent = 'primary' }) {
    const accents = {
        primary: 'bg-primary/10 text-primary',
        secondary: 'bg-secondary/10 text-secondary',
        warning: 'bg-amber-100 text-amber-700',
        danger: 'bg-error-container text-error',
    };

    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            <div className="mb-3 flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-on-surface-variant">{label}</p>
                {icon && (
                    <span className={`rounded-xl p-2 material-symbols-outlined ${accents[accent]}`}>
                        {icon}
                    </span>
                )}
            </div>
            <p className="text-2xl font-bold text-on-surface">{value}</p>
            {hint && <p className="mt-2 text-xs text-on-surface-variant">{hint}</p>}
        </div>
    );
}
