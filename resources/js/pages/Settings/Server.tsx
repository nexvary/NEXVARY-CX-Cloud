import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
  Server, Cpu, HardDrive, Database, Activity, RefreshCw,
  CheckCircle, XCircle, AlertCircle
} from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Metrics {
  cpu_percent: number;
  load_avg: number[];
  ram_total_kb: number;
  ram_used_kb: number;
  ram_percent: number;
  disk_total_gb: number;
  disk_used_gb: number;
  disk_percent: number;
  postgresql: boolean;
  redis: boolean;
  freeswitch: boolean;
  php_version: string;
  os: string;
  timestamp: string;
}

interface PbxStatus {
  connected: boolean;
  version?: string;
  current_sessions?: number;
  peak_sessions?: number;
}

interface Props {
  pbx: PbxStatus;
  metrics: Metrics;
}

const GaugeBar: React.FC<{ label: string; value: number; total?: string }> = ({ label, value, total }) => {
  const color =
    value > 90 ? 'var(--nx-error)' :
    value > 70 ? 'var(--nx-warning)' :
    'var(--nx-green)';

  return (
    <div>
      <div className="flex justify-between text-xs mb-1.5">
        <span className="text-nx-secondary font-medium">{label}</span>
        <span className="text-nx-primary font-semibold">
          {value}% {total && <span className="text-nx-muted font-normal">({total})</span>}
        </span>
      </div>
      <div className="h-2.5 rounded-full overflow-hidden" style={{ background: 'var(--nx-bg-elevated)' }}>
        <div
          className="h-full rounded-full transition-all duration-700"
          style={{ width: `${Math.min(value, 100)}%`, background: color, boxShadow: `0 0 8px ${color}60` }}
        />
      </div>
    </div>
  );
};

const StatusChip: React.FC<{ ok: boolean; label: string }> = ({ ok, label }) => (
  <div className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm ${
    ok ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400'
  }`}>
    {ok
      ? <CheckCircle className="w-4 h-4 shrink-0" />
      : <XCircle className="w-4 h-4 shrink-0" />
    }
    {label}
    <span className={`ms-auto nx-badge ${ok ? 'nx-badge-green' : 'nx-badge-red'}`}>
      {ok ? 'OK' : 'DOWN'}
    </span>
  </div>
);

const ServerHealth: React.FC<Props> = ({ pbx, metrics }) => {
  const [restarting, setRestarting] = useState(false);

  const handleRestart = () => {
    if (!confirm('Send restart command to FreeSWITCH? All active calls will be dropped.')) return;
    setRestarting(true);
    router.post('/settings/server/freeswitch/restart', {}, {
      onFinish: () => setRestarting(false),
    });
  };

  const ramTotalGb = (metrics.ram_total_kb / 1048576).toFixed(1);
  const ramUsedGb  = (metrics.ram_used_kb / 1048576).toFixed(1);

  return (
    <AppLayout title="Server Health">
      <Head title="Server Health" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Server Health</h1>
          <p className="nx-page-subtitle">
            Live system metrics — last updated {new Date(metrics.timestamp).toLocaleTimeString()}
          </p>
        </div>
        <button
          onClick={() => router.reload()}
          className="nx-btn-secondary gap-2"
        >
          <RefreshCw className="w-4 h-4" />
          Refresh
        </button>
      </div>

      <div className="p-6 space-y-6 nx-animate-in">
        {/* Service Status */}
        <div className="nx-card p-5">
          <h2 className="text-sm font-semibold text-nx-primary mb-4 flex items-center gap-2">
            <Activity className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
            Service Status
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <StatusChip ok={metrics.postgresql} label="PostgreSQL" />
            <StatusChip ok={metrics.redis} label="Redis" />
            <StatusChip ok={metrics.freeswitch} label="FreeSWITCH ESL" />
          </div>
        </div>

        {/* Resource Gauges */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="nx-card p-5 space-y-5">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <Cpu className="w-4 h-4" style={{ color: 'var(--nx-info)' }} />
              Compute
            </h2>
            <GaugeBar label="CPU Usage" value={metrics.cpu_percent} />
            <div className="text-xs text-nx-muted">
              Load avg: {metrics.load_avg.join(' / ')}
            </div>
          </div>

          <div className="nx-card p-5 space-y-5">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <Server className="w-4 h-4" style={{ color: 'var(--nx-warning)' }} />
              Memory
            </h2>
            <GaugeBar
              label="RAM"
              value={metrics.ram_percent}
              total={`${ramUsedGb} / ${ramTotalGb} GB`}
            />
          </div>

          <div className="nx-card p-5 space-y-5">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <HardDrive className="w-4 h-4" style={{ color: 'var(--nx-info)' }} />
              Storage
            </h2>
            <GaugeBar
              label="Disk ( / )"
              value={metrics.disk_percent}
              total={`${metrics.disk_used_gb} / ${metrics.disk_total_gb} GB`}
            />
          </div>

          {/* FreeSWITCH Details */}
          <div className="nx-card p-5">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2 mb-4">
              <Database className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
              FreeSWITCH
            </h2>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-nx-muted">Status</span>
                <span className={pbx.connected ? 'text-green-400' : 'text-red-400'}>
                  {pbx.connected ? 'Connected' : 'Unreachable'}
                </span>
              </div>
              {pbx.version && (
                <div className="flex justify-between">
                  <span className="text-nx-muted">Version</span>
                  <span className="text-nx-primary font-mono">{pbx.version}</span>
                </div>
              )}
              {pbx.current_sessions !== undefined && (
                <div className="flex justify-between">
                  <span className="text-nx-muted">Active sessions</span>
                  <span className="text-nx-primary">{pbx.current_sessions}</span>
                </div>
              )}
              {pbx.peak_sessions !== undefined && (
                <div className="flex justify-between">
                  <span className="text-nx-muted">Peak sessions</span>
                  <span className="text-nx-primary">{pbx.peak_sessions}</span>
                </div>
              )}
            </div>
            <div className="mt-4 pt-4 border-t border-nx">
              <button
                onClick={handleRestart}
                disabled={restarting}
                className="nx-btn-danger w-full text-xs py-1.5"
              >
                {restarting ? <span className="nx-spinner me-2" /> : null}
                Restart FreeSWITCH
              </button>
            </div>
          </div>
        </div>

        {/* System Info */}
        <div className="nx-card p-5">
          <h2 className="text-sm font-semibold text-nx-primary mb-3">System Information</h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
              <p className="text-nx-muted text-xs uppercase tracking-wider mb-1">OS</p>
              <p className="text-nx-primary font-mono text-xs">{metrics.os}</p>
            </div>
            <div>
              <p className="text-nx-muted text-xs uppercase tracking-wider mb-1">PHP Version</p>
              <p className="text-nx-primary font-mono">{metrics.php_version}</p>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default ServerHealth;
