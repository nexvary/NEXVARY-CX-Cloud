import React from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
  Phone, PhoneCall, Users, MessageSquare, Ticket,
  CreditCard, Server, AlertTriangle, TrendingUp, TrendingDown,
  Activity, Clock, BarChart3
} from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';

// ── Types ──────────────────────────────────────────────────────────────────

interface DashboardProps {
  stats: {
    active_calls: number;
    registered_extensions: number;
    total_extensions: number;
    online_agents: number;
    total_agents: number;
    waiting_calls: number;
    available_trunks: number;
    total_trunks: number;
    unread_sms: number;
    unread_email: number;
    open_tickets: number;
    today_call_cost: string;
    today_call_count: number;
    today_call_minutes: number;
  };
  pbx: {
    connected: boolean;
    version?: string;
    uptime?: string;
  };
  server: {
    cpu_percent: number;
    ram_percent: number;
    disk_percent: number;
    load_avg: string;
  };
  call_volume_chart: Array<{
    hour: string;
    calls: number;
    answered: number;
  }>;
  recent_calls: Array<{
    id: string;
    caller: string;
    destination: string;
    duration: string;
    status: string;
    started_at: string;
  }>;
  security_alerts: number;
}

// ── Stat Card Component ────────────────────────────────────────────────────

const StatCard: React.FC<{
  title: string;
  value: string | number;
  sub?: string;
  icon: React.ElementType;
  color?: 'green' | 'red' | 'blue' | 'yellow' | 'default';
  trend?: 'up' | 'down' | 'neutral';
  alert?: boolean;
}> = ({ title, value, sub, icon: Icon, color = 'default', trend, alert }) => {
  const colorMap = {
    green:   'var(--nx-success)',
    red:     'var(--nx-error)',
    blue:    'var(--nx-info)',
    yellow:  'var(--nx-warning)',
    default: 'var(--nx-silver-dim)',
  };
  const iconColor = colorMap[color];

  return (
    <div className={`nx-stat-card ${alert ? 'border-red-500/30' : ''}`}>
      <div className="flex items-start justify-between mb-3">
        <p className="text-xs font-medium text-nx-muted uppercase tracking-wider">{title}</p>
        <div
          className="w-8 h-8 rounded-lg flex items-center justify-center"
          style={{ background: `${iconColor}15` }}
        >
          <Icon className="w-4 h-4" style={{ color: iconColor }} />
        </div>
      </div>
      <div className="flex items-end justify-between">
        <div>
          <p className="text-2xl font-bold text-nx-primary">{value}</p>
          {sub && <p className="text-xs text-nx-muted mt-0.5">{sub}</p>}
        </div>
        {trend && (
          <div className={`flex items-center gap-1 text-xs ${
            trend === 'up' ? 'text-green-400' : trend === 'down' ? 'text-red-400' : 'text-nx-muted'
          }`}>
            {trend === 'up' ? <TrendingUp className="w-3.5 h-3.5" /> : <TrendingDown className="w-3.5 h-3.5" />}
          </div>
        )}
      </div>
    </div>
  );
};

// ── Server Resource Bar ────────────────────────────────────────────────────

const ResourceBar: React.FC<{ label: string; percent: number }> = ({ label, percent }) => {
  const color = percent > 90 ? 'var(--nx-error)' : percent > 70 ? 'var(--nx-warning)' : 'var(--nx-info)';
  return (
    <div>
      <div className="flex justify-between text-xs mb-1">
        <span className="text-nx-secondary">{label}</span>
        <span className="text-nx-primary font-medium">{percent}%</span>
      </div>
      <div className="h-1.5 rounded-full bg-nx-elevated overflow-hidden">
        <div
          className="h-full rounded-full transition-all duration-500"
          style={{ width: `${percent}%`, background: color }}
        />
      </div>
    </div>
  );
};

// ── Tooltip (Recharts) ─────────────────────────────────────────────────────

