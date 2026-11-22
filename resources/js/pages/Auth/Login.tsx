import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Login() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '' });

    return <main className="nx-setup-shell">
        <Head title={t('auth.login')} />
        <div className="nx-card nx-setup-card mx-auto grid w-full max-w-md gap-5 p-5 sm:p-8">
            <div className="flex items-center gap-3">
                <span className="nx-brand-mark" aria-hidden="true">N</span>
                <span className="font-bold tracking-wide" dir="ltr">NEXVARY CX Cloud</span>
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
