import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Mfa() {
    const { t } = useTranslation();
    const form = useForm({ code: '' });

    return <main className="nx-setup-shell">
        <Head title={t('auth.mfa_code')} />
        <form className="nx-card nx-setup-card mx-auto grid max-w-md gap-4 p-5 sm:p-8" onSubmit={event => {
            event.preventDefault();
            form.post('/mfa');
        }}>
            <h1 className="text-2xl font-semibold">{t('auth.mfa_code')}</h1>
            <label className="grid gap-1.5 text-sm">
                {t('auth.mfa_code')}
                <input className="nx-input" dir="ltr" name="code" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}"
                    required value={form.data.code} onChange={event => form.setData('code', event.target.value)} />
            </label>
            {form.errors.code && <p role="alert" className="nx-notice nx-notice-error">{form.errors.code}</p>}
            <button className="nx-btn-primary" disabled={form.processing}>{t('auth.login_button')}</button>
        </form>
    </main>;
}
