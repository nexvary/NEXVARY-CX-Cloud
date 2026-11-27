import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, ExternalLink, Info, CheckCircle2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

// ── Types ─────────────────────────────────────────────────────────────────────

interface PresetDefaults {
    sip_host?: string;
    sip_port?: number;
    transport?: string;
    realm?: string;
    proxy?: string;
    register?: boolean;
    caller_id_in_from?: boolean;
    auth_mode?: string;
}

interface Preset {
    key: string;
    label: string;
    version: string;
    docs_url: string | null;
    auth_modes: string[];
    defaults: Record<string, PresetDefaults>;
    preset_fields: string[];
    required_fields: string[];
    instructions: { en: string; ar: string };
}

interface Trunk {
    id?: string;
    name?: string;
    preset_key?: string;
    auth_mode?: string;
    sip_host?: string;
    sip_port?: number;
    transport?: string;
    proxy?: string;
    username?: string;
    realm?: string;
    caller_id_name?: string;
    caller_id_number?: string;
    caller_id_in_from?: boolean;
    register?: boolean;
    enabled?: boolean;
    preset_version?: string;
}

interface Props {
    trunk?: Trunk;
    presets: Record<string, Preset>;
}

// ── Provider pill ─────────────────────────────────────────────────────────────

const PROVIDER_COLORS: Record<string, string> = {
    telnyx:     'var(--nx-blue)',
    twilio:     '#e8232e',
    signalwire: '#049f8b',
    voicetel:   'var(--nx-warning)',
    generic:    'var(--nx-silver)',
};

const ProviderPill: React.FC<{ label: string; pKey: string; selected: boolean; onClick: () => void }> = ({
    label, pKey, selected, onClick,
}) => {
    const color = PROVIDER_COLORS[pKey] ?? 'var(--nx-silver)';
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl border transition-all"
            style={{
                background:   selected ? `${color}18` : 'var(--nx-bg-elevated)',
                borderColor:  selected ? color : 'var(--nx-border)',
                color:        selected ? color : 'var(--nx-text-secondary)',
                boxShadow:    selected ? `0 0 12px ${color}30` : 'none',
            }}
        >
            {selected && <CheckCircle2 className="w-3.5 h-3.5" />}
            <span className="text-sm font-medium">{label}</span>
        </button>
    );
};

// ── Main Form ─────────────────────────────────────────────────────────────────

