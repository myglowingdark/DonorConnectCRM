export default function PerformanceChart({
    title,
    data = [],
    emptyLabel = 'No data yet',
    accent = 'bg-primary',
}) {
    const max = Math.max(...data.map((d) => Number(d.value) || 0), 1);

    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            {title && <h3 className="mb-4 font-semibold">{title}</h3>}
            {!data.length ? (
                <p className="text-sm text-on-surface-variant">{emptyLabel}</p>
            ) : (
                <div className="flex h-44 items-end gap-2">
                    {data.map((item) => {
                        const value = Number(item.value) || 0;
                        const height = `${Math.max(8, (value / max) * 100)}%`;

                        return (
                            <div key={item.label} className="flex min-w-0 flex-1 flex-col items-center gap-2">
                                <span className="text-xs font-semibold tabular-nums text-on-surface">{value}</span>
                                <div className="flex h-28 w-full items-end justify-center rounded-t-lg bg-surface-container-low/60 px-1">
                                    <div
                                        className={`w-full max-w-[2.25rem] rounded-t-md ${accent}`}
                                        style={{ height }}
                                        title={`${item.label}: ${value}`}
                                    />
                                </div>
                                <span className="w-full truncate text-center text-[11px] text-on-surface-variant">
                                    {item.label}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
