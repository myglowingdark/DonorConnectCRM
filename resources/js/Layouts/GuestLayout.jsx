export default function GuestLayout({ children, fullBleed = false }) {
    if (fullBleed) {
        return <div className="min-h-screen bg-background">{children}</div>;
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-background px-6 py-12">
            <div className="w-full max-w-md rounded-2xl border border-slate-100 bg-white p-8 shadow-elevated">
                {children}
            </div>
        </div>
    );
}