const TrunkForm: React.FC<Props> = ({ trunk, presets }) => {
    const isEdit     = Boolean(trunk?.id);
    const presetList = Object.values(presets);

    const { data, setData, post, patch, processing, errors } = useForm({
        name:               trunk?.name ?? '',
        preset_key:         trunk?.preset_key ?? '',
        auth_mode:          trunk?.auth_mode ?? 'credential',
        sip_host:           trunk?.sip_host ?? '',
        sip_port:           trunk?.sip_port ?? 5060,
        transport:          trunk?.transport ?? 'udp',
        proxy:              trunk?.proxy ?? '',
        username:           trunk?.username ?? '',
        sip_password:       '',
        realm:              trunk?.realm ?? '',
        caller_id_name:     trunk?.caller_id_name ?? '',
        caller_id_number:   trunk?.caller_id_number ?? '',
        caller_id_in_from:  trunk?.caller_id_in_from ?? true,
        register:           trunk?.register ?? true,
        enabled:            trunk?.enabled ?? false,
        preset_version:     trunk?.preset_version ?? '',
        // SignalWire space derivation (UI-only, not sent to server)
        _space_name:        '',
    });

    const [showUnlockWarning, setShowUnlockWarning] = useState(false);
    const [fieldsUnlocked, setFieldsUnlocked] = useState(false);

    const selectedPreset   = data.preset_key ? presets[data.preset_key] : null;
    const authModes        = selectedPreset?.auth_modes ?? ['credential', 'ip_acl'];
    const presetFieldsLocked = fieldsUnlocked ? [] : (selectedPreset?.preset_fields ?? []);
    const presetInstructions = selectedPreset?.instructions.en ?? '';
    const docsUrl            = selectedPreset?.docs_url ?? null;

    const applyPresetDefaults = (key: string, mode: string) => {
        const p = presets[key];
        if (!p) return;
        const defaults = p.defaults[mode] ?? p.defaults[p.auth_modes[0]] ?? {};
        setData(prev => ({
            ...prev,
            preset_key:        key,
            auth_mode:         mode,
            _space_name:       '',
            sip_host:          defaults.sip_host          ?? prev.sip_host,
            sip_port:          defaults.sip_port          ?? prev.sip_port,
            transport:         defaults.transport          ?? prev.transport,
            realm:             defaults.realm              ?? prev.realm,
            proxy:             defaults.proxy              ?? prev.proxy ?? '',
            register:          defaults.register           ?? prev.register,
            caller_id_in_from: defaults.caller_id_in_from ?? prev.caller_id_in_from,
            preset_version:    p.version,
        }));
    };

    const selectProvider = (key: string) => {
        const mode = presets[key]?.auth_modes[0] ?? 'credential';
        applyPresetDefaults(key, mode);
        setShowUnlockWarning(false);
        setFieldsUnlocked(false);
    };

    const handleSpaceNameChange = (spaceName: string) => {
        setData(prev => ({
            ...prev,
            _space_name: spaceName,
            sip_host: spaceName ? `${spaceName}.sip.signalwire.com` : prev.sip_host,
            realm:    spaceName ? `${spaceName}.sip.signalwire.com` : prev.realm,
        }));
    };

    const isPresetLocked = (field: string) =>
        presetFieldsLocked.includes(field);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit) {
            patch(`/pbx/trunks/${trunk!.id}`);
        } else {
            post('/pbx/trunks');
        }
    };

    const fieldClass = (field: string, extra = '') =>
        `nx-input ${isPresetLocked(field) ? 'opacity-60 cursor-not-allowed' : ''} ${extra}`;

    return (
        <AppLayout title={isEdit ? 'Edit SIP Trunk' : 'New SIP Trunk'}>
            <Head title={isEdit ? 'Edit SIP Trunk' : 'New SIP Trunk'} />

            <div className="nx-page-header flex items-center gap-3">
                <Link href="/pbx/trunks" className="nx-btn-ghost p-1.5">
                    <ArrowLeft className="w-4 h-4" />
                </Link>
                <div>
                    <h1 className="nx-page-title">{isEdit ? `Edit: ${trunk?.name}` : 'New SIP Trunk'}</h1>
                    <p className="nx-page-subtitle">SIP Gateway Configuration</p>
                </div>
            </div>

            <div className="p-6 max-w-3xl nx-animate-in">
                <form onSubmit={submit} className="space-y-6">

                    {/* ── Provider selection ─────────────────────────────────── */}
                    <div className="nx-card p-6 space-y-4">
                        <h2 className="text-sm font-semibold text-nx-primary">1. Select Provider</h2>
                        <div className="flex flex-wrap gap-2">
                            {presetList.map(p => (
                                <ProviderPill
                                    key={p.key}
                                    pKey={p.key}
                                    label={p.label}
                                    selected={data.preset_key === p.key}
                                    onClick={() => selectProvider(p.key)}
                                />
                            ))}
                        </div>

                        {selectedPreset && presetInstructions && (
                            <div
                                className="flex gap-3 p-4 rounded-xl text-sm"
                                style={{ background: 'var(--nx-blue-muted)', borderLeft: '3px solid var(--nx-blue)' }}
                            >
                                <Info className="w-4 h-4 shrink-0 mt-0.5" style={{ color: 'var(--nx-blue)' }} />
                                <div>
                                    <p style={{ color: 'var(--nx-text-primary)' }}>{presetInstructions}</p>
                                    {docsUrl && (
                                        <a
                                            href={docsUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1 mt-2 text-xs"
                                            style={{ color: 'var(--nx-blue)' }}
                                        >
                                            Official documentation
                                            <ExternalLink className="w-3 h-3" />
                                        </a>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Auth mode selector — shown when provider supports multiple modes */}
                        {authModes.length > 1 && (
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium text-nx-secondary uppercase tracking-wide">
                                    Authentication Mode
                                </label>
                                <div className="flex gap-2">
                                    {authModes.map(mode => (
                                        <button
                                            key={mode}
                                            type="button"
                                            onClick={() => applyPresetDefaults(data.preset_key, mode)}
                                            className="px-3 py-1.5 rounded-lg text-xs font-medium transition-all border"
                                            style={{
                                                background:  data.auth_mode === mode ? 'var(--nx-green)' : 'var(--nx-bg-elevated)',
                                                color:       data.auth_mode === mode ? '#000' : 'var(--nx-text-secondary)',
                                                borderColor: data.auth_mode === mode ? 'var(--nx-green)' : 'var(--nx-border)',
                                            }}
                                        >
                                            {mode === 'credential' ? 'SIP Credentials' : 'IP Authentication'}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* ── Gateway identity ───────────────────────────────────── */}
                    <div className="nx-card p-6 space-y-4">
                        <h2 className="text-sm font-semibold text-nx-primary">2. Gateway Identity</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <label className="col-span-2 grid gap-1.5 text-sm">
                                Gateway Name
                                <input
                                    className="nx-input"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    required
                                    placeholder="e.g. Telnyx-Primary"
                                />
                                {errors.name && <p className="nx-error">{errors.name}</p>}
                            </label>

                            {/* SignalWire space name helper */}
                            {data.preset_key === 'signalwire' && (
                                <label className="col-span-2 grid gap-1.5 text-sm">
                                    SignalWire Space Name
                                    <input
                                        className="nx-input"
                                        value={data._space_name}
                                        onChange={e => handleSpaceNameChange(e.target.value)}
                                        placeholder="mycompany"
                                    />
                                    <span className="text-xs text-nx-muted">
                                        Derives SIP host: {data._space_name || '<space>'}.sip.signalwire.com
                                    </span>
                                </label>
                            )}

                            {/* SIP Host */}
                            {data.preset_key !== 'signalwire' && (
                                <label className="col-span-2 grid gap-1.5 text-sm">
                                    SIP Host
                                    {isPresetLocked('sip_host') && (
                                        <PresetBadge onOverride={() => setShowUnlockWarning(true)} />
                                    )}
                                    <input
                                        className={fieldClass('sip_host')}
                                        value={data.sip_host}
                                        onChange={e => setData('sip_host', e.target.value)}
                                        readOnly={isPresetLocked('sip_host')}
                                        required
                                        dir="ltr"
                                        placeholder="sip.example.com"
                                    />
                                    {errors.sip_host && <p className="nx-error">{errors.sip_host}</p>}
                                </label>
                            )}

                            <label className="grid gap-1.5 text-sm">
                                Port
                                {isPresetLocked('sip_port') && <PresetBadge onOverride={() => setShowUnlockWarning(true)} />}
                                <input
                                    type="number"
                                    className={fieldClass('sip_port')}
                                    value={data.sip_port}
                                    onChange={e => setData('sip_port', Number(e.target.value))}
                                    readOnly={isPresetLocked('sip_port')}
                                    min={1} max={65535} dir="ltr"
                                />
                            </label>

                            <label className="grid gap-1.5 text-sm">
                                Transport
                                {isPresetLocked('transport') && <PresetBadge onOverride={() => setShowUnlockWarning(true)} />}
                                <select
                                    className="nx-input"
                                    value={data.transport}
                                    onChange={e => setData('transport', e.target.value)}
                                    disabled={isPresetLocked('transport')}
                                >
                                    <option value="udp">UDP</option>
                                    <option value="tcp">TCP</option>
                                    <option value="tls">TLS (recommended)</option>
                                </select>
                            </label>

                            <label className="grid gap-1.5 text-sm">
                                Proxy
                                <input
                                    className="nx-input"
                                    value={data.proxy}
                                    onChange={e => setData('proxy', e.target.value)}
                                    dir="ltr"
                                    placeholder="optional"
                                />
                            </label>

                            <label className="grid gap-1.5 text-sm">
                                Realm
                                {isPresetLocked('realm') && <PresetBadge onOverride={() => setShowUnlockWarning(true)} />}
                                <input
                                    className={fieldClass('realm')}
                                    value={data.realm}
                                    onChange={e => setData('realm', e.target.value)}
                                    readOnly={isPresetLocked('realm')}
                                    dir="ltr"
                                    placeholder="optional — defaults to SIP host"
                                />
                            </label>
                        </div>
                    </div>

                    {/* ── Credentials (credential mode only) ────────────────── */}
                    {data.auth_mode === 'credential' && (
                        <div className="nx-card p-6 space-y-4">
                            <h2 className="text-sm font-semibold text-nx-primary">3. SIP Credentials</h2>
                            <div className="grid grid-cols-2 gap-4">
                                <label className="grid gap-1.5 text-sm">
                                    SIP Username
                                    <input
                                        className="nx-input"
                                        value={data.username}
                                        onChange={e => setData('username', e.target.value)}
                                        required={data.auth_mode === 'credential'}
                                        dir="ltr"
                                        autoComplete="off"
                                    />
                                    {errors.username && <p className="nx-error">{errors.username}</p>}
                                </label>

                                <label className="grid gap-1.5 text-sm">
                                    SIP Password
                                    <input
                                        type="password"
                                        className="nx-input"
                                        value={data.sip_password}
                                        onChange={e => setData('sip_password', e.target.value)}
                                        placeholder={isEdit ? '(leave blank to keep current)' : ''}
                                        dir="ltr"
                                        autoComplete="new-password"
                                    />
                                </label>
                            </div>
                        </div>
                    )}

                    {/* IP ACL info banner */}
                    {data.auth_mode === 'ip_acl' && (
                        <div
                            className="nx-card p-4 flex gap-3 items-start"
                            style={{ borderColor: 'var(--nx-warning)', borderLeftWidth: '3px' }}
                        >
                            <Info className="w-4 h-4 shrink-0 mt-0.5" style={{ color: 'var(--nx-warning)' }} />
                            <p className="text-sm" style={{ color: 'var(--nx-text-secondary)' }}>
                                IP Authentication mode — no SIP registration. Ensure this server's public IP
                                is whitelisted in your provider's IP Access Control List. No username or
                                password is required.
                            </p>
                        </div>
                    )}

                    {/* ── Caller ID & routing ────────────────────────────────── */}
                    <div className="nx-card p-6 space-y-4">
                        <h2 className="text-sm font-semibold text-nx-primary">
                            {data.auth_mode === 'credential' ? '4.' : '3.'} Caller ID & Routing
                        </h2>
                        <div className="grid grid-cols-2 gap-4">
                            <label className="grid gap-1.5 text-sm">
                                Caller ID Name
                                <input
                                    className="nx-input"
                                    value={data.caller_id_name}
                                    onChange={e => setData('caller_id_name', e.target.value)}
                                    dir="ltr"
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm">
                                Caller ID Number
                                <input
                                    className="nx-input"
                                    value={data.caller_id_number}
                                    onChange={e => setData('caller_id_number', e.target.value)}
                                    dir="ltr"
                                    placeholder="+1XXXXXXXXXX"
                                />
                            </label>

                            <label className="col-span-2 flex items-center gap-3 p-3 rounded-xl cursor-pointer"
                                style={{ background: 'var(--nx-bg-elevated)' }}>
                                <input
                                    type="checkbox"
                                    checked={data.caller_id_in_from}
                                    onChange={e => setData('caller_id_in_from', e.target.checked)}
                                    className="w-4 h-4"
                                />
                                <div>
                                    <p className="text-sm text-nx-primary font-medium">Caller ID in From header</p>
                                    <p className="text-xs text-nx-muted">
                                        When unchecked (VoiceTel), From User is the SIP account username — independent of the outbound caller ID.
                                    </p>
                                </div>
                            </label>

                            <label className="flex items-center gap-3 p-3 rounded-xl cursor-pointer"
                                style={{ background: 'var(--nx-bg-elevated)' }}>
                                <input
                                    type="checkbox"
                                    checked={data.register}
                                    onChange={e => setData('register', e.target.checked)}
                                    disabled={isPresetLocked('register')}
                                    className="w-4 h-4"
                                />
                                <div>
                                    <p className="text-sm text-nx-primary font-medium">SIP Registration</p>
                                    <p className="text-xs text-nx-muted">Required for credential auth; disabled for IP ACL.</p>
                                </div>
                            </label>

                            {isEdit && (
                                <label className="flex items-center gap-3 p-3 rounded-xl cursor-pointer"
                                    style={{ background: 'var(--nx-bg-elevated)' }}>
                                    <input
                                        type="checkbox"
                                        checked={data.enabled}
                                        onChange={e => setData('enabled', e.target.checked)}
                                        className="w-4 h-4"
                                    />
                                    <div>
                                        <p className="text-sm text-nx-primary font-medium">Enabled</p>
                                        <p className="text-xs text-nx-muted">New gateways are disabled until explicitly activated.</p>
                                    </div>
                                </label>
                            )}
                        </div>
                    </div>

                    {/* Preset override warning */}
                    {showUnlockWarning && (
                        <div
                            className="nx-card p-4 flex gap-3 items-start"
                            style={{ borderColor: 'var(--nx-warning)', borderLeftWidth: '3px' }}
                        >
                            <Info className="w-4 h-4 shrink-0 mt-0.5" style={{ color: 'var(--nx-warning)' }} />
                            <p className="text-sm" style={{ color: 'var(--nx-text-secondary)' }}>
                                You are overriding preset-recommended values. Ensure the custom values match
                                your provider's current documentation before saving.
                            </p>
                            <button
                                type="button"
                                className="text-xs underline ms-auto shrink-0"
                                onClick={() => { setFieldsUnlocked(true); setShowUnlockWarning(false); }}
                                style={{ color: 'var(--nx-warning)' }}
                            >
                                Unlock fields
                            </button>
                        </div>
                    )}

                    <div className="flex gap-3">
                        <button type="submit" disabled={processing} className="nx-btn-primary flex items-center gap-2">
                            <Save className="w-4 h-4" />
                            {isEdit ? 'Save Changes' : 'Create Gateway'}
                        </button>
                        <Link href="/pbx/trunks" className="nx-btn-ghost">Cancel</Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
};

const PresetBadge: React.FC<{ onOverride: () => void }> = ({ onOverride }) => (
    <span className="inline-flex items-center gap-1 text-xs" style={{ color: 'var(--nx-blue)' }}>
        Preset value
        <button type="button" onClick={onOverride} className="underline hover:opacity-70">
            override?
        </button>
    </span>
);

export default TrunkForm;
