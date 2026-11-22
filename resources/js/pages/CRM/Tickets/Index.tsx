import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Ticket, Edit, AlertCircle, Clock, CheckCircle } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface TicketItem {
  id: string;
  subject: string;
  status: 'open' | 'pending' | 'resolved' | 'closed';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  contact?: { name: string } | null;
  assignee?: { name: string } | null;
  created_at: string;
}

interface Props {
  tickets: { data: TicketItem[]; total: number; current_page: number; last_page: number };
  filters: { status?: string };
}

const statusBadge = (s: string) => {
  const map: Record<string, string> = {
    open: 'nx-badge-red',
    pending: 'nx-badge-yellow',
    resolved: 'nx-badge-green',
    closed: 'nx-badge-gray',
  };
  return map[s] ?? 'nx-badge-gray';
};

const priorityBadge = (p: string) => {
  const map: Record<string, string> = {
    urgent: 'nx-badge-red',
    high: 'nx-badge-yellow',
    medium: 'nx-badge-blue',
    low: 'nx-badge-gray',
  };
  return map[p] ?? 'nx-badge-gray';
};

const TicketsIndex: React.FC<Props> = ({ tickets, filters }) => {
  const statuses = ['all', 'open', 'pending', 'resolved', 'closed'];

  return (
    <AppLayout title="Support Tickets">
      <Head title="Support Tickets" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Support Tickets</h1>
          <p className="nx-page-subtitle">{tickets.total} tickets</p>
        </div>
        <Link href="/crm/tickets/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" />
          New Ticket
        </Link>
      </div>

      <div className="p-6 space-y-4 nx-animate-in">
        {/* Status filter tabs */}
        <div className="flex items-center gap-1 p-1 rounded-xl" style={{ background: 'var(--nx-bg-elevated)' }}>
          {statuses.map(s => (
            <button
              key={s}
              onClick={() =>
                router.get('/crm/tickets', s !== 'all' ? { status: s } : {}, { preserveState: true })
              }
              className={`px-3 py-1.5 rounded-lg text-sm font-medium capitalize transition-all ${
                (filters.status === s) || (!filters.status && s === 'all')
                  ? 'text-black'
                  : 'text-nx-secondary hover:text-nx-primary'
              }`}
              style={
                (filters.status === s) || (!filters.status && s === 'all')
                  ? { background: 'var(--nx-green)', color: '#000' }
                  : {}
              }
            >
              {s}
            </button>
          ))}
        </div>

        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>Contact</th>
                <th>Assigned To</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Created</th>
                <th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {tickets.data.length === 0 && (
                <tr>
                  <td colSpan={7} className="text-center py-12 text-nx-muted">
                    <Ticket className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    No tickets found.
                  </td>
                </tr>
              )}
              {tickets.data.map(t => (
                <tr key={t.id}>
                  <td>
                    <Link href={`/crm/tickets/${t.id}`} className="font-medium text-nx-primary hover:text-green-400 transition-colors truncate block max-w-xs">
                      {t.subject}
                    </Link>
                  </td>
                  <td className="text-nx-secondary text-sm">{t.contact?.name ?? '—'}</td>
                  <td className="text-nx-secondary text-sm">{t.assignee?.name ?? 'Unassigned'}</td>
                  <td><span className={`nx-badge ${priorityBadge(t.priority)}`}>{t.priority}</span></td>
                  <td><span className={`nx-badge ${statusBadge(t.status)}`}>{t.status}</span></td>
                  <td className="text-xs text-nx-muted">{t.created_at}</td>
                  <td>
                    <div className="flex items-center justify-end pe-2">
                      <Link href={`/crm/tickets/${t.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
};

export default TicketsIndex;
