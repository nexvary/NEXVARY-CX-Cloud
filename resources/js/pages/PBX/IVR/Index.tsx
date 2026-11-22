import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Layers, Edit, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface IvrMenu {
  id: string;
  name: string;
  timeout: number;
  max_failures: number;
  enabled: boolean;
  created_at: string;
}

interface Props {
  menus: { data: IvrMenu[]; total: number; current_page: number; last_page: number };
}

const IVRIndex: React.FC<Props> = ({ menus }) => {
  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete IVR menu "${name}"?`)) return;
    router.delete(`/pbx/ivr/${id}`);
  };

  return (
    <AppLayout title="IVR Menus">
      <Head title="IVR Menus" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">IVR Menus</h1>
          <p className="nx-page-subtitle">Configure interactive voice response trees</p>
        </div>
        <Link href="/pbx/ivr/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" /> New IVR Menu
        </Link>
      </div>

      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Timeout</th>
                <th>Max Failures</th>
                <th>Status</th>
                <th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {menus.data.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-nx-muted">
                    <Layers className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    No IVR menus configured.
                  </td>
                </tr>
              )}
              {menus.data.map(m => (
                <tr key={m.id}>
                  <td className="font-medium text-nx-primary">{m.name}</td>
                  <td className="text-nx-secondary text-sm">{m.timeout}s</td>
                  <td className="text-nx-secondary text-sm">{m.max_failures}</td>
                  <td>
                    <span className={`nx-badge ${m.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      {m.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <Link href={`/pbx/ivr/${m.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button onClick={() => handleDelete(m.id, m.name)} className="nx-btn-ghost p-1.5 text-red-400">
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

export default IVRIndex;
