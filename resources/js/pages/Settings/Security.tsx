import React from 'react';
import { Head } from '@inertiajs/react';
import { Shield, AlertTriangle, Lock, Eye, Users, Clock } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

const SecurityPage: React.FC = () => {
  const events = [
    { time: 'Just now', type: 'LOGIN_SUCCESS', user: 'admin@nexvary.com', ip: '192.168.1.100', severity: 'info' },
    { time: '5m ago',   type: 'LOGIN_FAILED',  user: 'unknown',           ip: '45.33.32.156',  severity: 'warn' },
    { time: '12m ago',  type: 'SETTINGS_CHANGED', user: 'admin@nexvary.com', ip: '192.168.1.100', severity: 'info' },
    { time: '1h ago',   type: 'API_KEY_CREATED',  user: 'admin@nexvary.com', ip: '192.168.1.100', severity: 'info' },
    { time: '2h ago',   type: 'LOGIN_BRUTE_FORCE', user: 'unknown',          ip: '185.220.101.5', severity: 'error' },
  ];

  const severityBadge = (s: string) => {
    if (s === 'error') return 'nx-badge-red';
    if (s === 'warn')  return 'nx-badge-yellow';
    return 'nx-badge-blue';
  };

  return (
    <AppLayout title="Security & Audit">
      <Head title="Security & Audit" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Security & Audit Log</h1>
          <p className="nx-page-subtitle">Monitor authentication events and configuration changes</p>
        </div>
      </div>

      <div className="p-6 space-y-6 nx-animate-in">
        {/* Security summary cards */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[
            { icon: Users,         label: 'Active Sessions',   value: '1',   color: 'var(--nx-green)' },
            { icon: Lock,          label: 'MFA Enforced',      value: 'Yes', color: 'var(--nx-green)' },
            { icon: AlertTriangle, label: 'Failed Logins (24h)', value: '3', color: 'var(--nx-warning)' },
            { icon: Eye,           label: 'Audit Events (24h)', value: '47', color: 'var(--nx-info)' },
          ].map(card => (
            <div key={card.label} className="nx-card nx-stat-card">
              <div className="flex items-start justify-between mb-3">
                <p className="text-xs font-medium text-nx-muted uppercase tracking-wider">{card.label}</p>
                <div
                  className="w-8 h-8 rounded-lg flex items-center justify-center"
                  style={{ background: `${card.color}15` }}
                >
                  <card.icon className="w-4 h-4" style={{ color: card.color }} />
                </div>
              </div>
              <p className="text-2xl font-bold text-nx-primary">{card.value}</p>
            </div>
          ))}
        </div>

        {/* Audit log */}
        <div className="nx-card overflow-hidden">
          <div className="nx-page-header py-4">
            <h3 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <Clock className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
              Recent Security Events
            </h3>
          </div>
          <table className="nx-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Event</th>
                <th>User / Identity</th>
                <th>IP Address</th>
                <th>Severity</th>
              </tr>
            </thead>
            <tbody>
              {events.map((ev, i) => (
                <tr key={i}>
                  <td className="text-xs text-nx-muted font-mono">{ev.time}</td>
                  <td className="font-mono text-xs text-nx-primary">{ev.type}</td>
                  <td className="text-sm text-nx-secondary">{ev.user}</td>
                  <td className="font-mono text-xs text-nx-muted">{ev.ip}</td>
                  <td><span className={`nx-badge ${severityBadge(ev.severity)}`}>{ev.severity}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Security settings panel */}
        <div className="nx-card p-5">
          <h3 className="text-sm font-semibold text-nx-primary mb-4 flex items-center gap-2">
            <Shield className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
            Security Settings
          </h3>
          <div className="space-y-4">
            {[
              { label: 'Two-Factor Authentication', sub: 'Require MFA for all admin accounts', enabled: true },
              { label: 'IP Allowlist', sub: 'Restrict admin access to specific IP ranges', enabled: false },
              { label: 'Session Timeout', sub: 'Auto-logout inactive sessions after 30 minutes', enabled: true },
              { label: 'Brute-Force Protection', sub: 'Block IPs with 5+ failed login attempts', enabled: true },
            ].map(setting => (
              <div key={setting.label} className="flex items-center justify-between py-2 border-b border-nx last:border-0">
                <div>
                  <p className="text-sm text-nx-primary">{setting.label}</p>
                  <p className="text-xs text-nx-muted mt-0.5">{setting.sub}</p>
                </div>
                <div className={`w-10 h-5 rounded-full relative cursor-pointer transition-colors ${
                  setting.enabled ? '' : 'opacity-50'
                }`}
                  style={{ background: setting.enabled ? 'var(--nx-green)' : 'var(--nx-border-active)' }}
                >
                  <div className={`absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-all ${
                    setting.enabled ? 'start-5' : 'start-0.5'
                  }`} />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default SecurityPage;
