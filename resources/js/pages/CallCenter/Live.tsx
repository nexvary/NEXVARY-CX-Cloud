import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { PhoneCall, PhoneOff, Clock, RefreshCw } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface ActiveCall {
  uuid: string;
  caller_id_name?: string;
  caller_id_number?: string;
  destination_number?: string;
  created?: string;
  state?: string;
}

interface Props {
  active_calls: ActiveCall[];
}

const LiveCalls: React.FC<Props> = ({ active_calls }) => {
  const [calls, setCalls] = useState(active_calls);
  const [lastRefresh, setLastRefresh] = useState(new Date());

  useEffect(() => {
    const interval = setInterval(() => {
      router.reload({ only: ['active_calls'], onSuccess: (page) => {
        const props = page.props as Props;
        setCalls(props.active_calls);
        setLastRefresh(new Date());
      }});
    }, 5000);
    return () => clearInterval(interval);
  }, []);

  const handleHangup = (uuid: string) => {
    if (!confirm('Terminate this call?')) return;
    router.delete(`/call-center/live/${uuid}`, {
      onSuccess: () => {
        setCalls(prev => prev.filter(c => c.uuid !== uuid));
      },
    });
  };

  return (
    <AppLayout title="Live Calls">
      <Head title="Live Calls" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Live Calls</h1>
          <p className="nx-page-subtitle">
            <span
              className="nx-dot nx-dot-green me-1.5 inline-block"
              style={{ animation: 'pulse 2s infinite' }}
            />
            {calls.length} active call{calls.length !== 1 ? 's' : ''} — auto-refreshes every 5s
          </p>
        </div>
        <div className="flex items-center gap-2 text-xs text-nx-muted">
          <RefreshCw className="w-3.5 h-3.5" />
          Last: {lastRefresh.toLocaleTimeString()}
        </div>
      </div>

      <div className="p-6 nx-animate-in">
        {calls.length === 0 ? (
          <div className="nx-card p-16 text-center">
            <PhoneCall className="w-12 h-12 mx-auto mb-4 opacity-20" />
            <p className="text-nx-muted text-sm">No active calls at this moment</p>
          </div>
        ) : (
          <div className="grid gap-3">
            {calls.map(call => (
              <div key={call.uuid} className="nx-card p-4 flex items-center gap-4">
                <div
                  className="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                  style={{ background: 'var(--nx-green-muted)' }}
                >
                  <PhoneCall className="w-5 h-5" style={{ color: 'var(--nx-green)' }} />
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-3">
                    <span className="font-mono font-semibold text-nx-primary">
                      {call.caller_id_number ?? 'Unknown'}
                    </span>
                    {call.caller_id_name && (
                      <span className="text-sm text-nx-secondary">({call.caller_id_name})</span>
                    )}
                    <span className="text-nx-muted text-sm">→</span>
                    <span className="font-mono text-nx-primary">{call.destination_number ?? '—'}</span>
                  </div>
                  <div className="flex items-center gap-2 mt-1 text-xs text-nx-muted">
                    <Clock className="w-3 h-3" />
                    <span>{call.created ?? 'connecting'}</span>
                    <span className="nx-badge nx-badge-green nx-call-active">
                      {call.state ?? 'ACTIVE'}
                    </span>
                  </div>
                </div>

                <button
                  onClick={() => handleHangup(call.uuid)}
                  className="nx-btn-danger gap-1.5 text-xs py-1.5"
                  title="Terminate call"
                >
                  <PhoneOff className="w-3.5 h-3.5" />
                  Hangup
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
};

export default LiveCalls;
