import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Headphones, Phone, MessageSquare, Users, CreditCard,
    Video, Bot, Settings, Plus, ChevronRight,
    Server, Database, Radio, ArrowRight, ArrowLeft,
} from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Trunk { id: string; name: string; host: string; enabled: boolean }
interface Services { freeswitch: boolean; database: boolean; redis: boolean }

interface Props {
    fusionPbx?: boolean;
    stats: {
        extensions: number; trunks: number; calls_today: number; open_tickets: number;
        active_calls: number; available_trunks: number; unread_sms: number; today_call_minutes: number;
    };
    pbx_health: { connected: boolean; version?: string };
    trunks: Trunk[];
    services: Services;
}

// ── Module card definitions ───────────────────────────────────────────────────

const useModules = () => {
    const { t } = useTranslation();
    return [
        {
            key: 'call_center',
            title: t('nav.call_center'),
            desc: t('dashboard.hub_call_center_desc'),
            icon: Headphones,
            href: '/call-center/live',
            color: 'var(--nx-blue)',
            glow: 'var(--nx-glow-blue-e)',
            muted: 'var(--nx-blue-muted)',
        },
        {
            key: 'pbx',
            title: t('nav.pbx'),
            desc: t('dashboard.hub_telephony_desc'),
            icon: Phone,
            href: '/pbx/extensions',
            color: 'var(--nx-pink)',
            glow: 'var(--nx-glow-pink)',
            muted: 'var(--nx-pink-muted)',
        },
        {
            key: 'sms',
            title: t('nav.sms'),
            desc: t('dashboard.hub_messages_desc'),
            icon: MessageSquare,
            href: '/sms',
            color: 'var(--nx-blue)',
            glow: 'var(--nx-glow-blue-e)',
            muted: 'var(--nx-blue-muted)',
        },
        {
            key: 'crm',
            title: t('nav.crm'),
            desc: t('dashboard.hub_crm_desc'),
            icon: Users,
            href: '/crm/contacts',
            color: 'var(--nx-pink)',
            glow: 'var(--nx-glow-pink)',
            muted: 'var(--nx-pink-muted)',
        },
        {
            key: 'billing',
            title: t('nav.billing'),
            desc: t('dashboard.hub_billing_desc'),
            icon: CreditCard,
            href: '/billing/accounts',
            color: 'var(--nx-green)',
            glow: 'var(--nx-glow-green)',
            muted: 'var(--nx-green-muted)',
        },
        {
            key: 'collaboration',
            title: t('nav.call_center') + ' — WebRTC',
            desc: t('dashboard.hub_collaboration_desc'),
            icon: Video,
            href: '/call-center/live',
            color: '#8b5cf6',
            glow: '0 0 20px rgba(139,92,246,0.3)',
            muted: 'rgba(139,92,246,0.12)',
        },
        {
            key: 'voice_ai',
            title: t('nav.voice_ai'),
            desc: t('dashboard.hub_voice_ai_desc'),
            icon: Bot,
            href: '/voice-ai',
            color: 'var(--nx-pink)',
            glow: 'var(--nx-glow-pink)',
            muted: 'var(--nx-pink-muted)',
        },
        {
            key: 'settings',
            title: t('nav.settings'),
            desc: t('dashboard.hub_system_desc'),
            icon: Settings,
            href: '/settings/server',
            color: 'var(--nx-blue)',
            glow: 'var(--nx-glow-blue-e)',
            muted: 'var(--nx-blue-muted)',
        },
    ];
};

// ── Dot status indicator ──────────────────────────────────────────────────────

const StatusDot: React.FC<{ ok: boolean }> = ({ ok }) => (
    <span
        className="inline-block w-2.5 h-2.5 rounded-full shrink-0"
        style={{
            background: ok ? 'var(--nx-green)' : 'var(--nx-error)',
            boxShadow: ok ? 'var(--nx-glow-green)' : 'var(--nx-glow-crimson)',
        }}
    />
);

// ── SIP Gateways Panel ────────────────────────────────────────────────────────

