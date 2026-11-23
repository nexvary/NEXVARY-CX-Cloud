import { Head, useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';

export default function ForgotPassword() {
    const { flash } = usePage().props as unknown as { flash?: { success?: string } };
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    return (
        <main className="nx-setup-shell">
            <Head title="Forgot Password" />
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
                        <div className="text-xs" style={{ color: 'var(--nx-silver-dim)' }} dir="ltr">nexvary.com</div>
                    </div>
                </div>

                <h1 className="text-2xl font-semibold">Reset Password</h1>
                <p className="text-sm" style={{ color: 'var(--nx-text-muted)' }}>
                    Enter your email address and we'll send you a reset link, if an account exists.
                </p>

                {flash?.success && (
                    <div className="nx-notice nx-notice-success">{flash.success}</div>
                )}

                <form
                    className="grid gap-4"
                    onSubmit={e => { e.preventDefault(); post('/forgot-password'); }}
                >
                    <label className="grid gap-1.5 text-sm">
                        Email
                        <input
                            type="email"
                            name="email"
                            className="nx-input"
                            dir="ltr"
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            required
                            autoFocus
                            autoComplete="email"
                        />
                        {errors.email && <p role="alert" className="nx-notice nx-notice-error">{errors.email}</p>}
                    </label>

                    <button type="submit" disabled={processing} className="nx-btn-primary">
                        Send Reset Link
                    </button>

                    <a href="/login" className="text-center text-sm" style={{ color: 'var(--nx-blue)' }}>
                        Back to Sign In
                    </a>
                </form>
            </div>
        </main>
    );
}
