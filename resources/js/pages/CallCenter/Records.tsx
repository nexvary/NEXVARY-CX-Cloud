import React from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Phone, Search, Filter, Download, ArrowUpRight, ArrowDownLeft, MinusCircle } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface CallRecord {
  id: string;
  caller_id_number: string;
  caller_id_name: string | null;
  destination_number: string;
  direction: 'inbound' | 'outbound' | 'internal';
  duration_seconds: number;
  hangup_cause: string;
  started_at: string;
  answered_at: string | null;
}

interface Props {
  records: {
    data: CallRecord[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
  };
  filters: {
    search?: string;
    date_from?: string;
    date_to?: string;
  };
}

const directionIcon = (dir: string) => {
  if (dir === 'inbound') return <ArrowDownLeft className="w-3.5 h-3.5 text-green-400" />;
  if (dir === 'outbound') return <ArrowUpRight className="w-3.5 h-3.5 text-blue-400" />;
  return <MinusCircle className="w-3.5 h-3.5 text-nx-muted" />;
};

const fmtDuration = (s: number) => {
  if (!s) return '—';
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  return h > 0
    ? `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
    : `${m}:${String(sec).padStart(2, '0')}`;
};

const hangupBadge = (cause: string, answered: string | null) => {
  if (cause === 'NORMAL_CLEARING' && answered) {
    return <span className="nx-badge nx-badge-green">Answered</span>;
  }
  if (!answered) {
    return <span className="nx-badge nx-badge-red">Missed</span>;
  }
  return <span className="nx-badge nx-badge-yellow">{cause}</span>;
};

const CDRPage: React.FC<Props> = ({ records, filters }) => {
  const { data, setData } = useForm({
    search:    filters.search ?? '',
    date_from: filters.date_from ?? '',
    date_to:   filters.date_to ?? '',
  });

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/call-center/records', data as Record<string, string>, { preserveState: true });
  };

  return (
    <AppLayout title="Call Records (CDR)">
      <Head title="Call Records" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Call Detail Records</h1>
          <p className="nx-page-subtitle">{records.total.toLocaleString()} total records</p>
        </div>
        <button className="nx-btn-secondary gap-2">
          <Download className="w-4 h-4" />
          Export CSV
        </button>
      </div>

      <div className="p-6 space-y-4 nx-animate-in">
        {/* Filters */}
        <form onSubmit={handleSearch} className="flex items-end gap-3 flex-wrap">
          <div className="flex-1 min-w-48">
            <label className="nx-label">Search caller / destination</label>
            <div className="relative">
              <Search className="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-nx-muted" />
              <input
                className="nx-input ps-9"
                value={data.search}
                onChange={e => setData('search', e.target.value)}
                placeholder="+1234... or extension"
              />
            </div>
          </div>
          <div>
            <label className="nx-label">From</label>
            <input
              type="date"
              className="nx-input"
              value={data.date_from}
              onChange={e => setData('date_from', e.target.value)}
            />
          </div>
          <div>
            <label className="nx-label">To</label>
            <input
              type="date"
              className="nx-input"
              value={data.date_to}
              onChange={e => setData('date_to', e.target.value)}
            />
          </div>
          <button type="submit" className="nx-btn-primary">
            <Filter className="w-4 h-4" />
            Apply
          </button>
        </form>

        {/* Table */}
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Dir</th>
                <th>Caller</th>
                <th>Destination</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              {records.data.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-center py-12 text-nx-muted">
                    <Phone className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    No records found for the selected filters.
                  </td>
                </tr>
              )}
              {records.data.map(r => (
                <tr key={r.id}>
                  <td>{directionIcon(r.direction)}</td>
                  <td>
                    <div className="font-mono text-sm text-nx-primary">{r.caller_id_number}</div>
                    {r.caller_id_name && (
                      <div className="text-xs text-nx-muted">{r.caller_id_name}</div>
                    )}
                  </td>
                  <td className="font-mono text-sm">{r.destination_number}</td>
                  <td className="font-mono text-sm">{fmtDuration(r.duration_seconds)}</td>
                  <td>{hangupBadge(r.hangup_cause, r.answered_at)}</td>
                  <td className="text-xs text-nx-muted">{r.started_at}</td>
                </tr>
              ))}
            </tbody>
          </table>

          {records.last_page > 1 && (
            <div className="px-4 py-3 border-t border-nx flex items-center justify-between text-sm text-nx-muted">
              <span>
                Showing {(records.current_page - 1) * records.per_page + 1}–
                {Math.min(records.current_page * records.per_page, records.total)} of {records.total}
              </span>
              <div className="flex gap-2">
                {records.current_page > 1 && (
                  <button
                    className="nx-btn-ghost text-xs"
                    onClick={() =>
                      router.get('/call-center/records', {
                        ...data,
                        page: records.current_page - 1,
                      } as Record<string, string | number>)
                    }
                  >
                    ← Previous
                  </button>
                )}
                {records.current_page < records.last_page && (
                  <button
                    className="nx-btn-ghost text-xs"
                    onClick={() =>
                      router.get('/call-center/records', {
                        ...data,
                        page: records.current_page + 1,
                      } as Record<string, string | number>)
                    }
                  >
                    Next →
                  </button>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
};

export default CDRPage;
