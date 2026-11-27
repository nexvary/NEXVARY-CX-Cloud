import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Shield, QrCode, KeyRound, Eye, EyeOff, CheckCircle2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Props {
    qr_url: string;
    secret: string;
    enrolled: boolean;
}

export default function MfaSetup({ qr_url, secret, enrolled }: Props) {
    const [showSecret, setShowSecret] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const handleEnable = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/mfa/enroll');
    };

    const disableForm = useForm({ password: '', code: '' });
    const handleDisable = (e: React.FormEvent) => {
        e.preventDefault();
        disableForm.post('/settings/mfa/disable');
    };

    return (
        <AppLayout title="Two-Factor Authentication">
            <Head title="Two-Factor Authentication" />

            <div className="nx-page-header">
                <h1 className="nx-page-title flex items-center gap-2">
                    <Shield className="w-5 h-5" style={{ color: 'var(--nx-green)' }} />
                    Two-Factor Authentication (MFA)
                </h1>
                <p className="nx-page-subtitle">Protect your account with TOTP-based verification</p>
            </div>

            <div className="p-6 max-w-xl space-y-6 nx-animate-in">

                {enrolled ? (
                    /* ── MFA already enabled ─────────────────────────────── */
                    <>
                        <div
                            className="nx-card p-5 flex items-center gap-4"
                            style={{ borderColor: 'var(--nx-green)', borderLeftWidth: '3px' }}
                        >
                            <CheckCircle2 className="w-6 h-6 shrink-0" style={{ color: 'var(--nx-green)' }} />
                            <div>
                                <p className="text-sm font-semibold text-nx-primary">MFA is enabled</p>
                                <p className="text-xs text-nx-muted">Your account requires a verification code at every sign-in.</p>
                            </div>
                        </div>

                        <div className="nx-card p-6 space-y-4">
                            <h2 className="text-sm font-semibold text-nx-primary">Recovery Codes</h2>
                            <p className="text-sm text-nx-secondary">
                                Use a recovery code to sign in if you lose access to your authenticator app.
                                Each code can be used only once.
                            </p>
                            <a
                                href="/settings/mfa/recovery"
                                className="nx-btn-ghost inline-flex items-center gap-2 text-sm"
                            >
                                <KeyRound className="w-4 h-4" />
                                View / Regenerate Recovery Codes
                            </a>
                        </div>

                        <div className="nx-card p-6 space-y-4">
                            <h2 className="text-sm font-semibold" style={{ color: 'var(--nx-error)' }}>Disable MFA</h2>
                            <p className="text-sm text-nx-secondary">
                                Requires your current password and a valid authentication code.
                            </p>
                            <form onSubmit={handleDisable} className="space-y-3">
                                <label className="grid gap-1.5 text-sm">
                                    Current Password
                                    <input
                                        type="password"
                                        className="nx-input"
                                        dir="ltr"
                                        value={disableForm.data.password}
                                        onChange={e => disableForm.setData('password', e.target.value)}
                                        required
                                        autoComplete="current-password"
                                    />
                                    {disableForm.errors.password && (
                                        <p className="nx-notice nx-notice-error">{disableForm.errors.password}</p>
                                    )}
                                </label>
                                <label className="grid gap-1.5 text-sm">
                                    Authentication Code
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        className="nx-input"
                                        dir="ltr"
                                        value={disableForm.data.code}
                                        onChange={e => disableForm.setData('code', e.target.value)}
                                        required
                                        pattern="[0-9]{6}"
                                        maxLength={6}
                                        autoComplete="one-time-code"
                                    />
                                    {disableForm.errors.code && (
                                        <p className="nx-notice nx-notice-error">{disableForm.errors.code}</p>
                                    )}
                                </label>
                                <button
                                    type="submit"
                                    disabled={disableForm.processing}
                                    className="nx-btn-ghost text-sm"
                                    style={{ color: 'var(--nx-error)', borderColor: 'var(--nx-error)' }}
                                >
                                    Disable MFA
                                </button>
                            </form>
                        </div>
                    </>
                ) : (
                    /* ── Enrollment flow ────────────────────────────────── */
                    <>
                        <div className="nx-card p-6 space-y-5">
                            <div className="flex items-center gap-2 mb-1">
                                <QrCode className="w-4 h-4" style={{ color: 'var(--nx-blue)' }} />
                                <h2 className="text-sm font-semibold text-nx-primary">1. Scan QR Code</h2>
                            </div>
                            <p className="text-sm text-nx-secondary">
                                Open your authenticator app (Google Authenticator, Authy, 1Password, etc.)
                                and scan this QR code.
                            </p>

                            {/* QR Code — displayed via img tag pointing to a QR generator URL */}
                            <div className="flex justify-center p-4 rounded-xl bg-white">
                                <img
                                    src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qr_url)}`}
                                    alt="MFA QR Code"
                                    className="w-48 h-48"
                                />
                            </div>

                            <div>
                                <p className="text-xs text-nx-muted mb-1">Manual entry key:</p>
                                <div
                                    className="flex items-center gap-2 p-3 rounded-xl font-mono text-sm"
                                    style={{ background: 'var(--nx-bg-elevated)' }}
                                >
                                    <span className={showSecret ? '' : 'blur-sm select-none'}>
                                        {secret}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setShowSecret(v => !v)}
                                        className="ms-auto"
                                    >
                                        {showSecret
                                            ? <EyeOff className="w-4 h-4 text-nx-muted" />
                                            : <Eye className="w-4 h-4 text-nx-muted" />}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div className="nx-card p-6 space-y-4">
                            <h2 className="text-sm font-semibold text-nx-primary">2. Confirm Code</h2>
                            <p className="text-sm text-nx-secondary">
                                Enter the 6-digit code from your authenticator app to complete enrollment.
                            </p>
                            <form onSubmit={handleEnable} className="space-y-3">
                                <label className="grid gap-1.5 text-sm">
                                    Verification Code
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        className="nx-input text-center tracking-widest text-lg"
                                        dir="ltr"
                                        value={data.code}
                                        onChange={e => setData('code', e.target.value)}
                                        required
                                        pattern="[0-9]{6}"
                                        maxLength={6}
                                        autoComplete="one-time-code"
                                        autoFocus
                                    />
                                    {errors.code && (
                                        <p role="alert" className="nx-notice nx-notice-error">{errors.code}</p>
                                    )}
                                </label>
                                <button type="submit" disabled={processing} className="nx-btn-primary w-full">
                                    Enable MFA
                                </button>
                            </form>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
