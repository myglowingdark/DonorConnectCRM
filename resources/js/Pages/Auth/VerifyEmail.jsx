import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <h2 className="text-headline-md text-on-surface">Verify email</h2>
            <p className="mt-2 text-sm text-on-surface-variant">
                Thanks for signing up! Before getting started, please verify
                your email address by clicking the link we just emailed you. If
                you didn&apos;t receive it, we can send another.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mt-4 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <form onSubmit={submit} className="mt-8 space-y-4">
                <PrimaryButton className="w-full justify-center py-3" disabled={processing}>
                    Resend Verification Email
                </PrimaryButton>

                <button
                    type="button"
                    onClick={() => router.post(route('logout'))}
                    className="w-full text-center text-sm font-medium text-secondary hover:underline"
                >
                    Log Out
                </button>
            </form>
        </GuestLayout>
    );
}
