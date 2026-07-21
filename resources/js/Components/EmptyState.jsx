export default function EmptyState({ icon = 'inbox', title, description, action }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-variant bg-white px-6 py-16 text-center">
            <span className="material-symbols-outlined mb-3 text-4xl text-outline">{icon}</span>
            <h3 className="text-lg font-semibold text-on-surface">{title}</h3>
            {description && <p className="mt-2 max-w-md text-sm text-on-surface-variant">{description}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
