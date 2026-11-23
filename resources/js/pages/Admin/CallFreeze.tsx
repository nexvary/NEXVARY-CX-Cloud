import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Snowflake, Shield, AlertTriangle, Server, Building2, ChevronDown } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface FreezeRecord {
    id: string;
    scope: 'tenant' | 'gateway';
    reason: string;
    activated_at: string;
    deactivated_at: string | null;
    active: boolean;
    tenant?: { id: string; name: string };
    trunk?: { id: string; name: string; sip_host: string };
    activated_by_user?: { name: string };
    deactivated_by_user?: { name: string } | null;
}

interface Tenant { id: string; name: string }
interface Trunk  { id: string; name: string; sip_host: string; tenant_id: string }

interface Props {
    active_freezes: FreezeRecord[];
    history: { data: FreezeRecord[]; current_page: number; last_page: number };
    tenants: Tenant[];
    trunks: Trunk[];
}

// ── Activate freeze form ──────────────────────────────────────────────────────

const ActivateForm: React.FC<{ tenants: Tenant[]; trunks: Trunk[] }> = ({ tenants, trunks }) => {
    const [scope, setScope] = useState<'tenant' | 'gateway'>('tenant');

    const tenantForm  = useForm({ tenant_id: '', reason: '' });
    const gatewayForm = useForm({ trunk_id: '',  reason: '' });

    return (
        <div className="nx-card p-6 space-y-5">
            <div className="flex items-center gap-2">
                <Snowflake className="w-4 h-4" style={{ color: 'var(--nx-pink)' }} />
                <h2 className="text-sm font-semibold text-nx-primary">Activate Emergency Freeze</h2>
            </div>

            <div
                className="p-4 rounded-xl flex gap-3"
                style={{ background: 'rgba(255,45,120,0.08)', border: '1px solid rgba(255,45,120,0.2)' }}
            >
                <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" style={{ color: 'var(--nx-pink)' }} />
                <p className="text-xs" style={{ color: 'var(--nx-text-secondary)' }}>
                    A freeze blocks new external outbound calls. Active calls, inbound calls, and internal
                    calls remain unaffected. Resuming outbound calling requires a fresh MFA verification.
                </p>
            </div>

            <div className="flex gap-2">
                {(['tenant', 'gateway'] as const).map(s => (
                    <button
                        key={s}
                        type="button"
                        onClick={() => setScope(s)}
                        className="px-4 py-2 rounded-lg text-sm font-medium transition-all border"
                        style={{
                            background:  scope === s ? 'var(--nx-pink-muted)' : 'var(--nx-bg-elevated)',
                            color:       scope === s ? 'var(--nx-pink)' : 'var(--nx-text-secondary)',
                            borderColor: scope === s ? 'var(--nx-pink)' : 'var(--nx-border)',
                        }}
                    >
                        {s === 'tenant' ? (
                            <span className="flex items-center gap-1.5"><Building2 className="w-3.5 h-3.5" /> Tenant</span>
                        ) : (
                            <span className="flex items-center gap-1.5"><Server className="w-3.5 h-3.5" /> Gateway</span>
                        )}
                    </button>
                ))}
            </div>

            {scope === 'tenant' ? (
                <form
                    className="space-y-3"
                    onSubmit={e => { e.preventDefault(); tenantForm.post('/admin/call-freeze/tenant'); }}
                >
                    <label className="grid gap-1.5 text-sm">
                        Tenant
                        <select
                            className="nx-input"
                            value={tenantForm.data.tenant_id}
                            onChange={e => tenantForm.setData('tenant_id', e.target.value)}
                            required
                        >
                            <option value="">Select tenant…</option>
                            {tenants.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </select>
                        {tenantForm.errors.tenant_id && <p className="nx-error">{tenantForm.errors.tenant_id}</p>}
                    </label>
                    <label className="grid gap-1.5 text-sm">
                        Reason (required, logged)
                        <textarea
                            className="nx-input"
                            rows={2}
                            value={tenantForm.data.reason}
                            onChange={e => tenantForm.setData('reason', e.target.value)}
                            required
                            maxLength={500}
                        />
                        {tenantForm.errors.reason && <p className="nx-error">{tenantForm.errors.reason}</p>}
                    </label>
                    <button type="submit" disabled={tenantForm.processing} className="nx-btn-primary text-sm"
                        style={{ background: 'var(--nx-pink)', color: '#fff' }}>
                        Freeze Tenant Outbound Calls
                    </button>
                </form>
            ) : (
                <form
                    className="space-y-3"
                    onSubmit={e => { e.preventDefault(); gatewayForm.post('/admin/call-freeze/gateway'); }}
                >
                    <label className="grid gap-1.5 text-sm">
                        SIP Gateway
                        <select
                            className="nx-input"
                            value={gatewayForm.data.trunk_id}
                            onChange={e => gatewayForm.setData('trunk_id', e.target.value)}
                            required
                        >
                            <option value="">Select gateway…</option>
                            {trunks.map(t => (
                                <option key={t.id} value={t.id}>{t.name} — {t.sip_host}</option>
                            ))}
                        </select>
                        {gatewayForm.errors.trunk_id && <p className="nx-error">{gatewayForm.errors.trunk_id}</p>}
                    </label>
                    <label className="grid gap-1.5 text-sm">
                        Reason (required, logged)
                        <textarea
                            className="nx-input"
                            rows={2}
                            value={gatewayForm.data.reason}
                            onChange={e => gatewayForm.setData('reason', e.target.value)}
                            required
                            maxLength={500}
                        />
                        {gatewayForm.errors.reason && <p className="nx-error">{gatewayForm.errors.reason}</p>}
                    </label>
                    <button type="submit" disabled={gatewayForm.processing} className="nx-btn-primary text-sm"
                        style={{ background: 'var(--nx-pink)', color: '#fff' }}>
                        Freeze Gateway
                    </button>
                </form>
            )}
        </div>
    );
};

// ── Deactivate freeze form ────────────────────────────────────────────────────

const DeactivateRow: React.FC<{ freeze: FreezeRecord }> = ({ freeze }) => {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    return (
        <div className="nx-card overflow-hidden">
            <div className="flex items-center gap-4 p-4">
                <Snowflake className="w-4 h-4 shrink-0" style={{ color: 'var(--nx-pink)' }} />
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-nx-primary">
                        {freeze.scope === 'tenant'
                            ? `Tenant: ${freeze.tenant?.name ?? freeze.tenant?.id}`
                            : `Gateway: ${freeze.trunk?.name ?? freeze.trunk?.id}`}
                    </p>
                    <p className="text-xs text-nx-muted truncate">{freeze.reason}</p>
                </div>
                <div className="text-xs text-nx-muted shrink-0">
                    {new Date(freeze.activated_at).toLocaleString()}
                </div>
                <button
                    type="button"
                    onClick={() => setOpen(v => !v)}
                    className="nx-btn-ghost text-xs px-2 py-1 flex items-center gap-1"
                    style={{ color: 'var(--nx-green)' }}
                >
                    Resume
                    <ChevronDown className={`w-3 h-3 transition-transform ${open ? 'rotate-180' : ''}`} />
                </button>
            </div>

            {open && (
                <div className="border-t p-4 space-y-3" style={{ borderColor: 'var(--nx-border)' }}>
                    <p className="text-xs text-nx-secondary">
                        Enter your MFA code to resume outbound calls. This action is audited.
                    </p>
                    <form
                        className="flex gap-3 items-end"
                        onSubmit={e => {
                            e.preventDefault();
                            post(`/admin/call-freeze/${freeze.id}/deactivate`);
                        }}
                    >
                        <label className="grid gap-1.5 text-sm flex-1">
                            MFA Code
                            <input
                                type="text"
                                inputMode="numeric"
                                className="nx-input"
                                dir="ltr"
                                value={data.code}
                                onChange={e => setData('code', e.target.value)}
                                required
                                pattern="[0-9]{6}"
                                maxLength={6}
                                autoComplete="one-time-code"
                            />
                            {errors.code && <p className="nx-error">{errors.code}</p>}
                        </label>
                        <button
                            type="submit"
                            disabled={processing}
                            className="nx-btn-primary text-sm"
                            style={{ background: 'var(--nx-green)', color: '#000' }}
                        >
                            Resume
                        </button>
                    </form>
                </div>
            )}
        </div>
    );
};

// ── Main Page ─────────────────────────────────────────────────────────────────

export default function CallFreezePage({ active_freezes, history, tenants, trunks }: Props) {
    return (
        <AppLayout title="Emergency Call Freeze">
            <Head title="Emergency Call Freeze" />

            <div className="nx-page-header">
                <h1 className="nx-page-title flex items-center gap-2">
                    <Shield className="w-5 h-5" style={{ color: 'var(--nx-pink)' }} />
                    Emergency Communications Freeze
                </h1>
                <p className="nx-page-subtitle">
                    Freeze or restore outbound calls by tenant or SIP gateway · audited · MFA required to restore
                </p>
            </div>

            <div className="p-6 space-y-6 nx-animate-in">

                {/* Active freezes */}
                {active_freezes.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold flex items-center gap-2" style={{ color: 'var(--nx-pink)' }}>
                            <Snowflake className="w-4 h-4" />
                            Active Freezes ({active_freezes.length})
                        </h2>
                        {active_freezes.map(f => (
                            <DeactivateRow key={f.id} freeze={f} />
                        ))}
                    </div>
                )}

                {active_freezes.length === 0 && (
                    <div className="nx-card p-5 flex items-center gap-3" style={{ borderColor: 'var(--nx-green)', borderLeftWidth: '3px' }}>
                        <Shield className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
                        <p className="text-sm text-nx-secondary">No active freezes — outbound calls are operating normally.</p>
                    </div>
                )}

                {/* Activate form */}
                <ActivateForm tenants={tenants} trunks={trunks} />

                {/* History */}
                {history.data.length > 0 && (
                    <div className="nx-card overflow-hidden">
                        <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--nx-border)' }}>
                            <h2 className="text-sm font-semibold text-nx-primary">Freeze History</h2>
                        </div>
                        <table className="nx-table">
                            <thead>
                                <tr>
                                    <th>Scope</th>
                                    <th>Reason</th>
                                    <th>Activated by</th>
                                    <th>Activated at</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.data.map(f => (
                                    <tr key={f.id}>
                                        <td className="text-sm">
                                            {f.scope === 'tenant'
                                                ? `Tenant: ${f.tenant?.name ?? '—'}`
                                                : `Gateway: ${f.trunk?.name ?? '—'}`}
                                        </td>
                                        <td className="text-xs text-nx-muted max-w-xs truncate">{f.reason}</td>
                                        <td className="text-xs">{f.activated_by_user?.name ?? '—'}</td>
                                        <td className="text-xs">{new Date(f.activated_at).toLocaleString()}</td>
                                        <td>
                                            <span className={`nx-badge ${f.active ? 'nx-badge-red' : 'nx-badge-green'} text-xs`}>
                                                {f.active ? 'Active' : 'Restored'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
