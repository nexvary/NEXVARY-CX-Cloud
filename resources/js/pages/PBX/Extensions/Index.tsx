import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Phone, Edit, Trash2, PhoneForwarded, MicOff } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Extension {
  id: string;
  number: string;
  name: string;
  dnd_enabled: boolean;
  call_forward_enabled: boolean;
  call_forward_number: string | null;
  enabled: boolean;
  context: string;
}

interface Props {
  extensions: {
    data: Extension[];
    current_page: number;
    last_page: number;
    total: number;
  };
}

const ExtensionsIndex: React.FC<Props> = ({ extensions }) => {
  const handleDelete = (id: string, num: string) => {
    if (!confirm(`Delete extension ${num}?`)) return;
    router.delete(`/pbx/extensions/${id}`);
  };

  return (
    <AppLayout title="Extensions">
      <Head title="Extensions" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Extensions</h1>
          <p className="nx-page-subtitle">Manage SIP extensions and user endpoints</p>
        </div>
        <Link href="/pbx/extensions/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" />
          Add Extension
        </Link>
      </div>

      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Extension</th>
                <th>Name</th>
                <th>Context</th>
                <th>Flags</th>
                <th>Status</th>
                <th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {extensions.data.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-center py-12 text-nx-muted">
                    <Phone className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    <p>No extensions configured yet.</p>
                    <Link href="/pbx/extensions/create" className="nx-btn-primary mt-4 inline-flex">
                      <Plus className="w-4 h-4" /> Add first extension
                    </Link>
                  </td>
                </tr>
              )}
              {extensions.data.map(ext => (
                <tr key={ext.id}>
                  <td>
                    <span className="font-mono text-lg font-semibold" style={{ color: 'var(--nx-green)' }}>
                      {ext.number}
                    </span>
                  </td>
                  <td className="font-medium text-nx-primary">{ext.name}</td>
                  <td className="text-nx-muted text-sm font-mono">{ext.context || 'default'}</td>
                  <td>
                    <div className="flex items-center gap-1.5">
                      {ext.dnd_enabled && (
                        <span className="nx-badge nx-badge-yellow" title="Do Not Disturb">
                          <MicOff className="w-3 h-3 me-1" /> DND
                        </span>
                      )}
                      {ext.call_forward_enabled && (
                        <span className="nx-badge nx-badge-blue" title={`Forward to ${ext.call_forward_number}`}>
                          <PhoneForwarded className="w-3 h-3 me-1" /> FWD
                        </span>
                      )}
                    </div>
                  </td>
                  <td>
                    <span className={`nx-badge ${ext.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      <span className={`nx-dot me-1.5 ${ext.enabled ? 'nx-dot-green' : 'nx-dot-red'}`} />
                      {ext.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <Link
                        href={`/pbx/extensions/${ext.id}/edit`}
                        className="nx-btn-ghost p-1.5"
                        title="Edit"
                      >
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button
                        onClick={() => handleDelete(ext.id, ext.number)}
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

          {extensions.last_page > 1 && (
            <div className="px-4 py-3 border-t border-nx flex items-center justify-between text-sm text-nx-muted">
              <span>{extensions.total} extensions</span>
              <span>Page {extensions.current_page} / {extensions.last_page}</span>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
};

export default ExtensionsIndex;
