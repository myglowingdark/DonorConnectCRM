import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

// TEMP: demo/testing quick-login — remove before production
const DEMO_USERS = [
    { label: 'Super Admin', email: 'admin@donorconnect.test' },
    { label: 'Org Admin', email: 'hope.admin@donorconnect.test' },
    { label: 'Volunteer', email: 'priya@donorconnect.test' },
];
const DEMO_PASSWORD = 'password';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const loginAsDemo = (email) => {
        setData({
            email,
            password: DEMO_PASSWORD,
            remember: false,
        });
        router.post(
            route('login'),
            { email, password: DEMO_PASSWORD, remember: false },
            { onFinish: () => reset('password') },
        );
    };

    return (
        <GuestLayout>
            <Head title="Login" />
            <div className="grid min-h-screen w-full lg:grid-cols-2">
                <div className="relative hidden overflow-hidden bg-primary lg:flex lg:flex-col lg:justify-between lg:p-12">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(109,245,225,0.25),transparent_40%),radial-gradient(circle_at_80%_80%,rgba(144,168,255,0.35),transparent_45%)]" />
                    <div className="relative z-10">
                        <div className="mb-8 flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 text-white">
                                <span className="material-symbols-outlined text-3xl">volunteer_activism</span>
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold text-white">DonorConnect CRM</h1>
                                <p className="text-sm text-primary-fixed">Trusted donor relationships</p>
                            </div>
                        </div>
                        <h2 className="max-w-md text-4xl font-bold leading-tight text-white">
                            Connect with past donors. Log every conversation. Grow giving.
                        </h2>
                        <p className="mt-4 max-w-md text-base text-primary-fixed">
                            Built for telecalling volunteers and nonprofit teams who need clear
                            organization context, secure data, and a fast calling workflow.
                        </p>
                    </div>
                    <div className="relative z-10 rounded-2xl border border-white/10 bg-white/10 p-6 text-white backdrop-blur">
                        <p className="text-sm opacity-90">
                            “Every follow-up is a chance to renew trust. DonorConnect keeps your
                            calling queue clear and your notes where they belong.”
                        </p>
                    </div>
                </div>

                <div className="flex items-center justify-center bg-background px-6 py-12">
                    <div className="w-full max-w-md rounded-2xl border border-slate-100 bg-white p-8 shadow-elevated">
                        <div className="mb-8 lg:hidden">
                            <h1 className="text-2xl font-bold text-primary">DonorConnect CRM</h1>
                            <p className="text-sm text-on-surface-variant">Sign in to continue</p>
                        </div>
                        <h2 className="text-headline-md text-on-surface">Welcome back</h2>
                        <p className="mt-2 text-sm text-on-surface-variant">
                            Sign in with your volunteer or admin account.
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

                            <div>
                                <InputLabel htmlFor="password" value="Password" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                <InputError message={errors.password} className="mt-2" />
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center">
                                    <Checkbox
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                    />
                                    <span className="ms-2 text-sm text-on-surface-variant">Remember me</span>
                                </label>
                                {canResetPassword && (
                                    <Link
                                        href={route('password.request')}
                                        className="text-sm font-medium text-secondary hover:underline"
                                    >
                                        Forgot password?
                                    </Link>
                                )}
                            </div>

                            <PrimaryButton className="w-full justify-center py-3" disabled={processing}>
                                Sign in
                            </PrimaryButton>
                        </form>

                        {/* TEMP: demo/testing quick-login — remove before production */}
                        <div className="mt-6 rounded-xl border border-dashed border-amber-300 bg-amber-50/80 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-amber-800">
                                Demo login (temporary)
                            </p>
                            <p className="mt-1 text-xs text-amber-700/80">
                                Password for all: <code className="font-mono">password</code>
                            </p>
                            <div className="mt-3 flex flex-col gap-2">
                                {DEMO_USERS.map((user) => (
                                    <button
                                        key={user.email}
                                        type="button"
                                        disabled={processing}
                                        onClick={() => loginAsDemo(user.email)}
                                        className="rounded-lg border border-amber-200 bg-white px-3 py-2 text-left text-sm font-medium text-on-surface transition hover:border-amber-400 hover:bg-amber-50 disabled:opacity-50"
                                    >
                                        {user.label}
                                        <span className="mt-0.5 block text-xs font-normal text-on-surface-variant">
                                            {user.email}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