const ChartTooltip: React.FC<any> = ({ active, payload, label }) => {
  if (!active || !payload?.length) return null;
  return (
    <div className="nx-card px-3 py-2 text-xs shadow-xl">
      <p className="text-nx-muted mb-1">{label}</p>
      {payload.map((p: any) => (
        <p key={p.dataKey} style={{ color: p.color }}>
          {p.name}: {p.value}
        </p>
      ))}
    </div>
  );
};

// ── Dashboard Page ─────────────────────────────────────────────────────────

const Dashboard: React.FC<DashboardProps> = ({
  stats, pbx, server, call_volume_chart, recent_calls, security_alerts
}) => {
  const { t } = useTranslation();

  return (
    <AppLayout title={t('nav.dashboard')}>
      <Head title={t('nav.dashboard')} />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">{t('dashboard.title')}</h1>
          <p className="nx-page-subtitle">{t('dashboard.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2 text-xs text-nx-muted">
          <Clock className="w-3.5 h-3.5" />
          <span>{t('dashboard.last_updated')}: {new Date().toLocaleTimeString()}</span>
        </div>
      </div>

      <div className="p-6 space-y-6 nx-animate-in">
        {/* ── Security Alert ─────────────────────────────────────── */}
        {security_alerts > 0 && (
          <div className="flex items-center gap-3 p-4 rounded-xl bg-red-500/10 border border-red-500/30">
            <AlertTriangle className="w-5 h-5 text-red-400 shrink-0" />
            <div>
              <p className="text-sm font-medium text-red-300">
                {security_alerts} {t('dashboard.security_alerts')}
              </p>
              <p className="text-xs text-red-400/70 mt-0.5">
                <a href="/settings/security" className="underline">{t('common.view_details')}</a>
              </p>
            </div>
          </div>
        )}

        {/* ── Primary Stats Grid ─────────────────────────────────── */}
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
          <StatCard
            title={t('dashboard.active_calls')}
            value={stats.active_calls}
            icon={PhoneCall}
            color={stats.active_calls > 0 ? 'green' : 'default'}
          />
          <StatCard
            title={t('dashboard.registered_ext')}
            value={`${stats.registered_extensions}/${stats.total_extensions}`}
            sub={t('dashboard.online')}
            icon={Phone}
            color={stats.registered_extensions > 0 ? 'blue' : 'red'}
          />
          <StatCard
            title={t('dashboard.online_agents')}
            value={`${stats.online_agents}/${stats.total_agents}`}
            icon={Users}
            color={stats.online_agents > 0 ? 'green' : 'default'}
          />
          <StatCard
            title={t('dashboard.waiting_calls')}
            value={stats.waiting_calls}
            icon={Clock}
            color={stats.waiting_calls > 5 ? 'yellow' : 'default'}
            alert={stats.waiting_calls > 10}
          />
          <StatCard
            title={t('dashboard.unread_sms')}
            value={stats.unread_sms}
            icon={MessageSquare}
            color={stats.unread_sms > 0 ? 'blue' : 'default'}
          />
          <StatCard
            title={t('dashboard.open_tickets')}
            value={stats.open_tickets}
            icon={Ticket}
            color={stats.open_tickets > 0 ? 'yellow' : 'default'}
          />
        </div>

        {/* ── Today Summary ──────────────────────────────────────── */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <StatCard
            title={t('dashboard.today_calls')}
            value={stats.today_call_count}
            sub={`${stats.today_call_minutes} ${t('dashboard.minutes')}`}
            icon={Activity}
            color="blue"
          />
          <StatCard
            title={t('dashboard.today_cost')}
            value={`$${stats.today_call_cost}`}
            icon={CreditCard}
            color="default"
          />
          <StatCard
            title={t('dashboard.trunks')}
            value={`${stats.available_trunks}/${stats.total_trunks}`}
            sub={t('dashboard.available')}
            icon={Server}
            color={stats.available_trunks > 0 ? 'green' : 'red'}
          />
        </div>

        {/* ── Charts Row ─────────────────────────────────────────── */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          {/* Call Volume Chart */}
          <div className="nx-card p-5 lg:col-span-2">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-sm font-semibold text-nx-primary">{t('dashboard.call_volume_today')}</h3>
              <BarChart3 className="w-4 h-4 text-nx-muted" />
            </div>
            {call_volume_chart.length > 0 ? (
              <ResponsiveContainer width="100%" height={160}>
                <AreaChart data={call_volume_chart}>
                  <defs>
                    <linearGradient id="callGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#e02020" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="#e02020" stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="ansGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <XAxis dataKey="hour" tick={{ fill: '#5c5f7a', fontSize: 11 }} />
                  <YAxis tick={{ fill: '#5c5f7a', fontSize: 11 }} />
                  <Tooltip content={<ChartTooltip />} />
                  <Area type="monotone" dataKey="calls" name="Total" stroke="#e02020" fill="url(#callGrad)" strokeWidth={1.5} dot={false} />
                  <Area type="monotone" dataKey="answered" name="Answered" stroke="#3b82f6" fill="url(#ansGrad)" strokeWidth={1.5} dot={false} />
                </AreaChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-40 flex items-center justify-center text-nx-muted text-sm">
                {t('dashboard.no_calls_today')}
              </div>
            )}
          </div>

          {/* Server Health */}
          <div className="nx-card p-5">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-sm font-semibold text-nx-primary">{t('dashboard.server_health')}</h3>
              <div className="flex items-center gap-1.5 text-xs text-green-400">
                <span className="nx-dot nx-dot-green" />
                {t('status.normal')}
              </div>
            </div>
            <div className="space-y-4">
              <ResourceBar label="CPU" percent={server.cpu_percent} />
              <ResourceBar label="RAM" percent={server.ram_percent} />
              <ResourceBar label={t('dashboard.disk')} percent={server.disk_percent} />
            </div>
            <div className="mt-4 pt-4 border-t border-nx">
              <p className="text-xs text-nx-muted">
                {t('dashboard.load_avg')}: <span className="text-nx-primary">{server.load_avg}</span>
              </p>
              <p className="text-xs text-nx-muted mt-1">
                PBX: {pbx.connected
                  ? <span className="text-green-400">{t('status.pbx_up')} {pbx.version && `v${pbx.version}`}</span>
                  : <span className="text-red-400">{t('status.pbx_down')}</span>
                }
              </p>
            </div>
          </div>
        </div>

        {/* ── Recent Calls ───────────────────────────────────────── */}
        <div className="nx-card overflow-hidden">
          <div className="nx-page-header flex items-center justify-between py-4">
            <h3 className="text-sm font-semibold text-nx-primary">{t('dashboard.recent_calls')}</h3>
            <a href="/call-center/records" className="text-xs text-nx-muted hover:text-nx-primary">{t('common.view_all')}</a>
          </div>
          <div className="overflow-x-auto">
            {recent_calls.length > 0 ? (
              <table className="nx-table">
                <thead>
                  <tr>
                    <th>{t('calls.caller')}</th>
                    <th>{t('calls.destination')}</th>
                    <th>{t('calls.duration')}</th>
                    <th>{t('calls.status')}</th>
                    <th>{t('calls.time')}</th>
                  </tr>
                </thead>
                <tbody>
                  {recent_calls.map(call => (
                    <tr key={call.id}>
                      <td className="font-mono text-sm">{call.caller}</td>
                      <td className="font-mono text-sm">{call.destination}</td>
                      <td className="text-nx-secondary">{call.duration}</td>
                      <td>
                        <span className={`nx-badge ${
                          call.status === 'answered' ? 'nx-badge-green' :
                          call.status === 'missed'   ? 'nx-badge-red' : 'nx-badge-gray'
                        }`}>
                          {call.status}
                        </span>
                      </td>
                      <td className="text-xs text-nx-muted">{call.started_at}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <div className="p-8 text-center text-nx-muted text-sm">
                {t('dashboard.no_recent_calls')}
              </div>
            )}
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default Dashboard;
