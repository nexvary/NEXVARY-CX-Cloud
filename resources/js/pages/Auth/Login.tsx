import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Login() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '' });

    return <main className="nx-setup-shell">
        <Head title={t('auth.login')} />
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
            <h1 className="text-2xl font-semibold">{t('auth.login')}</h1>
            <form className="grid gap-4" onSubmit={event => {
                event.preventDefault();
                post('/login', { onFinish: () => reset('password') });
            }}>
                <label className="grid gap-1.5 text-sm">
                    {t('auth.email')}
                    <input name="email" type="email" value={data.email} required dir="ltr" aria-invalid={!!errors.email}
                        onChange={event => setData('email', event.target.value)} className="nx-input" autoComplete="username" />
                </label>
                <label className="grid gap-1.5 text-sm">
                    {t('auth.password')}
                    <input name="password" type="password" required dir="ltr" value={data.password}
                        onChange={event => setData('password', event.target.value)} className="nx-input" autoComplete="current-password" />
                </label>
                {(errors.email || errors.password) && <p role="alert" className="nx-notice nx-notice-error">{errors.email ?? errors.password}</p>}
                <button type="submit" disabled={processing} className="nx-btn-primary">{t('auth.login_button')}</button>
            </form>
        </div>
    </main>;
}
