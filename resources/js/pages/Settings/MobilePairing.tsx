import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Smartphone, RefreshCw, Copy, CheckCircle, AlertTriangle } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Props {
    qr_data_uri: string | null;
    payload: string;
    expires_at: string;
}

export default function MobilePairing({ qr_data_uri, payload, expires_at }: Props) {
    const [copied, setCopied] = useState(false);

    const expiryLabel = new Date(expires_at).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });

    function refresh() {
        router.reload({ only: ['qr_data_uri', 'payload', 'expires_at'] });
    }

    async function copyPayload() {
        await navigator.clipboard.writeText(payload);
        setCopied(true);
        setTimeout(() => setCopied(false), 2500);
    }

    return (
        <AppLayout>
            <Head title="Pair Mobile App / ربط التطبيق" />

            <div className="max-w-lg mx-auto px-4 py-8">
                {/* Header */}
                <div className="flex items-center gap-3 mb-6">
                    <div className="p-2 rounded-xl" style={{ background: 'var(--nx-blue-muted)' }}>
                        <Smartphone className="w-6 h-6" style={{ color: 'var(--nx-blue)' }} />
                    </div>
                    <div>
                        <h1 className="text-lg font-semibold" style={{ color: 'var(--nx-primary)' }}>
                            ربط التطبيق / Pair Mobile App
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--nx-muted)' }}>
                            Scan with NEXVARY CX Manager on Android
                        </p>
                    </div>
                </div>

                {/* QR card */}
                <div
                    className="rounded-2xl p-6 flex flex-col items-center gap-4"
                    style={{ background: 'var(--nx-card)' }}
                >
                    {qr_data_uri ? (
                        <img
                            src={qr_data_uri}
                            alt="Pairing QR code"
                            className="w-64 h-64 rounded-xl"
                            style={{ background: '#fff', padding: '8px' }}
                        />
                    ) : (
                        <div
                            className="w-64 h-64 rounded-xl flex items-center justify-center gap-2 flex-col"
                            style={{ background: 'var(--nx-bg)', color: 'var(--nx-muted)' }}
                        >
                            <AlertTriangle className="w-8 h-8" />
                            <span className="text-sm text-center px-4">
                                QR rendering unavailable — use the copy button below
                            </span>
                        </div>
                    )}

                    {/* Expiry + refresh */}
                    <div className="flex items-center gap-2 text-sm" style={{ color: 'var(--nx-muted)' }}>
                        <span>Expires at {expiryLabel}</span>
                        <button
                            onClick={refresh}
                            className="flex items-center gap-1 hover:opacity-80 transition-opacity"
                            title="Generate a new QR"
                        >
                            <RefreshCw className="w-3.5 h-3.5" />
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>

                {/* Instructions */}
                <ol className="mt-5 space-y-2 text-sm" style={{ color: 'var(--nx-muted)' }}>
                    <li>
                        <span className="font-medium" style={{ color: 'var(--nx-primary)' }}>1.</span>{' '}
                        Open the NEXVARY CX Manager app → Server tab
                    </li>
                    <li>
                        <span className="font-medium" style={{ color: 'var(--nx-primary)' }}>2.</span>{' '}
                        Tap <strong>ربط عبر QR</strong> and point the camera at this code
                    </li>
                    <li>
                        <span className="font-medium" style={{ color: 'var(--nx-primary)' }}>3.</span>{' '}
                        The app will confirm pairing and show service status
                    </li>
                </ol>

                {/* Arabic steps */}
                <ol className="mt-3 space-y-2 text-sm text-right" dir="rtl" style={{ color: 'var(--nx-muted)' }}>
                    <li>
                        <span className="font-medium" style={{ color: 'var(--nx-primary)' }}>١.</span>{' '}
                        افتح تطبيق NEXVARY CX Manager ← تبويب الخادم
                    </li>
                    <li>
                        <span className="font-medium" style={{ color: 'var(--nx-primary)' }}>٢.</span>{' '}
                        اضغط <strong>ربط عبر QR</strong> ووجّه الكاميرا نحو الرمز
                    </li>
                    <li>
                        <span className="font-medium" style={{ color: 'var(--nx-primary)' }}>٣.</span>{' '}
                        سيؤكد التطبيق الإقران ويعرض حالة الخدمات
                    </li>
                </ol>

                {/* Manual paste fallback */}
                <div className="mt-6">
                    <p className="text-xs mb-2" style={{ color: 'var(--nx-muted)' }}>
                        No camera? Use manual paste in the app instead:
                    </p>
                    <div
                        className="rounded-xl p-3 flex items-center gap-3"
                        style={{ background: 'var(--nx-bg)' }}
                    >
                        <code
                            className="flex-1 text-xs break-all select-all"
                            style={{ color: 'var(--nx-primary)' }}
                        >
                            {payload}
                        </code>
                        <button
                            onClick={copyPayload}
                            className="shrink-0 p-2 rounded-lg hover:opacity-80 transition-opacity"
                            style={{ background: 'var(--nx-blue-muted)' }}
                            title="Copy to clipboard"
                        >
                            {copied
                                ? <CheckCircle className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
                                : <Copy className="w-4 h-4" style={{ color: 'var(--nx-blue)' }} />
                            }
                        </button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
