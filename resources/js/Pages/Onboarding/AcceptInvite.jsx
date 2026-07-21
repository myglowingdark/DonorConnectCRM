import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function AcceptInvite({ invite }) {
    const form = useForm({ name: '', password: '', password_confirmation: '' });

    return (
        <GuestLayout>
            <Head title="Accept invite" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('invites.accept', invite.token));
                }}
                className="mx-auto max-w-md space-y-4 rounded-xl bg-white p-6 shadow"
            >
                <h1 className="text-lg font-semibold">Join {invite.organization?.name}</h1>
                <p className="text-sm text-gray-600">{invite.email} · {invite.role}</p>
                <input
                    className="w-full rounded border px-3 py-2"
                    placeholder="Your name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                <input
                    type="password"
                    className="w-full rounded border px-3 py-2"
                    placeholder="Password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                />
                <input
                    type="password"
                    className="w-full rounded border px-3 py-2"
                    placeholder="Confirm password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                />
                <button type="submit" className="w-full rounded bg-primary px-4 py-2 text-white">
                    Create account
                </button>
            </form>
        </GuestLayout>
    );
}
