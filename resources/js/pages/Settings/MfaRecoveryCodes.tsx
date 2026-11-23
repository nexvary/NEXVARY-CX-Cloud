import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, AlertTriangle, RefreshCw } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Props {
    codes: string[];
    just_set: boolean;
}

export default function MfaRecoveryCodes({ codes, just_set }: Props) {
    const { data, setData, post, processing, errors } = useForm({ password: '' });

    const handleRegenerate = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/mfa/recovery/regenerate');
    };

    return (
        <AppLayout title="Recovery Codes">
            <Head title="MFA Recovery Codes" />

            <div className="nx-page-header">
                <h1 className="nx-page-title flex items-center gap-2">
                    <KeyRound className="w-5 h-5" style={{ color: 'var(--nx-warning)' }} />
                    Recovery Codes
                </h1>
                <p className="nx-page-subtitle">Use these if you lose access to your authenticator app</p>
            </div>

            <div className="p-6 max-w-xl space-y-6 nx-animate-in">

                {just_set && (
                    <div
                        className="nx-card p-4 flex gap-3"
                        style={{ borderColor: 'var(--nx-warning)', borderLeftWidth: '3px' }}
                    >
                        <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" style={{ color: 'var(--nx-warning)' }} />
                        <p className="text-sm" style={{ color: 'var(--nx-text-primary)' }}>
                            Save these codes now. They will not be shown again. Store them in a secure location
                            (password manager, printed copy in a safe). Each code can be used only once.
                        </p>
                    </div>
                )}

                {codes.length > 0 ? (
                    <div className="nx-card p-6 space-y-3">
                        <div className="grid grid-cols-2 gap-2">
                            {codes.map((code, i) => (
                                <div
                                    key={i}
                                    className="p-2.5 rounded-lg text-center font-mono text-sm tracking-wider"
                                    style={{ background: 'var(--nx-bg-elevated)', color: 'var(--nx-green)' }}
                                >
                                    {code}
                                </div>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                navigator.clipboard.writeText(codes.join('\n'));
                            }}
                            className="nx-btn-ghost text-xs w-full"
                        >
                            Copy all codes
                        </button>
                    </div>
                ) : (
                    <div className="nx-card p-8 text-center">
                        <KeyRound className="w-8 h-8 mx-auto mb-3 opacity-30" />
                        <p className="text-sm text-nx-muted">Recovery codes have already been viewed.</p>
                        <p className="text-xs text-nx-muted mt-1">Regenerate below to get new codes.</p>
                    </div>
                )}

                <div className="nx-card p-6 space-y-4">
                    <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
                        <RefreshCw className="w-4 h-4" />
                        Regenerate Recovery Codes
                    </h2>
                    <p className="text-sm text-nx-secondary">
                        Regenerating invalidates all current recovery codes. Requires your current password.
                    </p>
                    <form onSubmit={handleRegenerate} className="space-y-3">
                        <label className="grid gap-1.5 text-sm">
                            Current Password
                            <input
                                type="password"
                                className="nx-input"
                                dir="ltr"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                required
                                autoComplete="current-password"
                            />
                            {errors.password && <p className="nx-notice nx-notice-error">{errors.password}</p>}
                        </label>
                        <button type="submit" disabled={processing} className="nx-btn-primary text-sm">
                            Regenerate Codes
                        </button>
                    </form>
                </div>

                <a href="/settings/mfa/enroll" className="nx-btn-ghost text-sm inline-flex">
                    ← Back to MFA Settings
                </a>
            </div>
        </AppLayout>
    );
}
