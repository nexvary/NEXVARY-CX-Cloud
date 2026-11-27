import { Head, useForm } from '@inertiajs/react';

interface Props {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <main className="nx-setup-shell">
            <Head title="Set New Password" />
            <div className="nx-card nx-setup-card mx-auto grid w-full max-w-md gap-5 p-5 sm:p-8">
                <div className="flex items-center gap-3">
                    <img
                        src="/images/seal.webp"
                        alt="NEXVARY Inc."
                        className="w-11 h-11 rounded-full object-cover shrink-0"
                        style={{ boxShadow: '0 0 14px rgba(200,165,80,0.5)' }}
                    />
                    <div>
                        <div className="font-bold tracking-wide leading-tight" dir="ltr">NEXVARY CX Cloud</div>
                        <div className="text-xs" style={{ color: 'var(--nx-silver-dim)' }} dir="ltr">Set new password</div>
                    </div>
                </div>

                <h1 className="text-2xl font-semibold">Set New Password</h1>

                <form
                    className="grid gap-4"
                    onSubmit={e => { e.preventDefault(); post('/reset-password'); }}
                >
                    <label className="grid gap-1.5 text-sm">
                        Email
                        <input
                            type="email"
                            className="nx-input"
                            dir="ltr"
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            required
                            autoComplete="email"
                        />
                        {errors.email && <p role="alert" className="nx-notice nx-notice-error">{errors.email}</p>}
                    </label>

                    <label className="grid gap-1.5 text-sm">
                        New Password
                        <input
                            type="password"
                            className="nx-input"
                            dir="ltr"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            required
                            minLength={10}
                            autoComplete="new-password"
                        />
                        {errors.password && <p role="alert" className="nx-notice nx-notice-error">{errors.password}</p>}
                    </label>

                    <label className="grid gap-1.5 text-sm">
                        Confirm Password
                        <input
                            type="password"
                            className="nx-input"
                            dir="ltr"
                            value={data.password_confirmation}
                            onChange={e => setData('password_confirmation', e.target.value)}
                            required
                            autoComplete="new-password"
                        />
                    </label>

                    <button type="submit" disabled={processing} className="nx-btn-primary">
                        Set Password
                    </button>
                </form>
            </div>
        </main>
    );
}
