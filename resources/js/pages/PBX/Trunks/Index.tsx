import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Server, RefreshCw, Trash2, Edit } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Trunk {
  id: string;
  name: string;
  provider: string;
  username: string;
  sip_server: string;
  sip_port: number;
  register: boolean;
  enabled: boolean;
  created_at: string;
}

interface Props {
  trunks: {
    data: Trunk[];
    current_page: number;
    last_page: number;
    total: number;
  };
}

const TrunksIndex: React.FC<Props> = ({ trunks }) => {
  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete trunk "${name}"?`)) return;
    router.delete(`/pbx/trunks/${id}`);
  };

  const handleReload = (id: string) => {
    router.post(`/pbx/trunks/${id}/reload`);
  };

  return (
    <AppLayout title="SIP Trunks">
      <Head title="SIP Trunks" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">SIP Trunks</h1>
          <p className="nx-page-subtitle">Manage SIP gateway connections to your telephony providers</p>
        </div>
        <Link href="/pbx/trunks/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" />
          Add Trunk
        </Link>
      </div>

      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Name / Provider</th>
                <th>SIP Server</th>
                <th>Username</th>
                <th>Register</th>
                <th>Status</th>
                <th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {trunks.data.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-center py-12 text-nx-muted">
                    <Server className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    <p>No SIP trunks configured yet.</p>
                    <Link href="/pbx/trunks/create" className="nx-btn-primary mt-4 inline-flex">
                      <Plus className="w-4 h-4" /> Add first trunk
                    </Link>
                  </td>
                </tr>
              )}
              {trunks.data.map(trunk => (
                <tr key={trunk.id}>
                  <td>
                    <div className="font-medium text-nx-primary">{trunk.name}</div>
                    <div className="text-xs text-nx-muted mt-0.5">{trunk.provider}</div>
                  </td>
                  <td className="font-mono text-sm">
                    {trunk.sip_server}:{trunk.sip_port}
                  </td>
                  <td className="font-mono text-sm text-nx-secondary">{trunk.username}</td>
                  <td>
                    <span className={`nx-badge ${trunk.register ? 'nx-badge-green' : 'nx-badge-gray'}`}>
                      {trunk.register ? 'Yes' : 'No'}
                    </span>
                  </td>
                  <td>
                    <span className={`nx-badge ${trunk.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      <span className={`nx-dot me-1.5 ${trunk.enabled ? 'nx-dot-green' : 'nx-dot-red'}`} />
                      {trunk.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <button
                        onClick={() => handleReload(trunk.id)}
                        className="nx-btn-ghost p-1.5"
                        title="Reload gateway"
                      >
                        <RefreshCw className="w-3.5 h-3.5" />
                      </button>
                      <Link
                        href={`/pbx/trunks/${trunk.id}/edit`}
                        className="nx-btn-ghost p-1.5"
                        title="Edit"
                      >
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button
                        onClick={() => handleDelete(trunk.id, trunk.name)}
                        className="nx-btn-ghost p-1.5 text-red-400 hover:text-red-300"
                        title="Delete"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {trunks.last_page > 1 && (
            <div className="px-4 py-3 border-t border-nx flex items-center justify-between text-sm text-nx-muted">
              <span>{trunks.total} trunks</span>
              <span>Page {trunks.current_page} / {trunks.last_page}</span>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
};

export default TrunksIndex;
