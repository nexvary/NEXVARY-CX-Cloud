import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Users, Edit, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Queue {
  id: string;
  name: string;
  strategy: string;
  max_wait_time: number;
  enabled: boolean;
}

interface Props {
  queues: { data: Queue[]; total: number };
}

const QueuesIndex: React.FC<Props> = ({ queues }) => {
  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete queue "${name}"?`)) return;
    router.delete(`/pbx/queues/${id}`);
  };

  return (
    <AppLayout title="Call Queues">
      <Head title="Call Queues" />
      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Call Queues</h1>
          <p className="nx-page-subtitle">ACD queue configuration and agent management</p>
        </div>
        <Link href="/pbx/queues/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" /> New Queue
        </Link>
      </div>
      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Strategy</th>
                <th>Max Wait</th>
                <th>Status</th>
                <th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {queues.data.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-nx-muted">
                    <Users className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    No queues configured.
                  </td>
                </tr>
              )}
              {queues.data.map(q => (
                <tr key={q.id}>
                  <td className="font-medium text-nx-primary">{q.name}</td>
                  <td className="text-nx-secondary text-sm capitalize">{q.strategy}</td>
                  <td className="text-nx-secondary text-sm">{q.max_wait_time}s</td>
                  <td>
                    <span className={`nx-badge ${q.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      {q.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <Link href={`/pbx/queues/${q.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button onClick={() => handleDelete(q.id, q.name)} className="nx-btn-ghost p-1.5 text-red-400">
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
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

export default QueuesIndex;
