import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Login() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('login.post'));
    }

    return (
        <>
            <Head title={t('login')} />
            <div className="min-h-screen flex items-center justify-center nx-bg-base">
                <div className="w-full max-w-md p-8 nx-card">
                    {/* Logo */}
                    <div className="text-center mb-8">
                        <h1 className="text-2xl font-bold nx-text-silver tracking-widest uppercase">
                            NEXVARY
                        </h1>
                        <p className="text-sm nx-text-muted mt-1">CX Cloud Platform</p>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label className="block text-sm nx-text-muted mb-1">{t('email')}</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                className="nx-input w-full"
                                autoComplete="email"
                            />
                            {errors.email && <p className="text-xs nx-text-crimson mt-1">{errors.email}</p>}
                        </div>

                        <div>
                            <label className="block text-sm nx-text-muted mb-1">{t('password')}</label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                className="nx-input w-full"
                                autoComplete="current-password"
                            />
                            {errors.password && <p className="text-xs nx-text-crimson mt-1">{errors.password}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="nx-btn-primary w-full"
                        >
                            {processing ? t('loading') : t('sign_in')}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
