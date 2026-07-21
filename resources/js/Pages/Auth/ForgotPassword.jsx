import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <h2 className="text-headline-md text-on-surface">Forgot password</h2>
            <p className="mt-2 text-sm text-on-surface-variant">
                Enter your email address and we will send you a password reset
                link.
            </p>

            {status && (
                <div className="mt-4 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-8 space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <PrimaryButton className="w-full justify-center py-3" disabled={processing}>
                    Email Password Reset Link
                </PrimaryButton>

                <div className="text-center">
                    <Link
                        href={route('login')}
                        className="text-sm font-medium text-secondary hover:underline"
                    >
                        Back to login
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
