import React from 'react';
import { Head } from '@inertiajs/react';
import { Mic, Play, Download } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Recording {
  id: string;
  caller_id_number: string;
  destination_number: string;
  duration_seconds: number;
  recording_path: string;
  started_at: string;
}

interface Props {
  records: {
    data: Recording[];
    current_page: number;
    last_page: number;
    total: number;
  };
}

const fmtDuration = (s: number) => {
  const m = Math.floor(s / 60);
  const sec = s % 60;
  return `${m}:${String(sec).padStart(2, '0')}`;
};

const RecordingsPage: React.FC<Props> = ({ records }) => {
  return (
    <AppLayout title="Call Recordings">
      <Head title="Call Recordings" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Call Recordings</h1>
          <p className="nx-page-subtitle">{records.total} recordings stored</p>
        </div>
      </div>

      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Caller</th>
                <th>Destination</th>
                <th>Duration</th>
                <th>Date</th>
                <th className="text-end pe-4">Play</th>
              </tr>
            </thead>
            <tbody>
              {records.data.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-nx-muted">
                    <Mic className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    No recordings found.
                  </td>
                </tr>
              )}
              {records.data.map(r => (
                <tr key={r.id}>
                  <td className="font-mono text-sm">{r.caller_id_number}</td>
                  <td className="font-mono text-sm">{r.destination_number}</td>
                  <td className="text-nx-secondary">{fmtDuration(r.duration_seconds)}</td>
                  <td className="text-xs text-nx-muted">{r.started_at}</td>
                  <td>
                    <div className="flex items-center justify-end gap-2 pe-2">
                      <button className="nx-btn-ghost p-1.5" title="Play recording">
                        <Play className="w-3.5 h-3.5" style={{ color: 'var(--nx-green)' }} />
                      </button>
                      <a
                        href={`/recordings/${r.id}/download`}
                        className="nx-btn-ghost p-1.5"
                        title="Download"
                      >
                        <Download className="w-3.5 h-3.5" />
                      </a>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {records.last_page > 1 && (
            <div className="px-4 py-3 border-t border-nx flex items-center justify-between text-sm text-nx-muted">
              <span>Page {records.current_page} / {records.last_page}</span>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
};

export default RecordingsPage;