const SipPanel: React.FC<{ trunks: Trunk[]; services: Services }> = ({ trunks, services }) => {
    const { t, i18n } = useTranslation();
    const isRtl = i18n.dir() === 'rtl';
    const NavArrow = isRtl ? ArrowLeft : ArrowRight;

    return (
        <aside
            className="shrink-0 flex flex-col gap-3"
            style={{ width: '260px' }}
        >
            {/* SIP Gateways */}
            <div className="nx-card overflow-hidden">
                <div
                    className="flex items-center justify-between px-4 py-3 border-b"
                    style={{ borderColor: 'var(--nx-border)', background: 'var(--nx-bg-elevated)' }}
                >
                    <span className="text-sm font-semibold" style={{ color: 'var(--nx-silver)' }}>
                        {t('dashboard.sip_gateways')}
                    </span>
                    <Link
                        href="/pbx/trunks"
                        className="flex items-center gap-1 text-xs rounded-md px-2 py-1 transition-colors hover:bg-nx-card"
                        style={{ color: 'var(--nx-blue)', border: '1px solid var(--nx-blue-muted)' }}
                    >
                        <Plus className="w-3 h-3" />
                        {t('dashboard.add_gateway')}
                    </Link>
                </div>

                {trunks.length === 0 ? (
                    <div className="px-4 py-6 text-center text-xs" style={{ color: 'var(--nx-text-muted)' }}>
                        {t('dashboard.not_configured')}
                    </div>
                ) : (
                    <ul className="divide-y" style={{ borderColor: 'var(--nx-border)' }}>
                        {trunks.map(trunk => (
                            <li key={trunk.id} className="flex items-center gap-3 px-4 py-2.5">
                                <StatusDot ok={trunk.enabled} />
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs font-medium truncate" style={{ color: 'var(--nx-text-primary)' }}>
                                        {trunk.name}
                                    </p>
                                    <p className="text-xs truncate" style={{ color: 'var(--nx-text-muted)' }}>
                                        {trunk.host || t('dashboard.not_configured')}
                                    </p>
                                </div>
                                <span
                                    className="text-xs rounded px-1.5 py-0.5"
                                    style={{
                                        background: trunk.enabled ? 'var(--nx-green-muted)' : 'rgba(100,100,120,0.2)',
                                        color: trunk.enabled ? 'var(--nx-green)' : 'var(--nx-text-muted)',
                                    }}
                                >
                                    {trunk.enabled ? t('management.enabled') : t('management.disabled')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                {/* Quick nav */}
                <div className="border-t" style={{ borderColor: 'var(--nx-border)' }}>
                    {[
                        { label: t('dashboard.inbound_numbers'), href: '/pbx/dids' },
                        { label: t('dashboard.outbound_routes_link'), href: '/pbx/outbound-routes' },
                    ].map(item => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className="flex items-center justify-between px-4 py-2 text-xs transition-colors hover:bg-nx-card"
                            style={{ color: 'var(--nx-text-secondary)', borderBottom: '1px solid var(--nx-border)' }}
                        >
                            <span>{item.label}</span>
                            <NavArrow className="w-3 h-3" />
                        </Link>
                    ))}
                </div>
            </div>

            {/* Service Monitoring */}
            <div className="nx-card overflow-hidden">
                <div
                    className="px-4 py-3 border-b"
                    style={{ borderColor: 'var(--nx-border)', background: 'var(--nx-bg-elevated)' }}
                >
                    <span className="text-sm font-semibold" style={{ color: 'var(--nx-silver)' }}>
                        {t('dashboard.service_monitoring')}
                    </span>
                </div>
                <div className="p-4 flex flex-col gap-3">
                    {[
                        { icon: Radio, label: 'FreeSWITCH', ok: services.freeswitch },
                        { icon: Database, label: 'PostgreSQL', ok: services.database },
                        { icon: Server, label: 'Redis', ok: services.redis },
                    ].map(({ icon: Icon, label, ok }) => (
                        <div key={label} className="flex items-center gap-3">
                            <div
                                className="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                style={{ background: ok ? 'var(--nx-green-muted)' : 'rgba(224,32,32,0.1)' }}
                            >
                                <Icon className="w-4 h-4" style={{ color: ok ? 'var(--nx-green)' : 'var(--nx-error)' }} />
                            </div>
                            <span className="flex-1 text-sm" style={{ color: 'var(--nx-text-primary)' }}>{label}</span>
                            <StatusDot ok={ok} />
                        </div>
                    ))}
                </div>
            </div>
        </aside>
    );
};

// ── Module Card ───────────────────────────────────────────────────────────────

const ModuleCard: React.FC<{
    title: string; desc: string; icon: React.ElementType;
    href: string; color: string; glow?: string; muted: string;
}> = ({ title, desc, icon: Icon, href, color, muted }) => (
    <Link
        href={href}
        className="nx-card group relative overflow-hidden flex items-center gap-4 p-4 transition-all hover:scale-[1.02]"
        style={{ minHeight: '90px' }}
    >
        {/* Left colored accent bar */}
        <span
            className="absolute start-0 top-0 bottom-0 w-1 rounded-s-2xl"
            style={{ background: color }}
            aria-hidden="true"
        />
        {/* Hover glow overlay */}
        <span
            className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"
            style={{ background: `radial-gradient(circle at 20% 50%, ${muted}, transparent 70%)` }}
            aria-hidden="true"
        />

        {/* Icon */}
        <div
            className="relative z-10 shrink-0 w-11 h-11 rounded-xl flex items-center justify-center transition-all group-hover:scale-110"
            style={{ background: muted, boxShadow: `inset 0 1px 0 rgba(255,255,255,0.08)` }}
        >
            <Icon className="w-5 h-5" style={{ color, filter: `drop-shadow(0 0 6px ${color})` }} />
        </div>

        {/* Text */}
        <div className="relative z-10 flex-1 min-w-0">
            <p
                className="text-sm font-semibold leading-tight truncate transition-colors group-hover:text-white"
                style={{ color: 'var(--nx-text-primary)' }}
            >
                {title}
            </p>
            <p className="text-xs mt-0.5 truncate" style={{ color: 'var(--nx-text-muted)' }}>{desc}</p>
        </div>

        {/* Arrow */}
        <ChevronRight
            className="relative z-10 w-4 h-4 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity rtl:rotate-180"
            style={{ color }}
        />
    </Link>
);

// ── Main Dashboard ────────────────────────────────────────────────────────────

export default function Dashboard({ fusionPbx, stats, pbx_health, trunks = [], services }: Props) {
    const { t } = useTranslation();
    const modules = useModules();

    return (
        <AppLayout title={t('dashboard.title')}>
            {/* Platform title */}
            <div className="mb-5">
                <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>
                    {t('dashboard.platform_title')}
                </h1>
                <div className="flex flex-wrap gap-4 mt-2 text-xs" style={{ color: 'var(--nx-text-muted)' }}>
                    {!fusionPbx && <>
                        <span>
                            <span style={{ color: pbx_health.connected ? 'var(--nx-green)' : 'var(--nx-error)' }}>●</span>
                            {' '}FreeSWITCH{pbx_health.version ? ` ${pbx_health.version}` : ''}
                        </span>
                        <span>
                            <span style={{ color: 'var(--nx-blue)' }}>●</span>
                            {' '}{stats.active_calls} {t('dashboard.active_calls')}
                        </span>
                    </>}
                    <span>
                        <span style={{ color: 'var(--nx-pink)' }}>●</span>
                        {' '}{stats.unread_sms} {t('dashboard.unread_sms')}
                    </span>
                    <span>
                        <span style={{ color: 'var(--nx-warning)' }}>●</span>
                        {' '}{stats.open_tickets} {t('dashboard.open_tickets')}
                    </span>
                </div>
            </div>

            {/* Two-column body */}
            <div className="flex gap-4 items-start">
                {/* LEFT: SIP + Services panel */}
                {!fusionPbx && <SipPanel trunks={trunks} services={services ?? { freeswitch: pbx_health.connected, database: true, redis: true }} />}

                {/* RIGHT: Module cards grid */}
                <div className="flex-1 min-w-0 grid grid-cols-2 gap-3">
                    {modules.filter(m => !fusionPbx || (!m.href.startsWith('/pbx/') && m.href !== '/call-center/live')).map(m => (
                        <ModuleCard
                            key={m.key}
                            title={m.title}
                            desc={m.desc}
                            icon={m.icon}
                            href={m.href}
                            color={m.color}
                            glow={m.glow}
                            muted={m.muted}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
